<?php
// mysqli_report বন্ধ রাখা হলো যেন Server Error না আসে
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

// Railway Environment Variables অথবা fallback hardcoded credentials
$host   = getenv('MYSQLHOST') ?: "maglev.proxy.rlwy.net";
$user   = getenv('MYSQLUSER') ?: "root";
$pass   = getenv('MYSQLPASSWORD') ?: "QlQaYatJfbeFwpliRqXwbEpVpuTkBkav"; 
$dbname = getenv('MYSQLDATABASE') ?: "railway";
$port   = getenv('MYSQLPORT') ?: 29480;

// Database Connection
$conn = @mysqli_connect($host, $user, (string)$pass, $dbname, (int)$port);

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    die("<div style='font-family:sans-serif; background:#0f172a; color:#f87171; padding:40px; text-align:center;'>
            <h2>Service Temporarily Unavailable</h2>
            <p style='color:#94a3b8; margin-top:10px;'>Please try again in a moment. If this continues, contact the system administrator.</p>
         </div>");
}

mysqli_set_charset($conn, "utf8mb4");
?>