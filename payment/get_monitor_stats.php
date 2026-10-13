<?php
header('Content-Type: application/json; charset=utf-8');
include '../db.php';

$district = $_GET['district'] ?? '';

if (!empty($district)) {
    // Prepared Statement ব্যবহার করে সকল প্রয়োজনীয় কলামসহ সুরক্ষিত কোয়েরি
    $stmt = mysqli_prepare($conn, "
        SELECT 
            u.id, 
            u.full_name, 
            u.mobile_number, 
            u.username, 
            u.role, 
            u.batch_id, 
            u.photo,
            COUNT(CASE WHEN LOWER(pr.status) IN ('approved', 'success') AND COALESCE(pr.request_type, 'monthly') = 'monthly' THEN pr.id END) as total_tx,
            COALESCE(SUM(CASE WHEN LOWER(pr.status) IN ('approved', 'success') AND COALESCE(pr.request_type, 'monthly') = 'monthly' THEN pr.amount END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN LOWER(pr.status) IN ('approved', 'success') AND pr.request_type = 'donation' THEN pr.amount END), 0) as total_donation
        FROM users u
        LEFT JOIN payment_requests pr ON pr.user_id = u.id
        WHERE u.district = ?
        GROUP BY u.id, u.full_name, u.mobile_number, u.username, u.role, u.batch_id, u.photo
        ORDER BY total_paid DESC
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $district);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $members = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $members[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode([
            'status' => 'success',
            'data' => $members
        ]);
        exit();
    }
}

echo json_encode([
    'status' => 'error',
    'message' => 'Invalid district or query failed',
    'data' => []
]);
?>