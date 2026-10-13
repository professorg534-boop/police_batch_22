<?php
include '../db.php';
header('Content-Type: application/json');

if(isset($_GET['batch_id'])) {
    $batch_id = mysqli_real_escape_string($conn, $_GET['batch_id']);
    // আপনার টেবিল অনুযায়ী কলাম নাম (username/batch_id) চেক করে নিন
    $sql = "SELECT full_name, photo, division FROM users WHERE username = '$batch_id' OR id = '$batch_id' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'full_name' => $row['full_name'],
            'photo' => $row['photo'], // নিশ্চিত করুন photo কলামে ইমেজ নাম আছে
            'division' => $row['division']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
}
exit;
?>