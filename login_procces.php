<?php
// login_process.php
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validasi input
        if (empty($email) || empty($password)) {
            throw new Exception('Email dan password harus diisi');
        }
        
        if (!isValidEmail($email)) {
            throw new Exception('Format email tidak valid');
        }
        
        $pdo = getDBConnection();
        
        // Cari user berdasarkan email
        $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, email_verified, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('Email atau password salah');
        }
        
        // Cek apakah user aktif
        if (!$user['is_active']) {
            throw new Exception('Akun Anda telah dinonaktifkan');
        }
        
        // Verifikasi password
        if (!verifyPassword($password, $user['password_hash'])) {
            throw new Exception('Email atau password salah');
        }
        
        // Login berhasil - set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        
        // Buat session token di database
        $session_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $session_token, $expires_at]);
        
        $_SESSION['session_token'] = $session_token;
        
        $response['success'] = true;
        $response['message'] = 'Login berhasil';
        $response['redirect'] = 'dashboard.php';
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
}

// Jika request AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Jika bukan AJAX dan login berhasil
if ($response['success']) {
    header('Location: dashboard.php');
    exit;
}

// Jika ada error, kembali ke halaman login dengan pesan error
$_SESSION['login_error'] = $response['message'];
header('Location: login.php');
exit;
?>