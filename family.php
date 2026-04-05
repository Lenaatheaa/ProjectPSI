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

    // Include invitation helper functions
    require_once 'invitation_helper.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) { 
        // If there's an invitation code in URL, store it for after login
        if (isset($_GET['join']) && !empty($_GET['join'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: login.php');
            exit;
        }
        header('Location: login.php');
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Handle join group via URL parameter
    if (isset($_GET['join']) && !empty($_GET['join'])) {
        $invitation_code = trim($_GET['join']);
        
        try {
            // Validate invitation code
            $stmt = $pdo->prepare("
                SELECT g.*, 
                    u.full_name as creator_name,
                    COUNT(DISTINCT gm.user_id) as member_count,
                    CASE WHEN gm_check.user_id IS NOT NULL THEN 1 ELSE 0 END as already_member
                FROM groups g 
                LEFT JOIN users u ON g.created_by = u.id
                LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                LEFT JOIN group_members gm_check ON g.id = gm_check.group_id AND gm_check.user_id = ? AND gm_check.status = 'active'
                WHERE g.invitation_code = ? AND g.status = 'active'
                GROUP BY g.id
            ");
            $stmt->execute([$user_id, $invitation_code]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$group) {
                $_SESSION['join_message'] = [
                    'type' => 'error',
                    'text' => 'Kode undangan tidak valid atau grup tidak aktif!'
                ];
            } elseif ($group['already_member']) {
                $_SESSION['join_message'] = [
                    'type' => 'info',
                    'text' => 'Anda sudah menjadi anggota grup: ' . $group['name']
                ];
            } else {
                // Check if group has reached max members
                if ($group['member_count'] >= $group['max_members']) {
                    $_SESSION['join_message'] = [
                        'type' => 'error',
                        'text' => 'Grup sudah mencapai batas maksimum anggota!'
                    ];
                } else {
                    // Add user to group
                    $stmt = $pdo->prepare("
                        INSERT INTO group_members (group_id, user_id, role, status, joined_at) 
                        VALUES (?, ?, 'member', 'active', NOW())
                    ");
                    $stmt->execute([$group['id'], $user_id]);
                    
                    // Create monthly bill for the new member if monthly billing is enabled
                    if ($group['monthly_bill_amount'] > 0) {
                        createMonthlyBillForNewMember($pdo, $group['id'], $user_id, $group['monthly_bill_amount'], $group['created_by']);
                    }
                    
                    // Create notification for group creator
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, group_id, type, title, message, action_url, created_at)
                        VALUES (?, ?, 'member_joined', 'Anggota Baru Bergabung', 
                                CONCAT(?, ' bergabung ke grup ', ?), 
                                CONCAT('group_detail.php?id=', ?), NOW())
                    ");
                    $stmt->execute([
                        $group['created_by'], 
                        $group['id'], 
                        $_SESSION['user_name'] ?? 'User', 
                        $group['name'],
                        $group['id']
                    ]);
                    
                    // Log activity
                    $stmt = $pdo->prepare("
                        INSERT INTO group_activities (group_id, user_id, activity_type, activity_description, created_at)
                        VALUES (?, ?, 'member_joined', CONCAT(?, ' bergabung ke grup melalui link undangan'), NOW())
                    ");
                    $stmt->execute([
                        $group['id'], 
                        $user_id, 
                        $_SESSION['user_name'] ?? 'User'
                    ]);
                    
                    $_SESSION['join_message'] = [
                        'type' => 'success',
                        'text' => 'Berhasil bergabung dengan grup: ' . $group['name']
                    ];
                }
            }
        } catch(Exception $e) {
            error_log("Join group error: " . $e->getMessage());
            $_SESSION['join_message'] = [
                'type' => 'error',
                'text' => 'Terjadi kesalahan saat bergabung ke grup!'
            ];
        }
        
        // Redirect to remove join parameter
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // Function to create monthly bill for new member using group_bill table only
    function createMonthlyBillForNewMember($pdo, $group_id, $user_id, $monthly_amount, $created_by) {
        try {
            // Get the current month's billing period
            $current_month = date('Y-m');
            $due_date = date('Y-m-t'); // Last day of current month
            
            // Check if bill already exists for this month
            $stmt = $pdo->prepare("
                SELECT id FROM group_bill 
                WHERE group_id = ? AND user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') = ?
            ");
            $stmt->execute([$group_id, $user_id, $current_month]);
            
            if (!$stmt->fetch()) {
                // Create new monthly bill in group_bill table
                $stmt = $pdo->prepare("
                    INSERT INTO group_bill (group_id, user_id, bill_amount, remaining_amount, due_date, status, created_by, notes)
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, CONCAT('Tagihan bulanan untuk ', ?))
                ");
                $stmt->execute([
                    $group_id, 
                    $user_id, 
                    $monthly_amount, 
                    $monthly_amount, 
                    $due_date, 
                    $created_by,
                    $current_month
                ]);
            }
        } catch(Exception $e) {
            error_log("Create monthly bill error: " . $e->getMessage());
        }
    }

    // Function to create monthly bills for all active members
    function createMonthlyBillsForAllMembers($pdo, $group_id, $monthly_amount, $created_by) {
        try {
            // Get all active members
            $stmt = $pdo->prepare("
                SELECT user_id FROM group_members 
                WHERE group_id = ? AND status = 'active'
            ");
            $stmt->execute([$group_id]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($members as $member) {
                createMonthlyBillForNewMember($pdo, $group_id, $member['user_id'], $monthly_amount, $created_by);
            }
        } catch(Exception $e) {
            error_log("Create monthly bills for all members error: " . $e->getMessage());
        }
    }

    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_group') {
            try {
                $monthly_bill_amount = isset($_POST['monthly_bill_amount']) ? (float)$_POST['monthly_bill_amount'] : 0;
                $bill_day = isset($_POST['bill_day']) ? (int)$_POST['bill_day'] : 1;
                
                // Start transaction
                $pdo->beginTransaction();
                
                // Create group
                $stmt = $pdo->prepare("
                    INSERT INTO groups (name, description, target_amount, start_date, end_date, created_by, max_members, is_public, monthly_bill_amount, bill_day) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['target_amount'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $user_id,
                    $_POST['max_members'] ?? 50,
                    isset($_POST['is_public']) ? 1 : 0,
                    $monthly_bill_amount,
                    $bill_day
                ]);
                
                $group_id = $pdo->lastInsertId();
                
                // Add creator as group member with leader role
                $stmt = $pdo->prepare("
                    INSERT INTO group_members (group_id, user_id, role, status, joined_at) 
                    VALUES (?, ?, 'leader', 'active', NOW())
                ");
                $stmt->execute([$group_id, $user_id]);
                
                // Create monthly bill for creator if monthly billing is enabled
                if ($monthly_bill_amount > 0) {
                    createMonthlyBillForNewMember($pdo, $group_id, $user_id, $monthly_bill_amount, $user_id);
                }
                
                // Get the generated invitation code
                $stmt = $pdo->prepare("SELECT invitation_code FROM groups WHERE id = ?");
                $stmt->execute([$group_id]);
                $invitation_code = $stmt->fetchColumn();
                
                // Commit transaction
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Grup berhasil dibuat!',
                    'group_id' => $group_id,
                    'invitation_code' => $invitation_code
                ]);
                exit;
                
            } catch(Exception $e) {
                // Rollback transaction on error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Create group error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
        }
        
        if ($action === 'join_group') {
            try {
                $invitation_code = trim($_POST['invitation_code']);
                
                if (empty($invitation_code)) {
                    echo json_encode(['success' => false, 'message' => 'Kode undangan tidak boleh kosong!']);
                    exit;
                }
                
                // Start transaction
                $pdo->beginTransaction();
                
                // Validate invitation code and check membership
                $stmt = $pdo->prepare("
                    SELECT g.*, 
                        u.full_name as creator_name,
                        COUNT(DISTINCT gm.user_id) as member_count,
                        CASE WHEN gm_check.user_id IS NOT NULL THEN 1 ELSE 0 END as already_member
                    FROM groups g 
                    LEFT JOIN users u ON g.created_by = u.id
                    LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                    LEFT JOIN group_members gm_check ON g.id = gm_check.group_id AND gm_check.user_id = ? AND gm_check.status = 'active'
                    WHERE g.invitation_code = ? AND g.status = 'active'
                    GROUP BY g.id
                ");
                $stmt->execute([$user_id, $invitation_code]);
                $group = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$group) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Kode undangan tidak valid atau grup tidak aktif!']);
                    exit;
                }
                
                if ($group['already_member']) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Anda sudah menjadi anggota grup ini!']);
                    exit;
                }
                
                // Check if group has reached max members
                if ($group['member_count'] >= $group['max_members']) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Grup sudah mencapai batas maksimum anggota!']);
                    exit;
                }
                
                // Add user to group
                $stmt = $pdo->prepare("
                    INSERT INTO group_members (group_id, user_id, role, status, joined_at) 
                    VALUES (?, ?, 'member', 'active', NOW())
                ");
                $stmt->execute([$group['id'], $user_id]);
                
                // Create monthly bill for the new member if monthly billing is enabled
                if ($group['monthly_bill_amount'] > 0) {
                    createMonthlyBillForNewMember($pdo, $group['id'], $user_id, $group['monthly_bill_amount'], $group['created_by']);
                }
                
                // Create notification for group creator
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, group_id, type, title, message, action_url, created_at)
                    VALUES (?, ?, 'member_joined', 'Anggota Baru Bergabung', 
                            CONCAT(?, ' bergabung ke grup ', ?), 
                            CONCAT('group_detail.php?id=', ?), NOW())
                ");
                $stmt->execute([
                    $group['created_by'], 
                    $group['id'], 
                    $_SESSION['user_name'] ?? 'User', 
                    $group['name'],
                    $group['id']
                ]);
                
                // Log activity
                $stmt = $pdo->prepare("
                    INSERT INTO group_activities (group_id, user_id, activity_type, activity_description, created_at)
                    VALUES (?, ?, 'member_joined', CONCAT(?, ' bergabung ke grup'), NOW())
                ");
                $stmt->execute([
                    $group['id'], 
                    $user_id, 
                    $_SESSION['user_name'] ?? 'User'
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Berhasil bergabung dengan grup: ' . $group['name'],
                    'group_name' => $group['name'],
                    'group_id' => $group['id']
                ]);
                exit;
                
            } catch(Exception $e) {
                // Rollback transaction on error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Join group error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat bergabung ke grup!']);
                exit;
            }
        }
        
        if ($action === 'get_groups') {
            try {
                $stmt = $pdo->prepare("
                    SELECT g.*,
                        gm.role,
                        u.full_name as creator_name,
                        g.current_balance as current_amount,
                        g.total_spent as withdrawn_amount,
                        COUNT(DISTINCT gm2.user_id) as member_count,
                        DATEDIFF(g.end_date, CURDATE()) as days_remaining,
                        CASE 
                            WHEN g.target_amount > 0 THEN ROUND((g.current_balance / g.target_amount) * 100, 2)
                            ELSE 0 
                        END as progress_percentage
                    FROM groups g
                    JOIN group_members gm ON g.id = gm.group_id AND gm.user_id = ? AND gm.status = 'active'
                    LEFT JOIN users u ON g.created_by = u.id
                    LEFT JOIN group_members gm2 ON g.id = gm2.group_id AND gm2.status = 'active'
                    WHERE g.status = 'active'
                    GROUP BY g.id, gm.role
                    ORDER BY g.created_at DESC
                ");
                
                $stmt->execute([$user_id]);
                $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'groups' => $groups]);
                exit;
                
            } catch(Exception $e) {
                error_log("Get groups error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
        }
        
        if ($action === 'validate_invitation') {
            try {
                $invitation_code = trim($_POST['invitation_code']);
                
                if (empty($invitation_code)) {
                    echo json_encode(['success' => false, 'message' => 'Kode undangan tidak boleh kosong!']);
                    exit;
                }
                
                // Check if invitation code exists
                $stmt = $pdo->prepare("
                    SELECT g.id, g.name, g.description, g.target_amount, g.current_balance, g.end_date, g.max_members, g.monthly_bill_amount, g.bill_day,
                        u.full_name as creator_name,
                        COUNT(DISTINCT gm.user_id) as member_count,
                        CASE 
                            WHEN g.target_amount > 0 THEN ROUND((g.current_balance / g.target_amount) * 100, 2)
                            ELSE 0 
                        END as progress_percentage
                    FROM groups g 
                    LEFT JOIN users u ON g.created_by = u.id
                    LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                    WHERE g.invitation_code = ? AND g.status = 'active'
                    GROUP BY g.id
                ");
                $stmt->execute([$invitation_code]);
                $group = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$group) {
                    echo json_encode(['success' => false, 'message' => 'Kode undangan tidak valid!']);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'group' => $group
                ]);
                exit;
                
            } catch(Exception $e) {
                error_log("Validate invitation error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                exit;
            }
        }
        
        // Updated action to process bill payment - now updates group balance
        if ($action === 'process_bill_payment') {
            try {
                $bill_id = $_POST['bill_id'];
                $amount = (float)$_POST['amount'];
                $payment_method = $_POST['payment_method'] ?? 'balance';
                
                // Start transaction
                $pdo->beginTransaction();
                
                // Get bill details
                $stmt = $pdo->prepare("
                    SELECT gb.*, g.name as group_name, g.current_balance, g.total_collected
                    FROM group_bill gb 
                    JOIN groups g ON gb.group_id = g.id 
                    WHERE gb.id = ? AND gb.user_id = ?
                ");
                $stmt->execute([$bill_id, $user_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$bill) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Tagihan tidak ditemukan!']);
                    exit;
                }
                
                if ($amount > $bill['remaining_amount']) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Jumlah pembayaran melebihi sisa tagihan!']);
                    exit;
                }
                
                // Update group balance and total collected
                $new_balance = $bill['current_balance'] + $amount;
                $new_total_collected = $bill['total_collected'] + $amount;
                
                $stmt = $pdo->prepare("
                    UPDATE groups 
                    SET current_balance = ?, total_collected = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_balance, $new_total_collected, $bill['group_id']]);
                
                // Update group_bill
                $new_remaining = $bill['remaining_amount'] - $amount;
                $new_status = ($new_remaining <= 0) ? 'paid' : 'partial';
                
                $stmt = $pdo->prepare("
                    UPDATE group_bill 
                    SET remaining_amount = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_remaining, $new_status, $bill_id]);
                
                // Log the payment in group activities
                $stmt = $pdo->prepare("
                    INSERT INTO group_activities (group_id, user_id, activity_type, activity_description, created_at)
                    VALUES (?, ?, 'payment_made', CONCAT(?, ' membayar tagihan sebesar Rp ', FORMAT(?, 0)), NOW())
                ");
                $stmt->execute([
                    $bill['group_id'], 
                    $user_id, 
                    $_SESSION['user_name'] ?? 'User',
                    $amount
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pembayaran berhasil diproses!',
                    'remaining_amount' => $new_remaining,
                    'group_balance' => $new_balance
                ]);
                exit;
                
            } catch(Exception $e) {
                // Rollback transaction on error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Process bill payment error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memproses pembayaran!']);
                exit;
            }
        }
    }

    // Get user statistics - updated to use groups table data
    function getStatistics($pdo, $user_id) {
        try {
            // Total groups
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_groups 
                FROM groups g 
                JOIN group_members gm ON g.id = gm.group_id 
                WHERE gm.user_id = ? AND gm.status = 'active' AND g.status = 'active'
            ");
            $stmt->execute([$user_id]);
            $total_groups = $stmt->fetchColumn();
            
            // Active groups (not ended)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as active_groups 
                FROM groups g 
                JOIN group_members gm ON g.id = gm.group_id 
                WHERE gm.user_id = ? AND gm.status = 'active' AND g.status = 'active' AND g.end_date >= CURDATE()
            ");
            $stmt->execute([$user_id]);
            $active_groups = $stmt->fetchColumn();
            
            // Total contribution amount from group_bill where user has paid
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(bill_amount - remaining_amount), 0) as total_contribution 
                FROM group_bill gb
                JOIN groups g ON gb.group_id = g.id
                WHERE gb.user_id = ? AND gb.status IN ('paid', 'partial')
            ");
            $stmt->execute([$user_id]);
            $total_contribution = $stmt->fetchColumn();
            
            return [
                'total_groups' => $total_groups,
                'active_groups' => $active_groups,
                'total_contribution' => $total_contribution
            ];
        } catch(Exception $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return [
                'total_groups' => 0,
                'active_groups' => 0,
                'total_contribution' => 0
            ];
        }
    }

    $stats = getStatistics($pdo, $user_id);

    // Check for join message from URL join
    $join_message = null;
    if (isset($_SESSION['join_message'])) {
        $join_message = $_SESSION['join_message'];
        unset($_SESSION['join_message']);
    }

    // Get user info
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JalanYuk - Tabungan Bersama untuk Travelling</title>
    <link rel="stylesheet" href="css/family1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>/* Black & White Theme Styling */

/* Join Group Button Styles */
.btn-join {
    background: #000000;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    margin-left: 12px;
}

.btn-join:hover {
    background: #333333;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.btn-join:active {
    transform: translateY(0);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Header Actions Spacing */
.header-right {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

/* Empty State Styling - Black & White */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 60px 40px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    margin: 40px auto;
    min-height: 350px;
    width: 100%;
    max-width: 600px;
    position: relative;
    border: 1px solid #e5e7eb;
}

.empty-state .empty-icon {
    width: 80px;
    height: 80px;
    background: #f8f9fa;
    border: 2px solid #e5e7eb;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px auto;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.empty-state .empty-icon i {
    font-size: 32px;
    color: #6b7280;
}

.empty-state h3 {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 12px 0;
    letter-spacing: -0.5px;
}

.empty-state p {
    font-size: 16px;
    color: #6b7280;
    margin: 0 0 32px 0;
    max-width: 400px;
    line-height: 1.6;
}

/* Empty State Actions */
.empty-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}

.empty-actions .btn-primary {
    background: #000000;
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    min-width: 150px;
    justify-content: center;
}

.empty-actions .btn-primary:hover {
    background: #333333;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.empty-actions .btn-primary:active {
    transform: translateY(0);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.empty-actions .btn-join {
    margin-left: 0;
    min-width: 150px;
    justify-content: center;
}

/* Join Group Modal Icon */
.join-icon {
    background: #000000;
    color: white;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

/* Join Group Info Box */
.join-info {
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
}

.join-info .info-icon {
    background: #000000;
    color: white;
}

/* Form Help Text */
.form-help {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
    font-style: italic;
}

/* Alternative button style - white background with black text */
.btn-secondary {
    background: white;
    color: #000000;
    border: 2px solid #000000;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-secondary:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

.btn-secondary:active {
    transform: translateY(0);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-right {
        flex-direction: column;
        width: 100%;
        gap: 12px;
    }
    
    .btn-join {
        width: 100%;
        justify-content: center;
        margin-left: 0;
    }
    
    .empty-state {
        padding: 40px 20px;
        margin: 20px auto;
        max-width: 90%;
        min-height: 300px;
    }
    
    .empty-state .empty-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px auto;
    }
    
    .empty-state .empty-icon i {
        font-size: 24px;
    }
    
    .empty-state h3 {
        font-size: 20px;
    }
    
    .empty-state p {
        font-size: 14px;
        margin-bottom: 28px;
    }
    
    .empty-actions {
        flex-direction: column;
        gap: 12px;
        width: 100%;
    }
    
    .empty-actions .btn-primary,
    .empty-actions .btn-join {
        width: 100%;
        min-width: auto;
    }
}

.billing-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
}

.billing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.billing-title {
    color: #495057;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.billing-title i {
    color: #28a745;
    margin-right: 8px;
}

.billing-options {
    margin-top: 15px;
}

.form-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
    color: #495057;
}

.form-checkbox input[type="checkbox"] {
    margin-right: 8px;
    width: 16px;
    height: 16px;
}

.form-hint {
    color: #6c757d;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.billing-info {
    background: #e8f4f8;
    border-left: 4px solid #17a2b8;
    padding: 15px;
    margin-top: 15px;
    border-radius: 4px;
}

.info-icon {
    color: #17a2b8;
    margin-bottom: 8px;
}

.info-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}

.info-list {
    margin-left: 20px;
    color: #6c757d;
}

.info-list li {
    margin-bottom: 4px;
    font-size: 13px;
}

.currency-input {
    position: relative;
}

.currency-input::before {
    content: "Rp ";
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-weight: 500;
}

.currency-input input {
    padding-left: 35px;
}
    </style>
    </head>
    <body>
<!-- Fixed Grup Family HTML Structure -->
<div class="container">
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="page-title">Grup Family</h1>
                <p class="page-subtitle">Kelola tabungan bersama untuk perjalanan impian Anda</p>
            </div>
            <div class="header-right">
                <button class="btn-primary" onclick="openCreateGroupModal()">
                    <i class="fas fa-plus"></i> Buat Grup Baru
                </button>
                <button class="btn-join" onclick="openJoinGroupModal()">
                    <i class="fas fa-user-plus"></i> Join Grup
                </button>
            </div>
        </div>
    </header>

    <!-- Search and Filter -->
    <div class="search-filter-section">
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" placeholder="Cari grup..." id="searchInput">
        </div>
        <div class="filter-container">
            <select class="filter-select" id="filterSelect">
                <option value="all">Semua Grup</option>
                <option value="active">Grup Aktif</option>
                <option value="inactive">Grup Tidak Aktif</option>
                <option value="leader">Grup Saya (Leader)</option>
                <option value="member">Grup Member</option>
            </select>
            <i class="fas fa-filter filter-icon"></i>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">1</div>
                <div class="stat-label">Total Grup</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">1</div>
                <div class="stat-label">Grup Aktif</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">3</div>
                <div class="stat-label">Total Anggota</div>
            </div>
        </div>
    </div>

    <!-- Groups List -->
    <div class="groups-container" id="groupsContainer">
        <div class="group-card" data-group-id="1" onclick="openGroupDetail(1)">
            <div class="group-header">
                <div class="group-title-section">
                    <h3 class="group-title">Trip to Bali 2024</h3>
                    <span class="leader-badge">
                        <i class="fas fa-crown"></i> Leader
                    </span>
                </div>
            </div>
            
            <p class="group-description">Liburan keluarga ke Bali untuk merayakan tahun baru</p>
            
            <div class="progress-section">
                <div class="progress-header">
                    <span class="progress-label">Progress Dana</span>
                    <span class="progress-percentage">57%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 57%"></div>
                </div>
                <div class="progress-amounts">
                    <span class="current-amount">Rp 8.500.000</span>
                    <span class="target-amount">Rp 15.000.000</span>
                </div>
            </div>
            
            <div class="group-stats">
                <div class="stat-item">
                    <div class="stat-icon-small blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value">3</div>
                    <div class="stat-label-small">Anggota</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-small green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">2.833.333,33</div>
                    <div class="stat-label-small">Rata-rata</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-small purple">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-value">0</div>
                    <div class="stat-label-small">Hari lagi</div>
                </div>
            </div>
            
            <div class="group-footer">
                <div class="member-avatars">
                    <img src="https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2" alt="Yogas Wilbowo" class="member-avatar">
                    <img src="https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2" alt="Sarah Johnson" class="member-avatar">
                    <img src="https://images.pexels.com/photos/1516680/pexels-photo-1516680.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2" alt="Michael Chen" class="member-avatar">
                </div>
                <div class="group-end-date">
                    <span class="end-date-label">Berakhir</span>
                    <span class="end-date-value">31 Des 2024</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="loading" id="loadingIndicator" style="display: none;">
        <i class="fas fa-spinner fa-spin"></i> Memuat grup...
    </div>

    <!-- Empty state -->
    <div class="empty-state" id="emptyState" style="display: none;">
        <div class="empty-icon">
            <i class="fas fa-users"></i>
        </div>
        <h3>Belum ada grup</h3>
        <p>Buat grup pertama Anda untuk mulai menabung bersama</p>
        <div class="empty-actions">
            <button class="btn-primary" onclick="openCreateGroupModal()">
                <i class="fas fa-plus"></i> Buat Grup Baru
            </button>
            <button class="btn-join" onclick="openJoinGroupModal()">
                <i class="fas fa-user-plus"></i> Join Grup
            </button>
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal" id="createGroupModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title-section">
                <div class="modal-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h2 class="modal-title">Buat Grup Family</h2>
            </div>
            <button class="modal-close" onclick="closeCreateGroupModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form class="modal-form" id="createGroupForm" onsubmit="createGroup(event)">
            <div class="form-group">
                <label class="form-label">Nama Grup</label>
                <input type="text" class="form-input" name="name" placeholder="Contoh: Trip to Bali 2024" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea class="form-textarea" name="description" placeholder="Jelaskan tujuan dan detail perjalanan..." required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-bullseye"></i> Target Dana (Rupiah)
                </label>
                <input type="number" class="form-input" name="target_amount" placeholder="15000000" required>
            </div>
            
            <div class="form-group-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-calendar"></i> Tanggal Mulai
                    </label>
                    <input type="date" class="form-input" name="start_date" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="date" class="form-input" name="end_date" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-users"></i> Maksimum Anggota
                </label>
                <input type="number" class="form-input" name="max_members" value="50" min="2" max="100" required>
            </div>
            
            <!-- Monthly Billing Section -->
            <div class="billing-section">
                <div class="billing-header">
                    <h3 class="billing-title">
                        <i class="fas fa-receipt"></i> Pengaturan Tagihan Bulanan
                    </h3>
                    <label class="form-checkbox">
                        <input type="checkbox" id="enableMonthlyBilling" onchange="toggleMonthlyBilling()">
                        <span class="checkmark"></span>
                        Aktifkan tagihan bulanan
                    </label>
                </div>
                
                <div class="billing-options" id="billingOptions" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-money-bill-wave"></i> Jumlah Tagihan per Bulan (Rupiah)
                        </label>
                        <input type="number" class="form-input" name="monthly_bill_amount" placeholder="500000" min="0" step="1000">
                        <small class="form-hint">Jumlah yang harus dibayar setiap anggota per bulan</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar-day"></i> Tanggal Jatuh Tempo
                        </label>
                        <select class="form-input" name="bill_day">
                            <option value="1">Tanggal 1 setiap bulan</option>
                            <option value="5">Tanggal 5 setiap bulan</option>
                            <option value="10">Tanggal 10 setiap bulan</option>
                            <option value="15">Tanggal 15 setiap bulan</option>
                            <option value="20">Tanggal 20 setiap bulan</option>
                            <option value="25">Tanggal 25 setiap bulan</option>
                            <option value="28">Tanggal 28 setiap bulan</option>
                        </select>
                        <small class="form-hint">Tanggal jatuh tempo pembayaran setiap bulan</small>
                    </div>
                    
                    <div class="billing-info">
                        <div class="info-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-title">Informasi Tagihan Bulanan:</div>
                            <ul class="info-list">
                                <li>Tagihan akan dibuat otomatis setiap bulan</li>
                                <li>Anggota baru akan mendapat tagihan untuk bulan berjalan</li>
                                <li>Status pembayaran dapat dipantau di dashboard grup</li>
                                <li>Notifikasi otomatis untuk tagihan yang belum dibayar</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="is_public">
                    <span class="checkmark"></span>
                    Grup Publik (dapat ditemukan di pencarian)
                </label>
            </div>
            
            <div class="info-box">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <div class="info-title">Informasi Penting:</div>
                    <ul class="info-list">
                        <li>Anda akan menjadi ketua grup secara otomatis</li>
                        <li>Link undangan akan dibuat otomatis</li>
                        <li>Anggota dapat bergabung dengan kode undangan</li>
                        <li>Tagihan bulanan berlaku untuk semua anggota termasuk ketua</li>
                    </ul>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeCreateGroupModal()">Batal</button>
                <button type="submit" class="btn-primary">
                    <span class="btn-text">Buat Grup</span>
                    <span class="btn-loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Membuat...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Join Group Modal -->
<div class="modal" id="joinGroupModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title-section">
                <div class="modal-icon join-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2 class="modal-title">Join Grup Family</h2>
            </div>
            <button class="modal-close" onclick="closeJoinGroupModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form class="modal-form" id="joinGroupForm" onsubmit="joinGroup(event)">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-link"></i> Kode Undangan atau Link Grup
                </label>
                <input type="text" class="form-input" name="invitation_code" id="invitationCodeInput" placeholder="Masukkan kode undangan atau link grup..." required>
                <div class="form-help">
                    Kode undangan biasanya berupa 6-8 karakter atau link lengkap grup
                </div>
                <div id="validationMessage" class="validation-message"></div>
            </div>
            
            <!-- Group Preview -->
            <div id="groupPreview" class="group-preview" style="display: none;">
                <!-- Preview content will be populated by JavaScript -->
            </div>
            
            <div class="info-box join-info">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <div class="info-title">Cara Join Grup:</div>
                    <ul class="info-list">
                        <li>Minta kode undangan dari ketua grup</li>
                        <li>Masukkan kode atau paste link undangan</li>
                        <li>Anda akan otomatis bergabung jika kode valid</li>
                    </ul>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeJoinGroupModal()">Batal</button>
                <button type="submit" class="btn-join" id="joinButton" style="display: none;">
                    <span class="btn-text">
                        <i class="fas fa-user-plus"></i> Join Grup
                    </span>
                    <span class="btn-loading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Bergabung...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Invite Members Modal -->
<div class="modal" id="inviteMembersModal">
    <div class="modal-content">
        <div class="modal-header">
            <button class="modal-back" onclick="closeInviteMembersModal()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h2 class="modal-title">Undang Anggota</h2>
        </div>
        
        <div class="invite-content">
            <div class="form-group">
                <label class="form-label">Link Undangan</label>
                <div class="invite-link-container">
                    <input type="text" class="form-input invite-link" id="inviteLink" value="https://zp1v56uxy8rdx5ypatb0ockcb9tr6a-oci3--5173" readonly>
                    <button class="btn-copy" onclick="copyInviteLink()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            
            <div class="info-box">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="info-content">
                    <div class="info-title">Cara mengundang:</div>
                    <ul class="info-list">
                        <li>Salin link undangan di atas</li>
                        <li>Kirim ke calon anggota via WhatsApp, email, dll</li>
                        <li>Anggota yang bergabung perlu persetujuan Anda</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Group Modal -->
<div class="modal" id="leaveGroupModal">
    <div class="modal-content">
        <div class="warning-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h2 class="modal-title">Keluar dari Grup?</h2>
        <p class="modal-text">Anda yakin ingin keluar dari grup "Trip to Bali 2024"?</p>
        <p class="warning-text">Dana yang sudah Anda setor (Rp 3.000.000) akan tetap berada di grup dan tidak dapat dikembalikan secara otomatis.</p>
        
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeLeaveGroupModal()">Batal</button>
            <button type="button" class="btn-danger" onclick="confirmLeaveGroup()">Ya, Keluar</button>
        </div>
    </div>
</div>
    <script>
// Global variables
let allGroups = [];
let filteredGroups = [];
let groupPreview = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadGroups();
    setupEventListeners();
    checkJoinMessage();
});

