<?php
// Database Credentials
$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "online_shop"; //

// Database Connection Create Kora
$conn = mysqli_connect($host, $username, $password, $dbname);

// Connection Error Check
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// UTF-8 Charset
mysqli_set_charset($conn, "utf8mb4");

// Global Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>