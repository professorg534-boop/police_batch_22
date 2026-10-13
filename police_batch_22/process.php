<?php
session_start();
include 'db.php'; // মেইন ডিরেক্টরির কানেকশন ফাইল

// অ্যাডমিন অথেন্টিকেশন চেক
if (!isset($_SESSION['user_id'])) {
    exit("Access Denied");
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if ($id && $action == 'approve') {
    // ইউজারের স্ট্যাটাস একটিভ করা
    $update = mysqli_query($conn, "UPDATE users SET status='active' WHERE id='$id'");
    if($update) echo "success";
} 
elseif ($id && $action == 'reject') {
    // ইউজারের রিকোয়েস্ট ডিলিট করা
    $delete = mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    if($delete) echo "success";
}
exit();