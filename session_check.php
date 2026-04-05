<?php
// session_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

function getUserData() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        
        $user = $stmt->fetch();
        return $user ? $user : null;
    } catch (Exception $e) {
        error_log("Error getting user data: " . $e->getMessage());
        return null;
    }
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && getUserData() !== null;
}

function requireLogin($redirect_to = 'login.php') {
    if (!isUserLoggedIn()) {
        // Clear any invalid session data
        session_destroy();
        header('Location: ' . $redirect_to);
        exit;
    }
}

function redirectIfLoggedIn($redirect_to = 'dashboard.php') {
    if (isUserLoggedIn()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

// Get user data if logged in
$user_data = getUserData();
$is_logged_in = $user_data !== null;
?>