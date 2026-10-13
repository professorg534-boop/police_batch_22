<?php
session_start();
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['contribution_id'])) {
    $contribution_id = mysqli_real_escape_string($conn, $_POST['contribution_id']);
    
    // ১. কন্ট্রিবিউশন স্ট্যাটাস আপডেট করা
    $update_query = "UPDATE district_contributions SET status = 'paid' WHERE id = '$contribution_id'";
    
    if (mysqli_query($conn, $update_query)) {
        // ২. মেইন ফান্ড অপারেশনে কালেকশন এমাউন্ট যোগ করা
        $get_data = mysqli_query($conn, "SELECT op_id, required_amount FROM district_contributions WHERE id = '$contribution_id'");
        $row = mysqli_fetch_assoc($get_data);
        $op_id = $row['op_id'];
        $amount = $row['required_amount'];

        mysqli_query($conn, "UPDATE fund_operations SET collected_amount = collected_amount + $amount WHERE id = '$op_id'");
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}
?>