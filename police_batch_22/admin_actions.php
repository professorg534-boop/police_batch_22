<?php
include 'db.php';
session_start();

// Security: Only Super Admins can access this logic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die("Access Denied: Only Super Admin can perform these actions.");
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$action = mysqli_real_escape_string($conn, $_GET['action']);

switch ($action) {
    case 'approve':
        mysqli_query($conn, "UPDATE users SET status='active' WHERE id='$id'");
        $msg = "Member Approved Successfully";
        break;
        
    case 'make_admin':
        mysqli_query($conn, "UPDATE users SET role='admin' WHERE id='$id'");
        $msg = "Promoted to Admin";
        break;

    case 'make_super':
        mysqli_query($conn, "UPDATE users SET role='super_admin' WHERE id='$id'");
        $msg = "Promoted to Super Admin";
        break;

    case 'delete':
        // Safety: Cannot delete self
        if($id != $_SESSION['user_id']) {
            mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
            $msg = "User Removed";
        }
        break;
}

header("Location: dashboard.php?msg=" . urlencode($msg));
exit();
?>