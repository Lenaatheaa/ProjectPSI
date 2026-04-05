<?php
// Database configuration
$host = '127.0.0.1';
$dbname = 'jalanyukproject';
$username = 'root'; // Sesuaikan dengan username database Anda
$password = ''; // Sesuaikan dengan password database Anda

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    // Get active groups
    $stmt = $pdo->query("SELECT COUNT(*) as active_groups FROM groups WHERE status = 'active'");
    $activeGroups = $stmt->fetch(PDO::FETCH_ASSOC)['active_groups'];
    
    // Get total target amount from all active groups
    $stmt = $pdo->query("SELECT SUM(target_amount) as total_target FROM groups WHERE status = 'active'");
    $totalTarget = $stmt->fetch(PDO::FETCH_ASSOC)['total_target'] ?? 0;
    
    // Get completed groups (target achieved)
    $stmt = $pdo->query("SELECT COUNT(*) as completed_groups FROM groups WHERE status = 'completed'");
    $completedGroups = $stmt->fetch(PDO::FETCH_ASSOC)['completed_groups'];
    
    // Get travel targets statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN current_balance >= target_amount THEN 1 END) as achieved_targets,
            COUNT(CASE WHEN current_balance > 0 AND current_balance < target_amount THEN 1 END) as in_progress_targets,
            COUNT(CASE WHEN current_balance = 0 THEN 1 END) as pending_targets,
            COUNT(*) as total_targets
        FROM groups 
        WHERE status = 'active'
    ");
    $targetStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $achievedTargets = $targetStats['achieved_targets'] ?? 0;
    $inProgressTargets = $targetStats['in_progress_targets'] ?? 0;
    $pendingTargets = $targetStats['pending_targets'] ?? 0;
    $totalTargets = $targetStats['total_targets'] ?? 0;
    
    // Get all groups with target progress
    $stmt = $pdo->query("
        SELECT 
            g.*,
            u.full_name as creator_name,
            CASE 
                WHEN g.current_balance >= g.target_amount THEN 'completed'
                WHEN g.current_balance > 0 THEN 'in_progress'
                ELSE 'pending'
            END as progress_status,
            CASE 
                WHEN g.target_amount > 0 THEN ROUND((g.current_balance / g.target_amount) * 100, 1)
                ELSE 0
            END as progress_percentage
        FROM groups g
        LEFT JOIN users u ON g.created_by = u.id
        WHERE g.status = 'active'
        ORDER BY g.created_at DESC
    ");
    $travelTargets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user growth data (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as user_count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $userGrowthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get daily transactions data (using actual transactions table)
    $stmt = $pdo->query("
        SELECT 
            DAYNAME(created_at) as day_name,
            COUNT(*) as transaction_count,
            SUM(amount) as total_amount
        FROM transactions 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DAYNAME(created_at), DAYOFWEEK(created_at)
        ORDER BY DAYOFWEEK(created_at)
    ");
    $transactionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get transaction statistics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN transaction_type IN ('topup', 'transfer_in') THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN transaction_type IN ('transfer_out') THEN amount ELSE 0 END) as total_expense,
            COUNT(*) as total_transactions
        FROM transactions 
        WHERE status = 'completed'
    ");
    $transactionStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalIncome = $transactionStats['total_income'] ?? 0;
    $totalExpense = $transactionStats['total_expense'] ?? 0;
    $totalTransactions = $transactionStats['total_transactions'] ?? 0;
    $netBalance = $totalIncome - $totalExpense;
    
    // Calculate growth percentages (mock data for now)
    $userGrowth = "+12.5%";
    $groupGrowth = "+8.2%";
    $targetGrowth = "+15.3%";
    $completedGrowth = "+23.1%";
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    $totalUsers = 0;
    $activeGroups = 0;
    $totalTarget = 0;
    $completedGroups = 0;
    $userGrowthData = [];
    $transactionData = [];
    $totalIncome = 0;
    $totalExpense = 0;
    $netBalance = 0;
    $travelTargets = [];
    $achievedTargets = 0;
    $inProgressTargets = 0;
    $pendingTargets = 0;
}

// Format currency
function formatCurrency($amount) {
    if ($amount >= 1000000) {
        return "Rp " . number_format($amount / 1000000, 1) . "M";
    } elseif ($amount >= 1000) {
        return "Rp " . number_format($amount / 1000, 1) . "K";
    } else {
        return "Rp " . number_format($amount, 0);
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                $userId = $_POST['user_id'];
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                echo "<script>alert('User berhasil dihapus!'); window.location.href='';</script>";
                break;
                
            case 'verify':
                $userId = $_POST['user_id'];
                $status = $_POST['status'];
                $reason = $_POST['reason'] ?? null;
                
                $stmt = $pdo->prepare("UPDATE users SET verification_status = ?, rejection_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$status, $reason, $userId]);
                echo "<script>alert('Status verifikasi berhasil diupdate!'); window.location.href='';</script>";
                break;
                
            case 'toggle_active':
                $userId = $_POST['user_id'];
                $isActive = $_POST['is_active'];
                
                $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$isActive, $userId]);
                echo "<script>alert('Status user berhasil diupdate!'); window.location.href='';</script>";
                break;
                
            case 'update_transaction_status':
                $transactionId = $_POST['transaction_id'];
                $newStatus = $_POST['new_status'];
                
                $stmt = $pdo->prepare("UPDATE transactions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$newStatus, $transactionId]);
                echo "<script>alert('Status transaksi berhasil diupdate!'); window.location.href='';</script>";
                break;
                
            case 'update_group_status':
                $groupId = $_POST['group_id'];
                $newStatus = $_POST['new_status'];
                
                $stmt = $pdo->prepare("UPDATE groups SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$newStatus, $groupId]);
                echo "<script>alert('Status grup berhasil diupdate!'); window.location.href='';</script>";
                break;
        }
    }
}

// Get statistics
$activeUsersStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
$activeUsers = $activeUsersStmt->fetch()['count'];

$newUsersStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
$newUsers = $newUsersStmt->fetch()['count'];

$verifiedUsersStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE verification_status = 'approved'");
$verifiedUsers = $verifiedUsersStmt->fetch()['count'];

// Get all users with search and filter
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter !== 'all') {
    if ($filter === 'active') {
        $sql .= " AND is_active = 1";
    } elseif ($filter === 'inactive') {
        $sql .= " AND is_active = 0";
    }
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get transactions with filters
$transactionType = $_GET['transaction_type'] ?? 'all';
$transactionDate = $_GET['transaction_date'] ?? '';

$transactionSql = "
    SELECT 
        t.id,
        t.user_id,
        u.full_name as user_name,
        u.email as user_email,
        t.transaction_type,
        t.amount,
        t.payment_method,
        t.status,
        t.description,
        t.created_at,
        t.updated_at
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE 1=1
";

$transactionParams = [];

if ($transactionType !== 'all') {
    if ($transactionType === 'deposit') {
        $transactionSql .= " AND t.transaction_type = 'topup'";
    } elseif ($transactionType === 'withdrawal') {
        $transactionSql .= " AND t.transaction_type = 'transfer_out'";
    } elseif ($transactionType === 'transfer') {
        $transactionSql .= " AND t.transaction_type IN ('transfer_in', 'transfer_out')";
    }
}

if (!empty($transactionDate)) {
    $transactionSql .= " AND DATE(t.created_at) = ?";
    $transactionParams[] = $transactionDate;
}

$transactionSql .= " ORDER BY t.created_at DESC";
$transactionStmt = $pdo->prepare($transactionSql);
$transactionStmt->execute($transactionParams);
$transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format status
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return '<span class="status-badge status-approved">Disetujui</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected">Ditolak</span>';
        case 'pending':
            return '<span class="status-badge status-pending">Menunggu</span>';
        default:
            return '<span class="status-badge status-pending">Menunggu</span>';
    }
}

