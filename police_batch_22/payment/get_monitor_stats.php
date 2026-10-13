<?php
include '../db.php';

$district = $_GET['district'] ?? '';

if ($district) {
    // ইউজার লিস্ট এবং তাদের মোট পেমেন্ট একবারে নিয়ে আসা
    $sql = "SELECT u.full_name, u.username, u.photo, 
            (SELECT COUNT(*) FROM payments WHERE user_id = u.id) as total_tx, 
            (SELECT SUM(amount) FROM payments WHERE user_id = u.id AND status = 'Approved') as total_paid 
            FROM users u 
            WHERE u.district = '$district'";
            
    $result = mysqli_query($conn, $sql);
    $members = [];

    while($row = mysqli_fetch_assoc($result)) {
        $members[] = $row;
    }

    echo json_encode($members);
}
?>