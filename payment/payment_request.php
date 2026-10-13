<?php
session_start();

// ১. ডাটাবেস কানেকশন চেক
if (file_exists('db.php')) { 
    include 'db.php'; 
    $root = '';
} elseif (file_exists('../db.php')) { 
    include '../db.php'; 
    $root = '../';
} else { 
    die("Database connection file missing."); 
}

// prepare ফেইল করলে exception না ছুঁড়ে false ফেরত দিক
mysqli_report(MYSQLI_REPORT_OFF);

// --- অটোমেটিক ডাটাবেজ টেবিল আপডেট (Missing Columns Auto Fix) ---
$required_columns = [
    "request_type" => "VARCHAR(20) DEFAULT 'monthly'",
    "method"       => "VARCHAR(50) DEFAULT NULL",
    "trxid"        => "VARCHAR(100) DEFAULT NULL",
    "pay_month"    => "VARCHAR(50) DEFAULT NULL",
    "pay_year"     => "VARCHAR(10) DEFAULT NULL",
    "screenshot"   => "VARCHAR(255) DEFAULT NULL",
    "admin_id"     => "INT NULL DEFAULT NULL"
];

foreach ($required_columns as $col_name => $col_def) {
    $chk_col = mysqli_query($conn, "SHOW COLUMNS FROM payment_requests LIKE '$col_name'");
    if ($chk_col && mysqli_num_rows($chk_col) == 0) {
        mysqli_query($conn, "ALTER TABLE payment_requests ADD COLUMN `$col_name` $col_def");
    }
}

const MONTHLY_FEE = 50; // প্রতি মাসের ফি (৳)

// ২. সেশন সিকিউরিটি চেক
if (!isset($_SESSION['user_id'])) { 
    header("Location: {$root}login.php"); 
    exit(); 
}
$user_id = (int) $_SESSION['user_id'];

// ৩. ইউজারের তথ্য আহরণ
$me_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($me_stmt, "i", $user_id);
mysqli_stmt_execute($me_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($me_stmt));
mysqli_stmt_close($me_stmt);

if (!$user_data) { 
    session_destroy(); 
    header("Location: {$root}login.php"); 
    exit(); 
}

$my_district = $user_data['district'] ?? 'N/A';
$is_admin = in_array(strtolower(trim($user_data['role'] ?? '')), ['admin', 'super admin', 'super_admin', 'superadmin']);

$months_list = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
$allowed_years = [(string) date('Y'), (string) ((int) date('Y') - 1)];
$photo_base = file_exists('uploads') ? 'uploads/' : '../uploads/';

// অ্যাডমিন তালিকা
function load_admins($conn, $user_id, $district, $photo_base) {
    $list = [];
    foreach ([true, false] as $same_district) {
        $sql = "SELECT * FROM users WHERE LOWER(TRIM(role)) IN ('admin', 'super admin', 'super_admin', 'superadmin') AND id <> ?"
             . ($same_district ? " AND district = ?" : "") . " ORDER BY full_name";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) { continue; }
        if ($same_district) { mysqli_stmt_bind_param($stmt, "is", $user_id, $district); }
        else { mysqli_stmt_bind_param($stmt, "i", $user_id); }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($a = mysqli_fetch_assoc($res)) {
            $role = strtolower(trim((string) ($a['role'] ?? '')));
            $photo = '';
            if (!empty($a['photo']) && file_exists($photo_base . $a['photo'])) { $photo = $photo_base . rawurlencode($a['photo']); }
            $list[] = [
                'id' => (int) $a['id'],
                'name' => trim((string) ($a['full_name'] ?? '')) !== '' ? $a['full_name'] : 'Unknown',
                'role' => (strpos($role, 'super') !== false) ? 'Super Admin' : 'Admin',
                'phone' => trim((string) ($a['mobile_number'] ?? '')),
                'batch' => trim((string) ($a['batch_id'] ?? '')),
                'district' => trim((string) ($a['district'] ?? '')),
                'bp' => trim((string) ($a['bp_number'] ?? ($a['bp'] ?? ''))),
                'photo' => $photo,
                'local' => $same_district
            ];
        }
        mysqli_stmt_close($stmt);
        if (!empty($list)) { break; }
    }
    return $list;
}
$admins = load_admins($conn, $user_id, $my_district, $photo_base);
$admins_local = !empty($admins) && $admins[0]['local'];

