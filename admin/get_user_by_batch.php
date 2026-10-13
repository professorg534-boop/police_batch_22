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

// fund_allocation.php এর admin-check এর সাথে সামঞ্জস্যপূর্ণ — একই রোল যাদের ঐ পেজে ঢোকার অনুমতি,
// তারাই এই এন্ডপয়েন্ট থেকে ডেটা পাবে (আগে এখানে ভুলবশত শুধু super_admin এ সীমাবদ্ধ ছিল)
$me_stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1");
if (!$me_stmt) {
    error_log("get_user_by_batch.php me_stmt prepare failed: " . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => 'Server error while checking permissions.']);
    exit();
}
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

// সার্চ ইনপুট — শুধু সংখ্যা গ্রহণযোগ্য (batch_id সবসময় ৬ ডিজিট)
$search = isset($_GET['id']) ? preg_replace('/\D/', '', $_GET['id']) : '';

if ($search === '' || strlen($search) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search term too short']);
    exit();
}

// prefix search — ইউজার টাইপ করার সাথে সাথে মিলে যাওয়া প্রথম বৈধ মেম্বার
$stmt = mysqli_prepare($conn, "SELECT full_name, batch_id, bp_number, division, district, mobile_number, blood_group, role, status, photo
    FROM users WHERE batch_id LIKE CONCAT(?, '%') AND LOWER(status) = 'active' ORDER BY batch_id ASC LIMIT 1");

if (!$stmt) {
    // prepare ব্যর্থ হলে সবচেয়ে বড় সম্ভাবনা — কোনো একটা কলাম (photo/blood_group/bp_number) টেবিলে নেই
    error_log("get_user_by_batch.php main query prepare failed: " . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => 'Server error: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $search);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($user) {
    echo json_encode(['success' => true, 'user' => $user]);
} else {
    // এই batch_id এর কোনো ইউজার আদৌ আছে কিনা চেক করা হচ্ছে (status যাই হোক না কেন) —
    // যাতে "কেউ নেই" আর "আছে কিন্তু pending/inactive" — এই দুটো আলাদা বার্তা দেখানো যায়
    $any_stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE batch_id LIKE CONCAT(?, '%') ORDER BY batch_id ASC LIMIT 1");
    mysqli_stmt_bind_param($any_stmt, "s", $search);
    mysqli_stmt_execute($any_stmt);
    $any_user = mysqli_fetch_assoc(mysqli_stmt_get_result($any_stmt));
    mysqli_stmt_close($any_stmt);

    if ($any_user) {
        echo json_encode(['success' => false, 'message' => 'This member exists but their status is "' . $any_user['status'] . '", not active.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No member found with this Batch ID.']);
    }
}