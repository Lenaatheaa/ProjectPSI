<?php
// logout.php
session_start();
require_once 'config.php';

// Hapus session dari database jika ada
if (isset($_SESSION['session_token'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
    } catch (Exception $e) {
        error_log("Error deleting session: " . $e->getMessage());
    }
}

// Hapus semua session variables
$_SESSION = array();

// Hapus session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect ke login
header('Location: login.php');
exit;
?>