// Check for join message from PHP session
function checkJoinMessage() {
    <?php if ($join_message): ?>
    showNotification('<?php echo addslashes($join_message['text']); ?>', '<?php echo $join_message['type']; ?>');
    <?php endif; ?>
}

// Setup event listeners
function setupEventListeners() {
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterGroups, 300));
    }
    if (filterSelect) {
        filterSelect.addEventListener('change', filterGroups);
    }
    
    // Setup join group form validation
    const joinForm = document.getElementById('joinGroupForm');
    const invitationInput = document.getElementById('invitationCodeInput');
    
    if (invitationInput) {
        invitationInput.addEventListener('input', debounce(validateInvitationCode, 500));
        invitationInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                validateInvitationCode();
            }, 100);
        });
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const createModal = document.getElementById('createGroupModal');
        const joinModal = document.getElementById('joinGroupModal');
        
        if (event.target === createModal) {
            closeCreateGroupModal();
        }
        if (event.target === joinModal) {
            closeJoinGroupModal();
        }
    });

    // Handle ESC key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeCreateGroupModal();
            closeJoinGroupModal();
        }
    });
}

// Load groups from database
function loadGroups() {
    const loadingIndicator = document.getElementById('loadingIndicator');
    const groupsContainer = document.getElementById('groupsContainer');
    const emptyState = document.getElementById('emptyState');
    
    if (loadingIndicator) loadingIndicator.style.display = 'block';
    if (groupsContainer) groupsContainer.innerHTML = '';
    if (emptyState) emptyState.style.display = 'none';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_groups'
    })
    .then(response => response.json())
    .then(data => {
        if (loadingIndicator) loadingIndicator.style.display = 'none';
        
        if (data.success) {
            allGroups = data.groups;
            filteredGroups = [...allGroups];
            
            if (allGroups.length === 0) {
                if (emptyState) emptyState.style.display = 'block';
            } else {
                renderGroups(filteredGroups);
            }
            
            // Render stats if available
            if (data.stats) {
                renderStats(data.stats);
            }
        } else {
            showNotification('Error memuat grup: ' + data.message, 'error');
            if (emptyState) emptyState.style.display = 'block';
        }
    })
    .catch(error => {
        if (loadingIndicator) loadingIndicator.style.display = 'none';
        showNotification('Error memuat grup: ' + error.message, 'error');
        if (emptyState) emptyState.style.display = 'block';
    });
}

