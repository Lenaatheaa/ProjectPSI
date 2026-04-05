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

// Get group ID from URL parameter
$group_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'] ?? 1;

if (!$group_id) {
    header('Location: family.php');
    exit;
}

// Function to generate unique invitation code
function generateInvitationCode($pdo) {
    do {
        $code = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM groups WHERE invitation_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    
    return $code;
}

// Function to update group balance after payment
function updateGroupBalance($pdo, $group_id, $amount, $type = 'contribution') {
    try {
        if ($type === 'contribution') {
            $stmt = $pdo->prepare("
                UPDATE groups 
                SET 
                    total_collected = total_collected + ?,
                    current_balance = current_balance + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
        } else if ($type === 'expense') {
            $stmt = $pdo->prepare("
                UPDATE groups 
                SET 
                    total_spent = total_spent + ?,
                    current_balance = current_balance - ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
        }
        
        $stmt->execute([$amount, $amount, $group_id]);
        return true;
    } catch(Exception $e) {
        error_log("Error updating group balance: " . $e->getMessage());
        return false;
    }
}

// Function to check if users table exists and get its structure
function checkUsersTableStructure($pdo) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'users'");
        $stmt->execute();
        $table_exists = $stmt->fetchColumn();
        
        if (!$table_exists) {
            return false;
        }
        
        // Get column information
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $column_names = array_column($columns, 'Field');
        return $column_names;
    } catch(Exception $e) {
        return false;
    }
}

// Function to create or update users table
function ensureUsersTable($pdo) {
    try {
        $columns = checkUsersTableStructure($pdo);
        
        if ($columns === false) {
            // Table doesn't exist, create it
            $stmt = $pdo->prepare("
                CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    full_name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute();
        } else {
            // Table exists, check if required columns exist
            $required_columns = ['id', 'full_name', 'email'];
            foreach ($required_columns as $col) {
                if (!in_array($col, $columns)) {
                    // Add missing column
                    if ($col === 'full_name') {
                        $stmt = $pdo->prepare("ALTER TABLE users ADD COLUMN full_name VARCHAR(255) NOT NULL DEFAULT 'Unknown User'");
                        $stmt->execute();
                    } elseif ($col === 'email') {
                        $stmt = $pdo->prepare("ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE");
                        $stmt->execute();
                    }
                }
            }
        }
        
        // Insert default user if not exists
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO users (id, full_name, email) 
            VALUES (1, 'Default User', 'default@example.com')
        ");
        $stmt->execute();
        
        return true;
    } catch(Exception $e) {
        error_log("Error ensuring users table: " . $e->getMessage());
        return false;
    }
}

// Function to save invitation link to database
function saveInvitationLink($pdo, $group_id, $invitation_code, $invite_link, $created_by) {
    try {
        $token = bin2hex(random_bytes(32));
        
        // Check if group_invitations table exists, if not create it
        $stmt = $pdo->prepare("
            CREATE TABLE IF NOT EXISTS group_invitations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                invited_by INT NOT NULL,
                invitation_token VARCHAR(64) NOT NULL,
                invitation_code VARCHAR(50) NOT NULL,
                invitation_link TEXT NOT NULL,
                status ENUM('active', 'expired', 'used') DEFAULT 'active',
                expires_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        $stmt->execute();
        
        $stmt = $pdo->prepare("
            INSERT INTO group_invitations 
            (group_id, invited_by, invitation_token, invitation_code, invitation_link, status, expires_at, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
            ON DUPLICATE KEY UPDATE 
            invitation_token = VALUES(invitation_token),
            invitation_code = VALUES(invitation_code),
            invitation_link = VALUES(invitation_link),
            expires_at = VALUES(expires_at),
            updated_at = NOW()
        ");
        
        $stmt->execute([$group_id, $created_by, $token, $invitation_code, $invite_link]);
        
        return $token;
    } catch(Exception $e) {
        error_log("Error saving invitation link: " . $e->getMessage());
        return false;
    }
}

// Function to ensure group has invitation code
function ensureInvitationCode($pdo, $group_id) {
    $stmt = $pdo->prepare("SELECT invitation_code FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $current_code = $stmt->fetchColumn();
    
    if (empty($current_code)) {
        $new_code = generateInvitationCode($pdo);
        
        $stmt = $pdo->prepare("UPDATE groups SET invitation_code = ? WHERE id = ?");
        $stmt->execute([$new_code, $group_id]);
        
        return $new_code;
    }
    
    return $current_code;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_contribution') {
        try {
            $amount = floatval($_POST['amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Jumlah kontribusi tidak valid']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Create group_payment_history table if not exists
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS group_payment_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    payment_method VARCHAR(50) DEFAULT 'manual',
                    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
                    notes TEXT,
                    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            $stmt->execute();
            
            // Record payment in payment history
            $stmt = $pdo->prepare("
                INSERT INTO group_payment_history 
                (group_id, user_id, amount, payment_method, status, notes, payment_date) 
                VALUES (?, ?, ?, 'manual', 'completed', ?, NOW())
            ");
            $stmt->execute([$group_id, $user_id, $amount, $notes]);
            
            // Update group balance
            updateGroupBalance($pdo, $group_id, $amount, 'contribution');
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Kontribusi berhasil ditambahkan']);
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'add_expense') {
        try {
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Jumlah pengeluaran tidak valid']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            // Create group_expenses table if not exists
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS group_expenses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    description TEXT,
                    expense_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            $stmt->execute();
            
            // Record expense
            $stmt = $pdo->prepare("
                INSERT INTO group_expenses 
                (group_id, user_id, amount, description, expense_date) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$group_id, $user_id, $amount, $description]);
            
            // Update group balance
            updateGroupBalance($pdo, $group_id, $amount, 'expense');
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Pengeluaran berhasil ditambahkan']);
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'regenerate_invite') {
        try {
            $stmt = $pdo->prepare("
                SELECT g.created_by, gm.role 
                FROM groups g
                LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.user_id = ?
                WHERE g.id = ?
            ");
            $stmt->execute([$user_id, $group_id]);
            $group_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $is_leader = ($group_info['created_by'] == $user_id) || ($group_info['role'] === 'leader');
            
            if ($is_leader) {
                $new_code = generateInvitationCode($pdo);
                
                $stmt = $pdo->prepare("UPDATE groups SET invitation_code = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_code, $group_id]);
                
                $invite_link = generateInviteLink($new_code);
                
                $token = saveInvitationLink($pdo, $group_id, $new_code, $invite_link, $user_id);
                
                if ($token) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Kode undangan berhasil dibuat ulang',
                        'invitation_code' => $new_code,
                        'invite_link' => $invite_link,
                        'token' => $token
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan link undangan']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses']);
            }
            exit;
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'remove_member') {
        try {
            $member_id = $_POST['member_id'] ?? '';
            
            // Create group_members table if not exists
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS group_members (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    role ENUM('member', 'leader') DEFAULT 'member',
                    status ENUM('active', 'inactive', 'removed') DEFAULT 'active',
                    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_group_member (group_id, user_id)
                )
            ");
            $stmt->execute();
            
            $stmt = $pdo->prepare("
                SELECT g.created_by, gm.role 
                FROM groups g
                LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.user_id = ?
                WHERE g.id = ?
            ");
            $stmt->execute([$user_id, $group_id]);
            $group_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $is_leader = ($group_info['created_by'] == $user_id) || ($group_info['role'] === 'leader');
            
            if ($is_leader) {
                $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
                $stmt->execute([$group_id, $member_id]);
                
                echo json_encode(['success' => true, 'message' => 'Anggota berhasil dikeluarkan']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses']);
            }
            exit;
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'leave_group') {
        try {
            $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Berhasil keluar dari grup']);
            exit;
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Get group details
try {
    // Ensure users table exists with proper structure
    ensureUsersTable($pdo);
    
    // First, create group_members table if it doesn't exist
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS group_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            user_id INT NOT NULL,
            role ENUM('member', 'leader') DEFAULT 'member',
            status ENUM('active', 'inactive', 'removed') DEFAULT 'active',
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_group_member (group_id, user_id)
        )
    ");
    $stmt->execute();
    
    $stmt = $pdo->prepare("
        SELECT 
            g.*,
            gm.role as user_role,
            DATEDIFF(g.end_date, CURDATE()) as days_remaining
        FROM groups g
        LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.user_id = ? AND gm.status = 'active'
        WHERE g.id = ? AND (gm.user_id = ? OR g.created_by = ?)
    ");
    
    $stmt->execute([$user_id, $group_id, $user_id, $user_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        header('Location: family.php');
        exit;
    }
    
    // Use current_balance from groups table
    $group['current_amount'] = $group['current_balance'];
    
    // Ensure group has invitation code
    $group['invitation_code'] = ensureInvitationCode($pdo, $group_id);
    
    // Generate invite link
    $invite_link = generateInviteLink($group['invitation_code']);
    
    // Save invitation link if doesn't exist
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM group_invitations 
        WHERE group_id = ? AND invitation_code = ? AND status = 'active'
    ");
    $stmt->execute([$group_id, $group['invitation_code']]);
    
    if ($stmt->fetchColumn() == 0) {
        saveInvitationLink($pdo, $group_id, $group['invitation_code'], $invite_link, $group['created_by']);
    }
    
    // Calculate progress percentage
    $progress_percentage = $group['target_amount'] > 0 ? 
        round(($group['current_amount'] / $group['target_amount']) * 100) : 0;
    
    // Get group members with their individual contributions
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name as name,
            u.email,
            COALESCE(gm.role, CASE WHEN u.id = g.created_by THEN 'leader' ELSE 'member' END) as role,
            COALESCE(gm.joined_at, g.created_at) as joined_at,
            COALESCE(SUM(CASE WHEN gph.status = 'completed' THEN gph.amount ELSE 0 END), 0) as total_contribution,
            (SELECT COUNT(*) FROM group_payment_history gph2 
             WHERE gph2.user_id = u.id AND gph2.group_id = ? AND gph2.status = 'completed') as payment_count
        FROM users u
        LEFT JOIN group_members gm ON u.id = gm.user_id AND gm.group_id = ? AND gm.status = 'active'
        LEFT JOIN groups g ON g.id = ?
        LEFT JOIN group_payment_history gph ON u.id = gph.user_id AND gph.group_id = ?
        WHERE (gm.group_id = ? AND COALESCE(gm.status, 'active') = 'active')
           OR (u.id = g.created_by AND g.id = ?)
        GROUP BY u.id, u.full_name, u.email, gm.role, gm.joined_at, g.created_at, g.created_by
        ORDER BY 
            CASE 
                WHEN COALESCE(gm.role, CASE WHEN u.id = g.created_by THEN 'leader' ELSE 'member' END) = 'leader' THEN 1 
                ELSE 2 
            END,
            COALESCE(gm.joined_at, g.created_at) ASC
    ");
    
    $stmt->execute([$group_id, $group_id, $group_id, $group_id, $group_id, $group_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $active_members = count($members);
    $group['member_count'] = $active_members;
    
} catch(Exception $e) {
    die("Error loading group details: " . $e->getMessage());
}

// Format currency function
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format date function
function formatDate($date) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return $day . ' ' . $month . ' ' . $year;
}

// Get profile image
function getProfileImage($userId) {
    $images = [
        'https://images.pexels.com/photos/1222271/pexels-photo-1222271.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2',
        'https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2',
        'https://images.pexels.com/photos/1516680/pexels-photo-1516680.jpeg?auto=compress&cs=tinysrgb&w=100&h=100&dpr=2'
    ];
    return $images[$userId % count($images)];
}

// Generate invite link
function generateInviteLink($invitation_code) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $base_url = $protocol . '://' . $host;
        $join_path = '/join-group.php';
    } else {
        $base_url = 'https://jalanyuk.com';
        $join_path = '/join-group.php';
    }
    
    return $base_url . $join_path . '?code=' . $invitation_code;
}

// Check if user is leader
function isGroupLeader($user_id, $group) {
    return ($group['created_by'] == $user_id) || ($group['user_role'] === 'leader');
}

$is_leader = isGroupLeader($user_id, $group);

// Get recent payment history
try {
    $stmt = $pdo->prepare("
        SELECT 
            gph.*,
            u.full_name as user_name
        FROM group_payment_history gph
        JOIN users u ON gph.user_id = u.id
        WHERE gph.group_id = ?
        ORDER BY gph.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$group_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $recent_payments = [];
}

// Get recent expenses
try {
    $stmt = $pdo->prepare("
        SELECT 
            ge.*,
            u.full_name as user_name
        FROM group_expenses ge
        JOIN users u ON ge.user_id = u.id
        WHERE ge.group_id = ?
        ORDER BY ge.expense_date DESC
        LIMIT 10
    ");
    $stmt->execute([$group_id]);
    $recent_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $recent_expenses = [];
}
?>
<style>

    
                .action-buttons {
                    display: flex;
                    gap: 12px;
                    margin-top: 20px;
                    justify-content: center;
                    align-items: center;
                }

                .btn-contribution,
                .btn-dissolve {
                    background-color: #2b2b2b;
                    color: #ffffff;
                    border: none;
                    padding: 16px 32px;
                    border-radius: 25px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                    min-width: 140px;
                    justify-content: center;
                }

                .btn-contribution:hover,
                .btn-dissolve:hover {
                    background-color: #404040;
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
                }

                .btn-contribution:active,
                .btn-dissolve:active {
                    transform: translateY(0);
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
                }

                .btn-contribution i,
                .btn-dissolve i {
                    font-size: 16px;
                }
.invite-content {
    padding: 20px;
}

.invite-link-container, .invite-code-container {
    display: flex;
    gap: 10px;
    align-items: center;
}

.invite-link, .invite-code {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background-color: #f9f9f9;
    font-family: monospace;
    font-size: 14px;
}

.btn-copy {
    padding: 12px 16px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-copy:hover {
    background-color: #0056b3;
}

.share-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-share {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-share.whatsapp {
    background-color: #25d366;
    color: white;
}

.btn-share.email {
    background-color: #6c757d;
    color: white;
}

.btn-share.telegram {
    background-color: #0088cc;
    color: white;
}

.btn-share:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.member-settings {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.setting-item:last-child {
    border-bottom: none;
}

.setting-label {
    font-weight: 500;
    color: #495057;
}

.setting-value {
    font-weight: 600;
    color: #007bff;
}

.info-box {
    background-color: #e7f3ff;
    border: 1px solid #b8daff;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.info-icon {
    color: #007bff;
    font-size: 18px;
    margin-bottom: 10px;
}

.info-title {
    font-weight: 600;
    color: #007bff;
    margin-bottom: 8px;
}

.info-list {
    margin: 0;
    padding-left: 20px;
    color: #495057;
}

.info-list li {
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .share-buttons {
        flex-direction: column;
    }
    
    .btn-share {
        justify-content: center;
    }
    
    .invite-link-container, .invite-code-container {
        flex-direction: column;
    }
    
    .invite-link, .invite-code {
        width: 100%;
    }
}
</style>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['name']) ?> - JalanYuk</title>
    <link rel="stylesheet" href="css/family1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head> 
<body>
<div class="container">
        <!-- Header -->
        <header class="detail-header">
            <div class="header-content">
                <div class="header-left">
                    <button class="btn-back" onclick="goBack()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="header-info">
                        <div class="group-title-header">
                            <h1 class="page-title"><?= htmlspecialchars($group['name']) ?></h1>
                            <?php if ($group['user_role'] === 'leader' || $user_id == $group['created_by']): ?>
                            <span class="leader-badge">
                                <i class="fas fa-crown"></i> Leader
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="page-subtitle"><?= htmlspecialchars($group['description']) ?></p>
                    </div>
                </div>
                <div class="header-right">
                    <?php if ($group['user_role'] === 'leader' || $user_id == $group['created_by']): ?>
                    <button class="btn-primary" onclick="openInviteMembersModal()">
                        <i class="fas fa-user-plus"></i> Undang
                    </button>
                    <?php endif; ?>
                    <button class="btn-danger" onclick="openLeaveGroupModal()">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </button>
                </div>
            </div>
        </header>

        <!-- Progress Section -->
        <div class="progress-detail-section">
            <div class="progress-detail-card">
                <div class="progress-header">
                    <h2 class="section-title">Progress Tabungan</h2>
                    <div class="days-remaining">
                        <div class="days-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="days-content">
                            <div class="days-number"><?= max(0, $group['days_remaining']) ?></div>
                            <div class="days-label">Hari tersisa</div>
                        </div>
                    </div>
                </div>
                
                <div class="target-amount-section">
                    <div class="target-label">Target Dana</div>
                    <div class="target-amount-large"><?= formatRupiah($group['target_amount']) ?></div>
                </div>
                
                <div class="progress-bar-large">
                    <div class="progress-fill" style="width: <?= $progress_percentage ?>%"></div>
                </div>
                
                <div class="progress-info">
                    <div class="collected-amount">
                        <span class="collected-label">Terkumpul:</span>
                        <span class="collected-value"><?= formatRupiah($group['current_amount']) ?></span>
                    </div>
                    <div class="progress-percentage-large"><?= $progress_percentage ?>%</div>
                </div>
                
                <div class="end-date-section">
                    <span class="end-date-label">Berakhir pada</span>
                    <span class="end-date-value"><?= formatDate($group['end_date']) ?></span>
                </div>
                
            <!-- Action Buttons -->
<div class="action-buttons">
    <button class="btn-contribution" onclick="window.location.href='payment.php'">
        <i class="fas fa-plus"></i> Tagihan
    </button>

    <?php if ($group['user_role'] === 'leader' || $user_id == $group['created_by']): ?>
    <button class="btn-dissolve" onclick="openDissolveGroupModal()">
        <i class="fas fa-times-circle"></i> Bubarkan Grup
    </button>
    <?php endif; ?>
</div>


        <!-- Members Section -->
        <div class="members-section">
            <div class="members-header">
                <h2 class="section-title">Anggota Grup</h2>
                <div class="members-count">
                    <i class="fas fa-users"></i>
                    <span><?= $active_members ?> aktif</span>
                </div>
            </div>
            
            <div class="members-list">
                <?php if (empty($members)): ?>
                    <div class="no-members-message">
                        <p>Belum ada anggota di grup ini.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($members as $member): ?>
                    <div class="member-item">
                        <div class="member-info">
                            <img src="<?= getProfileImage($member['id']) ?>" alt="<?= htmlspecialchars($member['name']) ?>" class="member-avatar-large">
                            <div class="member-details">
                                <div class="member-name-section">
                                    <span class="member-name"><?= htmlspecialchars($member['name']) ?></span>
                                    <?php if ($member['role'] === 'leader' || $member['id'] == $group['created_by']): ?>
                                    <span class="leader-badge-small">
                                        <i class="fas fa-crown"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="member-email"><?= htmlspecialchars($member['email']) ?></div>
                                <div class="member-join-date">Bergabung <?= formatDate($member['joined_at']) ?></div>
                            </div>
                        </div>
                        <div class="member-contribution">
                            <div class="contribution-amount"><?= formatRupiah($member['total_contribution']) ?></div>
                            <div class="contribution-count"><?= $member['payment_count'] ?> pembayaran</div>
                            <?php if (($group['user_role'] === 'leader' || $user_id == $group['created_by']) && $member['role'] !== 'leader' && $member['id'] != $group['created_by']): ?>
                            <button class="btn-remove" onclick="removeMember(<?= $member['id'] ?>)">Keluarkan</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

 <!-- Invite Members Modal -->
<?php if ($group['user_role'] === 'leader' || $user_id == $group['created_by']): ?>
<div class="modal" id="inviteMembersModal">
    <div class="modal-content">
        <div class="modal-header">
            <button class="modal-back" onclick="closeInviteMembersModal()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h2 class="modal-title">Undang Anggota</h2>
        </div>
        
        <div class="invite-content">
            <!-- Link Undangan -->
            <div class="form-group">
                <label class="form-label">Link Undangan</label>
                <div class="invite-link-container">
                    <input type="text" class="form-input invite-link" id="inviteLink" value="<?= htmlspecialchars($invite_link) ?>" readonly>
                    <button class="btn-copy" onclick="copyInviteLink()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            <!-- Kode Undangan -->
            <div class="form-group">
                <label class="form-label">Kode Undangan</label>
                <div class="invite-code-container">
                    <input type="text" class="form-input invite-code" id="inviteCode" value="<?= htmlspecialchars($group['invitation_code']) ?>" readonly>
                    <button class="btn-copy" onclick="copyInviteCode()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            <!-- Quick Share Buttons -->
            <div class="form-group">
                <label class="form-label">Bagikan Cepat</label>
                <div class="share-buttons">
                    <button class="btn-share whatsapp" onclick="shareToWhatsApp()">
                        <i class="fab fa-whatsapp"></i>
                        WhatsApp
                    </button>
                    <button class="btn-share email" onclick="shareToEmail()">
                        <i class="fas fa-envelope"></i>
                        Email
                    </button>
                    <button class="btn-share telegram" onclick="shareToTelegram()">
                        <i class="fab fa-telegram"></i>
                        Telegram
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
                        <li>Salin link undangan atau kode undangan di atas</li>
                        <li>Kirim ke calon anggota via WhatsApp, email, atau media sosial</li>
                        <li>Anggota dapat bergabung dengan mengklik link atau memasukkan kode</li>
                        <li>Anggota yang bergabung perlu persetujuan Anda sebagai leader</li>
                    </ul>
                </div>
            </div>

            <!-- Member Management -->
            <div class="form-group">
                <label class="form-label">Pengaturan Anggota</label>
                <div class="member-settings">
                    <div class="setting-item">
                        <span class="setting-label">Maksimal Anggota:</span>
                        <span class="setting-value"><?= $group['max_members'] ?> orang</span>
                    </div>
                    <div class="setting-item">
                        <span class="setting-label">Anggota Saat Ini:</span>
                        <span class="setting-value"><?= $group['member_count'] ?> orang</span>
                    </div>
                    <div class="setting-item">
                        <span class="setting-label">Sisa Slot:</span>
                        <span class="setting-value"><?= ($group['max_members'] - $group['member_count']) ?> orang</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <?php endif; ?>

    <!-- Leave Group Modal -->
    <div class="modal" id="leaveGroupModal">
        <div class="modal-content">
            <div class="warning-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h2 class="modal-title">Keluar dari Grup?</h2>
            <p class="modal-text">Anda yakin ingin keluar dari grup "<?= htmlspecialchars($group['name']) ?>"?</p>
            <?php 
            // Get current user's contribution
            $current_user_contribution = 0;
            foreach ($members as $member) {
                if ($member['id'] == $user_id) {
                    $current_user_contribution = $member['total_contribution'];
                    break;
                }
            }
            ?>
            <?php if ($current_user_contribution > 0): ?>
            <p class="warning-text">Dana yang sudah Anda setor (<?= formatRupiah($current_user_contribution) ?>) akan tetap berada di grup dan tidak dapat dikembalikan secara otomatis.</p>
            <?php endif; ?>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeLeaveGroupModal()">Batal</button>
                <button type="button" class="btn-danger" onclick="confirmLeaveGroup()">Ya, Keluar</button>
            </div>
        </div>
    </div>

    <script src="js/family.js"></script>
    <script>
      // Function untuk memposisikan modal di tengah layar
function centerModal(modalId) {
    const modal = document.getElementById(modalId);
    const modalContent = modal.querySelector('.modal-content') || modal.querySelector('.modal-dialog') || modal.firstElementChild;
    
    if (modalContent) {
        // Reset positioning
        modalContent.style.position = 'fixed';
        modalContent.style.top = '50%';
        modalContent.style.left = '50%';
        modalContent.style.transform = 'translate(-50%, -50%)';
        modalContent.style.zIndex = '1001';
        modalContent.style.maxHeight = '90vh';
        modalContent.style.overflow = 'auto';
        modalContent.style.margin = '0';
        
        // Ensure modal backdrop is properly positioned
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.zIndex = '1000';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    }
}

// Function untuk copy invite link dengan notifikasi yang terpusat
function copyInviteLink() {
    const inviteLink = document.getElementById('inviteLink');
    inviteLink.select();
    inviteLink.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(inviteLink.value).then(function() {
        showNotification('Link undangan berhasil disalin!', 'success');
    }).catch(function(err) {
        // Fallback untuk browser lama
        document.execCommand('copy');
        showNotification('Link undangan berhasil disalin!', 'success');
    });
}

// Function untuk copy invite code dengan notifikasi yang terpusat
function copyInviteCode() {
    const inviteCode = document.getElementById('inviteCode');
    inviteCode.select();
    inviteCode.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(inviteCode.value).then(function() {
        showNotification('Kode undangan berhasil disalin!', 'success');
    }).catch(function(err) {
        document.execCommand('copy');
        showNotification('Kode undangan berhasil disalin!', 'success');
    });
}

// Function untuk share ke WhatsApp
function shareToWhatsApp() {
    const inviteLink = document.getElementById('inviteLink').value;
    const groupName = document.querySelector('[data-group-name]')?.dataset.groupName || "Grup Tabungan";
    const inviteCode = document.getElementById('inviteCode')?.value || "";
    const message = `Halo! Kamu diundang untuk bergabung dengan grup tabungan "${groupName}". \n\nKlik link ini untuk bergabung: ${inviteLink}\n\nAtau gunakan kode undangan: ${inviteCode}`;
    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, '_blank');
}

// Function untuk share ke Email
function shareToEmail() {
    const inviteLink = document.getElementById('inviteLink').value;
    const groupName = document.querySelector('[data-group-name]')?.dataset.groupName || "Grup Tabungan";
    const inviteCode = document.getElementById('inviteCode')?.value || "";
    const subject = `Undangan Bergabung Grup Tabungan: ${groupName}`;
    const body = `Halo!

Kamu diundang untuk bergabung dengan grup tabungan "${groupName}".

Klik link berikut untuk bergabung:
${inviteLink}

Atau gunakan kode undangan: ${inviteCode}

Terima kasih!`;
    
    const emailUrl = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    window.location.href = emailUrl;
}

// Function untuk share ke Telegram
function shareToTelegram() {
    const inviteLink = document.getElementById('inviteLink').value;
    const groupName = document.querySelector('[data-group-name]')?.dataset.groupName || "Grup Tabungan";
    const inviteCode = document.getElementById('inviteCode')?.value || "";
    const message = `Halo! Kamu diundang untuk bergabung dengan grup tabungan "${groupName}". \n\nKlik link ini untuk bergabung: ${inviteLink}\n\nAtau gunakan kode undangan: ${inviteCode}`;
    const telegramUrl = `https://t.me/share/url?url=${encodeURIComponent(inviteLink)}&text=${encodeURIComponent(message)}`;
    window.open(telegramUrl, '_blank');
}

// Function untuk menampilkan notifikasi terpusat
function showNotification(message, type = 'info') {
    // Hapus notifikasi yang sudah ada
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // Buat elemen notifikasi
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10000;
        background-color: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: inherit;
        font-size: 14px;
        max-width: 90%;
        animation: slideDown 0.3s ease-out;
    `;
    
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;

    // Tambahkan animasi CSS
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
            @keyframes slideUp {
                from {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Tambahkan ke body
    document.body.appendChild(notification);

    // Auto hide setelah 3 detik
    setTimeout(() => {
        notification.style.animation = 'slideUp 0.3s ease-out';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Function untuk remove member dengan konfirmasi terpusat
function removeMember(memberId) {
    if (confirm('Yakin ingin mengeluarkan anggota ini?')) {
        const groupId = new URLSearchParams(window.location.search).get('id');
        fetch(`group_detail.php?id=${groupId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=remove_member&member_id=' + memberId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan', 'error');
        });
    }
}

// Function untuk confirm leave group
function confirmLeaveGroup() {
    const groupId = new URLSearchParams(window.location.search).get('id');
    fetch(`group_detail.php?id=${groupId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=leave_group'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => {
                window.location.href = 'family.php';
            }, 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Terjadi kesalahan', 'error');
    });
}

// Function untuk go back
function goBack() {
    window.location.href = 'family.php';
}

// Modal functions dengan positioning terpusat
function openInviteMembersModal() {
    const modal = document.getElementById('inviteMembersModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent scrolling
    
    // Center the modal after it's displayed
    setTimeout(() => {
        centerModal('inviteMembersModal');
    }, 10);
}

function closeInviteMembersModal() {
    document.getElementById('inviteMembersModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
}

function openLeaveGroupModal() {
    const modal = document.getElementById('leaveGroupModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent scrolling
    
    // Center the modal after it's displayed
    setTimeout(() => {
        centerModal('leaveGroupModal');
    }, 10);
}

function closeLeaveGroupModal() {
    document.getElementById('leaveGroupModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
}

// Close modal when clicking outside
window.onclick = function(event) {
    const inviteModal = document.getElementById('inviteMembersModal');
    const leaveModal = document.getElementById('leaveGroupModal');
    
    if (event.target == inviteModal) {
        closeInviteMembersModal();
    }
    if (event.target == leaveModal) {
        closeLeaveGroupModal();
    }
}

// Close modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeInviteMembersModal();
        closeLeaveGroupModal();
    }
});

// Handle window resize untuk memastikan modal tetap terpusat
window.addEventListener('resize', function() {
    const inviteModal = document.getElementById('inviteMembersModal');
    const leaveModal = document.getElementById('leaveGroupModal');
    
    if (inviteModal && inviteModal.style.display === 'block') {
        centerModal('inviteMembersModal');
    }
    if (leaveModal && leaveModal.style.display === 'block') {
        centerModal('leaveGroupModal');
    }
});

// Prevent form submission on Enter key in readonly inputs
document.addEventListener('DOMContentLoaded', function() {
    const readonlyInputs = document.querySelectorAll('input[readonly]');
    readonlyInputs.forEach(input => {
        input.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    });
    
    // Ensure modals are properly positioned on page load
    setTimeout(() => {
        const inviteModal = document.getElementById('inviteMembersModal');
        const leaveModal = document.getElementById('leaveGroupModal');
        
        if (inviteModal && inviteModal.style.display === 'block') {
            centerModal('inviteMembersModal');
        }
        if (leaveModal && leaveModal.style.display === 'block') {
            centerModal('leaveGroupModal');
        }
    }, 100);
});
    </script>


                <script>
                // Tambahan efek interaktif
                document.addEventListener('DOMContentLoaded', function() {
                    const buttons = document.querySelectorAll('.btn-contribution, .btn-dissolve');
                    
                    buttons.forEach(button => {
                        button.addEventListener('mouseenter', function() {
                            this.style.transform = 'translateY(-2px)';
                        });
                        
                        button.addEventListener('mouseleave', function() {
                            this.style.transform = 'translateY(0)';
                        });
                    });
                });
                </script>