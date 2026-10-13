<?php
session_start();
error_reporting(0); 
include 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// ইউজারের নিজস্ব তথ্য
$u_res = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$me = mysqli_fetch_assoc($u_res);

// অ্যানালিটিক্স
$total_m = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM users"))['t'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Executive Alpha | Command Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary: #6366f1; --accent: #a855f7; --bg: #010409;
            --card-bg: rgba(22, 27, 34, 0.6); --border: rgba(255, 255, 255, 0.05);
            --text-dim: #8b949e;
        }

        * { margin:0; padding:0; box-sizing: border-box; }
        body { 
            background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #e6edf3;
            background-image: radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.1) 0%, transparent 50%);
            min-height: 100vh; overflow-x: hidden;
        }

        /* Sidebar - High Professional Look */
        .sidebar {
            width: 260px; height: 100vh; position: fixed; left: 0; top: 0;
            background: rgba(13, 17, 23, 0.9); backdrop-filter: blur(20px);
            border-right: 1px solid var(--border); padding: 40px 20px; z-index: 100;
        }
        .nav-logo { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 50px; display: flex; align-items: center; gap: 10px; }
        .nav-link {
            display: flex; align-items: center; gap: 12px; padding: 14px 18px; color: var(--text-dim);
            text-decoration: none; border-radius: 12px; font-weight: 600; margin-bottom: 8px; transition: 0.3s;
        }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.05); color: #fff; box-shadow: inset 0 0 0 1px var(--border); }
        .nav-link i { font-size: 18px; color: var(--primary); }

        /* Main Workspace */
        .content { margin-left: 260px; padding: 40px 60px; }

        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .h-info h1 { font-size: 30px; font-weight: 800; letter-spacing: -1px; }

        /* Stats Grid */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 50px; }
        .stat-box { 
            background: var(--card-bg); border: 1px solid var(--border); padding: 25px; border-radius: 24px;
            position: relative; transition: 0.3s;
        }
        .stat-box:hover { border-color: var(--primary); transform: translateY(-5px); }
        .stat-box p { font-size: 12px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; }
        .stat-box h2 { font-size: 28px; font-weight: 800; margin-top: 5px; }

        /* MEMBERS SECTION - The Premium Identity Grid */
        .section-header { margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .section-header h2 { font-size: 24px; font-weight: 800; }
        .line { flex-grow: 1; height: 1px; background: var(--border); }

        .member-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        
        /* Member Card Design */
        .member-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0) 100%);
            border: 1px solid var(--border); border-radius: 28px; padding: 24px;
            backdrop-filter: blur(10px); transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .member-card:hover { 
            border-color: rgba(99, 102, 241, 0.4); 
            background: rgba(99, 102, 241, 0.05);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5);
        }

        .m-head { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .m-pic { width: 50px; height: 50px; border-radius: 15px; background: var(--primary); display: grid; place-items: center; font-weight: 800; font-size: 20px; color: #fff; }
        .m-title h4 { font-size: 17px; font-weight: 700; color: #fff; }
        .m-title span { font-size: 11px; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }

        .m-body { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 20px; }
        .data-point label { font-size: 9px; color: var(--text-dim); font-weight: 800; text-transform: uppercase; display: block; margin-bottom: 3px; }
        .data-point p { font-size: 13px; font-weight: 600; color: #cbd5e1; }

        .m-foot { margin-top: 20px; display: flex; justify-content: space-between; align-items: center; }
        .status-pill { padding: 6px 14px; border-radius: 10px; font-size: 10px; font-weight: 800; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.1); }
        .location-tag { font-size: 11px; color: var(--text-dim); display: flex; align-items: center; gap: 5px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="nav-logo"><i class="fas fa-hexagon-nodes"></i> COMMAND</div>
    <nav>
        <a href="dashboard.php" class="nav-link active"><i class="fas fa-grid-horizontal"></i> Overview</a>
        <a href="members.php" class="nav-link"><i class="fas fa-user-group"></i> Members</a>
        <a href="payment/index.php" class="nav-link"><i class="fas fa-receipt"></i> Payment Center</a>
        <a href="our_mission.php" class="nav-link"><i class="fas fa-solid fa-bullseye"></i> Our Mission</a>
        <a href="logout.php" class="nav-link" style="margin-top: 180px; color: #f85149;"><i class="fas fa-power-off"></i> Sign Out</a>
    </nav>
</aside>

<main class="content">
    <header>
        <div class="h-info">
            <h1>HQ Command Center</h1>
            <p style="color: var(--text-dim); font-size: 14px;">Welcome back, <b><?php echo $me['full_name']; ?></b></p>
        </div>
        <div style="background: rgba(99, 102, 241, 0.1); padding: 8px 20px; border-radius: 15px; border: 1px solid rgba(99, 102, 241, 0.2);">
            <span style="font-size: 12px; font-weight: 800; color: var(--primary);">BATCH 2022 SECURE ACCESS</span>
        </div>
    </header>

    <div class="stats-row">
        <div class="stat-box"><p>Total Personnel</p><h2><?php echo $total_m; ?></h2></div>
        <div class="stat-box"><p>Total Funding</p><h2><?php echo $me['batch_id']; ?></h2></div>
        <div class="stat-box"><p>Fund Painding</p><h2><?php echo $me['bp_no'] ?? 'N/A'; ?></h2></div>
        <div class="stat-box" style="background: var(--primary);"><p style="color: #fff;">System Status</p><h2 style="color: #fff;">ACTIVE</h2></div>
    </div>

    <div class="section-header">
        <h2>Personnel Members</h2>
        <div class="line"></div>
        <i class="fas fa-users-viewfinder" style="color: var(--text-dim);"></i>
    </div>

    <div class="member-grid">
        <?php
        $res = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC LIMIT 12");
        while($row = mysqli_fetch_assoc($res)):
        ?>
        <div class="member-card">
            <div class="m-head">
                <div class="m-pic"><?php echo substr($row['full_name'], 0, 1); ?></div>
                <div class="m-title">
                    <h4><?php echo $row['full_name']; ?></h4>
                    <span>Batch ID: <?php echo $row['batch_id']; ?></span>
                </div>
            </div>

            <div class="m-body">
                <div class="data-point">
                    <label>BP Number</label>
                    <p><?php echo $row['bp_no'] ?: '---'; ?></p>
                </div>
                <div class="data-point">
                    <label>Gender</label>
                    <p><?php echo strtoupper($row['gender']); ?></p>
                </div>
                <div class="data-point">
                    <label>Contact</label>
                    <p><?php echo $row['mobile_number']; ?></p>
                </div>
                <div class="data-point">
                    <label>Division</label>
                    <p><?php echo $row['division']; ?></p>
                </div>
            </div>

            <div class="m-foot">
                <span class="status-pill">VERIFIED UNIT</span>
                <span class="location-tag"><i class="fas fa-location-dot"></i> <?php echo $row['district']; ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</main>

</body>
</html>