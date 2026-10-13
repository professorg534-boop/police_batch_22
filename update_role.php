<?php
session_start();
include 'db.php';

// ১. সিকিউরিটি চেক: লগইন করা আছে কি না
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id='$my_id'"));
$my_role = strtolower($me['role'] ?? 'member');

// চেক করা: ইউজার কি অ্যাডমিন বা সুপার অ্যাডমিন?
$is_admin = (in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin']));

// ২. যদি সে অ্যাডমিন না হয়, তবে মেম্বার লিস্টে ফেরত পাঠিয়ে দেওয়া
if (!$is_admin) {
    header("Location: members.php?error=Unauthorized");
    exit();
}

// ৩. ডাটা রিসিভ করা
if (isset($_GET['id']) && isset($_GET['role'])) {
    
    $target_id = mysqli_real_escape_string($conn, $_GET['id']);
    $new_role = mysqli_real_escape_string($conn, $_GET['role']);

    // ৪. সুপার অ্যাডমিন লজিক: 
    // সাধারণ অ্যাডমিন কাউকে সুপার অ্যাডমিন বানাতে পারবে না
    if (strpos($new_role, 'super') !== false) {
        if (strpos($my_role, 'super') === false) {
            header("Location: members.php?error=Only Super Admins can do this");
            exit();
        }
    }

    // ৫. রোল আপডেট কুয়েরি
    $update_sql = "UPDATE users SET role = '$new_role' WHERE id = '$target_id'";

    if (mysqli_query($conn, $update_sql)) {
        header("Location: members.php?success=Role updated to " . ucfirst($new_role));
        exit();
    } else {
        header("Location: members.php?error=Update failed");
        exit();
    }

} else {
    header("Location: members.php");
    exit();
}
?>