// মেম্বারের মাসভিত্তিক স্ট্যাটাস
function load_month_status($conn, $user_id, $months_list) {
    $map = [];
    $stmt = mysqli_prepare($conn, "SELECT pay_month, pay_year, status FROM payment_requests WHERE user_id = ? AND COALESCE(request_type, 'monthly') = 'monthly'");
    if (!$stmt) { return $map; }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $canon = [];
    foreach ($months_list as $m) { $canon[strtolower($m)] = $m; }

    while ($r = mysqli_fetch_assoc($res)) {
        $y = trim((string) $r['pay_year']);
        $s = strtolower(trim((string) $r['status']));
        $state = in_array($s, ['approved', 'success', 'paid'], true) ? 'paid' : ($s === 'pending' ? 'pending' : '');
        if ($state === '' || $y === '') { continue; }
        foreach (explode(',', (string) $r['pay_month']) as $m) {
            $k = strtolower(trim($m));
            if (!isset($canon[$k])) { continue; }
            $mn = $canon[$k];
            if (($map[$y][$mn] ?? '') !== 'paid') { $map[$y][$mn] = $state; }
        }
    }
    mysqli_stmt_close($stmt);
    return $map;
}

$msg = "";
$msg_type = "";
$old = ['type' => 'monthly', 'year' => $allowed_years[0], 'months' => [], 'amount' => '', 'method' => '', 'trxid' => '', 'admin' => 0];

if (!empty($_SESSION['pay_flash'])) {
    $msg = $_SESSION['pay_flash']['msg'];
    $msg_type = $_SESSION['pay_flash']['type'];
    unset($_SESSION['pay_flash']);
}

$month_status = load_month_status($conn, $user_id, $months_list);

// ৪. পেমেন্ট রিকোয়েস্ট প্রসেসিং
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $req_type = (($_POST['request_type'] ?? 'monthly') === 'donation') ? 'donation' : 'monthly';
    $method = trim($_POST['method'] ?? '');
    $trxid = trim($_POST['trxid'] ?? '');
    $admin_id = (int) ($_POST['admin_id'] ?? 0);
    $pay_year = trim($_POST['pay_year'] ?? $allowed_years[0]);
    if (!in_array($pay_year, $allowed_years, true)) { $pay_year = $allowed_years[0]; }

    $sel_months = [];
    foreach ((array) ($_POST['pay_month'] ?? []) as $m) {
        $m = trim((string) $m);
        if (in_array($m, $months_list, true) && !in_array($m, $sel_months, true)) { $sel_months[] = $m; }
    }

    $rows = [];
    if ($req_type === 'monthly') {
        foreach ($sel_months as $m) { $rows[] = [$m, (float) MONTHLY_FEE]; }
        $amount = count($sel_months) * MONTHLY_FEE;
    } else {
        $sel_months = [];
        $amount = floatval($_POST['amount'] ?? 0);
        $rows[] = ['', $amount];
    }

    $old = ['type' => $req_type, 'year' => $pay_year, 'months' => $sel_months, 'amount' => ($req_type === 'donation' && $amount > 0) ? (string) $amount : '', 'method' => $method, 'trxid' => $trxid, 'admin' => $admin_id];

    $locked = [];
    foreach ($sel_months as $m) {
        if (isset($month_status[$pay_year][$m])) {
            $locked[] = $m . ' (' . ($month_status[$pay_year][$m] === 'paid' ? 'Paid' : 'Pending') . ')';
        }
    }

    if ($req_type === 'monthly' && empty($sel_months)) {
        $msg = "Please select at least one month.";
        $msg_type = "error";
    } elseif ($amount <= 0) {
        $msg = "Please enter a valid amount greater than 0.";
        $msg_type = "error";
    } elseif (!empty($admins) && !in_array($admin_id, array_column($admins, 'id'), true)) {
        $msg = "Please select an admin.";
        $msg_type = "error";
    } elseif (empty($method)) {
        $msg = "Please select a payment method.";
        $msg_type = "error";
    } elseif (empty($trxid)) {
        $msg = "Transaction ID (TrxID) is required.";
        $msg_type = "error";
    } elseif (!empty($locked)) {
        $msg = "These months are already submitted for {$pay_year}: " . implode(', ', $locked) . ". Please deselect them.";
        $msg_type = "error";
    } else {
        $screenshot_filename = "";
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['screenshot']['tmp_name'];
            $file_name = $_FILES['screenshot']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed_exts)) {
                $new_filename = 'pay_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_dir = file_exists('uploads') ? 'uploads/' : '../uploads/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                    $screenshot_filename = $new_filename;
                }
            }
        }

        mysqli_begin_transaction($conn);
        $cols = "user_id, district, amount, status, request_type, method, trxid, pay_month, pay_year, screenshot, created_at, admin_id";
        $vals = "?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW(), ?";
        $stmt = mysqli_prepare($conn, "INSERT INTO payment_requests ({$cols}) VALUES ({$vals})");

        if ($stmt) {
            $row_month = '';
            $row_amount = 0.0;
            mysqli_stmt_bind_param($stmt, "isdssssssi", $user_id, $my_district, $row_amount, $req_type, $method, $trxid, $row_month, $pay_year, $screenshot_filename, $admin_id);

            $all_ok = true;
            foreach ($rows as $r) {
                $row_month = $r[0];
                $row_amount = (float) $r[1];
                if (!mysqli_stmt_execute($stmt)) {
                    $all_ok = false;
                    error_log("payment_request.php insert failed: " . mysqli_stmt_error($stmt));
                    break;
                }
            }
            mysqli_stmt_close($stmt);

            if ($all_ok) {
                mysqli_commit($conn);
                $detail = ($req_type === 'monthly') ? " (" . count($rows) . " month" . (count($rows) > 1 ? "s" : "") . " · ৳" . number_format($amount) . ")" : "";
                $_SESSION['pay_flash'] = ['type' => 'success', 'msg' => "Payment request submitted successfully! Pending admin approval." . $detail];
                header("Location: " . basename($_SERVER['PHP_SELF']));
                exit();
            }
            mysqli_rollback($conn);
            $msg = "Failed to submit payment request. Please try again.";
            $msg_type = "error";
        } else {
            $db_err = mysqli_error($conn);
            mysqli_rollback($conn);
            $msg = "Database query error: " . $db_err;
            $msg_type = "error";
        }
    }
}

