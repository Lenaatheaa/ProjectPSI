<?php
// balance.php
require_once 'config.php';

/**
 * Get user balance by user ID
 */
function getUserBalance($user_id) {
    global $connection;
    
    try {
        $stmt = $connection->prepare("SELECT balance FROM users WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['balance'];
        }
        return 0.00;
    } catch (Exception $e) {
        error_log("Error getting balance: " . $e->getMessage());
        return 0.00;
    }
}

/**
 * Format balance dengan pemisah ribuan
 */
function formatBalance($balance) {
    return number_format($balance, 2, '.', ',');
}

/**
 * Format balance dengan mata uang Rupiah
 */
function formatCurrency($balance) {
    return 'Rp ' . formatBalance($balance);
}

/**
 * Update user balance
 */
function updateUserBalance($user_id, $new_balance) {
    global $connection;
    
    try {
        $stmt = $connection->prepare("UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ? AND is_active = 1");
        $stmt->bind_param("di", $new_balance, $user_id);
        $stmt->execute();
        
        return $stmt->affected_rows > 0;
    } catch (Exception $e) {
        error_log("Error updating balance: " . $e->getMessage());
        return false;
    }
}
?>