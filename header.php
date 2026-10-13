<?php
// সেশন এবং ডাটাবেস চেক (যদি আগে না করা থাকে)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

// লগইন না থাকলে ইনডেক্স পেজে পাঠিয়ে দিবে
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// বর্তমান ফাইলের নাম বের করা (অ্যাক্টিভ মেনু হাইলাইট করার জন্য)
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Police Batch 22 | Management System</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { --primary: #2563eb; --primary-hover: #1d4ed8; --dark: #0f172a; --sidebar: #1e293b; --bg: #f8fafc; --text-main: #334155; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); margin: 0; display: flex; color: var(--text-main); }
        
        /* Sidebar Design */
        .sidebar { width: 260px; background: var(--sidebar); height: 100vh; color: white; position: fixed; display: flex; flex-direction: column; transition: 0.3s; }
        .sidebar-brand { padding: 30px 20px; text-align: center; font-size: 20px; font-weight: 700; border-bottom: 1px solid #334155; color: #fff; }
        .sidebar-brand i { color: #38bdf8; margin-right: 10px; }
        
        .sidebar-menu { flex: 1; padding: 20px 10px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 15px; color: #94a3b8; text-decoration: none; border-radius: 10px; margin-bottom: 8px; font-weight: 500; transition: 0.2s; }
        .sidebar-menu a i { width: 25px; font-size: 18px; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-menu a.active { background: var(--primary); color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        
        .logout-section { padding: 20px; border-top: 1px solid #334155; }
        .btn-logout { background: #ef4444; color: white !important; justify-content: center; }
        .btn-logout:hover { background: #dc2626 !important; }

        /* Main Content Container */
        .content { margin-left: 260px; width: calc(100% - 260px); padding: 40px; min-height: 100vh; box-sizing: border-box; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-brand span, .sidebar-menu a span { display: none; }
            .content { margin-left: 70px; width: calc(100% - 70px); }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-building-shield"></i> <span>BATCH 22</span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> <span>Dashboard</span>
            </a>
            <a href="member_list.php" class="<?php echo ($current_page == 'member_list.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-viewfinder"></i> <span>Member List</span>
            </a>
            <a href="#" class="<?php echo ($current_page == 'fund.php') ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-dollar"></i> <span>Welfare Fund</span>
            </a>
            <a href="#" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-gear"></i> <span>Settings</span>
            </a>
        </nav>

        <div class="logout-section">
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-power-off"></i> <span>Logout System</span>
            </a>
        </div>
    </div>

    <div class="content">