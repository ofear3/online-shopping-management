<?php
require_once '../config/db.php';

// Session Array Clear Kora
$_SESSION = array();

// Session Cookie Delete Kora
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session Destroy
session_destroy();

// Redirect to Admin Login Page
header("Location: login.php");
exit();
?>