// Render statistics
function renderStats(stats) {
    const totalGroups = stats.total_groups || allGroups.length;
    const activeGroups = stats.active_groups || allGroups.filter(g => g.days_remaining >= 0).length;
    const totalMembers = stats.total_members || allGroups.reduce((sum, g) => sum + g.member_count, 0);
    
    // Update stats display if elements exist
    const totalGroupsEl = document.getElementById('totalGroups');
    const activeGroupsEl = document.getElementById('activeGroups');
    const totalMembersEl = document.getElementById('totalMembers');
    
    if (totalGroupsEl) totalGroupsEl.textContent = totalGroups;
    if (activeGroupsEl) activeGroupsEl.textContent = activeGroups;
    if (totalMembersEl) totalMembersEl.textContent = totalMembers;
}

// Render groups
function renderGroups(groups) {
    const groupsContainer = document.getElementById('groupsContainer');
    const emptyState = document.getElementById('emptyState');
    
    if (!groupsContainer) return;
    
    if (groups.length === 0) {
        groupsContainer.innerHTML = '<div class="no-results">Tidak ada grup yang ditemukan</div>';
        return;
    }
    
    if (emptyState) emptyState.style.display = 'none';
    
    const groupsHTML = groups.map(group => createGroupCard(group)).join('');
    groupsContainer.innerHTML = groupsHTML;
}

