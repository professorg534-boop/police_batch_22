<?php
include '../db.php';
header('Content-Type: application/json');

$district = $_GET['district'] ?? '';

if (!$district) {
    echo json_encode(['error' => 'No district selected']);
    exit;
}

// ১. মেম্বারদের তথ্য এবং তাদের মোট পেমেন্ট ক্যালকুলেশন
$query = "SELECT u.id, u.full_name, u.role, u.username, u.photo, 
          COUNT(p.id) as tx_count, 
          SUM(CASE WHEN p.status = 'approved' THEN p.amount ELSE 0 END) as total_paid
          FROM users u 
          LEFT JOIN payments p ON u.id = p.user_id 
          WHERE u.district = '$district' AND u.status = 'active'
          GROUP BY u.id";

$res = mysqli_query($conn, $query);
$members = [];
while ($row = mysqli_fetch_assoc($res)) {
    $members[] = $row;
}

// ২. ডিস্ট্রিক্ট স্ট্যাটাস (Stats)
$stats_q = mysqli_query($conn, "SELECT 
    COUNT(DISTINCT user_id) as total_users,
    SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_amount,
    COUNT(id) as total_transactions
    FROM payments WHERE district = '$district'");
$stats = mysqli_fetch_assoc($stats_q);

echo json_encode([
    'total_users' => $stats['total_users'] ?? 0,
    'total_amount' => $stats['total_amount'] ?? 0,
    'total_transactions' => $stats['total_transactions'] ?? 0,
    'members' => $members
]);