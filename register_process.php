<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ইনপুট রিসিভ ও isset চেক
    $full_name  = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $bp_number  = isset($_POST['bp_number']) ? trim($_POST['bp_number']) : '';
    $mobile     = isset($_POST['mobile_number']) ? trim($_POST['mobile_number']) : '';
    $gender     = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $division   = isset($_POST['division']) ? trim($_POST['division']) : '';
    $district   = isset($_POST['district']) ? trim($_POST['district']) : '';
    $batch_id   = isset($_POST['batch_id']) ? trim($_POST['batch_id']) : '';
    $blood_group = isset($_POST['blood_group']) ? trim($_POST['blood_group']) : '';
    $password   = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_pw = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // ---- Server-side ভ্যালিডেশন (ক্লায়েন্ট সাইড bypass করলেও যেন আটকায়) ----
    $errors = [];

    if ($full_name === '') $errors[] = "Full name is required.";
    if ($bp_number === '') $errors[] = "BP number is required.";
    if (!preg_match('/^01[3-9]\d{8}$/', $mobile)) $errors[] = "Invalid mobile number format.";
    if (!in_array($gender, ['Male', 'Female'])) $errors[] = "Invalid gender selection.";
    if ($division === '') $errors[] = "Division is required.";
    if ($district === '') $errors[] = "District is required.";
    if (!preg_match('/^\d{6}$/', $batch_id)) $errors[] = "Batch ID must be exactly 6 digits.";
    if (!in_array($blood_group, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])) $errors[] = "Invalid blood group selection.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

    if (!empty($errors)) {
        renderErrorPage("Validation Failed", implode(' ', $errors));
    }

    // ১. পাসওয়ার্ড ম্যাচ চেক
    if ($password !== $confirm_pw) {
        renderErrorPage("Security Breach!", "Passwords do not match. Please ensure both secret keys are identical for personnel verification.");
    }

    // ২. ব্যাচ আইডি ডুপ্লিকেট চেক (Prepared Statement)
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE batch_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check_stmt, "s", $batch_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);

    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        mysqli_stmt_close($check_stmt);
        renderErrorPage("Identity Conflict!", "The Batch ID (" . htmlspecialchars($batch_id) . ") is already registered. Please use your unique identity.", true);
    }
    mysqli_stmt_close($check_stmt);

    // ৩. প্রথম ইউজার চেক (Admin/Member Assignment)
    $check_table_empty = mysqli_query($conn, "SELECT id FROM users LIMIT 1");
    if (mysqli_num_rows($check_table_empty) == 0) {
        $role = 'super_admin';
        $status = 'active';
    } else {
        $role = 'member';
        $status = 'pending';
    }

    // ৪. পাসওয়ার্ড হ্যাশ করা — এটাই সবচেয়ে গুরুত্বপূর্ণ অংশ
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ৫. ডাটাবেজে ইনসার্ট (Prepared Statement — SQL Injection প্রুফ)
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, bp_number, mobile_number, gender, division, district, batch_id, blood_group, password, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param(
        $insert_stmt,
        "sssssssssss",
        $full_name, $bp_number, $mobile, $gender, $division, $district, $batch_id, $blood_group, $hashed_password, $role, $status
    );

    if (mysqli_stmt_execute($insert_stmt)) {
        mysqli_stmt_close($insert_stmt);
        renderSuccessPage($role, [
            'full_name'    => $full_name,
            'bp_number'    => $bp_number,
            'mobile_number'=> $mobile,
            'gender'       => $gender,
            'division'     => $division,
            'district'     => $district,
            'batch_id'     => $batch_id,
            'blood_group'  => $blood_group,
            'status'       => $status,
        ]);
    } else {
        mysqli_stmt_close($insert_stmt);
        // ডাটাবেজ এরর ইউজারকে সরাসরি না দেখিয়ে লগ ফাইলে রাখা হচ্ছে
        error_log("Registration insert failed: " . mysqli_error($conn));
        renderErrorPage("Registration Failed", "Something went wrong while processing your request. Please try again later or contact the admin.");
    }
}

/**
 * এরর পেজ দেখানোর জন্য রিইউজেবল ফাংশন
 */