// Create group card HTML (menggunakan design dari kode pertama)
function createGroupCard(group) {
    const progress = group.target_amount > 0 ? Math.round((group.current_amount / group.target_amount) * 100) : 0;
    const averageContribution = group.member_count > 0 ? group.current_amount / group.member_count : 0;
    const daysRemaining = Math.max(0, group.days_remaining);
    
    // Format currency
    const formatCurrency = (amount) => {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
    };
    
    // Format date
    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', { 
            day: 'numeric', 
            month: 'short', 
            year: 'numeric' 
        });
    };
    
    // Default member avatars
    const defaultAvatars = [
        'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2',
        'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2',
        'https://images.pexels.com/photos/1516680/pexels-photo-1516680.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2'
    ];
    
    const memberAvatarsHTML = defaultAvatars.slice(0, Math.min(group.member_count, 3))
        .map((avatar, index) => `<img src="${avatar}" alt="Member ${index + 1}" class="member-avatar">`)
        .join('');
    
    return `
        <div class="group-card" data-group-id="${group.id}" onclick="openGroupDetail(${group.id})">
            <div class="group-header">
                <div class="group-title-section">
                    <h3 class="group-title">${escapeHtml(group.name)}</h3>
                    ${group.role === 'leader' ? '<span class="leader-badge"><i class="fas fa-crown"></i> Leader</span>' : ''}
                </div>
                <div class="group-actions">
                    <button class="btn-icon" onclick="event.stopPropagation(); shareGroup('${group.invitation_code || ''}')" title="Bagikan">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    <button class="btn-icon" onclick="event.stopPropagation(); openGroupDetail(${group.id})" title="Lihat Detail">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <p class="group-description">${escapeHtml(group.description)}</p>
            
            <div class="progress-section">
                <div class="progress-header">
                    <span class="progress-label">Progress Dana</span>
                    <span class="progress-percentage">${progress}%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${progress}%"></div>
                </div>
                <div class="progress-amounts">
                    <span class="current-amount">${formatCurrency(group.current_amount)}</span>
                    <span class="target-amount">${formatCurrency(group.target_amount)}</span>
                </div>
            </div>
            
            <div class="group-stats">
                <div class="stat-item">
                    <div class="stat-icon-small blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value">${group.member_count}</div>
                    <div class="stat-label-small">Anggota</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-small green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">${new Intl.NumberFormat('id-ID').format(averageContribution)}</div>
                    <div class="stat-label-small">Rata-rata</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon-small purple">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-value">${daysRemaining}</div>
                    <div class="stat-label-small">Hari lagi</div>
                </div>
            </div>
            
            <div class="group-footer">
                <div class="member-avatars">
                    ${memberAvatarsHTML}
                </div>
                <div class="group-end-date">
                    <span class="end-date-label">Berakhir</span>
                    <span class="end-date-value">${formatDate(group.end_date)}</span>
                </div>
            </div>
        </div>
    `;
}

