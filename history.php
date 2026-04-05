<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'jalanyukproject';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Get user information and balance
$stmt = $pdo->prepare("SELECT full_name, email, balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit();
}

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, u.full_name as recipient_name, u.email as recipient_email
    FROM transactions t
    LEFT JOIN users u ON (
        (t.transaction_type = 'transfer_out' AND t.description LIKE CONCAT('%', u.email, '%'))
        OR (t.transaction_type = 'transfer_in' AND t.description LIKE CONCAT('%', u.email, '%'))
    )
    WHERE t.user_id = ? 
    ORDER BY t.created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Function to get transaction icon
function getTransactionIcon($type) {
    switch($type) {
        case 'topup':
            return 'fas fa-plus-circle';
        case 'transfer_out':
            return 'fas fa-arrow-up';
        case 'transfer_in':
            return 'fas fa-arrow-down';
        case 'bill_payment':
            return 'fas fa-file-invoice-dollar';
        default:
            return 'fas fa-circle';
    }
}

// Function to get transaction title
function getTransactionTitle($transaction) {
    switch($transaction['transaction_type']) {
        case 'topup':
            return 'Top Up - ' . $transaction['payment_method'];
        case 'transfer_out':
            // Extract recipient from description
            preg_match('/Transfer ke (.+?) \((.+?)\)/', $transaction['description'], $matches);
            return 'Transfer to ' . ($matches[1] ?? 'Unknown');
        case 'transfer_in':
            // Extract sender from description
            preg_match('/Transfer dari (.+?) \((.+?)\)/', $transaction['description'], $matches);
            return 'Transfer from ' . ($matches[1] ?? 'Unknown');
        case 'bill_payment':
            return 'Pembayaran Tagihan - ' . ($transaction['payment_method'] ?? 'Unknown');
        default:
            return ucfirst($transaction['transaction_type']);
    }
}

