<?php
session_start();

// Store user role before destroying session
$user_role = $_SESSION['role'] ?? '';

// Destroy all session data
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear browser cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Start new session for success message
session_start();
$_SESSION['success'] = "You have been successfully logged out.";

// Redirect based on user role
switch ($user_role) {
    case 'vet':
        header("Location: login_vet.php");
        break;
    case 'lgu':
        header("Location: login_lgu.php");
        break;
    case 'admin':
        header("Location: login_admin.php");
        break;
    default:
        header("Location: login.php"); // For regular users
        break;
}
exit();
?>