// Filter groups
function filterGroups() {
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const filterValue = filterSelect ? filterSelect.value : 'all';
    
    filteredGroups = allGroups.filter(group => {
        const matchesSearch = group.name.toLowerCase().includes(searchTerm) || 
                            group.description.toLowerCase().includes(searchTerm);
        
        let matchesFilter = true;
        if (filterValue === 'active') {
            matchesFilter = group.days_remaining > 0;
        } else if (filterValue === 'inactive') {
            matchesFilter = group.days_remaining <= 0;
        } else if (filterValue === 'leader') {
            matchesFilter = group.role === 'leader';
        } else if (filterValue === 'member') {
            matchesFilter = group.role === 'member';
        }
        
        return matchesSearch && matchesFilter;
    });
    
    renderGroups(filteredGroups);
}

// Modal functions for create group
function openCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Set default dates
        const today = new Date().toISOString().split('T')[0];
        const nextMonth = new Date();
        nextMonth.setMonth(nextMonth.getMonth() + 1);
        const endDate = nextMonth.toISOString().split('T')[0];
        
        const startDateInput = modal.querySelector('input[name="start_date"]');
        const endDateInput = modal.querySelector('input[name="end_date"]');
        
        if (startDateInput) startDateInput.value = today;
        if (endDateInput) endDateInput.value = endDate;
        
        // Focus on first input
        const firstInput = modal.querySelector('input[type="text"]');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        
        const form = document.getElementById('createGroupForm');
        if (form) {
            form.reset();
        }
    }
}