// Function to get transaction type for filtering
function getTransactionFilterType($type) {
    switch($type) {
        case 'topup':
            return 'income';
        case 'transfer_out':
            return 'transfer';
        case 'transfer_in':
            return 'income';
        case 'bill_payment':
            return 'expense';
        default:
            return 'expense';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Jalanyuk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #ffffff;
            color: #000000;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header - Full Width */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #000000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            border-bottom: 1px solid #333;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logo:hover {
            color: #4CAF50;
            transform: scale(1.05);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-icon {
            position: relative;
            color: #ffffff;
            font-size: 20px;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #4CAF50;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }

        /* Main Content - Add top padding to account for fixed header */
        .main-content {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 40px;
            margin-bottom: 40px;
            align-items: start;
            margin-top: 100px; /* Space for fixed header */
        }

        .welcome-section h1 {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
            color: #000000;
        }

        .balance-card {
            background: linear-gradient(135deg, #2b2b2b 0%, #1a1a1a 100%);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #333;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 350px;
            justify-self: end;
        }

        .balance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .balance-title {
            color: #ccc;
            font-size: 14px;
        }

        .balance-amount {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 15px;
        }

        .balance-actions {
            display: flex;
            gap: 12px;
        }

        .btn-action {
            background: #2b2b2b;
            color: #ffffff;
            border: none;
            padding: 10px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            text-decoration: none;
        }

        .btn-action:hover {
            background: #404040;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        /* History Section */
        .history-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            color: #000000;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .history-title {
            font-size: 24px;
            font-weight: 700;
            color: #000000;
        }

        .see-all-btn {
            background: #2b2b2b;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .see-all-btn:hover {
            background: #404040;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .transaction-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .transaction-details h3 {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 5px;
        }

        .transaction-details p {
            font-size: 14px;
            color: #666;
        }

        .transaction-amount {
            font-size: 16px;
            font-weight: 700;
        }

        .amount-negative {
            color: #ff4444;
        }

        .amount-positive {
            color: #4CAF50;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-completed {
            background: #e8f5e8;
            color: #4CAF50;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        /* Filter Buttons */
        .filter-section {
            margin-bottom: 30px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            background: #2b2b2b;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #404040;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .no-transactions {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-transactions i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ccc;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                padding: 15px 20px;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-top: 80px;
            }
            
            .welcome-section h1 {
                font-size: 32px;
            }
            
            .balance-amount {
                font-size: 28px;
            }
            
            .container {
                padding: 15px;
            }
            
            .balance-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header - Full Width -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">Jalanyuk</a>
            <div class="user-info">
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($user['full_name']); ?></span>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Main Content -->
        <div class="main-content">
            <div class="welcome-section">
                <h1>Transaction<br>History</h1>
                <p style="color: #666; font-size: 18px; margin-top: 10px;">
                    Track all your financial activities
                </p>
            </div>

            <div class="balance-card">
                <div class="balance-header">
                    <span class="balance-title">Current Balance</span>
                    <i class="fas fa-wallet" style="color: #ccc;"></i>
                </div>
                <div class="balance-amount"><?php echo formatCurrency($user['balance']); ?></div>
                <div class="balance-actions">
                    <a href="topup.php" class="btn-action">
                        <i class="fas fa-plus"></i> Top Up
                    </a>
                    <a href="transfer.php" class="btn-action">
                        <i class="fas fa-arrow-up"></i> Transfer
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterTransactions('all')">
                    <i class="fas fa-list"></i> Semua
                </button>
                <button class="filter-btn" onclick="filterTransactions('income')">
                    <i class="fas fa-arrow-down"></i> Pemasukan
                </button>
                <button class="filter-btn" onclick="filterTransactions('expense')">
                    <i class="fas fa-arrow-up"></i> Pengeluaran
                </button>
                <button class="filter-btn" onclick="filterTransactions('transfer')">
                    <i class="fas fa-exchange-alt"></i> Transfer
                </button>
            </div>
        </div>

        <!-- History Section -->
        <div class="history-section">
            <div class="history-header">
                <h2 class="history-title">Recent Transactions</h2>
                <button class="see-all-btn">See All</button>
            </div>

            <div class="transaction-list">
                <?php if (empty($transactions)): ?>
                    <div class="no-transactions">
                        <i class="fas fa-receipt"></i>
                        <h3>No transactions found</h3>
                        <p>Your transaction history will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <div class="transaction-item" data-type="<?php echo getTransactionFilterType($transaction['transaction_type']); ?>">
                            <div class="transaction-info">
                                <div class="transaction-icon">
                                    <i class="<?php echo getTransactionIcon($transaction['transaction_type']); ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <h3><?php echo htmlspecialchars(getTransactionTitle($transaction)); ?></h3>
                                    <p><?php echo date('j M Y \a\t g:i A', strtotime($transaction['created_at'])); ?>
                                        <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="transaction-amount <?php echo ($transaction['transaction_type'] == 'topup' || $transaction['transaction_type'] == 'transfer_in') ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php 
                                $sign = ($transaction['transaction_type'] == 'topup' || $transaction['transaction_type'] == 'transfer_in') ? '+' : '-';
                                echo $sign . formatCurrency($transaction['amount']);
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Filter functionality
        function filterTransactions(type) {
            const transactions = document.querySelectorAll('.transaction-item');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Remove active class from all buttons
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Show/hide transactions based on type
            transactions.forEach(transaction => {
                if (type === 'all') {
                    transaction.style.display = 'flex';
                } else {
                    const transactionType = transaction.getAttribute('data-type');
                    if (transactionType === type) {
                        transaction.style.display = 'flex';
                    } else {
                        transaction.style.display = 'none';
                    }
                }
            });
        }

        // Button hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn-action, .filter-btn');
            
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        // Smooth scroll and animations
        document.addEventListener('DOMContentLoaded', function() {
            const transactionItems = document.querySelectorAll('.transaction-item');
            
            transactionItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                item.style.transition = 'all 0.5s ease';
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Auto-refresh page every 30 seconds to get latest transactions
        setInterval(function() {
            // Only refresh if user is still active (not typing, clicking, etc.)
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>