function renderErrorPage($title, $message, $isWarning = false) {
    $color = $isWarning ? '#fbbf24' : '#f87171';
    $colorName = $isWarning ? 'warning' : 'danger';
    $icon = $isWarning ? 'fa-id-card-clip' : 'fa-shield-slash';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> | Batch 22</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root { --<?= $colorName ?>: <?= $color ?>; --bg: #0f172a; --card: #1e293b; }
            * { box-sizing: border-box; }
            body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; padding: 20px; color: white; }
            .error-card { background: var(--card); padding: 40px; border-radius: 28px; width: 100%; max-width: 400px; text-align: center; border: 1px solid <?= $color ?>33; }
            .icon-box { width: 70px; height: 70px; background: <?= $color ?>1a; border-radius: 20px; display: flex; justify-content: center; align-items: center; margin: 0 auto 20px; border: 2px solid var(--<?= $colorName ?>); transform: rotate(45deg); }
            .icon-box i { font-size: 28px; color: var(--<?= $colorName ?>); transform: rotate(-45deg); }
            h2 { font-size: 20px; font-weight: 800; margin-bottom: 10px; color: var(--<?= $colorName ?>); }
            p { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 25px; }
            .back-btn { display: inline-block; padding: 12px 24px; background: <?= $color ?>1a; color: var(--<?= $colorName ?>); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 14px; border: 1px solid var(--<?= $colorName ?>); transition: 0.3s; }
            .back-btn:hover { background: var(--<?= $colorName ?>); color: #fff; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="icon-box"><i class="fas <?= $icon ?>"></i></div>
            <h2><?= htmlspecialchars($title) ?></h2>
            <p><?= $message /* উপরে htmlspecialchars করা batch_id সহ আগে থেকেই সেফ */ ?></p>
            <a href="javascript:history.back()" class="back-btn"><i class="fas fa-arrow-left"></i> Try Again</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/**
 * সাকসেস পেজ দেখানোর জন্য ফাংশন — পাসওয়ার্ড ছাড়া বাকি সব তথ্যের সামারি দেখাবে
 */
function renderSuccessPage($role, $data) {
    $rows = [
        ['fa-user',        'Full Name',     $data['full_name']],
        ['fa-id-badge',    'BP Number',     $data['bp_number']],
        ['fa-phone',       'Mobile Number', $data['mobile_number']],
        ['fa-venus-mars',  'Gender',        $data['gender']],
        ['fa-map-marker-alt', 'Division',   $data['division']],
        ['fa-city',        'District',      $data['district']],
        ['fa-hashtag',     'Batch Identity',$data['batch_id']],
        ['fa-tint',        'Blood Group',   $data['blood_group']],
        ['fa-circle-info', 'Status',        ucfirst($data['status'])],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Success | Batch 22</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root { --primary: #6366f1; --bg: #0f172a; --card: #1e293b; }
            * { box-sizing: border-box; }
            body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; padding: 20px; color: white; }
            .success-card { background: var(--card); padding: 45px 35px; border-radius: 32px; width: 100%; max-width: 480px; text-align: center; border: 1px solid #334155; position: relative; animation: slideUp 0.6s ease-out; }
            @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
            .icon-box { width: 70px; height: 70px; background: rgba(99, 102, 241, 0.1); border-radius: 22px; display: flex; justify-content: center; align-items: center; margin: 0 auto 20px; border: 2px solid var(--primary); transform: rotate(45deg); }
            .icon-box i { font-size: 28px; color: var(--primary); transform: rotate(-45deg); }
            h2 { font-size: 22px; font-weight: 800; margin-bottom: 10px; color: #fff; }
            p.subtitle { color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 25px; }

            .summary-box { background: #0f172a; border: 1px solid #334155; border-radius: 20px; padding: 8px 20px; text-align: left; margin-bottom: 25px; }
            .summary-row { display: flex; align-items: center; gap: 14px; padding: 14px 0; border-bottom: 1px solid #1e293b; }
            .summary-row:last-child { border-bottom: none; }
            .summary-row i { width: 20px; color: var(--primary); font-size: 15px; flex-shrink: 0; }
            .summary-label { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; }
            .summary-value { font-size: 14px; color: #fff; font-weight: 700; word-break: break-word; }

            .status-pill { display: inline-block; padding: 3px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
            .status-pending { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
            .status-active { background: rgba(74, 222, 128, 0.15); color: #4ade80; }

            .login-btn { display: inline-flex; align-items: center; gap: 10px; width: 100%; justify-content: center; padding: 15px; background: var(--primary); color: #fff; text-decoration: none; border-radius: 14px; font-weight: 800; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
            .login-btn:hover { background: #4f46e5; }
            .timer-text { font-size: 12px; color: #64748b; margin-top: 15px; }

            @media (max-width: 480px) {
                .success-card { padding: 30px 20px; }
            }
        </style>
        <meta http-equiv="refresh" content="10;url=login.php">
    </head>
    <body>
        <div class="success-card">
            <div class="icon-box"><i class="fas fa-check"></i></div>
            <h2><?= ($role === 'super_admin') ? 'Master Access Created' : 'Request Transmitted' ?></h2>
            <p class="subtitle"><?= ($role === 'super_admin') ? 'Congratulations! You are the System Founder.' : 'Your join request has been submitted. Please review your details below.' ?></p>

            <div class="summary-box">
                <?php foreach ($rows as $r): [$icon, $label, $value] = $r; ?>
                    <div class="summary-row">
                        <i class="fas <?= $icon ?>"></i>
                        <div>
                            <span class="summary-label"><?= htmlspecialchars($label) ?></span>
                            <span class="summary-value">
                                <?php if ($label === 'Status'): ?>
                                    <span class="status-pill <?= ($data['status'] === 'active') ? 'status-active' : 'status-pending' ?>"><?= htmlspecialchars($value) ?></span>
                                <?php else: ?>
                                    <?= htmlspecialchars($value) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <a href="login.php" class="login-btn">Go to Login <i class="fas fa-arrow-right"></i></a>
            <div class="timer-text">Auto-redirecting in 10 seconds...</div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>