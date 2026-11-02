<?php
declare(strict_types=1);

// Target isolated admin session only
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session variables
$_SESSION = array();

// Destroy admin cookie only without affecting user state
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to admin login page
header("Location: admin_login.php");
exit();
?>