// Modal functions for join group
function openJoinGroupModal() {
    const modal = document.getElementById('joinGroupModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Clear previous data
        clearJoinGroupForm();
        
        // Focus on invitation input
        const invitationInput = document.getElementById('invitationCodeInput');
        if (invitationInput) {
            setTimeout(() => invitationInput.focus(), 100);
        }
    }
}

function closeJoinGroupModal() {
    const modal = document.getElementById('joinGroupModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        clearJoinGroupForm();
    }
}

function clearJoinGroupForm() {
    const form = document.getElementById('joinGroupForm');
    const groupPreviewDiv = document.getElementById('groupPreview');
    const joinButton = document.getElementById('joinButton');
    const validationMessage = document.getElementById('validationMessage');
    
    if (form) form.reset();
    if (groupPreviewDiv) groupPreviewDiv.style.display = 'none';
    if (joinButton) joinButton.style.display = 'none';
    if (validationMessage) validationMessage.textContent = '';
    
    groupPreview = null;
}

// Create group function
function createGroup(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create_group');
    
    const submitButton = form.querySelector('button[type="submit"]');
    const btnText = submitButton.querySelector('.btn-text');
    const btnLoading = submitButton.querySelector('.btn-loading');
    
    // Show loading state
    submitButton.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnLoading) btnLoading.style.display = 'inline-flex';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeCreateGroupModal();
            loadGroups(); // Reload groups
            
            // Show invitation code
            if (data.invitation_code && data.group_id) {
                setTimeout(() => {
                    showInvitationCode(data.invitation_code, data.group_id);
                }, 1000);
            }
        } else {
            showNotification(data.message || 'Gagal membuat grup', 'error');
        }
    })
    .catch(error => {
        console.error('Error creating group:', error);
        showNotification('Terjadi kesalahan saat membuat grup', 'error');
    })
    .finally(() => {
        // Reset loading state
        submitButton.disabled = false;
        if (btnText) btnText.style.display = 'inline';
        if (btnLoading) btnLoading.style.display = 'none';
    });
}

