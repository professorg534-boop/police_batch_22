<?php
session_start();
include 'db.php';

// যদি লগইন করা না থাকে তবে লগইন পেজে পাঠান
if (!isset($_SESSION['user_name'])) {
    header("Location: index.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $batch_id  = mysqli_real_escape_string($conn, $_POST['batch_id']);
    $district  = mysqli_real_escape_string($conn, $_POST['district']);
    $role      = $_POST['user_role'];
    $password  = $_POST['password']; // এখানে পাসওয়ার্ড সরাসরি সেভ হচ্ছে

    $sql = "INSERT INTO users (full_name, batch_id, district, user_role, password) 
            VALUES ('$full_name', '$batch_id', '$district', '$role', '$password')";

    if (mysqli_query($conn, $sql)) {
        $message = "<div style='color: #10b981;'>Member Registered Successfully!</div>";
    } else {
        $message = "<div style='color: #ef4444;'>Error: " . mysqli_error($conn) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Member | Police Batch 22</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background-color: #f1f5f9; display: flex; }
        .sidebar { width: 260px; background: #1e293b; color: white; height: 100vh; padding: 30px 20px; position: fixed; }
        .main-content { margin-left: 260px; width: 100%; padding: 40px; }
        .form-card { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; margin: auto; }
        h2 { color: #1e293b; margin-bottom: 25px; border-bottom: 2px solid #38bdf8; display: inline-block; padding-bottom: 5px; }
        .input-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #64748b; font-size: 14px; }
        input, select { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; transition: 0.3s; }
        input:focus { border-color: #38bdf8; }
        .btn-submit { background: #38bdf8; color: #1e293b; padding: 12px 25px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-submit:hover { background: #0ea5e9; }
        .back-link { display: block; text-align: center; margin-top: 15px; text-decoration: none; color: #64748b; font-size: 14px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2 style="color: #38bdf8; font-size: 20px;">Police Batch 22</h2>
        <p style="color: #94a3b8; margin-top: 20px;">Registration Panel</p>
    </div>

    <div class="main-content">
        <div class="form-card">
            <h2>Add New Member</h2>
            <?php echo $message; ?>
            <form method="POST">
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter Full Name" required>
                </div>
                <div class="input-group">
                    <label>Batch ID</label>
                    <input type="text" name="batch_id" placeholder="e.g. 2022-005" required>
                </div>
                <div class="input-group">
                    <label>District</label>
                    <input type="text" name="district" placeholder="Enter District" required>
                </div>
                <div class="input-group">
                    <label>Role</label>
                    <select name="user_role">
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="input-group">