<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// ১. ডাটা সামারি (টপ বার)
$stats_query = mysqli_query($conn, "SELECT 
    (SELECT COUNT(id) FROM users) as total_all_members,
    (SELECT SUM(amount) FROM payment_requests WHERE status IN ('success', 'Approved', 'approved')) as total_all_funding,
    (SELECT COUNT(id) FROM payment_requests WHERE status IN ('success', 'Approved', 'approved')) as total_all_success");
$summary = mysqli_fetch_assoc($stats_query);

// ২. লগইন করা ইউজারের প্রোফাইল
$me_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$me = mysqli_fetch_assoc($me_query);
$my_phone = $me['phone'] ?? $me['mobile_number'] ?? 'N/A';

// ৩. মিশন মনিটরিং ডাটা
$active_mission = null;
$target_user = null;
$district_breakdown = [];

$res = mysqli_query($conn, "SELECT * FROM fund_operations WHERE status = 'active' ORDER BY id DESC LIMIT 1");
if($res && mysqli_num_rows($res) > 0) {
    $active_mission = mysqli_fetch_assoc($res);
    $op_id = $active_mission['id'];
    
    if ($active_mission['target_batch_id'] !== 'LEGACY_FUND') {
        $t_id = $active_mission['target_batch_id'];
        $target_res = mysqli_query($conn, "SELECT * FROM users WHERE batch_id = '$t_id'");
        $target_user = ($target_res) ? mysqli_fetch_assoc($target_res) : null;
    }
    // জেলা ভিত্তিক কন্ট্রিবিউশন লিস্ট
    $district_breakdown = mysqli_query($conn, "SELECT * FROM district_contributions WHERE op_id = '$op_id' ORDER BY required_amount DESC");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPHA HQ | Tactical Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --bg: #050508; --side: #0a0a0f; --accent: #6366f1; --panel: rgba(15, 15, 20, 0.9); --border: rgba(255,255,255,0.06); --success: #00ff7f; --warning: #fbbf24; --info: #3b82f6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; min-height: 100vh; overflow-x: hidden; }

        .sidebar { width: 280px; background: var(--side); height: 100vh; position: fixed; border-right: 1px solid var(--border); padding: 30px 20px; display: flex; flex-direction: column; z-index: 100; }
        .logo { font-family: 'Space Grotesk'; font-size: 24px; color: var(--accent); margin-bottom: 50px; font-weight: 800; text-align: center; letter-spacing: 2px; }
        .nav-content { flex-grow: 1; }
        .nav-link { text-decoration: none; color: #888; padding: 15px 20px; border-radius: 12px; display: flex; align-items: center; gap: 15px; margin-bottom: 8px; font-weight: 600; font-size: 14px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: rgba(99, 102, 241, 0.1); color: #fff; }
        .nav-link.active { border-left: 4px solid var(--accent); background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%); }
        .logout-btn { text-decoration: none; color: #ff4d4d; padding: 15px 20px; display: flex; align-items: center; gap: 15px; font-weight: 700; font-size: 14px; margin-top: auto; }

        .main { margin-left: 280px; width: calc(100% - 280px); padding: 40px; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: var(--panel); border: 1px solid var(--border); padding: 25px; border-radius: 20px; }
        .stat-box small { color: #555; font-size: 10px; font-weight: 800; letter-spacing: 1px; }
        .stat-box h2 { font-family: 'Space Grotesk'; font-size: 30px; margin-top: 5px; }

        .dashboard-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 30px; }
        .intel-card { background: var(--panel); border: 1px solid var(--border); border-radius: 25px; padding: 30px; border-top: 3px solid var(--accent); margin-bottom: 25px; }
        .intel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .intel-item { background: rgba(0,0,0,0.3); padding: 12px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03); }
        .intel-item label { display: block; font-size: 8px; color: #555; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
        .intel-item span { font-size: 12px; color: #ccc; font-weight: 700; }

        .briefing-box { background: rgba(99, 102, 241, 0.05); border: 1px dashed var(--accent); border-radius: 20px; padding: 20px; }
        .briefing-box h4 { font-size: 10px; color: var(--accent); letter-spacing: 2px; font-weight: 800; margin-bottom: 10px; text-transform: uppercase; }
        .briefing-content { font-size: 13px; line-height: 1.6; color: #aaa; }

        .monitor-frame { background: #08080c; border: 2px solid #1a1a2e; border-radius: 25px; height: 600px; display: flex; flex-direction: column; overflow: hidden; }
        .monitor-header { background: #000; padding: 15px 25px; display: flex; justify-content: space-between; border-bottom: 1px solid #1a1a2e; }
        .monitor-body { flex-grow: 1; overflow-y: auto; padding: 20px; }
        
        .dist-log { background: rgba(255,255,255,0.02); padding: 15px; border-radius: 15px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #333; }
        .dist-log.status-pending { border-left-color: var(--warning); background: rgba(251, 191, 36, 0.03); }
        .dist-log.status-admin { border-left-color: var(--info); background: rgba(59, 130, 246, 0.03); }
        .dist-log.status-success { border-left-color: var(--success); background: rgba(0, 255, 127, 0.03); }

        .progress-hud { margin-top: 25px; padding: 20px; background: rgba(0,0,0,0.4); border-radius: 20px; }
        .bar-bg { width: 100%; height: 10px; background: #111; border-radius: 10px; margin: 15px 0; overflow: hidden; }
        .bar-fill { height: 100%; background: linear-gradient(90deg, var(--accent), var(--success)); width: 0%; transition: 1.5s; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo">ALPHA HQ</div>
    <div class="nav-content">
        <a href="dashboard.php" class="nav-link active"><i class="fa-solid fa-house"></i> Dashboard</a>
        <a href="members.php" class="nav-link"><i class="fa-solid fa-users"></i> Members</a>
        <a href="admin/fund_allocation.php" class="nav-link"><i class="fa-solid fa-microchip"></i> Matrix HQ</a>
        <a href="payment/district_monitor.php" class="nav-link"><i class="fa-solid fa-wallet"></i> Finances</a>
        <a href="our_mission.php" class="nav-link"><i class="fa-solid fa-flag"></i> Our Mission</a>
    </div>
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-sign-out"></i> SIGN OUT</a>
</aside>

<main class="main">
    <div class="stats-row">
        <div class="stat-box"><small>TOTAL PERSONNEL</small><h2><?php echo number_format($summary['total_all_members']); ?></h2></div>
        <div class="stat-box"><small>GRAND ASSETS</small><h2 style="color:var(--success)">৳<?php echo number_format($summary['total_all_funding'] ?? 0); ?></h2></div>
        <div class="stat-box"><small>ACTIVE MISSIONS</small><h2><?php echo ($active_mission ? '01' : '00'); ?></h2></div>
    </div>

    <div class="dashboard-grid">
        <div class="left-col">
            <div class="intel-card">
                <h3 style="font-family:'Space Grotesk'; font-size: 13px; color: var(--accent); margin-bottom: 20px; letter-spacing: 2px;">PERSONNEL INTELLIGENCE</h3>
                <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 25px;">
                    <img src="<?php echo (!empty($me['photo'])) ? 'uploads/'.$me['photo'] : 'https://cdn-icons-png.flaticon.com/512/6522/6522516.png'; ?>" style="width: 80px; height: 80px; border-radius: 20px; border: 2px solid var(--accent); padding: 3px; object-fit: cover;">
                    <div>
                        <h2 style="font-size: 22px;"><?php echo $me['full_name']; ?></h2>
                        <span style="font-size: 10px; color: var(--success); font-weight: 800;">STATUS: ACTIVE DUTY</span>
                    </div>
                </div>
                <div class="intel-grid">
                    <div class="intel-item"><label>Batch ID</label><span>#<?php echo $me['batch_id']; ?></span></div>
                    <div class="intel-item"><label>District Sector</label><span><?php echo $me['district']; ?></span></div>
                    <div class="intel-item"><label>Mobile Unit</label><span><?php echo $my_phone; ?></span></div>
                    <div class="intel-item"><label>Mission Goal</label><span style="color:var(--warning)">৳<?php echo $active_mission ? number_format($active_mission['total_required']) : '0'; ?></span></div>
                </div>
                <?php if($active_mission): ?>
                <div class="progress-hud">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <small style="color:var(--accent); font-weight:800; font-size:9px;">DEPLOYMENT PROGRESS</small>
                        <b id="syncPercent" style="color:var(--success); font-size:12px;">0%</b>
                    </div>
                    <div class="bar-bg"><div id="matrixBar" class="bar-fill"></div></div>
                    <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 800;">
                        <span>COLLECTED: <b id="colAmt" style="color:var(--success)">৳0</b></span>
                        <span>/ ৳<?php echo number_format($active_mission['total_required']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if($active_mission): ?>
            <div class="briefing-box">
                <h4><i class="fa-solid fa-file-contract"></i> Operational Briefing</h4>
                <div class="briefing-content"><?php echo !empty($active_mission['briefing']) ? nl2br($active_mission['briefing']) : "No detailed briefing provided."; ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="monitor-frame">
            <div class="monitor-header">
                <span style="font-family:'Space Grotesk'; font-size: 11px; color: var(--success);"><i class="fa-solid fa-tower-broadcast"></i> LIVE REGIONAL FEED</span>
                <span style="font-size: 9px; color: #444;">OP_ID: #<?php echo $active_mission['id'] ?? 'NULL'; ?></span>
            </div>
            <div class="monitor-body">
                <?php if($active_mission && $district_breakdown): ?>
                    <?php while($dist = mysqli_fetch_assoc($district_breakdown)): 
                        $status = strtolower($dist['status']);
                        
                        $status_class = "status-pending";
                        $display_text = "WAITING FOR ADMIN";
                        $display_color = "var(--warning)";

                        if($status == 'approved' || $status == 'admin_approved') {
                            $status_class = "status-admin";
                            $display_text = "WAITING FOR SUPER ADMIN";
                            $display_color = "var(--info)";
                        } elseif($status == 'success' || $status == 'paid') {
                            $status_class = "status-success";
                            $display_text = "TRANSACTION VERIFIED";
                            $display_color = "var(--success)";
                        }
                    ?>
                    <div class="dist-log <?php echo $status_class; ?>">
                        <div>
                            <div style="font-size: 13px; font-weight: 800; color: #eee;"><?php echo strtoupper($dist['district_name']); ?></div>
                            <small style="font-size: 9px; color: <?php echo $display_color; ?>; font-weight: 700;"><?php echo $display_text; ?></small>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-family:'Space Grotesk'; font-size: 16px; font-weight: 800; color: <?php echo $display_color; ?>;">৳<?php echo number_format($dist['required_amount']); ?></div>
                            <small style="font-size: 9px; opacity: 0.6; font-weight: 800;"><?php echo strtoupper($status); ?></small>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 150px;"><p style="color: #222; font-size: 12px; font-weight: 800;">SYSTEM IDLE</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function syncMission() {
    fetch('check_operation_status.php').then(r => r.json()).then(res => {
        if(res.active) {
            if(document.getElementById('matrixBar')) document.getElementById('matrixBar').style.width = res.progress_percent + '%';
            if(document.getElementById('syncPercent')) document.getElementById('syncPercent').innerText = res.progress_percent + '%';
            if(document.getElementById('colAmt')) document.getElementById('colAmt').innerText = '৳' + new Intl.NumberFormat().format(res.data.collected_amount);
        }
    }).catch(e => console.error("Sync Error"));
}
setInterval(syncMission, 5000); syncMission();
</script>
</body>
</html>