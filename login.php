<?php
include 'db.php';
session_start();
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $batch_id = isset($_POST['batch_id']) ? trim($_POST['batch_id']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($batch_id === '' || $password === '') {
        $error = "Please fill in all fields.";
    } elseif (!preg_match('/^\d{6}$/', $batch_id)) {
        $error = "Invalid Batch ID format.";
    } else {
        // Prepared statement — SQL Injection থেকে সুরক্ষিত
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE batch_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $batch_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            // হ্যাশ করা পাসওয়ার্ড যাচাই
            $passwordMatched = password_verify($password, $user['password']);

            // পুরনো একাউন্ট যাদের পাসওয়ার্ড এখনো plain text আকারে আছে, তাদের জন্য
            if (!$passwordMatched && $password === $user['password']) {
                $passwordMatched = true;

                // লগইনের সাথে সাথে পাসওয়ার্ডটি hash করে ডাটাবেজে আপডেট করে দেওয়া হচ্ছে
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($updateStmt, "si", $newHash, $user['id']);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }

            if ($passwordMatched) {
                if ($user['status'] == 'active') {
                    // Session fixation প্রতিরোধে সেশন আইডি নতুন করে তৈরি
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Access Denied: Account not active.";
                }
            } else {
                $error = "Authentication Failed: Invalid password.";
            }
        } else {
            $error = "Identity Not Found: Check Batch ID.";
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Login | Batch 22</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #6366f1; --accent: #818cf8; --bg: #0f172a; --card: #1e293b; }

        * { box-sizing: border-box; }

        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; color: white; }

        /* সলিড প্রিমিয়াম কার্ড (নো শ্যাডো) */
        .card { background: var(--card); padding: 45px 40px; border-radius: 28px; width: 100%; max-width: 420px; border: 1px solid #334155; position: relative; box-shadow: none; }
        .card::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 5px; background: linear-gradient(90deg, var(--primary), var(--accent)); border-radius: 28px 28px 0 0; }

        .header-area { text-align: center; margin-bottom: 35px; }
        .header-area h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; margin: 0; text-transform: uppercase; color: #fff; }
        .header-area p { color: #94a3b8; font-size: 13px; margin-top: 8px; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }

        /* এরর মেসেজ স্টাইল */
        .error-box { background: rgba(248, 113, 113, 0.05); color: #f87171; padding: 14px; border-radius: 12px; font-size: 12px; font-weight: 700; text-align: center; margin-bottom: 25px; border: 1px solid rgba(248, 113, 113, 0.1); display: flex; align-items: center; justify-content: center; gap: 8px; }

        label { display: block; font-size: 11px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; margin-left: 5px; }

        .input-wrapper { position: relative; margin-bottom: 25px; }
        input { width: 100%; padding: 16px 18px; border-radius: 14px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; outline: none; transition: 0.3s; font-size: 14px; font-weight: 600; }
        input:focus { border-color: var(--primary); background: #111b2e; }

        .login-btn { width: 100%; padding: 18px; background: var(--primary); color: white; border: none; border-radius: 14px; font-size: 15px; font-weight: 800; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .login-btn:hover { background: #4f46e5; transform: translateY(-2px); }

        /* টু-লাইন প্রিমিয়াম ফুটার */
        .footer-link { text-align: center; margin-top: 35px; padding-top: 25px; border-top: 1px solid #334155; }
        .footer-link p { font-size: 14px; color: #94a3b8; margin: 0 0 15px 0; font-weight: 500; }

        .signup-btn-link {
            display: inline-block;
            color: #fff;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid var(--primary);
            background: rgba(99, 102, 241, 0.03);
            transition: 0.3s;
            text-transform: uppercase;
        }
        .signup-btn-link:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            .card { padding: 30px 22px; border-radius: 22px; }
            .header-area h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header-area">
        <h1>Member Access</h1>
        <p>Personnel Portal • Batch 22</p>
    </div>

    <?php if($error): ?>
        <div class="error-box">
            <i class="fas fa-shield-alt"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-wrapper">
            <label><i class="fas fa-fingerprint"></i> Batch Identity (6 Digits)</label>
            <input type="text" name="batch_id" placeholder="Enter Identity ID" pattern="\d{6}" maxlength="6" required autocomplete="off" value="<?= isset($batch_id) ? htmlspecialchars($batch_id) : '' ?>">
        </div>

        <div class="input-wrapper">
            <label><i class="fas fa-key"></i> Secure Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="login-btn">
            Authorize Login <i class="fas fa-chevron-right"></i>
        </button>
    </form>

    <div class="footer-link">
        <p>New officer in Batch 22?</p>
        <a href="signup.php" class="signup-btn-link">
            Request Enrollment <i class="fas fa-user-plus" style="margin-left:8px;"></i>
        </a>
    </div>
</div>

</body>
</html>