// Join group function
function joinGroup(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'join_group');
    
    const submitButton = form.querySelector('button[type="submit"]');
    const btnText = submitButton.querySelector('.btn-text');
    const btnLoading = submitButton.querySelector('.btn-loading');
    
    // Show loading state
    submitButton.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnLoading) btnLoading.style.display = 'inline-flex';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            closeJoinGroupModal();
            loadGroups(); // Reload groups
        } else {
            showNotification(data.message || 'Gagal bergabung ke grup', 'error');
        }
    })
    .catch(error => {
        console.error('Error joining group:', error);
        showNotification('Terjadi kesalahan saat bergabung ke grup', 'error');
    })
    .finally(() => {
        // Reset loading state
        submitButton.disabled = false;
        if (btnText) btnText.style.display = 'inline-flex';
        if (btnLoading) btnLoading.style.display = 'none';
    });
}

// Validate invitation code
function validateInvitationCode() {
    const input = document.getElementById('invitationCodeInput');
    const message = document.getElementById('validationMessage');
    const preview = document.getElementById('groupPreview');
    const joinButton = document.getElementById('joinButton');
    
    if (!input || !message) return;
    
    let invitationCode = input.value.trim();
    
    if (invitationCode.length < 3) {
        message.textContent = '';
        if (preview) preview.style.display = 'none';
        if (joinButton) joinButton.style.display = 'none';
        return;
    }
    
    // Extract code from URL if it's a full URL
    if (invitationCode.includes('join=')) {
        const match = invitationCode.match(/join=([^&]+)/);
        if (match) {
            invitationCode = match[1];
            input.value = invitationCode;
        }
    }
    
    message.textContent = 'Memvalidasi kode...';
    message.className = 'validation-message info';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=validate_invitation&invitation_code=${encodeURIComponent(invitationCode)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            message.textContent = 'Kode valid! Klik tombol join untuk bergabung.';
            message.className = 'validation-message success';
            showGroupPreview(data.group);
            if (joinButton) joinButton.style.display = 'inline-flex';
        } else {
            message.textContent = data.message || 'Kode undangan tidak valid';
            message.className = 'validation-message error';
            if (preview) preview.style.display = 'none';
            if (joinButton) joinButton.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error validating invitation:', error);
        message.textContent = 'Terjadi kesalahan saat memvalidasi kode';
        message.className = 'validation-message error';
        if (preview) preview.style.display = 'none';
        if (joinButton) joinButton.style.display = 'none';
    });
}

// Show group preview
function showGroupPreview(group) {
    const preview = document.getElementById('groupPreview');
    if (!preview) return;
    
    const availableSlots = group.max_members - group.member_count;
    
    preview.innerHTML = `
        <div class="preview-header">
            <div class="preview-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="preview-info">
                <h4>${escapeHtml(group.name)}</h4>
                <p>Dibuat oleh: ${escapeHtml(group.creator_name || 'Unknown')}</p>
            </div>
        </div>
        <div class="preview-details">
            <div class="preview-item">
                <i class="fas fa-bullseye"></i>
                <span>Target: Rp ${formatNumber(group.target_amount)}</span>
            </div>
            <div class="preview-item">
                <i class="fas fa-calendar"></i>
                <span>Berakhir: ${formatDate(group.end_date)}</span>
            </div>
            <div class="preview-item">
                <i class="fas fa-users"></i>
                <span>${group.member_count}/${group.max_members} anggota</span>
            </div>
            ${availableSlots > 0 ? 
                `<div class="preview-item success">
                    <i class="fas fa-check-circle"></i>
                    <span>${availableSlots} slot tersedia</span>
                </div>` :
                `<div class="preview-item error">
                    <i class="fas fa-times-circle"></i>
                    <span>Grup penuh</span>
                </div>`
            }
        </div>
        <div class="preview-description">
            <p>${escapeHtml(group.description)}</p>
        </div>
    `;
    
    preview.style.display = 'block';
}

