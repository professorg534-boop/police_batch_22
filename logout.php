<?php
// ১. সেশন শুরু করা
session_start();

// ২. সেশনের সকল ডাটা মুছে ফেলা
$_SESSION = array();

// ৩. যদি সেশন কুকি থাকে, তবে সেটিও ডিলিট করা
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ৪. সেশনটি সম্পূর্ণ ধ্বংস করা
session_destroy();

// ৫. লগইন পেজে (index.php) পাঠিয়ে দেওয়া
header("Location: index.php");
exit;
?>