<?php
// নিশ্চিত হোন ফাইলের শুরুতে কোনো স্পেস নেই
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "police_batch_22";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Connection failed"]);
    exit();
}
// এখানে যদি echo "Database Connected Successfully!"; থাকে তবে তা ডিলিট করুন।
?>