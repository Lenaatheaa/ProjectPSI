<?php
// api/join_group.php
header('Content-Type: application/json');
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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
$invite_input = trim($_POST['invite_code'] ?? '');

if (empty($invite_input)) {
    echo json_encode(['success' => false, 'message' => 'Link undangan tidak boleh kosong']);
    exit();
}

// Extract invite code from URL or use as is
$invitation_code = $invite_input;

// Handle various URL formats
if (strpos($invite_input, 'http') === 0) {
    // Extract code from URL
    $parsed_url = parse_url($invite_input);
    
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $params);
        $invitation_code = $params['code'] ?? '';
    } else {
        // Maybe it's in the path like /join/ABCD1234
        $path_parts = explode('/', trim($parsed_url['path'], '/'));
        $last_part = end($path_parts);
        
        // If last part is not a PHP file, use it as invitation code
        if (strpos($last_part, '.php') === false && !empty($last_part)) {
            $invitation_code = $last_part;
        } else {
            $invitation_code = '';
        }
    }
}

// Clean up the invitation code - support both formats (GRP + alphanumeric or just alphanumeric)
$invitation_code = strtoupper(trim($invitation_code));

if (empty($invitation_code) || strlen($invitation_code) < 6) {
    echo json_encode(['success' => false, 'message' => 'Kode undangan tidak valid']);
    exit();
}

try {
    // Find group by invitation code - check both possible formats
    $query = "SELECT g.*, 
                     gm_check.user_id as already_member, 
                     gm_check.status as member_status,
                     u_creator.full_name as creator_name
              FROM groups g 
              LEFT JOIN group_members gm_check ON g.id = gm_check.group_id AND gm_check.user_id = ?
              LEFT JOIN users u_creator ON g.created_by = u_creator.id
              WHERE g.invitation_code = ? 
                AND g.status = 'active'
                AND g.end_date >= CURDATE()";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $invitation_code]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Grup tidak ditemukan, sudah berakhir, atau kode undangan tidak valid']);
        exit();
    }
    
    // Check if user is the group creator
    if ($group['created_by'] == $user_id) {
        echo json_encode([
            'success' => false, 
            'message' => 'Anda adalah pembuat grup ini'
        ]);
        exit();
    }
    
    // Check if user is already a member
    if ($group['already_member']) {
        $status_messages = [
            'active' => 'Anda sudah menjadi anggota grup ini',
            'pending' => 'Permintaan bergabung Anda sedang menunggu persetujuan',
            'inactive' => 'Keanggotaan Anda dalam grup ini tidak aktif'
        ];
        
        echo json_encode([
            'success' => false, 
            'message' => $status_messages[$group['member_status']] ?? 'Status keanggotaan tidak diketahui',
            'group_name' => $group['name'],
            'group_id' => $group['id']
        ]);
        exit();
    }
    
    // Check if group has reached maximum members
    $member_count_query = "SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND status = 'active'";
    $count_stmt = $pdo->prepare($member_count_query);
    $count_stmt->execute([$group['id']]);
    $current_members = $count_stmt->fetchColumn();
    
    if ($current_members >= $group['max_members']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Grup sudah mencapai batas maksimum anggota (' . $group['max_members'] . ' orang)'
        ]);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Add user as active member
        $join_query = "INSERT INTO group_members (group_id, user_id, role, status, joined_at) 
                       VALUES (?, ?, 'member', 'active', NOW())
                       ON DUPLICATE KEY UPDATE 
                       status = 'active', 
                       joined_at = NOW()";
        $join_stmt = $pdo->prepare($join_query);
        $join_stmt->execute([$group['id'], $user_id]);
        
        // Log the join activity in group_activities if table exists
        try {
            $activity_query = "INSERT INTO group_activities (group_id, user_id, activity_type, activity_description, created_at) 
                              VALUES (?, ?, 'member_joined', ?, NOW())";
            $activity_stmt = $pdo->prepare($activity_query);
            $activity_stmt->execute([
                $group['id'], 
                $user_id, 
                "Bergabung dengan grup: " . $group['name']
            ]);
        } catch(Exception $e) {
            // Activity log is not critical, continue
        }
        
        // Create notification for group creator
        try {
            $notification_query = "INSERT INTO notifications (user_id, group_id, type, title, message, action_url, created_at) 
                                  VALUES (?, ?, 'member_joined', ?, ?, ?, NOW())";
            $notification_stmt = $pdo->prepare($notification_query);
            $notification_stmt->execute([
                $group['created_by'],
                $group['id'],
                'Anggota Baru Bergabung',
                'Seorang anggota baru telah bergabung dengan grup "' . $group['name'] . '"',
                'group-detail.php?id=' . $group['id']
            ]);
        } catch(Exception $e) {
            // Notification is not critical, continue
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Berhasil bergabung dengan grup: ' . $group['name'],
            'group_name' => $group['name'],
            'group_id' => $group['id'],
            'creator_name' => $group['creator_name']
        ]);
        
    } catch(Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>