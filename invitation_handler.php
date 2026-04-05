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

/**
 * Validate and process invitation link
 * @param string $invitation_code
 * @return array
 */
function validateInvitationLink($pdo, $invitation_code) {
    try {
        // Check if invitation link exists and is valid
        $stmt = $pdo->prepare("
            SELECT 
                gi.id as invitation_id,
                gi.group_id,
                gi.invitation_token,
                gi.invitation_link,
                gi.expires_at,
                gi.status,
                g.name as group_name,
                g.description as group_description,
                g.status as group_status,
                u.full_name as invited_by_name
            FROM group_invitations gi
            JOIN groups g ON gi.group_id = g.id
            JOIN users u ON gi.invited_by = u.id
            WHERE gi.invitation_code = ? 
            AND gi.status = 'active'
            AND gi.expires_at > NOW()
            AND g.status = 'active'
            ORDER BY gi.created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$invitation_code]);
        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invitation) {
            return [
                'success' => false,
                'message' => 'Link undangan tidak valid atau sudah kedaluwarsa',
                'data' => null
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Link undangan valid',
            'data' => $invitation
        ];
        
    } catch(Exception $e) {
        error_log("Error validating invitation link: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Terjadi kesalahan saat memvalidasi link undangan',
            'data' => null
        ];
    }
}

/**
 * Process login via invitation link
 * @param array $invitation_data
 * @param int $user_id
 * @return array
 */
function processInvitationLogin($pdo, $invitation_data, $user_id) {
    try {
        $group_id = $invitation_data['group_id'];
        
        // Check if user is already a member of the group
        $stmt = $pdo->prepare("
            SELECT id, status FROM group_members 
            WHERE group_id = ? AND user_id = ?
        ");
        $stmt->execute([$group_id, $user_id]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($membership) {
            if ($membership['status'] === 'active') {
                return [
                    'success' => true,
                    'message' => 'Anda sudah menjadi anggota grup ini',
                    'redirect_url' => 'group_detail.php?id=' . $group_id
                ];
            } else {
                // Reactivate membership
                $stmt = $pdo->prepare("
                    UPDATE group_members 
                    SET status = 'active', joined_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$membership['id']]);
                
                return [
                    'success' => true,
                    'message' => 'Berhasil bergabung kembali ke grup',
                    'redirect_url' => 'group_detail.php?id=' . $group_id
                ];
            }
        }
        
        // Add user to group
        $stmt = $pdo->prepare("
            INSERT INTO group_members (group_id, user_id, role, status, joined_at) 
            VALUES (?, ?, 'member', 'active', NOW())
        ");
        $stmt->execute([$group_id, $user_id]);
        
        // Create notification for group creator/leaders
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, group_id, type, title, message, action_url, created_at)
            SELECT 
                DISTINCT CASE 
                    WHEN gm.role = 'leader' THEN gm.user_id
                    ELSE g.created_by 
                END as user_id,
                ? as group_id,
                'member_joined' as type,
                'Anggota Baru Bergabung' as title,
                CONCAT(u.full_name, ' bergabung ke grup ', g.name, ' melalui link undangan') as message,
                CONCAT('group_detail.php?id=', ?) as action_url,
                NOW() as created_at
            FROM groups g
            LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.role = 'leader'
            JOIN users u ON u.id = ?
            WHERE g.id = ?
        ");
        $stmt->execute([$group_id, $group_id, $user_id, $group_id]);
        
        return [
            'success' => true,
            'message' => 'Berhasil bergabung ke grup!',
            'redirect_url' => 'group_detail.php?id=' . $group_id
        ];
        
    } catch(Exception $e) {
        error_log("Error processing invitation login: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Terjadi kesalahan saat bergabung ke grup',
            'redirect_url' => null
        ];
    }
}

/**
 * Auto login via invitation link (for family.php)
 * @param string $invitation_code
 * @return array
 */
function autoLoginViaInvitation($pdo, $invitation_code) {
    // First validate the invitation
    $validation_result = validateInvitationLink($pdo, $invitation_code);
    
    if (!$validation_result['success']) {
        return $validation_result;
    }
    
    // Store invitation data in session for later use
    $_SESSION['pending_invitation'] = [
        'code' => $invitation_code,
        'data' => $validation_result['data'],
        'timestamp' => time()
    ];
    
    return [
        'success' => true,
        'message' => 'Link undangan valid. Silakan login untuk bergabung.',
        'requires_login' => true,
        'group_name' => $validation_result['data']['group_name'],
        'invited_by' => $validation_result['data']['invited_by_name']
    ];
}

/**
 * Process pending invitation after login
 * @param int $user_id
 * @return array
 */
function processPendingInvitation($pdo, $user_id) {
    if (!isset($_SESSION['pending_invitation'])) {
        return ['success' => false, 'message' => 'Tidak ada undangan pending'];
    }
    
    $pending = $_SESSION['pending_invitation'];
    
    // Check if invitation is still valid (not older than 1 hour)
    if (time() - $pending['timestamp'] > 3600) {
        unset($_SESSION['pending_invitation']);
        return ['success' => false, 'message' => 'Session undangan sudah kedaluwarsa'];
    }
    
    // Revalidate invitation
    $validation_result = validateInvitationLink($pdo, $pending['code']);
    if (!$validation_result['success']) {
        unset($_SESSION['pending_invitation']);
        return $validation_result;
    }
    
    // Process the invitation
    $result = processInvitationLogin($pdo, $validation_result['data'], $user_id);
    
    // Clear pending invitation
    unset($_SESSION['pending_invitation']);
    
    return $result;
}

// Export functions for use in other files
// This file can be included in other PHP files to use these functions
?>