// Function to format active status
function getActiveStatus($isActive) {
    return $isActive ? '<span class="status-badge status-active">Aktif</span>' : '<span class="status-badge status-inactive">Tidak Aktif</span>';
}

// Function to format transaction status
function getTransactionStatus($status) {
    switch ($status) {
        case 'completed':
            return '<span class="status-badge status-approved">Selesai</span>';
        case 'pending':
            return '<span class="status-badge status-pending">Menunggu</span>';
        case 'failed':
            return '<span class="status-badge status-rejected">Gagal</span>';
        case 'cancelled':
            return '<span class="status-badge status-inactive">Dibatalkan</span>';
        default:
            return '<span class="status-badge status-pending">Menunggu</span>';
    }
}

// Function to format transaction type
function getTransactionType($type) {
    switch ($type) {
        case 'topup':
            return '<span class="transaction-type deposit">Deposit</span>';
        case 'transfer_out':
            return '<span class="transaction-type withdrawal">Penarikan</span>';
        case 'transfer_in':
            return '<span class="transaction-type transfer">Transfer Masuk</span>';
        default:
            return '<span class="transaction-type">' . ucfirst($type) . '</span>';
    }
}

// Function to get transaction icon
function getTransactionIcon($type) {
    switch ($type) {
        case 'topup':
            return '<i class="fas fa-plus-circle text-success"></i>';
        case 'transfer_out':
            return '<i class="fas fa-minus-circle text-danger"></i>';
        case 'transfer_in':
            return '<i class="fas fa-exchange-alt text-info"></i>';
        default:
            return '<i class="fas fa-circle"></i>';
    }
}

// Function to get progress status badge
function getProgressStatusBadge($status) {
    switch ($status) {
        case 'completed':
            return '<span class="progress-badge progress-completed">Target Tercapai</span>';
        case 'in_progress':
            return '<span class="progress-badge progress-in-progress">Dalam Progress</span>';
        case 'pending':
            return '<span class="progress-badge progress-pending">Belum Dimulai</span>';
        default:
            return '<span class="progress-badge progress-pending">Belum Dimulai</span>';
    }
}

// Function to get progress color
function getProgressColor($status) {
    switch ($status) {
        case 'completed':
            return '#28a745';
        case 'in_progress':
            return '#ffc107';
        case 'pending':
            return '#6c757d';
        default:
            return '#6c757d';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MIS Travel</title>
    <link rel="stylesheet" href="css/admin.css  ">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <script src="js/admin.js" type="text/javascript"></script>
    <style>
        
 /* Dashboard Charts Grid */
.dashboard-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
}

/* Chart Container */
.chart-container {
    background: #f8fafc; /* latar container putih terang */
    border-radius: 16px;
    padding: 24px;
    border: 2px solid #000000; /* outline hitam */
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}

.chart-container:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

/* Chart Title */
.chart-container h3 {
    color: #000000; /* judul hitam */
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 20px;
    text-align: left;
    letter-spacing: 0.5px;
}

/* Chart Canvas */
.chart-container canvas {
    width: 100% !important;
    height: auto !important;
    max-height: 220px;
    border-radius: 10px;
    background: #ffffff;
    padding: 8px;
}

/* Time Selector Dropdown */
.time-selector {
    display: flex;
    justify-content: flex-end;
    margin-top: 25px;
}

.time-selector select {
    background: #ffffff;
    border: 1px solid #000000;
    border-radius: 8px;
    padding: 10px 16px;
    color: #000000;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.time-selector select:focus {
    outline: none;
    border-color: #000000;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.15);
}

.time-selector select option {
    background: #ffffff;
    color: #000000;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .dashboard-charts {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .chart-container {
        padding: 20px;
    }

    .chart-container h3 {
        font-size: 1.3rem;
        margin-bottom: 16px;
    }

    .chart-container canvas {
        max-height: 180px;
    }
}

