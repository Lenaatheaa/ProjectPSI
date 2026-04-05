<?php
// api/transactions.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = '127.0.0.1';
$dbname = 'jalanyukproject';
$username = 'root'; // Adjust according to your setup
$password = ''; // Adjust according to your setup

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

switch ($method) {
    case 'GET':
        if ($path === '/summary') {
            getTransactionSummary($pdo);
        } elseif ($path === '/chart-data') {
            getChartData($pdo);
        } else {
            getTransactions($pdo);
        }
        break;
    
    case 'PUT':
        if (preg_match('/^\/(\d+)\/status$/', $path, $matches)) {
            updateTransactionStatus($pdo, $matches[1]);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getTransactions($pdo) {
    $type = $_GET['type'] ?? 'all';
    $date = $_GET['date'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Filter by transaction type
    if ($type !== 'all') {
        switch ($type) {
            case 'deposit':
                $whereClause .= " AND transaction_type = 'topup'";
                break;
            case 'withdrawal':
                $whereClause .= " AND transaction_type = 'withdrawal'";
                break;
            case 'transfer':
                $whereClause .= " AND (transaction_type = 'transfer_in' OR transaction_type = 'transfer_out')";
                break;
        }
    }
    
    // Filter by date
    if ($date) {
        $whereClause .= " AND DATE(created_at) = ?";
        $params[] = $date;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM transactions t 
                 LEFT JOIN users u ON t.user_id = u.id 
                 $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();
    
    // Get transactions with user info
    $sql = "SELECT t.*, u.username, u.email 
            FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            $whereClause 
            ORDER BY t.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formattedTransactions = array_map(function($transaction) {
        return [
            'id' => $transaction['id'],
            'user' => [
                'id' => $transaction['user_id'],
                'username' => $transaction['username'] ?? 'Unknown',
                'email' => $transaction['email'] ?? 'Unknown'
            ],
            'type' => $transaction['transaction_type'],
            'amount' => (float)$transaction['amount'],
            'payment_method' => $transaction['payment_method'],
            'status' => $transaction['status'],
            'description' => $transaction['description'],
            'created_at' => $transaction['created_at'],
            'updated_at' => $transaction['updated_at']
        ];
    }, $transactions);
    
    echo json_encode([
        'data' => $formattedTransactions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
            'total_count' => $totalCount,
            'per_page' => $limit
        ]
    ]);
}

function getTransactionSummary($pdo) {
    // Get summary for current month
    $currentMonth = date('Y-m');
    $previousMonth = date('Y-m', strtotime('-1 month'));
    
    // Current month totals
    $currentSql = "SELECT 
                    SUM(CASE WHEN transaction_type IN ('topup', 'transfer_in') AND status = 'completed' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN transaction_type IN ('transfer_out', 'withdrawal') AND status = 'completed' THEN amount ELSE 0 END) as total_expense
                   FROM transactions 
                   WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
    
    $stmt = $pdo->prepare($currentSql);
    $stmt->execute([$currentMonth]);
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Previous month totals
    $stmt->execute([$previousMonth]);
    $previousData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalIncome = (float)($currentData['total_income'] ?? 0);
    $totalExpense = (float)($currentData['total_expense'] ?? 0);
    $netBalance = $totalIncome - $totalExpense;
    
    // Calculate percentage changes
    $previousIncome = (float)($previousData['total_income'] ?? 0);
    $previousExpense = (float)($previousData['total_expense'] ?? 0);
    $previousBalance = $previousIncome - $previousExpense;
    
    $incomeChange = $previousIncome > 0 ? (($totalIncome - $previousIncome) / $previousIncome) * 100 : 0;
    $expenseChange = $previousExpense > 0 ? (($totalExpense - $previousExpense) / $previousExpense) * 100 : 0;
    $balanceChange = $previousBalance != 0 ? (($netBalance - $previousBalance) / abs($previousBalance)) * 100 : 0;
    
    echo json_encode([
        'total_income' => $totalIncome,
        'total_expense' => $totalExpense,
        'net_balance' => $netBalance,
        'income_change' => round($incomeChange, 1),
        'expense_change' => round($expenseChange, 1),
        'balance_change' => round($balanceChange, 1)
    ]);
}

function getChartData($pdo) {
    $days = (int)($_GET['days'] ?? 30);
    
    $sql = "SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN transaction_type IN ('topup', 'transfer_in') AND status = 'completed' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN transaction_type IN ('transfer_out', 'withdrawal') AND status = 'completed' THEN amount ELSE 0 END) as expense
            FROM transactions 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$days]);
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($chartData);
}

function updateTransactionStatus($pdo, $transactionId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $status = $input['status'] ?? '';
    
    $allowedStatuses = ['pending', 'completed', 'failed', 'cancelled'];
    if (!in_array($status, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }
    
    $sql = "UPDATE transactions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$status, $transactionId])) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update status']);
    }
}
?>