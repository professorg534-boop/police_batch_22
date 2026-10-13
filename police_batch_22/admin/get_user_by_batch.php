<?php
// Path: admin/get_user_by_batch.php
include '../db.php'; 

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if(isset($_GET['id'])) {
    $batch_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // '*' ব্যবহার করছি যাতে সব কলাম অটো চলে আসে, নাম ভুল হওয়ার ভয় না থাকে
    $query = "SELECT * FROM users WHERE batch_id = '$batch_id' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // সব ডাটা আসছে কি না তা নিশ্চিত করতে অ্যারে পাঠানো হচ্ছে
        echo json_encode([
            'success' => true, 
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'User not found in database with Batch ID: ' . $batch_id
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
}
?>