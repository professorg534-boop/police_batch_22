<?php
session_start();
include 'db.php';
$error = "";

// চেক করা হচ্ছে ডাটাবেস থেকে কোনো রিকোয়েস্ট এসেছে কি না
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $batch_id = mysqli_real_escape_string($conn, $_POST['batch_id']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // কোয়েরি: আইডি অনুযায়ী তথ্য আনা
    $sql = "SELECT * FROM users WHERE batch_id = '$batch_id' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        // পাসওয়ার্ড চেক
        if ($password == $row['password']) {
            // স্ট্যাটাস চেক (শুধুমাত্র active ইউজারদের জন্য)
            if ($row['status'] == 'active') {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_batch'] = $row['batch_id'];
                $_SESSION['role'] = $row['user_role'];
                $_SESSION['admin_district'] = isset($row['admin_district']) ? $row['admin_district'] : "";
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "আপনার অ্যাকাউন্টটি পেন্ডিং আছে। জেলা এডমিনের অনুমোদনের অপেক্ষা করুন।";
            }
        } else {
            $error = "ভুল পাসওয়ার্ড দিয়েছেন!";
        }
    } else {
        $error = "আইডি নম্বরটি ডাটাবেসে পাওয়া যায়নি!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Police Batch 22</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #0f172a; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-box { background: rgba(255, 255, 255, 0.08); padding: 40px; border-radius: 20px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); width: 350px; text-align: center; color: #fff; }
        h2 { margin-bottom: 25px; color: #38bdf8; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: #fff; outline: none; }
        button { width: 100%; padding: 12px; background: #38bdf8; border: none; border-radius: 10px; color: #0f172a; font-weight: 600; cursor: pointer; transition: 0.3s; }
        button:hover { background: #0ea5e9; }
        .error { color: #f87171; font-size: 13px; margin-bottom: 15px; background: rgba(248, 113, 113, 0.1); padding: 10px; border-radius: 8px; }
        .signup-link { margin-top: 20px; color: #94a3b8; font-size: 14px; }
        .signup-link a { color: #38bdf8; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Police Batch 22</h2>
        <?php if($error != "") echo "<div class='error'>$error</div>"; ?>
        <form method="POST">
            <input type="text" name="batch_id" placeholder="6-Digit ID Number" maxlength="6" required>
            <input type="password" name="password" placeholder="6-Digit Password" maxlength="6" required>
            <button type="submit">SIGN IN</button>
        </form>
        <div class="signup-link">
            নতুন মেম্বার? <br>
            <a href="signup.php">Send Join Request</a>
        </div>
    </div>
</body>
</html>