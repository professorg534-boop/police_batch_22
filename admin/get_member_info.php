<?php
session_start();
include '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// লগইন করা ইউজারের রোল চেক (fund_allocation.php / get_user_by_batch.php এর সাথে সামঞ্জস্যপূর্ণ)
$me_stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($me_stmt, "i", $user_id);
mysqli_stmt_execute($me_stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($me_stmt));
mysqli_stmt_close($me_stmt);

$my_role = strtolower(trim($me['role'] ?? ''));
$is_admin = in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin']);

if (!$me || !$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if (!isset($_GET['batch_id']) || trim($_GET['batch_id']) === '') {
    echo json_encode(['success' => false, 'message' => 'batch_id is required']);
    exit();
}

// batch_id দিয়েই খোঁজা হচ্ছে — 'username' কলাম তোমার টেবিলে নেই বলে আগে এটা কাজ করছিল না
$batch_id = preg_replace('/\D/', '', $_GET['batch_id']);

if ($batch_id === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid batch_id']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT full_name, photo, division, district, mobile_number, bp_number, blood_group, role, batch_id, status FROM users WHERE batch_id = ? LIMIT 1");

if (!$stmt) {
    error_log("get_member_info.php prepare failed: " . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => 'Server error: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $batch_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($row) {
    echo json_encode([
        'success' => true,
        'full_name' => $row['full_name'],
        'photo' => $row['photo'],
        'division' => $row['division'],
        'district' => $row['district'],
        'mobile_number' => $row['mobile_number'],
        'bp_number' => $row['bp_number'],
        'blood_group' => $row['blood_group'],
        'role' => $row['role'],
        'batch_id' => $row['batch_id'],
        'status' => $row['status'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}