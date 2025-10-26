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

// Redirect based on user role with parameters
switch ($user_role) {
    case 'vet':
        header("Location: login.php?role=vet");
        break;
    case 'lgu':
        header("Location: login.php?role=lgu");
        break;
    case 'admin':
        header("Location: login.php?role=admin");
        break;
    default:
        header("Location: login.php");
        break;
}
exit();
?>
