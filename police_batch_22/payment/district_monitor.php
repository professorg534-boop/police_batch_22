<?php
session_start();
include '../db.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$user_res = mysqli_query($conn, "SELECT district, role, full_name FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_res);
$my_district = $user_data['district'];
$is_admin = (in_array(strtolower($user_data['role']), ['admin', 'super admin', 'superadmin']));

// Real-time Intelligence Stats
$total_personnel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM users WHERE district = '$my_district'"))['total'];
$total_funds = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total_amt FROM payment_requests WHERE district = '$my_district' AND (status = 'Approved' OR status = 'success')"))['total_amt'] ?? 0;
$verified_tx = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total_count FROM payment_requests WHERE district = '$my_district' AND (status = 'Approved' OR status = 'success')"))['total_count'];
$pending_count = $is_admin ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM payment_requests WHERE district = '$my_district' AND status = 'Pending'"))['total'] : 0;

$member_list_res = mysqli_query($conn, "SELECT u.full_name, u.mobile_number, u.role, 
                    (SELECT COUNT(id) FROM payment_requests WHERE user_id = u.id AND (status = 'Approved' OR status = 'success')) as tx_count, 
                    (SELECT SUM(amount) FROM payment_requests WHERE user_id = u.id AND (status = 'Approved' OR status = 'success')) as total_paid 
                    FROM users u WHERE u.district = '$my_district' ORDER BY total_paid DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TERMINAL | District Intelligence</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #050506; --panel: #0c0c0e; --accent: #6366f1;
            --border: rgba(255, 255, 255, 0.05); --text-primary: #f8fafc;
            --text-secondary: #64748b; --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: var(--bg); color: var(--text-primary); 
            font-family: 'Inter', sans-serif; display: flex; height: 100vh; overflow: hidden;
        }

        /* --- Global Aesthetics --- */
        .glass-effect { background: rgba(12, 12, 14, 0.7); backdrop-filter: blur(20px); border: 1px solid var(--border); }

        /* --- Sidebar: Minimalist & Clean --- */
        .sidebar { 
            width: 260px; background: #080809; border-right: 1px solid var(--border);
            padding: 40px 24px; display: flex; flex-direction: column;
        }
        .brand { font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 48px; display: flex; align-items: center; gap: 10px; }
        .brand .dot { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; box-shadow: 0 0 15px var(--accent); }

        .nav-section { margin-bottom: 32px; }
        .nav-label { font-size: 10px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px; padding-left: 12px; }
        .nav-link { 
            display: flex; align-items: center; gap: 12px; padding: 12px 14px; color: var(--text-secondary);
            text-decoration: none; border-radius: 12px; transition: 0.2s; font-size: 14px; font-weight: 500;
        }
        .nav-link i { font-size: 16px; opacity: 0.7; }
        .nav-link:hover, .nav-link.active { background: #111114; color: #fff; }
        .nav-link.active { border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.5); }

        /* --- Main Content Area --- */
        .viewport { flex: 1; padding: 48px 60px; overflow-y: auto; }

        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px; }
        .header h1 { font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; }
        .header p { color: var(--text-secondary); font-size: 14px; margin-top: 4px; }
        .sector-badge { background: #111114; border: 1px solid var(--border); padding: 8px 16px; border-radius: 30px; font-size: 12px; font-weight: 600; color: var(--accent); }

        /* --- Stat Cards: High-Precision Look --- */
        .stats-grid { display: grid; grid-template-columns: repeat(<?php echo $is_admin ? '4' : '3'; ?>, 1fr); gap: 24px; margin-bottom: 48px; }
        .stat-card { 
            padding: 24px; border-radius: 20px; position: relative; transition: 0.3s;
        }
        .stat-card:hover { border-color: rgba(255,255,255,0.1); background: #0d0d0f; }
        .stat-card span { font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 12px; }
        .stat-card h2 { font-family: 'Outfit', sans-serif; font-size: 24px; font-weight: 700; }

        /* --- Data Panel --- */
        .panel { border-radius: 24px; overflow: hidden; padding: 32px; }
        .panel-title { font-size: 16px; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 20px; font-size: 11px; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 20px; font-size: 14px; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.01); }

        .name-cell { font-weight: 600; color: #fff; }
        .sub-text { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .badge { background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 8px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
        .amt { font-family: 'Outfit', sans-serif; font-weight: 700; color: var(--success); font-size: 16px; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #1a1a1c; border-radius: 10px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <div class="dot"></div>
        ALPHA SYSTEM
    </div>
    
    <div class="nav-section">
        <div class="nav-label">Core View</div>
        <a href="../dashboard.php" class="nav-link"><i class="fa-solid fa-house-chimney"></i> Dashboard</a>
        <a href="district_monitor.php" class="nav-link active"><i class="fa-solid fa-compass"></i> District Hub</a>
    </div>

    <div class="nav-section">
        <div class="nav-label">Resources</div>
        <a href="payment_request.php" class="nav-link"><i class="fa-solid fa-wallet"></i> My Deposits</a>
        <?php if($is_admin): ?>
        <a href="district_administry.php" class="nav-link" style="color: var(--accent);"><i class="fa-solid fa-shield-halved"></i> Administry</a>
        <?php endif; ?>
    </div>

    <div style="margin-top: auto;">
        <div style="padding: 16px; background: #0c0c0e; border: 1px solid var(--border); border-radius: 16px;">
            <p style="font-size: 10px; color: var(--text-secondary); font-weight: 600;">ACTIVE USER</p>
            <p style="font-size: 13px; font-weight: 600; margin-top: 4px;"><?php echo $user_data['full_name']; ?></p>
        </div>
    </div>
</aside>

<main class="viewport">
    <header class="header">
        <div>
            <h1>Regional Intelligence</h1>
            <p>Aggregated data from your assigned sector.</p>
        </div>
        <div class="sector-badge"><?php echo strtoupper($my_district); ?> SECTOR</div>
    </header>

    <div class="stats-grid">
        <div class="stat-card glass-effect">
            <span>Personnel</span>
            <h2><?php echo number_format($total_personnel); ?></h2>
        </div>
        <div class="stat-card glass-effect">
            <span>Capital Flow</span>
            <h2 style="color: var(--success);">৳<?php echo number_format($total_funds); ?></h2>
        </div>
        <div class="stat-card glass-effect">
            <span>Transactions</span>
            <h2 style="color: var(--accent);"><?php echo $verified_tx; ?></h2>
        </div>
        <?php if($is_admin): ?>
        <div class="stat-card glass-effect" style="border-left: 2px solid #f59e0b;">
            <span style="color: #f59e0b;">Action Required</span>
            <h2><?php echo $pending_count; ?></h2>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel glass-effect">
        <div class="panel-title">
            <i class="fa-solid fa-list-check" style="color: var(--accent); font-size: 14px;"></i>
            Regional Roster
        </div>
        <table>
            <thead>
                <tr>
                    <th>Member Identity</th>
                    <th>Classification</th>
                    <th>Activity</th>
                    <th>Asset Value</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($member_list_res)): ?>
                <tr>
                    <td>
                        <div class="name-cell"><?php echo htmlspecialchars($row['full_name']); ?></div>
                        <div class="sub-text"><?php echo $row['mobile_number']; ?></div>
                    </td>
                    <td><span class="badge"><?php echo $row['role']; ?></span></td>
                    <td>
                        <span style="font-weight: 500;"><?php echo $row['tx_count']; ?></span>
                        <span style="color: var(--text-secondary); font-size: 12px;"> Success</span>
                    </td>
                    <td><span class="amt">৳<?php echo number_format($row['total_paid'] ?? 0); ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>