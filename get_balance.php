<?php
// get_balance.php
require_once 'session_check.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit();
}

// Database connection
function getDbConnection() {
    $host = '127.0.0.1';
    $dbname = 'jalanyukproject';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        return null;
    }
}

// Function to format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 2, ',', '.');
}

function formatCurrencyShort($amount) {
    if ($amount >= 1000000000) {
        return 'Rp ' . number_format($amount / 1000000000, 1, ',', '.') . 'B';
    } elseif ($amount >= 1000000) {
        return 'Rp ' . number_format($amount / 1000000, 1, ',', '.') . 'M';
    } elseif ($amount >= 1000) {
        return 'Rp ' . number_format($amount / 1000, 1, ',', '.') . 'K';
    } else {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

// Get user balance
$pdo = getDbConnection();
if (!$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $balance = floatval($result['balance']);
        
        echo json_encode([
            'success' => true,
            'balance' => $balance,
            'formatted_balance' => formatCurrency($balance),
            'formatted_balance_short' => formatCurrencyShort($balance),
            'last_updated' => date('d M Y, H:i')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error getting balance'
    ]);
}
?>