<?php
// balance_functions.php
// File terpisah untuk fungsi-fungsi balance yang bisa digunakan di berbagai file

/**
 * Function untuk mendapatkan balance user dari database
 * @param int $user_id ID user
 * @return float Balance user
 */
function getUserBalance($user_id) {
    global $conn;
    
    if (empty($user_id) || !is_numeric($user_id)) {
        return 0.00;
    }
    
    try {
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return floatval($row['balance']);
        }
        
        return 0.00;
    } catch (Exception $e) {
        error_log("Error getting user balance for user ID {$user_id}: " . $e->getMessage());
        return 0.00;
    }
}

/**
 * Function untuk update balance user
 * @param int $user_id ID user
 * @param float $new_balance Balance baru
 * @return bool True jika berhasil, false jika gagal
 */
function updateUserBalance($user_id, $new_balance) {
    global $conn;
    
    if (empty($user_id) || !is_numeric($user_id) || $new_balance < 0) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE users SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND is_active = 1");
        $stmt->bind_param("di", $new_balance, $user_id);
        $result = $stmt->execute();
        
        return $result && $stmt->affected_rows > 0;
    } catch (Exception $e) {
        error_log("Error updating user balance for user ID {$user_id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Function untuk menambah balance user
 * @param int $user_id ID user
 * @param float $amount Jumlah yang akan ditambahkan
 * @return bool True jika berhasil, false jika gagal
 */
function addUserBalance($user_id, $amount) {
    if (empty($user_id) || !is_numeric($user_id) || $amount <= 0) {
        return false;
    }
    
    $current_balance = getUserBalance($user_id);
    $new_balance = $current_balance + $amount;
    
    return updateUserBalance($user_id, $new_balance);
}

/**
 * Function untuk mengurangi balance user
 * @param int $user_id ID user
 * @param float $amount Jumlah yang akan dikurangi
 * @return bool True jika berhasil, false jika gagal
 */
function deductUserBalance($user_id, $amount) {
    if (empty($user_id) || !is_numeric($user_id) || $amount <= 0) {
        return false;
    }
    
    $current_balance = getUserBalance($user_id);
    
    // Cek apakah balance mencukupi
    if ($current_balance < $amount) {
        return false; // Balance tidak mencukupi
    }
    
    $new_balance = $current_balance - $amount;
    
    return updateUserBalance($user_id, $new_balance);
}

/**
 * Function untuk format rupiah
 * @param float $amount Jumlah uang
 * @return string Format rupiah
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Function untuk mengecek apakah balance mencukupi
 * @param int $user_id ID user
 * @param float $required_amount Jumlah yang dibutuhkan
 * @return bool True jika mencukupi, false jika tidak
 */
function checkBalanceSufficient($user_id, $required_amount) {
    $current_balance = getUserBalance($user_id);
    return $current_balance >= $required_amount;
}

/**
 * Function untuk mencatat log transaksi balance (opsional)
 * Bisa digunakan untuk audit trail
 */
function logBalanceTransaction($user_id, $transaction_type, $amount, $description = '') {
    global $conn;
    
    try {
        // Assuming you have a balance_logs table
        $stmt = $conn->prepare("INSERT INTO balance_logs (user_id, transaction_type, amount, description, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("isds", $user_id, $transaction_type, $amount, $description);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error logging balance transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Function untuk refresh balance di session (jika menggunakan session untuk cache balance)
 * @param int $user_id ID user
 */
function refreshBalanceInSession($user_id) {
    if (isset($_SESSION['user_data']) && $_SESSION['user_data']['id'] == $user_id) {
        $_SESSION['user_data']['balance'] = getUserBalance($user_id);
    }
}
?>