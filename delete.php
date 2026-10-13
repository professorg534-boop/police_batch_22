<?php
session_start();
include 'db.php';

$admin_id = $_SESSION['user_id'];
$admin_query = mysqli_query($conn, "SELECT role FROM users WHERE id = '$admin_id'");
$admin_data = mysqli_fetch_assoc($admin_query);

if ($admin_data['role'] == 'super_admin' || $admin_data['role'] == 'admin') {
    if (isset($_GET['id'])) {
        $user_id = mysqli_real_escape_string($conn, $_GET['id']);
        $delete = mysqli_query($conn, "DELETE FROM users WHERE id = '$user_id'");
        
        if ($delete) {
            header("Location: dashboard.php?msg=Record Deleted");
        }
    }
} else {
    echo "Unauthorized access!";
}
?>