// Share group function
function shareGroup(invitationCode) {
    if (!invitationCode) return;
    
    const baseUrl = window.location.origin + window.location.pathname;
    const shareUrl = `${baseUrl}?join=${invitationCode}`;
    
    if (navigator.share) {
        navigator.share({
            title: 'Join Grup JalanYuk',
            text: 'Bergabunglah dengan grup tabungan bersama kami!',
            url: shareUrl
        });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(shareUrl).then(() => {
            showNotification('Link undangan berhasil disalin ke clipboard!', 'success');
        });
    } else {
        // Fallback
        const textArea = document.createElement('textarea');
        textArea.value = shareUrl;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Link undangan berhasil disalin!', 'success');
    }
}

// Show invitation code modal
function showInvitationCode(code, groupId) {
    const baseUrl = window.location.origin + window.location.pathname;
    const shareUrl = `${baseUrl}?join=${code}`;
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title-section">
                    <div class="modal-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="modal-title">Grup Berhasil Dibuat!</h2>
                </div>
            </div>
            
            <div class="invitation-info">
                <p>Grup Anda telah berhasil dibuat. Bagikan kode atau link berikut untuk mengundang anggota:</p>
                
                <div class="code-section">
                    <label>Kode Undangan:</label>
                    <div class="code-display">
                        <span class="code">${code}</span>
                        <button class="btn-copy" onclick="copyToClipboard('${code}')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div class="code-section">
                    <label>Link Undangan:</label>
                    <div class="code-display">
                        <span class="code">${shareUrl}</span>
                        <button class="btn-copy" onclick="copyToClipboard('${shareUrl}')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-secondary" onclick="this.closest('.modal').remove()">Tutup</button>
                <button class="btn-primary" onclick="openGroupDetail(${groupId})">Lihat Grup</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
}

// View/Open group detail
function openGroupDetail(groupId) {
    window.location.href = `group_detail.php?id=${groupId}`;
}

// Notification system (mengganti showAlert dengan showNotification seperti kode pertama)
function showNotification(message, type) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Berhasil disalin ke clipboard!', 'success');
        });
    } else {
        // Fallback
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Berhasil disalin!', 'success');
    }
}   
</script>
<!-- JavaScript untuk toggle billing options -->
<script>
function toggleMonthlyBilling() {
    const checkbox = document.getElementById('enableMonthlyBilling');
    const billingOptions = document.getElementById('billingOptions');
    const monthlyAmountInput = document.querySelector('input[name="monthly_bill_amount"]');
    
    if (checkbox.checked) {
        billingOptions.style.display = 'block';
        monthlyAmountInput.required = true;
    } else {
        billingOptions.style.display = 'none';
        monthlyAmountInput.required = false;
        monthlyAmountInput.value = '';
    }
}

// Format number input dengan rupiah
function formatRupiah(input) {
    let value = input.value.replace(/[^\d]/g, '');
    if (value) {
        value = parseInt(value).toLocaleString('id-ID');
        input.value = value;
    }
}

// Remove format sebelum submit
function removeRupiahFormat(input) {
    input.value = input.value.replace(/[^\d]/g, '');
}

// Handle form submission
function createGroup(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline-block';
    submitBtn.disabled = true;
    
    // Prepare data
    const data = {
        action: 'create_group',
        name: formData.get('name'),
        description: formData.get('description'),
        target_amount: formData.get('target_amount'),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date'),
        max_members: formData.get('max_members'),
        is_public: formData.get('is_public') ? 1 : 0,
        monthly_bill_amount: formData.get('monthly_bill_amount') || 0,
        bill_day: formData.get('bill_day') || 1
    };
    
    // Validate dates
    const startDate = new Date(data.start_date);
    const endDate = new Date(data.end_date);
    const today = new Date();
    
    if (startDate < today) {
        alert('Tanggal mulai tidak boleh kurang dari hari ini');
        resetSubmitButton();
        return;
    }
    
    if (endDate <= startDate) {
        alert('Tanggal selesai harus setelah tanggal mulai');
        resetSubmitButton();
        return;
    }
    
    // Validate monthly billing
    if (document.getElementById('enableMonthlyBilling').checked) {
        if (!data.monthly_bill_amount || data.monthly_bill_amount <= 0) {
            alert('Jumlah tagihan bulanan harus diisi dan lebih dari 0');
            resetSubmitButton();
            return;
        }
    }
    
    // Send request
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Grup berhasil dibuat!');
            closeCreateGroupModal();
            // Refresh page or update UI
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat membuat grup');
    })
    .finally(() => {
        resetSubmitButton();
    });
    
    function resetSubmitButton() {
        btnText.style.display = 'inline-block';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
    }
}

// Modal functions - Updated untuk center modal
function closeCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        modal.style.display = 'none';
        // Remove event listener for Escape key
        document.removeEventListener('keydown', handleEscapeKey);
    }
}

function openCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        // Set modal display dengan flexbox untuk center
        modal.style.display = 'flex';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        modal.style.zIndex = '1000';
        
        // Add event listener for Escape key
        document.addEventListener('keydown', handleEscapeKey);
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeCreateGroupModal();
            }
        });
    }
}

// Handle Escape key untuk close modal
function handleEscapeKey(event) {
    if (event.key === 'Escape') {
        closeCreateGroupModal();
    }
}

// Set minimum dates
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    if (startDateInput) {
        const today = new Date().toISOString().split('T')[0];
        startDateInput.min = today;
        startDateInput.value = today;
    }
    
    if (endDateInput) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        endDateInput.min = tomorrow.toISOString().split('T')[0];
    }
    
    // Update end date minimum when start date changes
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            selectedDate.setDate(selectedDate.getDate() + 1);
            endDateInput.min = selectedDate.toISOString().split('T')[0];
        });
    }
    
    // Initialize modal styling untuk center positioning
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        // Set initial modal styles
        modal.style.display = 'none';
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        modal.style.zIndex = '1000';
        
        // Style modal content for center positioning
        const modalContent = modal.querySelector('.modal-content') || modal.firstElementChild;
        if (modalContent) {
            modalContent.style.position = 'relative';
            modalContent.style.maxWidth = '500px';
            modalContent.style.width = '90%';
            modalContent.style.maxHeight = '90vh';
            modalContent.style.overflowY = 'auto';
            modalContent.style.backgroundColor = 'white';
            modalContent.style.borderRadius = '8px';
            modalContent.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        }
    }
});
</script>

</body>
</html>