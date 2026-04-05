<?php
/**
 * Invitation Helper Functions
 * Contains utility functions for handling group invitations
 */

/**
 * Generate a unique invitation code
 * @return string
 */
function generateInvitationCode() {
    // Generate a random 8-character code
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

/**
 * Validate invitation code format
 * @param string $code
 * @return bool
 */
function validateInvitationCodeFormat($code) {
    // Check if code is 8 characters long and contains only alphanumeric characters
    return preg_match('/^[A-Z0-9]{8}$/', $code);
}

/**
 * Get invitation URL for a group
 * @param string $invitation_code
 * @param string $base_url (optional)
 * @return string
 */
function getInvitationUrl($invitation_code, $base_url = null) {
    if ($base_url === null) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    }
    
    return rtrim($base_url, '/') . '/family.php?join=' . urlencode($invitation_code);
}

/**
 * Format invitation message for sharing
 * @param string $group_name
 * @param string $invitation_url
 * @param string $creator_name (optional)
 * @return string
 */
function formatInvitationMessage($group_name, $invitation_url, $creator_name = null) {
    $message = "🎯 Anda diundang untuk bergabung dengan grup tabungan: *{$group_name}*\n\n";
    
    if ($creator_name) {
        $message .= "📤 Diundang oleh: {$creator_name}\n\n";
    }
    
    $message .= "🔗 Klik link berikut untuk bergabung:\n{$invitation_url}\n\n";
    $message .= "💡 Atau salin kode undangan dan masukkan secara manual di aplikasi.";
    
    return $message;
}

/**
 * Check if invitation code is expired based on group end date
 * @param PDO $pdo
 * @param string $invitation_code
 * @return bool
 */
function isInvitationExpired($pdo, $invitation_code) {
    try {
        $stmt = $pdo->prepare("
            SELECT end_date 
            FROM groups 
            WHERE invitation_code = ? AND status = 'active'
        ");
        $stmt->execute([$invitation_code]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            return true; // Invalid code is considered expired
        }
        
        return strtotime($group['end_date']) < time();
    } catch (Exception $e) {
        error_log("Check invitation expiry error: " . $e->getMessage());
        return true; // On error, consider expired for safety
    }
}

/**
 * Get group info by invitation code
 * @param PDO $pdo
 * @param string $invitation_code
 * @return array|false
 */
function getGroupByInvitationCode($pdo, $invitation_code) {
    try {
        $stmt = $pdo->prepare("
            SELECT g.*, 
                   u.full_name as creator_name,
                   COUNT(DISTINCT gm.user_id) as member_count
            FROM groups g 
            LEFT JOIN users u ON g.created_by = u.id
            LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
            WHERE g.invitation_code = ? AND g.status = 'active'
            GROUP BY g.id
        ");
        $stmt->execute([$invitation_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Get group by invitation code error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log invitation activity
 * @param PDO $pdo
 * @param int $group_id
 * @param int $user_id
 * @param string $activity_type
 * @param string $description
 * @return bool
 */
function logInvitationActivity($pdo, $group_id, $user_id, $activity_type, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO group_activities (group_id, user_id, activity_type, activity_description, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$group_id, $user_id, $activity_type, $description]);
        return true;
    } catch (Exception $e) {
        error_log("Log invitation activity error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send invitation notification
 * @param PDO $pdo
 * @param int $recipient_user_id
 * @param int $group_id
 * @param string $group_name
 * @param string $sender_name
 * @return bool
 */
function sendInvitationNotification($pdo, $recipient_user_id, $group_id, $group_name, $sender_name) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, group_id, type, title, message, action_url, created_at)
            VALUES (?, ?, 'group_invitation', 'Undangan Grup', 
                    CONCAT(?, ' mengundang Anda bergabung ke grup ', ?), 
                    CONCAT('family.php?group_id=', ?), NOW())
        ");
        $stmt->execute([
            $recipient_user_id, 
            $group_id, 
            $sender_name, 
            $group_name,
            $group_id
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Send invitation notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user can join group (not already member, group not full, etc.)
 * @param PDO $pdo
 * @param int $user_id
 * @param string $invitation_code
 * @return array
 */
function canUserJoinGroup($pdo, $user_id, $invitation_code) {
    try {
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
            return ['can_join' => false, 'reason' => 'Kode undangan tidak valid atau grup tidak aktif!'];
        }
        
        if ($group['already_member']) {
            return ['can_join' => false, 'reason' => 'Anda sudah menjadi anggota grup ini!'];
        }
        
        if ($group['member_count'] >= $group['max_members']) {
            return ['can_join' => false, 'reason' => 'Grup sudah mencapai batas maksimum anggota!'];
        }
        
        if (strtotime($group['end_date']) < time()) {
            return ['can_join' => false, 'reason' => 'Grup sudah berakhir!'];
        }
        
        return ['can_join' => true, 'group' => $group];
    } catch (Exception $e) {
        error_log("Can user join group error: " . $e->getMessage());
        return ['can_join' => false, 'reason' => 'Terjadi kesalahan saat memvalidasi undangan!'];
    }
}
?>