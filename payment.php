<?php
session_start();

// Database configuration
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user balance
function getUserBalance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? floatval($user['balance']) : 0;
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_bill') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate input
        if (!isset($_POST['group_id']) || !isset($_POST['amount'])) {
            throw new Exception("Data tidak lengkap");
        }
        
        $group_id = intval($_POST['group_id']);
        $amount = floatval($_POST['amount']);
        
        if ($group_id <= 0 || $amount <= 0) {
            throw new Exception("Data tidak valid");
        }
        
        $pdo->beginTransaction();
        
        // Verify user is member of the group
        $stmt = $pdo->prepare("
            SELECT * FROM groups 
            WHERE id = ? AND (created_by = ? OR id IN (
                SELECT group_id FROM group_members WHERE user_id = ?
            ))
        ");
        $stmt->execute([$group_id, $user_id, $user_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            throw new Exception("Anda tidak memiliki akses ke grup ini");
        }
        
        // Check if there's actually a bill to pay
        if ($group['monthly_bill_amount'] != $amount) {
            throw new Exception("Jumlah tagihan tidak sesuai");
        }
        
        // Check if bill amount is greater than 0
        if ($group['monthly_bill_amount'] <= 0) {
            throw new Exception("Tidak ada tagihan yang perlu dibayar");
        }
        
        // Get current user balance
        $current_balance = getUserBalance($pdo, $user_id);
        
        if ($current_balance < $amount) {
            throw new Exception("Saldo tidak mencukupi untuk membayar tagihan ini");
        }
        
        // Update user balance (deduct payment amount)
        $new_balance = $current_balance - $amount;
        $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $user_id]);
        
        // Update group current balance (add payment amount)
        $stmt = $pdo->prepare("UPDATE groups SET current_balance = current_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $group_id]);
        
        // Reset the monthly bill amount to 0 after payment (tagihan menghilang)
        $stmt = $pdo->prepare("UPDATE groups SET monthly_bill_amount = 0 WHERE id = ?");
        $stmt->execute([$group_id]);
        
        // Record the payment transaction in transactions table
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, 
                transaction_type, 
                amount, 
                payment_method, 
                status, 
                description, 
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $description = "Pembayaran tagihan grup: " . $group['name'] . " (ID: " . $group_id . ")";
        
        $stmt->execute([
            $user_id,
            'bill_payment',
            $amount,
            'Balance',
            'completed',
            $description
        ]);
        
        $transaction_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = "Pembayaran berhasil diproses. Tagihan telah dibayar dan saldo grup telah diperbarui.";
        $response['new_balance'] = $new_balance;
        $response['transaction_id'] = $transaction_id;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get user balance for display
$user_balance = getUserBalance($pdo, $user_id);

// Get user groups with monthly bills - Only show groups with bills > 0
$stmt = $pdo->prepare("
    SELECT g.*, 
           CASE 
               WHEN g.monthly_bill_amount > 0 THEN 'pending'
               ELSE 'no_bill'
           END as bill_status
    FROM groups g 
    WHERE (g.created_by = ? OR g.id IN (
        SELECT group_id FROM group_members WHERE user_id = ?
    ))
    AND g.status = 'active'
    AND g.monthly_bill_amount > 0
    ORDER BY g.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$user_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent bill payments from transactions table
$stmt = $pdo->prepare("
    SELECT 
        t.id as transaction_id,
        t.amount,
        t.description,
        t.status,
        t.created_at as payment_date,
        t.transaction_type
    FROM transactions t
    WHERE t.user_id = ?
    AND t.transaction_type = 'bill_payment'
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Function to safely get user name
function getUserName($user_info) {
    if (empty($user_info)) {
        return 'Unknown User';
    }
    
    // Check for different possible name columns
    if (isset($user_info['name'])) {
        return $user_info['name'];
    } elseif (isset($user_info['username'])) {
        return $user_info['username'];
    } elseif (isset($user_info['full_name'])) {
        return $user_info['full_name'];
    } elseif (isset($user_info['first_name'])) {
        $name = $user_info['first_name'];
        if (isset($user_info['last_name'])) {
            $name .= ' ' . $user_info['last_name'];
        }
        return $name;
    } elseif (isset($user_info['email'])) {
        return $user_info['email'];
    } else {
        return 'User #' . $user_info['id'];
    }
}

// Get user display name
$user_name = getUserName($user_info);

// Function to safely get group name
function getGroupName($group) {
    return isset($group['name']) ? $group['name'] : 'Unknown Group';
}

// Function to safely format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Function to safely format date
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Function to get payment status
function getPaymentStatus($group) {
    if ($group['monthly_bill_amount'] > 0) {
        return 'pending';
    } else {
        return 'paid';
    }
}

// Function to get transaction type display name
function getTransactionTypeDisplay($type) {
    switch($type) {
        case 'bill_payment':
            return 'Pembayaran Tagihan';
        case 'topup':
            return 'Top Up';
        case 'transfer_in':
            return 'Transfer Masuk';
        case 'transfer_out':
            return 'Transfer Keluar';
        default:
            return ucfirst($type);
    }
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch($status) {
        case 'completed':
            return 'badge-success';
        case 'pending':
            return 'badge-warning';
        case 'failed':
            return 'badge-danger';
        case 'cancelled':
            return 'badge-secondary';
        default:
            return 'badge-secondary';
    }
}

// Debug function (remove in production)
function debugArray($array, $label = '') {
    echo "<pre>";
    echo $label ? "<strong>$label:</strong>\n" : '';
    print_r($array);
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan - Jalanyuk</title>
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
    color: #ffffff;
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

/* Alert Messages */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 10px;
    display: none;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Main Content */
.main-content {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 40px;
    margin-bottom: 40px;
    align-items: start;
    margin-top: 100px;
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

/* Bills Section */
.bills-section {
    background: #ffffff;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 40px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid #f0f0f0;
}

.bills-header {
    margin-bottom: 25px;
}

.bills-title {
    font-size: 24px;
    font-weight: 700;
    color: #000000;
}

.bills-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.bill-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.bill-item:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.bill-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.bill-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #000000;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.bill-details h3 {
    font-size: 16px;
    font-weight: 600;
    color: #000000;
    margin-bottom: 5px;
}

.bill-details p {
    font-size: 14px;
    color: #666;
}

.bill-amount {
    font-size: 18px;
    font-weight: 700;
    color: #000000;
    margin-right: 15px;
}

.btn-pay {
    background: #000000ff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
}

.btn-pay:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
}

/* Recent Bills Section */
.recent-bills-section {
    background: #ffffff;
    border-radius: 20px;
    padding: 30px;
    color: #000000;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid #f0f0f0;
}

.recent-bills-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.recent-bills-title {
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

.recent-bills-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.recent-bill-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    border-bottom: 1px solid #f0f0f0;
}

.recent-bill-item:last-child {
    border-bottom: none;
}

.recent-bill-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.recent-bill-icon {
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

.recent-bill-details h3 {
    font-size: 16px;
    font-weight: 600;
    color: #000000;
    margin-bottom: 5px;
}

.recent-bill-details p {
    font-size: 14px;
    color: #666;
}

.recent-bill-amount {
    font-size: 16px;
    font-weight: 700;
    color: #000000;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
}

.status-paid {
    background: #e8f5e8;
    color: #4CAF50;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-overdue {
    background: #f8d7da;
    color: #721c24;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: none;
    border-radius: 15px;
    width: 80%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
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
        font-size: 24px;
    }
    
    .container {
        padding: 15px;
    }
    
    .balance-card {
        width: 100%;
    }

    .bill-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .bill-item > div:last-child {
        align-self: flex-end;
        display: flex;
        align-items: center;
        gap: 10px;
    }
}
</style>
    <!-- Add your existing CSS here -->
</head>
<body>
<!-- Header -->
<header class="header">
    <div class="header-content">
        <a href="index.php" class="logo">Jalanyuk</a>
        <div class="user-info">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </div>
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <span><?php echo htmlspecialchars($user_name); ?></span>
        </div>
    </div>
</header>

    <div class="container">
        <!-- Alert Messages -->
        <div id="alert-success" class="alert alert-success" style="display: none;"></div>
        <div id="alert-error" class="alert alert-error" style="display: none;"></div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="welcome-section">
                <h1>Tagihan &<br>Pembayaran</h1>
                <p style="color: #666; font-size: 18px; margin-top: 10px;">
                    Bayar semua tagihan dengan mudah dan aman
                </p>
            </div>

            <div class="balance-card">
                <div class="balance-header">
                    <span class="balance-title">Current Balance</span>
                    <i class="fas fa-wallet" style="color: #ccc;"></i>
                </div>
                <div class="balance-amount" id="current-balance">Rp <?php echo number_format($user_balance, 0, ',', '.'); ?></div>
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

        <!-- Monthly Bills Section -->
        <div class="bills-section">
            <div class="bills-header">
                <h2 class="bills-title">Tagihan Bulanan</h2>
            </div>

            <div class="bills-list" id="bills-list">
                <?php if (empty($user_groups)): ?>
                    <div class="bill-item">
                        <div class="bill-info">
                            <div class="bill-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="bill-details">
                                <h3>Tidak ada tagihan bulanan</h3>
                                <p>Bergabunglah dengan grup untuk melihat tagihan bulanan</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($user_groups as $group): ?>
                        <?php if ($group['monthly_bill_amount'] > 0): ?>
                            <div class="bill-item" id="bill-item-<?php echo $group['id']; ?>">
                                <div class="bill-info">
                                    <div class="bill-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="bill-details">
                                        <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                        <p>Tagihan bulanan grup - Jatuh tempo setiap tanggal <?php echo $group['bill_day']; ?></p>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="bill-amount">Rp <?php echo number_format($group['monthly_bill_amount'], 0, ',', '.'); ?></div>
                                    <button class="btn-pay" onclick="payBill(<?php echo $group['id']; ?>, <?php echo $group['monthly_bill_amount']; ?>, '<?php echo addslashes($group['name']); ?>')">
                                        Bayar
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Konfirmasi Pembayaran</h2>
            <div id="payment-details"></div>
            <button id="confirm-payment" class="btn-pay" style="margin-top: 1rem;">Konfirmasi Pembayaran</button>
        </div>
    </div>

    <script>
        let currentPayment = null;
        
        // Modal functionality
        const modal = document.getElementById('paymentModal');
        const span = document.getElementsByClassName('close')[0];
        
        span.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Payment function
        function payBill(groupId, amount, groupName) {
            currentPayment = {
                group_id: groupId,
                amount: amount,
                group_name: groupName
            };
            
            const currentBalance = <?php echo $user_balance; ?>;
            
            if (currentBalance < amount) {
                showAlert('error', 'Saldo tidak mencukupi untuk pembayaran ini');
                return;
            }
            
            document.getElementById('payment-details').innerHTML = `
                <p><strong>Grup:</strong> ${groupName}</p>
                <p><strong>Jumlah:</strong> Rp ${amount.toLocaleString('id-ID')}</p>
                <p><strong>Saldo saat ini:</strong> Rp ${currentBalance.toLocaleString('id-ID')}</p>
                <p><strong>Saldo setelah pembayaran:</strong> Rp ${(currentBalance - amount).toLocaleString('id-ID')}</p>
            `;
            
            modal.style.display = 'block';
        }
        
        // Confirm payment
        document.getElementById('confirm-payment').onclick = function() {
            if (currentPayment) {
                this.disabled = true;
                this.textContent = 'Memproses...';
                processPayment(currentPayment);
            }
        }
        
        // Process payment
        function processPayment(payment) {
            const formData = new FormData();
            formData.append('action', 'pay_bill');
            formData.append('group_id', payment.group_id);
            formData.append('amount', payment.amount);
            formData.append('bill_type', payment.group_name);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                modal.style.display = 'none';
                
                // Reset button
                const confirmBtn = document.getElementById('confirm-payment');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Konfirmasi Pembayaran';
                
                if (data.success) {
                    showAlert('success', data.message);
                    
                    // Update balance display
                    document.getElementById('current-balance').textContent = 
                        'Rp ' + data.new_balance.toLocaleString('id-ID');
                    
                    // Remove the paid bill item from the list
                    const billItem = document.getElementById('bill-item-' + payment.group_id);
                    if (billItem) {
                        billItem.style.opacity = '0';
                        billItem.style.transform = 'translateX(-100%)';
                        billItem.style.transition = 'all 0.5s ease';
                        
                        setTimeout(() => {
                            billItem.remove();
                            
                            // Check if there are any bills left
                            const billsList = document.getElementById('bills-list');
                            const remainingBills = billsList.querySelectorAll('.bill-item');
                            
                            if (remainingBills.length === 0) {
                                billsList.innerHTML = `
                                    <div class="bill-item">
                                        <div class="bill-info">
                                            <div class="bill-icon">
                                                <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                            </div>
                                            <div class="bill-details">
                                                <h3>Semua tagihan sudah dibayar</h3>
                                                <p>Tidak ada tagihan bulanan yang tertunda</p>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }
                        }, 500);
                    }
                    
                    // Refresh page after 3 seconds to show updated recent payments
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    showAlert('error', data.message || 'Terjadi kesalahan saat memproses pembayaran');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modal.style.display = 'none';
                
                // Reset button
                const confirmBtn = document.getElementById('confirm-payment');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Konfirmasi Pembayaran';
                
                showAlert('error', 'Terjadi kesalahan saat memproses pembayaran');
            });
        }
        
        // Show alert function
        function showAlert(type, message) {
            const alertElement = document.getElementById('alert-' + type);
            alertElement.textContent = message;
            alertElement.style.display = 'block';
            
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }
        
        // Format currency function
        function formatCurrency(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }
        
        // Button hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn-action, .see-all-btn');
            
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Animate elements on load
        document.addEventListener('DOMContentLoaded', function() {
            const billItems = document.querySelectorAll('.bill-item');
            
            billItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                item.style.transition = 'all 0.5s ease';
                
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html> 