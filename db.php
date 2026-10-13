<?php
// mysqli_report বন্ধ রাখা হলো
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

// Database Credentials
$host   = "maglev.proxy.rlwy.net";
$user   = "root";
$pass   = "QlQaYatJfbeFwpliRqXwbEpVpuTkBkav"; 
$dbname = "railway";
$port   = 29480;

// Database Connection Setup
$conn = mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database Connection Error!");
}

mysqli_set_charset($conn, "utf8mb4");
?>