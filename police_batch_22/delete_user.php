<?php
session_start();
include 'db.php';

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id='$my_id'"));
$my_role = strtolower($me['role'] ?? '');

if (in_array($my_role, ['admin', 'super admin', 'super_admin']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    header("Location: members.php?msg=Member Removed");
} else {
    header("Location: members.php");
}
?>