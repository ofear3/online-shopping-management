<?php
declare(strict_types=1);

// সেশন শুরু করা (যদি আগে থেকে শুরু না হয়ে থাকে)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// সব সেশন ভ্যারিয়েবল খালি বা ডিলিট করা
$_SESSION = array();

// যদি সেশন কুকি ব্যবহার করা হয়ে থাকে, তবে সেটিও ডিলিট বা এক্সপায়ার করে দেওয়া
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// সেশন পুরোপুরি ধ্বংস (destroy) করা
session_destroy();

// লগআউট হওয়ার পর সরাসরি মূল index.php-তে রিডাইরেক্ট করা
header('Location: /online-shopping-management/index.php');
exit;