@media (max-width: 480px) {
    .chart-container {
        padding: 16px;
    }

    .chart-container canvas {
        max-height: 150px;
    }
}

/* Legend Container */
.chart-container .chart-legend {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 15px;
    flex-wrap: wrap;
    z-index: 2;
}

.chart-container .legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #000000;
    opacity: 0.9;
}

.chart-container .legend-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}

/* No Data / Error State */
.chart-error,
.chart-no-data {
    text-align: center;
    padding: 40px 20px;
    color: #00000099;
    font-size: 1rem;
    z-index: 2;
}

.chart-error i,
.chart-no-data i {
    font-size: 2rem;
    margin-bottom: 12px;
    opacity: 0.3;
}

/* Travel Targets Styles */
.targets-overview {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.target-progress-chart {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.target-progress-chart h3 {
    margin-bottom: 1rem;
    color: #333;
    font-size: 1.2rem;
}

.target-progress-chart canvas {
    max-height: 250px;
}

.target-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.6rem;
}

.target-stat-card {
    background: white;
    border-radius: 10px;
    padding: 0.8rem;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: relative;
    overflow: hidden;
    min-height: 90px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.target-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.target-stat-card.achieved::before {
    background: #28a745;
}

.target-stat-card.in-progress::before {
    background: #ffc107;
}

.target-stat-card.pending::before {
    background: #6c757d;
}

.target-stat-card h4 {
    margin: 0 0 0.2rem 0;
    color: #666;
    font-size: 0.8rem;
    font-weight: 500;
}

.target-stat-card p {
    margin: 0 0 0.2rem 0;
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.target-stat-card span {
    font-size: 0.75rem;
    padding: 0.15rem 0.4rem;
    border-radius: 20px;
    font-weight: 500;
}

.target-stat-card.achieved span {
    background: #d4edda;
    color: #155724;
}

.target-stat-card.in-progress span {
    background: #fff3cd;
    color: #856404;
}

.target-stat-card.pending span {
    background: #f8d7da;
    color: #721c24;
}

.targets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.target-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid #ddd;
}

.target-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.target-card.completed {
    border-left-color: #28a745;
}

.target-card.in_progress {
    border-left-color: #ffc107;
}

.target-card.pending {
    border-left-color: #6c757d;
}

.target-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.target-header h3 {
    margin: 0;
    font-size: 1.3rem;
    color: #333;
    font-weight: 600;
}

.progress-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.progress-badge.progress-completed {
    background: #d4edda;
    color: #155724;
}

.progress-badge.progress-in-progress {
    background: #fff3cd;
    color: #856404;
}

.progress-badge.progress-pending {
    background: #f8f9fa;
    color: #6c757d;
}

.target-description {
    margin-bottom: 1.5rem;
}

.target-description p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.5;
}

.target-progress {
    margin-bottom: 1.5rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.current-amount {
    font-weight: bold;
    color: #333;
    font-size: 1.1rem;
}

.target-amount {
    color: #666;
    font-size: 0.9rem;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    transition: width 0.3s ease;
    border-radius: 4px;
}

.progress-percentage {
    text-align: right;
    font-size: 0.8rem;
    color: #666;
    font-weight: 500;
}

.target-details {
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: #666;
}

.detail-item i {
    width: 16px;
    margin-right: 0.5rem;
    color: #999;
}

.target-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.target-actions .btn {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s;
}

.target-actions .btn-primary {
    background: #007bff;
    color: white;
    border: none;
}

.target-actions .btn-primary:hover {
    background: #0056b3;
}

.target-actions .form-select {
    padding: 0.3rem 0.5rem;
    font-size: 0.8rem;
    border-radius: 6px;
    border: 1px solid #ddd;
    min-width: 120px;
}

.target-actions .form-select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

/* Responsive Design */
@media (max-width: 768px) {
    .targets-overview {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .target-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }
    
    .target-stat-card {
        padding: 1rem;
    }
    
    .target-stat-card h4 {
        font-size: 0.8rem;
    }
    
    .target-stat-card p {
        font-size: 1.5rem;
    }
    
    .target-stat-card span {
        font-size: 0.7rem;
        padding: 0.1rem 0.3rem;
    }
    
    .targets-grid {
        grid-template-columns: 1fr;
    }
    
    .target-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .target-actions .form-select {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .target-stats {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .target-stat-card {
        padding: 0.8rem;
    }
}
</style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-plane"></i>
                    </div>
                    <span class="logo-text">Admin Dashboard</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <li class="nav-item active" data-page="dashboard">
                        <a href="#" class="nav-link">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item" data-page="users">
                        <a href="#" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Manajemen User</span>
                        </a>
                    </li>
                    <li class="nav-item" data-page="savings">
                        <a href="#" class="nav-link">
                            <i class="fas fa-piggy-bank"></i>
                            <span>Grup Tabungan</span>
                        </a>
                    </li>
                    <li class="nav-item" data-page="transactions">
                        <a href="#" class="nav-link">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Transaksi</span>
                        </a>
                    </li>
                    <li class="nav-item" data-page="targets">
                        <a href="#" class="nav-link">
                            <i class="fas fa-bullseye"></i>
                            <span>Target Travel</span>
                        </a>
                    </li>


                    <!-- Kamu bebas hapus bagian ini tanpa merusak layout -->
                    <!-- <li class="nav-item" data-page="settings">
                        <a href="#" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Pengaturan</span>
                        </a>
                    </li> -->
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <h1 class="page-title">Dashboard</h1>
                    <p class="welcome-text">Selamat datang kembali, Admin!</p>
                </div>
                <div class="header-right">
                    <div class="notification-container">
                        <div class="notification-icon" id="notificationIcon">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </div>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h4>Notifikasi</h4>
                                <button class="mark-all-read">Tandai Semua Dibaca</button>
                            </div>
                            <div class="notification-list">
                                <div class="notification-item unread">
                                    <div class="notification-icon-small">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p><strong>User Baru</strong></p>
                                        <p>Ahmad Rizki telah mendaftar</p>
                                        <span class="notification-time">2 menit lalu</span>
                                    </div>
                                </div>
                                <div class="notification-item unread">
                                    <div class="notification-icon-small">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p><strong>Transaksi Baru</strong></p>
                                        <p>Deposit Rp 500.000 dari Siti Nurhaliza</p>
                                        <span class="notification-time">5 menit lalu</span>
                                    </div>
                                </div>
                                <div class="notification-item">
                                    <div class="notification-icon-small">
                                        <i class="fas fa-bullseye"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p><strong>Target Tercapai</strong></p>
                                        <p>Maya Sari mencapai target Bali</p>
                                        <span class="notification-time">1 jam lalu</span>
                                    </div>
                                </div>
                            </div>
                            <div class="notification-footer">
                                <a href="#" class="view-all-notifications">Lihat Semua Notifikasi</a>
                            </div>
                        </div>
                    </div>
                    <div class="admin-profile">
                        <img src="https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?auto=compress&cs=tinysrgb&w=150&h=150&fit=crop" alt="Admin" class="admin-avatar">
                        <span class="admin-name">Admin</span>
                    </div>
                </div>
            </header>

           <!-- Content Pages -->
<div class="content-wrapper">
    <!-- Dashboard Page -->
    <div class="page-content active" id="dashboard-page">
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>TOTAL USERS</h3>
                    <p class="stat-number"><?php echo number_format($totalUsers); ?></p>
                    <span class="stat-change positive"><?php echo $userGrowth; ?></span>
                </div>
            </div>
            
            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="stat-info">
                    <h3>GRUP AKTIF</h3>
                    <p class="stat-number"><?php echo number_format($activeGroups); ?></p>
                    <span class="stat-change positive"><?php echo $groupGrowth; ?></span>
                </div>
            </div>
            
            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3>TOTAL TARGET</h3>
                    <p class="stat-number"><?php echo formatCurrency($totalTarget); ?></p>
                    <span class="stat-change positive"><?php echo $targetGrowth; ?></span>
                </div>
            </div>
            
            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <div class="stat-info">
                    <h3>GRUP SELESAI</h3>
                    <p class="stat-number"><?php echo number_format($completedGroups); ?></p>
                    <span class="stat-change positive"><?php echo $completedGrowth; ?></span>
                </div>
            </div>
        </div>
        
        <div class="dashboard-charts">
            <div class="chart-container">
                <h3>Pertumbuhan User</h3>
                <canvas id="userGrowthChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Aktivitas Grup Harian</h3>
                <canvas id="transactionChart"></canvas>
            </div>
        </div>
        
        <div class="time-selector">
            <select id="timeRange">
                <option value="7">7 Hari Terakhir</option>
                <option value="30">30 Hari Terakhir</option>
                <option value="90">90 Hari Terakhir</option>
            </select>
        </div>
    </div>


              <!-- User Management Page -->
<div class="page-content" id="users-page">
    <div class="page-header">
        <h2>Manajemen User</h2>
    </div>
    
    <div class="user-stats">
        <div class="user-stat-card">
            <h4>User Aktif</h4>
            <p class="stat-value"><?php echo number_format($activeUsers); ?></p>
        </div>
        <div class="user-stat-card">
            <h4>User Baru (Bulan ini)</h4>
            <p class="stat-value"><?php echo number_format($newUsers); ?></p>
        </div>
        <div class="user-stat-card">
            <h4>User Terverifikasi</h4>
            <p class="stat-value"><?php echo number_format($verifiedUsers); ?></p>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Cari user..." id="userSearch" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-options">
                <select id="userFilter">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Semua User</option>
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>User Aktif</option>
                    <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>User Tidak Aktif</option>
                </select>
            </div>
        </div>
        
        <table class="data-table" id="usersTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Telepon</th>
                    <th>Status Verifikasi</th>
                    <th>Status Aktif</th>
                    <th>Bergabung</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <td><?php echo getStatusBadge($user['verification_status']); ?></td>
                    <td><?php echo getActiveStatus($user['is_active']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <div class="action-buttons">
                            <!-- View Button -->
                            <button class="btn-action btn-view" onclick="viewUser(<?php echo $user['id']; ?>)" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <!-- Verification Buttons -->
                            <?php if ($user['verification_status'] === 'pending'): ?>
                            <button class="btn-action btn-approve" onclick="verifyUser(<?php echo $user['id']; ?>, 'approved')" title="Setujui">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn-action btn-reject" onclick="verifyUser(<?php echo $user['id']; ?>, 'rejected')" title="Tolak">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                            
                            <!-- Toggle Active Button -->
                            <button class="btn-action <?php echo $user['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?>" 
                                    onclick="toggleActive(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? '0' : '1'; ?>)"
                                    title="<?php echo $user['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                            </button>
                            
                            <!-- Delete Button -->
                            <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal untuk View User Detail -->
<div id="userModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Detail User</h3>
        <div id="userDetails"></div>
    </div>
</div>

<!-- Modal untuk Rejection Reason -->
<div id="rejectModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Alasan Penolakan</h3>
        <form id="rejectForm" method="POST">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="user_id" id="rejectUserId">
            <input type="hidden" name="status" value="rejected">
            <textarea name="reason" placeholder="Masukkan alasan penolakan..." required></textarea>
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Tolak User</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

              <!-- Savings Groups Page -->
<div class="page-content" id="savings-page">
    <div class="page-header">
        <h2>Grup Tabungan</h2>
    </div>

    <div class="savings-overview">
        <div class="savings-chart">
            <h3>Distribusi Grup Tabungan</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="savingsDistributionChart"></canvas>
            </div>
        </div>
        <div class="savings-stats">
            <div class="savings-stat-item">
                <h4>Total Grup Aktif</h4>
                <p id="totalActiveGroups">Loading...</p>
            </div>
            <div class="savings-stat-item">
                <h4>Rata-rata per Grup</h4>
                <p id="avgPerGroup">Loading...</p>
            </div>
            <div class="savings-stat-item">
                <h4>Grup Terbesar</h4>
                <p id="largestGroup">Loading...</p>
            </div>
        </div>
    </div>

    <div class="groups-grid" id="groupsGrid">
        <!-- Group items will be loaded here -->
    </div>
</div>

<!-- Tambahkan Chart.js CDN di head HTML Anda -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

              
<!-- Transactions Page -->
<div class="page-content" id="transactions-page">
    <div class="page-header">
        <h2>Transaksi</h2>
        <div class="transaction-filters">
            <form method="GET" style="display: flex; gap: 10px;">
                <select name="transaction_type" id="transactionType" onchange="this.form.submit()">
                    <option value="all" <?= $transactionType === 'all' ? 'selected' : '' ?>>Semua Transaksi</option>
                    <option value="deposit" <?= $transactionType === 'deposit' ? 'selected' : '' ?>>Deposit</option>
                    <option value="withdrawal" <?= $transactionType === 'withdrawal' ? 'selected' : '' ?>>Penarikan</option>
                    <option value="transfer" <?= $transactionType === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                </select>
                <input type="date" name="transaction_date" id="transactionDate" value="<?= $transactionDate ?>" onchange="this.form.submit()">
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>

    <div class="transaction-summary">
        <div class="summary-card income">
            <div class="summary-icon">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="summary-info">
                <h4>Total Masuk</h4>
                <p><?= formatCurrency($totalIncome) ?></p>
                <span class="change positive">+8.5%</span>
            </div>
        </div>
        <div class="summary-card expense">
            <div class="summary-icon">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="summary-info">
                <h4>Total Keluar</h4>
                <p><?= formatCurrency($totalExpense) ?></p>
                <span class="change negative">-2.1%</span>
            </div>
        </div>
        <div class="summary-card balance">
            <div class="summary-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="summary-info">
                <h4>Saldo Bersih</h4>
                <p><?= formatCurrency($netBalance) ?></p>
                <span class="change positive">+12.8%</span>
            </div>
        </div>
    </div>

    <div class="transaction-chart">
        <h3>Tren Transaksi</h3>
        <canvas id="transactionTrendChart"></canvas>
    </div>

    <div class="table-container">
        <table class="data-table" id="transactionsTable">
            <thead>
                <tr>
                    <th>ID Transaksi</th>
                    <th>User</th>
                    <th>Tipe</th>
                    <th>Jumlah</th>
                    <th>Metode Pembayaran</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="transactionsTableBody">
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td>
                        <div class="transaction-id">
                            <?= getTransactionIcon($transaction['transaction_type']) ?>
                            #<?= str_pad($transaction['id'], 5, '0', STR_PAD_LEFT) ?>
                        </div>
                    </td>
                    <td>
                        <div class="user-info">
                            <strong><?= htmlspecialchars($transaction['user_name'] ?? 'Unknown User') ?></strong>
                            <small><?= htmlspecialchars($transaction['user_email'] ?? '') ?></small>
                        </div>
                    </td>
                    <td><?= getTransactionType($transaction['transaction_type']) ?></td>
                    <td>
                        <div class="amount <?= in_array($transaction['transaction_type'], ['topup', 'transfer_in']) ? 'positive' : 'negative' ?>">
                            <?= in_array($transaction['transaction_type'], ['topup', 'transfer_in']) ? '+' : '-' ?>
                            <?= formatCurrency($transaction['amount']) ?>
                        </div>
                    </td>
                    <td>
                        <div class="payment-method">
                            <i class="fas fa-<?= $transaction['payment_method'] === 'Transfer' ? 'exchange-alt' : 'credit-card' ?>"></i>
                            <?= htmlspecialchars($transaction['payment_method']) ?>
                        </div>
                    </td>
                    <td><?= getTransactionStatus($transaction['status']) ?></td>
                    <td>
                        <div class="date-info">
                            <strong><?= date('d/m/Y', strtotime($transaction['created_at'])) ?></strong>
                            <small><?= date('H:i', strtotime($transaction['created_at'])) ?></small>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-info" onclick="viewTransaction(<?= $transaction['id'] ?>)" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($transaction['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-success" onclick="updateTransactionStatus(<?= $transaction['id'] ?>, 'completed')" title="Setujui">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="updateTransactionStatus(<?= $transaction['id'] ?>, 'failed')" title="Tolak">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>Tidak ada transaksi ditemukan</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    
<!-- Travel Targets Page -->
<div class="page-content" id="targets-page">
    <div class="page-header">
        <h2>Target Travel</h2>
    </div>

    <div class="targets-overview">
        <div class="target-progress-chart">
            <h3>Progress Target</h3>
            <canvas id="targetProgressChart"></canvas>
        </div>
        <div class="target-stats">
            <div class="target-stat-card achieved">
                <h4>Target Tercapai</h4>
                <p><?= $achievedTargets ?></p>
                <span>+23.1%</span>
            </div>
            <div class="target-stat-card in-progress">
                <h4>Dalam Progress</h4>
                <p><?= $inProgressTargets ?></p>
                <span>+15.2%</span>
            </div>
            <div class="target-stat-card pending">
                <h4>Belum Dimulai</h4>
                <p><?= $pendingTargets ?></p>
                <span>-5.8%</span>
            </div>
        </div>
    </div>

    <div class="targets-grid" id="targetsGrid">
        <?php foreach ($travelTargets as $target): ?>
            <div class="target-card <?= $target['progress_status'] ?>">
                <div class="target-header">
                    <h3><?= htmlspecialchars($target['name']) ?></h3>
                    <?= getProgressStatusBadge($target['progress_status']) ?>
                </div>
                
                <div class="target-description">
                    <p><?= htmlspecialchars($target['description']) ?></p>
                </div>
                
                <div class="target-progress">
                    <div class="progress-info">
                        <span class="current-amount"><?= formatCurrency($target['current_balance']) ?></span>
                        <span class="target-amount">dari <?= formatCurrency($target['target_amount']) ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $target['progress_percentage'] ?>%; background-color: <?= getProgressColor($target['progress_status']) ?>"></div>
                    </div>
                    <div class="progress-percentage">
                        <?= $target['progress_percentage'] ?>%
                    </div>
                </div>
                
                <div class="target-details">
                    <div class="detail-item">
                        <i class="fas fa-user"></i>
                        <span>Dibuat oleh: <?= htmlspecialchars($target['creator_name']) ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-calendar"></i>
                        <span><?= date('d M Y', strtotime($target['start_date'])) ?> - <?= date('d M Y', strtotime($target['end_date'])) ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        <span>Max: <?= $target['max_members'] ?> member</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-<?= $target['is_public'] ? 'globe' : 'lock' ?>"></i>
                        <span><?= $target['is_public'] ? 'Publik' : 'Privat' ?></span>
                    </div>
                </div>
                
                <div class="target-actions">
                    <button class="btn btn-primary btn-sm" onclick="viewTargetDetails(<?= $target['id'] ?>)">
                        <i class="fas fa-eye"></i> Detail
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="update_group_status">
                        <input type="hidden" name="group_id" value="<?= $target['id'] ?>">
                        <select name="new_status" onchange="this.form.submit()" class="form-select form-select-sm">
                            <option value="active" <?= $target['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="completed" <?= $target['status'] === 'completed' ? 'selected' : '' ?>>Selesai</option>
                            <option value="cancelled" <?= $target['status'] === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                            <option value="inactive" <?= $target['status'] === 'inactive' ? 'selected' : '' ?>>Tidak Aktif</option>
                        </select>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Function to view target details
function viewTargetDetails(targetId) {
    // You can implement a modal or redirect to detail page
    alert('Lihat detail target ID: ' + targetId);
}

// Update target progress chart
function updateTargetProgressChart() {
    const ctx = document.getElementById('targetProgressChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Target Tercapai', 'Dalam Progress', 'Belum Dimulai'],
            datasets: [{
                data: [<?= $achievedTargets ?>, <?= $inProgressTargets ?>, <?= $pendingTargets ?>],
                backgroundColor: [
                    '#28a745',
                    '#ffc107',
                    '#6c757d'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Initialize chart when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('targetProgressChart')) {
        updateTargetProgressChart();
    }
});
</script>

                <!-- Reports Page -->
                <div class="page-content" id="reports-page">
                    <div class="page-header">
                        <h2>Laporan</h2>
                     

                <!-- AI Assistant Page -->
                <div class="page-content" id="ai-assistant-page">
                    <div class="ai-chat-container">
                        <div class="chat-header">
                            <div class="chat-title">
                                <i class="fas fa-robot"></i>
                                <h2>AI Assistant</h2>
                            </div>
                            <div class="chat-status">
                                <span class="status-indicator online"></span>
                                <span>Online</span>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <div class="message ai-message">
                                <div class="message-avatar">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div class="message-content">
                                    <p>Halo! Saya AI Assistant Anda. Bagaimana saya bisa membantu Anda hari ini?</p>
                                    <span class="message-time">10:30</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chat-input-container">
                            <div class="chat-input-wrapper">
                                <input type="text" id="chatInput" placeholder="Ketik pesan Anda..." maxlength="500">
                                <button id="sendButton" class="send-button">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            <div class="input-suggestions">
                                <button class="suggestion-btn" data-text="Berapa total user aktif bulan ini?">
                                    Statistik User
                                </button>
                                <button class="suggestion-btn" data-text="Bagaimana performa transaksi hari ini?">
                                    Performa Transaksi
                                </button>
                                <button class="suggestion-btn" data-text="Analisis target travel yang tercapai">
                                    Analisis Target
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Page -->
                <div class="page-content" id="settings-page">
                    <div class="page-header">
                        <h2>Pengaturan</h2>
                    </div>

                    <div class="settings-sections">
                        <div class="settings-section">
                            <h3>Pengaturan Umum</h3>
                            <div class="setting-item">
                                <label>Nama Platform</label>
                                <input type="text" value="MIS Travel" id="platformName">
                            </div>
                            <div class="setting-item">
                                <label>Email Admin</label>
                                <input type="email" value="admin@mistravel.com" id="adminEmail">
                            </div>
                            <div class="setting-item">
                                <label>Zona Waktu</label>
                                <select id="timezone">
                                    <option value="WIB">WIB (UTC+7)</option>
                                    <option value="WITA">WITA (UTC+8)</option>
                                    <option value="WIT">WIT (UTC+9)</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3>Notifikasi</h3>
                            <div class="setting-item">
                                <label class="switch">
                                    <input type="checkbox" id="emailNotifications" checked>
                                    <span class="slider"></span>
                                </label>
                                <span>Email Notifications</span>
                            </div>
                            <div class="setting-item">
                                <label class="switch">
                                    <input type="checkbox" id="pushNotifications" checked>
                                    <span class="slider"></span>
                                </label>
                                <span>Push Notifications</span>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3>Keamanan</h3>
                            <div class="setting-item">
                                <button class="btn-secondary" id="changePasswordBtn">Ubah Password</button>
                            </div>
                            <div class="setting-item">
                                <button class="btn-secondary" id="enable2FABtn">Aktifkan 2FA</button>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h3>API Configuration</h3>
                            <div class="setting-item">
                                <label>OpenAI API Key</label>
                                <input type="password" placeholder="sk-..." id="openaiApiKey">
                                <button class="btn-primary" id="saveApiKeyBtn">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- API Key Modal -->
    <div class="modal" id="apiKeyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Konfigurasi OpenAI API</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Masukkan API Key OpenAI Anda untuk menggunakan AI Assistant:</p>
                <input type="password" id="apiKeyInput" placeholder="sk-...">
                <button id="saveApiKey" class="save-btn">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Transaction Detail Modal -->
    <div class="modal" id="transactionDetailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Detail Transaksi</h3>
                <button class="modal-close" id="closeTransactionModal">&times;</button>
            </div>
            <div class="modal-body" id="transactionDetailContent">
                <!-- Transaction details will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="modal-close" id="closeEditUserModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <div class="form-group">
                        <label>Nama</label>
                        <input type="text" id="editUserName" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="editUserEmail" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="editUserStatus">
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Total Tabungan</label>
                        <input type="number" id="editUserSavings" min="0">
                    </div>
                    <button type="submit" class="save-btn">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>

    <script>
// Search functionality
document.getElementById('userSearch').addEventListener('input', function() {
    const searchValue = this.value;
    const filterValue = document.getElementById('userFilter').value;
    updateTable(searchValue, filterValue);
});

// Filter functionality
document.getElementById('userFilter').addEventListener('change', function() {
    const filterValue = this.value;
    const searchValue = document.getElementById('userSearch').value;
    updateTable(searchValue, filterValue);
});

function updateTable(search, filter) {
    const url = new URL(window.location);
    url.searchParams.set('search', search);
    url.searchParams.set('filter', filter);
    window.location.href = url.toString();
}

// View user details
function viewUser(userId) {
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('userDetails').innerHTML = `
                    <div class="user-detail-grid">
                        <div class="detail-item">
                            <label>Nama Lengkap:</label>
                            <span>${user.full_name}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email:</label>
                            <span>${user.email}</span>
                        </div>
                        <div class="detail-item">
                            <label>Telepon:</label>
                            <span>${user.phone}</span>
                        </div>
                        <div class="detail-item">
                            <label>Tanggal Lahir:</label>
                            <span>${user.birth_date}</span>
                        </div>
                        <div class="detail-item">
                            <label>Alamat:</label>
                            <span>${user.address}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status Verifikasi:</label>
                            <span>${user.verification_status}</span>
                        </div>
                        <div class="detail-item">
                            <label>Email Verified:</label>
                            <span>${user.email_verified ? 'Ya' : 'Tidak'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Bergabung:</label>
                            <span>${user.created_at}</span>
                        </div>
                        ${user.ktp_path ? `<div class="detail-item">
                            <label>KTP:</label>
                            <a href="${user.ktp_path}" target="_blank" class="btn btn-sm btn-primary">Lihat KTP</a>
                        </div>` : ''}
                        ${user.rejection_reason ? `<div class="detail-item">
                            <label>Alasan Penolakan:</label>
                            <span>${user.rejection_reason}</span>
                        </div>` : ''}
                    </div>
                `;
                document.getElementById('userModal').style.display = 'block';
            }
        });
}

// Verify user
function verifyUser(userId, status) {
    if (status === 'approved') {
        if (confirm('Apakah Anda yakin ingin menyetujui user ini?')) {
            submitVerification(userId, status);
        }
    } else {
        document.getElementById('rejectUserId').value = userId;
        document.getElementById('rejectModal').style.display = 'block';
    }
}

function submitVerification(userId, status, reason = null) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="verify">
        <input type="hidden" name="user_id" value="${userId}">
        <input type="hidden" name="status" value="${status}">
        ${reason ? `<input type="hidden" name="reason" value="${reason}">` : ''}
    `;
    document.body.appendChild(form);
    form.submit();
}

// Toggle active status
function toggleActive(userId, isActive) {
    const action = isActive ? 'mengaktifkan' : 'menonaktifkan';
    if (confirm(`Apakah Anda yakin ingin ${action} user ini?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="is_active" value="${isActive}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Delete user
function deleteUser(userId) {
    if (confirm('Apakah Anda yakin ingin menghapus user ini? Tindakan ini tidak dapat dibatalkan.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Modal close events
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<script>
// Chart data from PHP
const userGrowthData = <?php echo json_encode($userGrowthData); ?>;
const transactionData = <?php echo json_encode($transactionData); ?>;

// User Growth Chart
const userCtx = document.getElementById('userGrowthChart').getContext('2d');
const userChart = new Chart(userCtx, {
    type: 'line',
    data: {
        labels: userGrowthData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('id-ID', { month: 'short' });
        }),
        datasets: [{
            label: 'User Growth',
            data: userGrowthData.map(item => item.user_count),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Transaction Chart (using group creation data)
const transactionCtx = document.getElementById('transactionChart').getContext('2d');
const transactionChart = new Chart(transactionCtx, {
    type: 'bar',
    data: {
        labels: transactionData.map(item => item.day_name),
        datasets: [{
            label: 'Grup Dibuat',
            data: transactionData.map(item => item.transaction_count),
            backgroundColor: '#10b981',
            borderColor: '#059669',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

</script>
<script>
// Function to view transaction details
function viewTransaction(transactionId) {
    // Implement modal or redirect to transaction detail page
    alert('Melihat detail transaksi #' + transactionId);
}

// Function to update transaction status
function updateTransactionStatus(transactionId, newStatus) {
    if (confirm('Apakah Anda yakin ingin mengubah status transaksi ini?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_transaction_status">
            <input type="hidden" name="transaction_id" value="${transactionId}">
            <input type="hidden" name="new_status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize transaction trend chart
const transactionTrendData = <?= json_encode($transactionData) ?>;
const ctx = document.getElementById('transactionTrendChart').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: transactionTrendData.map(item => item.day_name),
        datasets: [{
            label: 'Jumlah Transaksi',
            data: transactionTrendData.map(item => item.transaction_count),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }, {
            label: 'Total Nilai (Juta)',
            data: transactionTrendData.map(item => (item.total_amount || 0) / 1000000),
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            title: {
                display: true,
                text: 'Tren Transaksi 7 Hari Terakhir'
            },
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Jumlah Transaksi'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Total Nilai (Juta)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>
<script>// Function untuk load data grup dan update chart
async function loadGroupsData() {
    try {
        const response = await fetch('get_groups_data.php');
        const data = await response.json();
        
        if (data.error) {
            console.error('Error:', data.error);
            return;
        }
        
        // Update statistik
        updateGroupStats(data.stats);
        
        // Create pie chart
        createSavingsDistributionChart(data.status_distribution);
        
    } catch (error) {
        console.error('Error fetching data:', error);
    }
}

// Function untuk update statistik grup
function updateGroupStats(stats) {
    // Update Total Grup Aktif
    const totalActiveElement = document.querySelector('.savings-stat-item:nth-child(1) p');
    if (totalActiveElement) {
        totalActiveElement.textContent = stats.active_groups.toLocaleString('id-ID');
    }
    
    // Update Rata-rata per Grup
    const avgElement = document.querySelector('.savings-stat-item:nth-child(2) p');
    if (avgElement) {
        avgElement.textContent = `Rp ${stats.avg_target}`;
    }
    
    // Update Grup Terbesar
    const maxElement = document.querySelector('.savings-stat-item:nth-child(3) p');
    if (maxElement) {
        maxElement.textContent = `Rp ${stats.max_target}`;
    }
}

// Function untuk create pie chart
function createSavingsDistributionChart(statusData) {
    const ctx = document.getElementById('savingsDistributionChart');
    if (!ctx) return;
    
    // Destroy existing chart if exists
    if (window.savingsChart) {
        window.savingsChart.destroy();
    }
    
    // Prepare data for chart
    const labels = [];
    const counts = [];
    const colors = [];
    
    const colorMap = {
        'active': '#4CAF50',
        'completed': '#2196F3', 
        'cancelled': '#F44336',
        'inactive': '#FF9800'
    };
    
    const labelMap = {
        'active': 'Aktif',
        'completed': 'Selesai',
        'cancelled': 'Dibatalkan',
        'inactive': 'Tidak Aktif'
    };
    
    statusData.forEach(item => {
        labels.push(labelMap[item.status] || item.status);
        counts.push(parseInt(item.count));
        colors.push(colorMap[item.status] || '#607D8B');
    });
    
    // Create chart
    window.savingsChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors,
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#ffffff',
                        font: {
                            size: 12
                        },
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Load data when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadGroupsData();
});

// Optional: Auto refresh data every 30 seconds
setInterval(loadGroupsData, 30000);</script>

</body>

</html>