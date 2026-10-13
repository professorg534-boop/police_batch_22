<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host   = "maglev.proxy.rlwy.net";
$user   = "root";
$pass   = "QlQaYatJfbeFwpliRqXwbEpVpuTkBkav"; 
$dbname = "railway";
$port   = 29480;

$conn = @mysqli_connect($host, $user, $pass, $dbname, $port);

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