$user_photo_path = 'https://cdn-icons-png.flaticon.com/512/6522/6522516.png';
if (!empty($user_data['photo'])) {
    if (file_exists('uploads/' . $user_data['photo'])) {
        $user_photo_path = 'uploads/' . htmlspecialchars($user_data['photo']);
    } elseif (file_exists('../uploads/' . $user_data['photo'])) {
        $user_photo_path = '../uploads/' . htmlspecialchars($user_data['photo']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPHA HQ | Payment Deposit</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --ink: #eaf1ff; --muted: #8ea3cc; --accent: #2f6bff; --accent-2: #38bdf8; --accent-soft: rgba(47,107,255,.18);
            --card-bg: rgba(12, 22, 52, 0.65); --line: rgba(130,170,255,.16);
            --ok: #34d399; --ok-bg: rgba(52,211,153,.14); --wait: #fbbf24; --wait-bg: rgba(251,191,36,.14);
            --danger: #f87171; --danger-bg: rgba(248,113,113,.14);
            --display: 'Sora', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { min-height: 100vh; color: var(--ink); font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(900px 520px at 90% -8%, rgba(47,107,255,.42), transparent 60%),
                radial-gradient(700px 520px at -12% 32%, rgba(56,189,248,.16), transparent 60%),
                linear-gradient(180deg, #061030 0%, #030817 100%) fixed; }

        .wrap { max-width: 680px; margin: 0 auto; padding: 22px 12px 120px; }

        .hero { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 22px; padding: 0 4px; }
        .brand { display: flex; align-items: center; gap: 8px; font: 700 13px var(--display); color: var(--accent-2); margin-bottom: 8px; }
        .mark { width: 26px; height: 26px; border-radius: 9px; display: grid; place-items: center; color: #fff; font: 700 15px var(--display);
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 6px 18px rgba(47,107,255,.55); }
        .hero h1 { font: 700 24px/1.2 var(--display); letter-spacing: -.5px; }
        .avatar { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid rgba(96,165,250,.7); box-shadow: 0 0 0 4px rgba(47,107,255,.18), 0 10px 26px rgba(47,107,255,.35); }

        .banner { padding: 13px 16px; border-radius: 16px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border: 1px solid var(--line); line-height: 1.45; }
        .banner.success { background: var(--ok-bg); color: var(--ok); border-color: rgba(52,211,153,.3); }
        .banner.error { background: var(--danger-bg); color: var(--danger); border-color: rgba(248,113,113,.3); }

        .card { position: relative; overflow: hidden; background: linear-gradient(180deg, rgba(255,255,255,.075), rgba(255,255,255,.03)); border: 1px solid var(--line);
            backdrop-filter: blur(16px); border-radius: 24px; padding: 22px 18px; margin-bottom: 20px;
            box-shadow: 0 20px 45px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.07); }
        .card.pay::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 2px; background: linear-gradient(90deg, transparent, var(--accent-2), transparent); opacity: .8; }
        .card h3 { font: 700 15px var(--display); margin-bottom: 20px; display: flex; align-items: center; gap: 9px; color: #fff; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .lbl-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
        .lbl-row label { margin: 0; }
        .rate { font-size: 11px; font-weight: 800; color: var(--accent-2); background: var(--accent-soft); padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
        .hint { margin-top: 7px; font-size: 11.5px; font-weight: 600; color: var(--muted); }

        input[type="text"], input[type="number"], select {
            width: 100%; background: rgba(0,0,0,.35); border: 1px solid var(--line); border-radius: 14px;
            padding: 13px 14px; color: #fff; font: 600 14px 'Manrope', sans-serif; outline: none; transition: .25s;
        }
        input:focus, select:focus { border-color: var(--accent-2); box-shadow: 0 0 0 3px rgba(56,189,248,.15); }
        input::placeholder { color: #5a6e94; font-weight: 500; }
        select { appearance: none; -webkit-appearance: none; cursor: pointer; padding-right: 40px;
            background: rgba(0,0,0,.35) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%238ea3cc' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>") no-repeat right 15px center; }
        select option { background: #081232; color: #fff; }
        #amountIn { font: 700 18px var(--display); color: var(--accent-2); }
        #amountIn[readonly] { background: rgba(47,107,255,.10); border-color: rgba(56,189,248,.28); cursor: default; }

        .type-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 22px; }
        .type-btn { background: rgba(0,0,0,.3); border: 1px solid var(--line); border-radius: 14px; padding: 13px 10px; text-align: center; cursor: pointer;
            transition: .25s; color: var(--muted); font: 700 13px 'Manrope', sans-serif; }
        .type-btn i { margin-right: 7px; }
        .type-btn:hover { border-color: rgba(56,189,248,.5); color: #fff; }
        .type-btn.active { background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; border-color: var(--accent-2); box-shadow: 0 8px 20px rgba(47,107,255,.4); }

        .legend { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-bottom: 12px; font-size: 10.5px; font-weight: 700; color: var(--muted); }
        .legend span { display: inline-flex; align-items: center; gap: 6px; }
        .dot { width: 9px; height: 9px; border-radius: 3px; display: inline-block; border: 1px solid var(--line); }
        .dot.sel { background: var(--accent-2); border-color: var(--accent-2); }
        .dot.pen { background: var(--wait); border-color: var(--wait); }
        .dot.paid { background: var(--ok); border-color: var(--ok); }

        .months-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        .month-item { position: relative; min-width: 0; }
        .month-item input { position: absolute; opacity: 0; pointer-events: none; }
        .m-tile { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; width: 100%; min-height: 60px; padding: 8px 2px;
            border-radius: 14px; border: 1px solid var(--line); background: rgba(255,255,255,.04); color: var(--muted); cursor: pointer; transition: .2s;
            text-align: center; user-select: none; font-family: 'Manrope', sans-serif; letter-spacing: 0; text-transform: none; margin: 0; }
        .m-tile .mn { font: 700 12px var(--display); color: inherit; }
        .m-tile .ms { font-size: 10px; font-weight: 700; opacity: .85; display: inline-flex; align-items: center; gap: 4px; }
        .m-tile .full { display: none; }
        .month-item label.m-tile:hover { border-color: rgba(56,189,248,.55); color: #fff; transform: translateY(-1px); }
        .month-item input:checked + .m-tile { background: linear-gradient(135deg, rgba(47,107,255,.45), rgba(56,189,248,.28)); border-color: var(--accent-2); color: #fff; box-shadow: 0 8px 20px rgba(47,107,255,.35); }
        .month-item input:focus-visible + .m-tile { outline: 2px solid var(--accent-2); outline-offset: 2px; }
        .m-tile.paid { background: var(--ok-bg); border-color: rgba(52,211,153,.35); color: var(--ok); cursor: not-allowed; }
        .m-tile.pending { background: var(--wait-bg); border-color: rgba(251,191,36,.35); color: var(--wait); cursor: not-allowed; }

        .summary { background: rgba(255,255,255,.04); border: 1px solid var(--line); border-radius: 16px; padding: 12px 14px; margin-bottom: 20px; }
        .s-row { display: flex; justify-content: space-between; gap: 12px; font-size: 12px; font-weight: 600; color: var(--muted); padding: 4px 0; }
        .s-row b { color: #fff; font-weight: 700; text-align: right; word-break: break-word; }

        .admin-card { margin-top: 12px; border: 1px solid rgba(56,189,248,.28); border-radius: 18px; padding: 14px; background: linear-gradient(135deg, rgba(47,107,255,.16), rgba(56,189,248,.06)); box-shadow: inset 0 1px 0 rgba(255,255,255,.06); }
        .admin-card[hidden] { display: none; }
        .ac-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .ac-av { width: 48px; height: 48px; border-radius: 14px; overflow: hidden; flex-shrink: 0; display: grid; place-items: center; font: 700 18px var(--display); color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 6px 16px rgba(47,107,255,.4); }
        .ac-av img { width: 100%; height: 100%; object-fit: cover; }
        .ac-id { min-width: 0; }
        .ac-id b { display: block; font: 700 15px var(--display); color: #fff; word-break: break-word; }
        .ac-role { display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; font-size: 10.5px; font-weight: 800; color: var(--accent-2); background: var(--accent-soft); padding: 3px 9px; border-radius: 999px; text-transform: uppercase; letter-spacing: .4px; }
        .ac-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .ai { background: rgba(0,0,0,.25); border: 1px solid var(--line); border-radius: 12px; padding: 8px 10px; min-width: 0; }
        .ai span { display: block; font-size: 9.5px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
        .ai b { font-size: 13px; font-weight: 700; color: #fff; word-break: break-word; }
        .ac-actions { display: flex; gap: 8px; margin-top: 10px; }
        .ac-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 10px; border-radius: 12px; border: 1px solid var(--line); background: rgba(255,255,255,.05);
            color: var(--accent-2); font: 700 12px 'Manrope', sans-serif; text-decoration: none; cursor: pointer; transition: .2s; }
        .ac-btn:hover { background: var(--accent-soft); color: #fff; border-color: var(--accent-2); }

        .file-box { border: 1.5px dashed var(--line); border-radius: 16px; padding: 22px 16px; text-align: center; background: rgba(0,0,0,.2); cursor: pointer; transition: .25s; position: relative; }
        .file-box:hover { border-color: var(--accent-2); background: rgba(47,107,255,.06); }
        .file-box i { font-size: 24px; color: var(--accent-2); margin-bottom: 8px; }
        .file-box p { font-size: 12px; color: var(--muted); font-weight: 600; }
        .file-box input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }

        .btn-submit { width: 100%; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; border: none; padding: 15px; border-radius: 16px;
            font: 700 14px var(--display); letter-spacing: .4px; cursor: pointer; transition: .25s; box-shadow: 0 12px 28px rgba(47,107,255,.45); }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); filter: brightness(1.1); }
        .btn-submit:disabled { opacity: .75; cursor: wait; }

        .history-link { display: flex; align-items: center; justify-content: space-between; text-decoration: none; color: var(--accent-2); font-weight: 700; font-size: 13px; padding: 16px 18px; }
        .history-link:hover { border-color: rgba(56,189,248,.45); }

        .toast { position: fixed; left: 50%; bottom: 100px; transform: translate(-50%, 16px); opacity: 0; pointer-events: none; z-index: 80; max-width: calc(100% - 32px);
            background: rgba(8,18,52,.95); backdrop-filter: blur(16px); border: 1px solid var(--line); border-radius: 14px; padding: 11px 16px;
            font-size: 12.5px; font-weight: 700; display: flex; align-items: center; gap: 9px; transition: .3s; box-shadow: 0 14px 40px rgba(0,0,0,.5); }
        .toast.show { opacity: 1; transform: translate(-50%, 0); }
        .toast.ok { border-color: rgba(52,211,153,.4); color: var(--ok); }
        .toast.warn { border-color: rgba(251,191,36,.4); color: var(--wait); }

        .bnav { position: fixed; left: 50%; transform: translateX(-50%); bottom: 12px; width: calc(100% - 24px); max-width: 520px; z-index: 50;
            display: flex; justify-content: space-around; gap: 2px; background: rgba(8,18,52,.72); backdrop-filter: blur(22px); border: 1px solid var(--line);
            border-radius: 26px; padding: 7px; box-shadow: 0 18px 50px rgba(0,0,0,.55); }
        .bnav a { flex: 1; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 8px 4px; border-radius: 19px; color: var(--muted); font-size: 11px; font-weight: 700; transition: .3s; }
        .bnav a i { font-size: 17px; }
        .bnav a:hover { background: var(--accent-soft); color: #fff; }
        .bnav a.active { color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 8px 22px rgba(47,107,255,.6); }

        @media (max-width: 340px) { .months-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (min-width: 560px) {
            .months-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
            .m-tile .short { display: none; } .m-tile .full { display: inline; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>

<main class="wrap">

    <header class="hero">
        <div>
            <div class="brand"><span class="mark">α</span>Alpha HQ</div>
            <h1>Deposit Funds</h1>
        </div>
        <img class="avatar" src="<?= $user_photo_path ?>" alt="Avatar">
    </header>

    <?php if (!empty($msg)): ?>
        <div class="banner <?= htmlspecialchars($msg_type) ?>">
            <i class="fas fa-<?= $msg_type === 'success' ? 'circle-check' : 'triangle-exclamation' ?>"></i>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endif; ?>

    <!-- History Link Card -->
    <a href="member_payments.php" class="card history-link">
        <span><i class="fas fa-receipt" style="margin-right: 8px;"></i> View Transaction History</span>
        <i class="fas fa-chevron-right"></i>
    </a>

    <form method="POST" enctype="multipart/form-data" id="payForm">
        <input type="hidden" name="submit_payment" value="1">

        <div class="card pay">
            <h3><i class="fas fa-wallet" style="color:var(--accent-2)"></i> Payment Details</h3>

            <!-- Deposit Type -->
            <label>Request Type</label>
            <div class="type-grid">
                <button type="button" class="type-btn active" id="btnMonthly" onclick="setType('monthly')"><i class="fas fa-calendar-check"></i>Monthly Deposit</button>
                <button type="button" class="type-btn" id="btnDonation" onclick="setType('donation')"><i class="fas fa-hand-holding-heart"></i>Special Donation</button>
            </div>
            <input type="hidden" name="request_type" id="requestType" value="monthly">

            <!-- Month & Year Selection -->
            <div id="monthSec">
                <div class="form-group">
                    <label for="payYear">Select Billing Year</label>
                    <select name="pay_year" id="payYear">
                        <?php foreach ($allowed_years as $y): ?>
                            <option value="<?= $y ?>" <?= $old['year'] === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <div class="lbl-row">
                        <label>Select Month(s)</label>
                        <span class="rate">৳<?= MONTHLY_FEE ?> / month</span>
                    </div>
                    <div class="legend">
                        <span><i class="dot"></i>Available</span>
                        <span><i class="dot sel"></i>Selected</span>
                        <span><i class="dot pen"></i>Pending</span>
                        <span><i class="dot paid"></i>Paid</span>
                    </div>
                    <div class="months-grid" id="monthsGrid"></div>
                </div>
            </div>

            <!-- Calculation summary (monthly only) -->
            <div class="summary" id="summary">
                <div class="s-row"><span>Selected months</span><b id="sumMonths">None selected</b></div>
                <div class="s-row"><span>Calculation</span><b id="sumCalc">0 × ৳<?= MONTHLY_FEE ?></b></div>
            </div>

            <!-- Amount -->
            <div class="form-group">
                <label for="amountIn">Deposit Amount (৳)</label>
                <input type="number" name="amount" id="amountIn" placeholder="e.g. 500" step="any" min="1">
                <div class="hint" id="amtHint"></div>
            </div>

            <?php if (!empty($admins)): ?>
            <!-- Admin Selection -->
            <div class="form-group">
                <label for="adminSel">Select Admin</label>
                <select name="admin_id" id="adminSel" required>
                    <option value="" disabled <?= empty($old['admin']) ? 'selected' : '' ?>>Choose Admin...</option>
                    <?php foreach ($admins as $ad): ?>
                        <option value="<?= $ad['id'] ?>" <?= (int) $old['admin'] === $ad['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ad['name']) ?><?= $ad['batch'] !== '' ? ' · #' . htmlspecialchars($ad['batch']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$admins_local): ?><div class="hint">No admin found in your district, so all admins are listed.</div><?php endif; ?>
                <div class="admin-card" id="adminCard" hidden></div>
            </div>
            <?php endif; ?>

            <!-- Payment Method -->
            <div class="form-group">
                <label for="method">Payment Method</label>
                <select name="method" id="method" required>
                    <option value="" disabled <?= $old['method'] === '' ? 'selected' : '' ?>>Choose Method...</option>
                    <?php foreach (['bKash' => 'bKash', 'Nagad' => 'Nagad', 'Rocket' => 'Rocket', 'Bank' => 'Bank Transfer'] as $mv => $ml): ?>
                        <option value="<?= $mv ?>" <?= $old['method'] === $mv ? 'selected' : '' ?>><?= $ml ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Transaction ID -->
            <div class="form-group">
                <label for="trxid">Transaction ID (TrxID)</label>
                <input type="text" name="trxid" id="trxid" placeholder="e.g. BX9823KJ10" value="<?= htmlspecialchars($old['trxid']) ?>" required>
            </div>

            <!-- Screenshot Upload -->
            <div class="form-group">
                <label>Payment Proof (Screenshot)</label>
                <div class="file-box">
                    <i class="fas fa-cloud-arrow-up"></i>
                    <p id="fileLabel">Click to upload image receipt (Optional)</p>
                    <input type="file" name="screenshot" accept="image/*" onchange="showFileName(this)">
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                Submit Payment Request<span id="btnTotal"></span> <i class="fas fa-paper-plane" style="margin-left: 6px;"></i>
            </button>

        </div>

    </form>

</main>

<div class="toast" id="toast" role="status"></div>

<!-- Bottom Fixed Navigation Bar -->
<nav class="bnav" aria-label="Main Navigation">
    <a href="<?= $root ?>dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
    <a href="district_monitor.php"><i class="fa-solid fa-compass"></i>District</a>
    <a href="payment_request.php" class="active" aria-current="page"><i class="fa-solid fa-wallet"></i>Deposits</a>
    <?php if ($is_admin): ?>
        <a href="district_administry.php"><i class="fa-solid fa-shield-halved"></i>Administry</a>
    <?php endif; ?>
</nav>

<script>
const FEE = <?= MONTHLY_FEE ?>;
const MONTHS = <?= json_encode($months_list) ?>;
const STATUS = <?= json_encode($month_status, JSON_FORCE_OBJECT) ?>;
const OLD = <?= json_encode($old, JSON_UNESCAPED_UNICODE) ?>;
const ADMINS = <?= json_encode($admins, JSON_UNESCAPED_UNICODE) ?>;
const $ = id => document.getElementById(id);

let type = null, donationAmt = OLD.type === 'donation' ? OLD.amount : '', toastTimer;
const BTN_HTML = $('submitBtn').innerHTML;

function selectedMonths() {
    return Array.from(document.querySelectorAll('#monthsGrid input:checked')).map(i => i.value);
}

function toast(msg, kind) {
    const t = $('toast');
    t.className = 'toast show ' + (kind || '');
    t.innerHTML = '<i class="fas fa-' + (kind === 'ok' ? 'circle-check' : 'circle-info') + '"></i><span>' + msg + '</span>';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

function updateTotal() {
    if (type !== 'monthly') return;
    const sel = selectedMonths(), total = sel.length * FEE;
    $('amountIn').value = total || '';
    $('sumMonths').textContent = sel.length ? sel.map(m => m.slice(0, 3)).join(', ') : 'None selected';
    $('sumCalc').textContent = sel.length + ' × ৳' + FEE;
    $('btnTotal').textContent = total ? ' · ৳' + total.toLocaleString() : '';
}

function renderMonths(selected) {
    const year = $('payYear').value, st = STATUS[year] || {}, pick = new Set(selected || []);
    $('monthsGrid').innerHTML = MONTHS.map(m => {
        const name = '<span class="mn"><span class="full">' + m + '</span><span class="short">' + m.slice(0, 3) + '</span></span>';
        const s = st[m];
        if (s === 'paid' || s === 'pending') {
            return '<div class="month-item"><button type="button" class="m-tile ' + s + '" data-m="' + m + '" data-s="' + s + '" aria-disabled="true">' + name +
                   '<span class="ms"><i class="fas fa-' + (s === 'paid' ? 'circle-check' : 'clock') + '"></i>' + (s === 'paid' ? 'Paid' : 'Pending') + '</span></button></div>';
        }
        return '<div class="month-item"><input type="checkbox" name="pay_month[]" value="' + m + '" id="m_' + m + '"' + (pick.has(m) ? ' checked' : '') + '>' +
               '<label class="m-tile" for="m_' + m + '">' + name + '<span class="ms">৳' + FEE + '</span></label></div>';
    }).join('');
    updateTotal();
}

function setType(t) {
    const a = $('amountIn');
    if (type === 'donation') donationAmt = a.value;
    type = t;
    $('requestType').value = t;
    $('btnMonthly').classList.toggle('active', t === 'monthly');
    $('btnDonation').classList.toggle('active', t === 'donation');
    $('monthSec').style.display = $('summary').style.display = (t === 'monthly') ? 'block' : 'none';
    if (t === 'monthly') {
        a.readOnly = true; a.required = false; a.placeholder = 'Select month(s) above';
        $('amtHint').textContent = 'Auto-calculated at ৳' + FEE + ' per month.';
        updateTotal();
    } else {
        a.readOnly = false; a.required = true; a.placeholder = 'e.g. 500'; a.value = donationAmt;
        $('amtHint').textContent = 'Enter the amount you wish to donate.';
        $('btnTotal').textContent = '';
    }
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : s;
    return d.innerHTML;
}

function renderAdmin() {
    const sel = $('adminSel'), card = $('adminCard');
    if (!sel || !card) return;
    const a = ADMINS.find(x => String(x.id) === sel.value);
    if (!a) { card.hidden = true; card.innerHTML = ''; return; }
    const av = a.photo ? '<img src="' + esc(a.photo) + '" alt="">' : esc((a.name || 'A').charAt(0).toUpperCase());
    const cell = (k, v) => '<div class="ai"><span>' + k + '</span><b>' + (v ? esc(v) : '—') + '</b></div>';
    card.innerHTML =
        '<div class="ac-top"><div class="ac-av">' + av + '</div><div class="ac-id"><b>' + esc(a.name) + '</b>' +
        '<span class="ac-role"><i class="fas fa-shield-halved"></i>' + esc(a.role) + '</span></div></div>' +
        '<div class="ac-grid">' + cell('Contact', a.phone) + cell('Batch ID', a.batch ? '#' + a.batch : '') + cell('District', a.district) + (a.bp ? cell('BP Number', a.bp) : '') + '</div>' +
        (a.phone ? '<div class="ac-actions"><a class="ac-btn" href="tel:' + esc(a.phone.replace(/[^0-9+]/g, '')) + '"><i class="fas fa-phone"></i>Call</a>' +
                   '<button type="button" class="ac-btn" id="copyPhone"><i class="fas fa-copy"></i>Copy number</button></div>' : '');
    card.hidden = false;
    const cp = $('copyPhone');
    if (cp) cp.onclick = () => {
        const done = () => toast('Contact number copied.', 'ok');
        if (navigator.clipboard) { navigator.clipboard.writeText(a.phone).then(done, () => toast(esc(a.phone), 'warn')); }
        else { toast(esc(a.phone), 'warn'); }
    };
}

function showFileName(input) {
    const label = $('fileLabel');
    if (input.files && input.files[0]) {
        label.innerText = 'Selected: ' + input.files[0].name;
        label.style.color = '#34d399';
    } else {
        label.innerText = 'Click to upload image receipt (Optional)';
        label.style.color = '#8ea3cc';
    }
}

$('monthsGrid').addEventListener('click', e => {
    const t = e.target.closest('.m-tile[data-s]');
    if (!t) return;
    const paid = t.dataset.s === 'paid';
    toast(t.dataset.m + ' ' + $('payYear').value + (paid ? ' is already paid.' : ' is pending admin approval.'), paid ? 'ok' : 'warn');
});
$('monthsGrid').addEventListener('change', updateTotal);
if ($('adminSel')) $('adminSel').addEventListener('change', renderAdmin);
$('payYear').addEventListener('change', () => renderMonths([]));

$('payForm').addEventListener('submit', e => {
    if (type === 'monthly' && selectedMonths().length === 0) {
        e.preventDefault();
        toast('Select at least one month to continue.', 'warn');
        return;
    }
    const b = $('submitBtn');
    b.disabled = true;
    b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
});

window.addEventListener('DOMContentLoaded', () => {
    setType(OLD.type);
    renderMonths(OLD.months);
    renderAdmin();
});
</script>

</body>
</html>