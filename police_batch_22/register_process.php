<?php
include 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ডাটা রিসিভ
    $full_name   = mysqli_real_escape_string($conn, $_POST['full_name']);
    $bp_number   = mysqli_real_escape_string($conn, $_POST['bp_number']);
    $mobile      = mysqli_real_escape_string($conn, $_POST['mobile_number']);
    $gender      = mysqli_real_escape_string($conn, $_POST['gender']);
    $division    = mysqli_real_escape_string($conn, $_POST['division']);
    $district    = mysqli_real_escape_string($conn, $_POST['district']);
    $batch_id    = mysqli_real_escape_string($conn, $_POST['batch_id']);
    $password    = $_POST['password'];
    $confirm_pw  = $_POST['confirm_password'];

    // ১. পাসওয়ার্ড ম্যাচ চেক (Error Interface)
    if ($password !== $confirm_pw) {
        die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Validation Failed | Batch 22</title>
            <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
            <style>
                :root { --danger: #f87171; --bg: #0f172a; --card: #1e293b; }
                body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; color: white; }
                .error-card { background: var(--card); padding: 40px; border-radius: 28px; width: 90%; max-width: 400px; text-align: center; border: 1px solid rgba(248, 113, 113, 0.2); }
                .icon-box { width: 70px; height: 70px; background: rgba(248, 113, 113, 0.1); border-radius: 20px; display: flex; justify-content: center; align-items: center; margin: 0 auto 20px; border: 2px solid var(--danger); transform: rotate(45deg); }
                .icon-box i { font-size: 28px; color: var(--danger); transform: rotate(-45deg); }
                h2 { font-size: 20px; font-weight: 800; margin-bottom: 10px; color: var(--danger); }
                p { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 25px; }
                .back-btn { display: inline-block; padding: 12px 24px; background: rgba(248, 113, 113, 0.1); color: var(--danger); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 14px; border: 1px solid var(--danger); transition: 0.3s; }
                .back-btn:hover { background: var(--danger); color: #fff; }
            </style>
        </head>
        <body>
            <div class='error-card'>
                <div class='icon-box'><i class='fas fa-shield-slash'></i></div>
                <h2>Security Breach!</h2>
                <p>Passwords do not match. Please ensure both secret keys are identical for personnel verification.</p>
                <a href='javascript:history.back()' class='back-btn'>Re-enter Password</a>
            </div>
        </body>
        </html>");
    }

    // ২. ব্যাচ আইডি চেক (Duplicate Warning Interface)
    $check_user = mysqli_query($conn, "SELECT id FROM users WHERE batch_id = '$batch_id'");
    if (mysqli_num_rows($check_user) > 0) {
        die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Access Denied | Batch 22</title>
            <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap' rel='stylesheet'>
            <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
            <style>
                :root { --warning: #fbbf24; --bg: #0f172a; --card: #1e293b; }
                body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; color: white; }
                .error-card { background: var(--card); padding: 40px; border-radius: 28px; width: 90%; max-width: 400px; text-align: center; border: 1px solid rgba(251, 191, 36, 0.2); animation: shake 0.5s ease-in-out; }
                @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-10px); } 75% { transform: translateX(10px); } }
                .icon-box { width: 70px; height: 70px; background: rgba(251, 191, 36, 0.1); border-radius: 20px; display: flex; justify-content: center; align-items: center; margin: 0 auto 20px; border: 2px solid var(--warning); transform: rotate(45deg); }
                .icon-box i { font-size: 28px; color: var(--warning); transform: rotate(-45deg); }
                h2 { font-size: 20px; font-weight: 800; margin-bottom: 10px; color: var(--warning); }
                p { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 25px; }
                .back-btn { display: inline-block; padding: 12px 24px; background: rgba(251, 191, 36, 0.1); color: var(--warning); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 14px; border: 1px solid var(--warning); transition: 0.3s; }
                .back-btn:hover { background: var(--warning); color: #000; }
            </style>
        </head>
        <body>
            <div class='error-card'>
                <div class='icon-box'><i class='fas fa-id-card-clip'></i></div>
                <h2>Identity Conflict!</h2>
                <p>The Batch ID (<b>$batch_id</b>) is already registered. Please use your unique identity.</p>
                <a href='javascript:history.back()' class='back-btn'><i class='fas fa-arrow-left'></i> Try Different ID</a>
            </div>
        </body>
        </html>");
    }

    // ৩. প্রথম ইউজার চেক (Admin/Member Assignment)
    $check_table_empty = mysqli_query($conn, "SELECT id FROM users LIMIT 1");
    if (mysqli_num_rows($check_table_empty) == 0) {
        $role = 'super_admin';
        $status = 'active';
    } else {
        $role = 'member'; 
        $status = 'pending'; 
    }

    // ৪. ডাটাবেজে ইনসার্ট
    $sql = "INSERT INTO users (full_name, bp_number, mobile_number, gender, division, district, batch_id, password, role, status) 
            VALUES ('$full_name', '$bp_number', '$mobile', '$gender', '$division', '$district', '$batch_id', '$password', '$role', '$status')";

    if (mysqli_query($conn, $sql)) {
        // --- ৫. সাকসেস ইন্টারফেস ---
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Success | Batch 22</title>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                :root { --primary: #6366f1; --bg: #0f172a; --card: #1e293b; }
                body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; color: white; overflow: hidden; }
                .success-card { background: var(--card); padding: 50px 40px; border-radius: 32px; width: 90%; max-width: 450px; text-align: center; border: 1px solid #334155; position: relative; animation: slideUp 0.6s ease-out; }
                @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
                .icon-box { width: 80px; height: 80px; background: rgba(99, 102, 241, 0.1); border-radius: 25px; display: flex; justify-content: center; align-items: center; margin: 0 auto 25px; border: 2px solid var(--primary); transform: rotate(45deg); }
                .icon-box i { font-size: 32px; color: var(--primary); transform: rotate(-45deg); animation: checkPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.3s both; }
                h2 { font-size: 24px; font-weight: 800; margin-bottom: 15px; color: #fff; }
                p { color: #94a3b8; font-size: 15px; line-height: 1.6; margin-bottom: 35px; }
                .loader-bar { width: 100%; height: 6px; background: #0f172a; border-radius: 10px; overflow: hidden; margin-bottom: 10px; }
                .loader-fill { width: 0%; height: 100%; background: var(--primary); animation: load 3.5s linear forwards; }
                @keyframes load { to { width: 100%; } }
                .timer-text { font-size: 12px; color: #64748b; }
            </style>
            <meta http-equiv="refresh" content="4;url=login.php">
        </head>
        <body>
            <div class="success-card">
                <div class="icon-box"><i class="fas fa-check"></i></div>
                <h2><?php echo ($role === 'super_admin') ? 'Master Access Created' : 'Request Transmitted'; ?></h2>
                <p><?php echo ($role === 'super_admin') ? 'Congratulations! You are the System Founder.' : 'Greetings, <b>'.$full_name.'</b>. Your join request has been sent for approval.'; ?></p>
                <div class="loader-bar"><div class="loader-fill"></div></div>
                <div class="timer-text">Redirecting to Login...</div>
            </div>
        </body>
        </html>
        <?php
    } else {
        echo "Database Error: " . mysqli_error($conn);
    }
}
?>