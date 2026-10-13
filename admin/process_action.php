<?php
include '../db.php';
if(isset($_POST['action'])){
    $id = $_POST['id'];
    $status = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
    
    $update = "UPDATE payments SET status = '$status' WHERE id = '$id'";
    if(mysqli_query($conn, $update)){
        header("Location: ../dashboard.php?msg=success");
    }
}
?>