<?php
session_start();

// ১. ডাটাবেস কানেকশন চেক (নিরাপদ ফলব্যাক)
if (file_exists('db.php')) { 
    include 'db.php'; 
} elseif (file_exists('../db.php')) { 
    include '../db.php'; 
} else { 
    die("<div style='color:red; font-family:sans-serif; padding:20px;'>CRITICAL ERROR: db.php file missing.</div>"); 
}

// ২. সেশন সিকিউরিটি চেক
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
$user_id = (int) $_SESSION['user_id'];

// ৩. লগইন করা ইউজারের তথ্য ও রোল আহরণ
$me_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($me_stmt, "i", $user_id);
mysqli_stmt_execute($me_stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($me_stmt));
mysqli_stmt_close($me_stmt);

if (!$me) { 
    session_destroy(); 
    header("Location: login.php"); 
    exit(); 
}

$my_role = strtolower(trim($me['role'] ?? 'member'));
$is_admin = in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin']);
$is_super_admin = in_array($my_role, ['super admin', 'super_admin', 'superadmin']);

// ৪. "Finish Event" অ্যাকশন — শুধুমাত্র Super Admin এর জন্য
$finish_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish_operation'])) {
    if (!$is_super_admin) {
        $finish_error = "Only Super Admin can finish an operation.";
    } else {
        $op_id = (int) ($_POST['op_id'] ?? 0);

        mysqli_begin_transaction($conn);

        $lock_stmt = mysqli_prepare($conn, "SELECT id, total_required FROM fund_operations WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE");
        mysqli_stmt_bind_param($lock_stmt, "i", $op_id);
        mysqli_stmt_execute($lock_stmt);
        $op_row = mysqli_fetch_assoc(mysqli_stmt_get_result($lock_stmt));
        mysqli_stmt_close($lock_stmt);

        if (!$op_row) {
            mysqli_rollback($conn);
            $finish_error = "This operation is no longer active.";
        } else {
            $sum_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(required_amount), 0) as paid_total FROM district_contributions WHERE op_id = ? AND LOWER(status) IN ('paid', 'success')");
            mysqli_stmt_bind_param($sum_stmt, "i", $op_id);
            mysqli_stmt_execute($sum_stmt);
            $paid_total = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($sum_stmt))['paid_total'];
            mysqli_stmt_close($sum_stmt);

            $close_stmt = mysqli_prepare($conn, "UPDATE fund_operations SET status = 'completed', collected_amount = ? WHERE id = ? AND status = 'active'");
            mysqli_stmt_bind_param($close_stmt, "di", $paid_total, $op_id);
            mysqli_stmt_execute($close_stmt);
            $closed_ok = mysqli_stmt_affected_rows($close_stmt) > 0;
            mysqli_stmt_close($close_stmt);

            if ($closed_ok) {
                mysqli_commit($conn);
                header("Location: dashboard.php?closed=1");
                exit();
            } else {
                mysqli_rollback($conn);
                $finish_error = "Could not close this operation. Please try again.";
            }
        }
    }
}

// ৫. ড্যাশবোর্ড ওভারভিউ স্ট্যাটাস সামারি
$stats_stmt = mysqli_prepare($conn, "SELECT
    (SELECT COUNT(id) FROM users) as total_all_members,
    (SELECT COALESCE(SUM(amount), 0) FROM payment_requests WHERE LOWER(status) IN ('success', 'approved')) as total_raised,
    (SELECT COUNT(id) FROM payment_requests WHERE LOWER(status) IN ('success', 'approved')) as total_all_success,
    (SELECT COALESCE(SUM(collected_amount), 0) FROM fund_operations WHERE status = 'completed') as total_disbursed");
mysqli_stmt_execute($stats_stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt)) ?: ['total_all_members' => 0, 'total_raised' => 0, 'total_all_success' => 0, 'total_disbursed' => 0];
mysqli_stmt_close($stats_stmt);

$available_funds = (float) $summary['total_raised'] - (float) $summary['total_disbursed'];

// ৬. অ্যাকটিভ মিশন + জেলাভিত্তিক ডিস্ট্রিবিউশন তথ্য
$active_mission = null;
$target_user = null;
$districts = [];
$live_collected = 0;
$init_pct = 0;
$paid_count = 0;

$res = mysqli_query($conn, "SELECT * FROM fund_operations WHERE status = 'active' ORDER BY id DESC LIMIT 1");
if ($res && mysqli_num_rows($res) > 0) {
    $active_mission = mysqli_fetch_assoc($res);
    $op_id = (int) $active_mission['id'];

    if ($active_mission['target_batch_id'] !== 'LEGACY_FUND') {
        $t_id = $active_mission['target_batch_id'];
        $target_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE batch_id = ? LIMIT 1");
        mysqli_stmt_bind_param($target_stmt, "s", $t_id);
        mysqli_stmt_execute($target_stmt);
        $target_user = mysqli_fetch_assoc(mysqli_stmt_get_result($target_stmt));
        mysqli_stmt_close($target_stmt);
    }

    $dist_stmt = mysqli_prepare($conn, "SELECT * FROM district_contributions WHERE op_id = ? ORDER BY required_amount DESC");
    mysqli_stmt_bind_param($dist_stmt, "i", $op_id);
    mysqli_stmt_execute($dist_stmt);
    $dist_res = mysqli_stmt_get_result($dist_stmt);
    while ($row = mysqli_fetch_assoc($dist_res)) {
        $s = strtolower($row['status']);
        if ($s === 'paid' || $s === 'success') { 
            $paid_count++; 
            $live_collected += (float) $row['required_amount']; 
        }
        $districts[] = $row;
    }
    mysqli_stmt_close($dist_stmt);

    $req = (float) $active_mission['total_required'];
    $init_pct = $req > 0 ? min(100, (int) round($live_collected / $req * 100)) : 0;
}

// জেলা পেমেন্ট স্ট্যাটাস ব্যাজ নির্ধারণী ফাংশন
function district_stage($status) {
    $s = strtolower($status);
    if ($s === 'paid' || $s === 'success') return [3, 'done', 'Paid'];
    if ($s === 'waiting_super_admin') return [2, 'admin', 'Approved · waiting for Super Admin'];
    return [1, 'wait', 'Pending'];
}

function short_num($n) {
    $n = (float) $n;
    if ($n >= 10000000) return round($n / 10000000, 1) . 'Cr';
    if ($n >= 100000) return round($n / 100000, 1) . 'L';
    if ($n >= 1000) return round($n / 1000, 1) . 'K';
    return number_format($n);
}

// সময়ভিত্তিক অভিবাদন
$h = (int) date('G');
$greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');

// প্রোফাইল ছবির পাথ ডাইনামিক ফিল্টার
$user_photo_path = 'https://cdn-icons-png.flaticon.com/512/6522/6522516.png';
if (!empty($me['photo'])) {
    if (file_exists('uploads/' . $me['photo'])) {
        $user_photo_path = 'uploads/' . htmlspecialchars($me['photo']);
    } elseif (file_exists('../uploads/' . $me['photo'])) {
        $user_photo_path = '../uploads/' . htmlspecialchars($me['photo']);
    }
}

// নেভিগেশন লিঙ্কগুলোর ডাইনামিক ফিল্টার (রুট বা সাবফোল্ডারের সাথে সামঞ্জস্য রাখতে)
$link_fund_allocation = file_exists('fund_allocation.php') ? 'fund_allocation.php' : 'admin/fund_allocation.php';
$link_district_monitor = file_exists('district_monitor.php') ? 'district_monitor.php' : 'payment/district_monitor.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPHA HQ | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --ink: #eaf1ff; --muted: #8ea3cc; --accent: #2f6bff; --accent-2: #38bdf8; --accent-soft: rgba(47,107,255,.18);
            --card: rgba(255,255,255,.05); --line: rgba(130,170,255,.16);
            --ok: #34d399; --ok-bg: rgba(52,211,153,.14); --wait: #fbbf24; --wait-bg: rgba(251,191,36,.14);
            --info: #60a5fa; --info-bg: rgba(96,165,250,.16); --danger: #f87171; --danger-bg: rgba(248,113,113,.14);
            --display: 'Sora', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; color: var(--ink); font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(900px 520px at 90% -8%, rgba(47,107,255,.42), transparent 60%),
                radial-gradient(700px 520px at -12% 32%, rgba(56,189,248,.16), transparent 60%),
                linear-gradient(180deg, #061030 0%, #030817 100%) fixed; }
        button:focus-visible, a:focus-visible { outline: 3px solid var(--accent-2); outline-offset: 2px; }

        .wrap { max-width: 760px; margin: 0 auto; padding: 22px 12px 124px; }

        /* Hero Header */
        .hero { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 22px; padding: 0 4px; }
        .brand { display: flex; align-items: center; gap: 8px; font: 700 13px var(--display); color: var(--accent-2); margin-bottom: 14px; }
        .mark { width: 26px; height: 26px; border-radius: 9px; display: grid; place-items: center; color: #fff; font: 700 15px var(--display);
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 6px 18px rgba(47,107,255,.55); }
        .hero small { color: var(--muted); font-size: 13px; font-weight: 600; }
        .hero h1 { font: 700 28px/1.15 var(--display); letter-spacing: -.6px; word-break: break-word; }
        .avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
            border: 2px solid rgba(96,165,250,.7); box-shadow: 0 0 0 4px rgba(47,107,255,.18), 0 10px 26px rgba(47,107,255,.45); }

        .banner { padding: 13px 16px; border-radius: 16px; font-size: 13px; font-weight: 600; margin-bottom: 16px; border: 1px solid var(--line); }
        .banner.ok { background: var(--ok-bg); color: var(--ok); }
        .banner.err { background: var(--danger-bg); color: var(--danger); }

        /* Stats Cards */
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 8px; }
        .stat { background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03)); border: 1px solid var(--line);
            border-radius: 18px; padding: 11px 7px 11px 9px; min-width: 0; box-shadow: 0 12px 30px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.07); }
        .stat .ic { width: 30px; height: 30px; border-radius: 10px; display: grid; place-items: center; font-size: 13px; margin-bottom: 9px; }
        .stat small { display: block; color: var(--muted); font-size: 9.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stat h2 { font: 700 17px var(--display); letter-spacing: -.4px; margin-top: 2px; white-space: nowrap; }
        
        .strip { display: flex; gap: 8px; margin-bottom: 18px; }
        .strip div { flex: 1; min-width: 0; background: rgba(255,255,255,.04); border: 1px solid var(--line); border-radius: 14px; padding: 9px 12px; font-size: 11px; font-weight: 600; color: var(--muted); }
        .strip b { display: block; color: var(--ink); font: 700 14px var(--display); margin-top: 1px; }

        .card { background: linear-gradient(180deg, rgba(255,255,255,.075), rgba(255,255,255,.03)); border: 1px solid var(--line);
            backdrop-filter: blur(16px); border-radius: 26px; padding: 20px 16px; margin-bottom: 16px;
            box-shadow: 0 24px 50px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.07); }
        .card h3 { font: 700 15px var(--display); margin-bottom: 14px; display: flex; align-items: center; gap: 9px; }
        
        .chip { display: inline-block; font-size: 11px; font-weight: 700; padding: 5px 11px; border-radius: 999px; }
        .st-wait { background: var(--wait-bg); color: var(--wait); }
        .st-admin { background: var(--info-bg); color: var(--info); }
        .st-done { background: var(--ok-bg); color: var(--ok); }

        /* Current Requirement Card */
        .m-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
        .m-head .op { font-size: 12px; font-weight: 700; color: var(--accent-2); }
        .m-head .who { font: 700 18px var(--display); margin-top: 3px; word-break: break-word; }
        .m-head .who small { display: block; font: 600 12px 'Manrope', sans-serif; color: var(--muted); margin-top: 2px; }
        
        .finish-btn { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(248,113,113,.3); border-radius: 999px; padding: 9px 14px; font: 700 12px 'Manrope', sans-serif; cursor: pointer; white-space: nowrap; transition: 0.3s; }
        .finish-btn:hover { background: var(--danger); color: #fff; }
        
        .brief { font-size: 14px; line-height: 1.65; color: #c3d2f0; background: rgba(255,255,255,.04); border: 1px solid var(--line); border-radius: 16px; padding: 14px; word-break: break-word; margin-bottom: 14px; }
        
        .progress { display: flex; gap: 16px; align-items: center; color: #fff; border-radius: 20px; padding: 16px;
            background: linear-gradient(135deg, #1d3fb8 0%, #2f6bff 55%, #38bdf8 130%); box-shadow: 0 18px 40px rgba(47,107,255,.45), inset 0 1px 0 rgba(255,255,255,.25); }
        
        .ring { --p: 0; width: 86px; height: 86px; flex-shrink: 0; display: grid; place-items: center; position: relative; }
        .ring::before { content: ""; position: absolute; inset: 0; border-radius: 50%;
            background: conic-gradient(#fff calc(var(--p) * 1%), rgba(255,255,255,.22) 0);
            -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 9px), #000 calc(100% - 8px));
            mask: radial-gradient(farthest-side, transparent calc(100% - 9px), #000 calc(100% - 8px)); }
        .ring span { position: relative; font: 700 18px var(--display); }
        
        .progress .txt { flex-grow: 1; min-width: 0; }
        .progress small { opacity: .9; font-size: 12px; font-weight: 600; }
        .bar { height: 8px; background: rgba(255,255,255,.22); border-radius: 8px; margin: 8px 0; overflow: hidden; }
        .bar div { height: 100%; background: #fff; border-radius: 8px; transition: width 1s; }
        .amt { font-size: 13px; font-weight: 700; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }

        /* District Distribution Feed */
        .count { margin-left: auto; font: 700 12px 'Manrope', sans-serif; color: var(--muted); }
        .feed { max-height: 440px; overflow-y: auto; padding-right: 2px; scrollbar-width: thin; scrollbar-color: rgba(96,165,250,.4) transparent; }
        .task { background: rgba(255,255,255,.045); border: 1px solid var(--line); border-left: 4px solid var(--wait); border-radius: 18px; padding: 14px 15px; margin-bottom: 10px; }
        .task.admin { border-left-color: var(--info); }
        .task.done { border-left-color: var(--ok); }
        .task .top { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; }
        .task b { font: 700 14px var(--display); text-transform: uppercase; letter-spacing: .3px; word-break: break-word; }
        .task .money { font: 700 16px var(--display); white-space: nowrap; }
        .task .bottom { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        .steps { display: flex; gap: 5px; }
        .steps i { width: 22px; height: 5px; border-radius: 5px; background: rgba(255,255,255,.14); }
        .task.wait .steps i:nth-child(-n+1) { background: var(--wait); }
        .task.admin .steps i:nth-child(-n+2) { background: var(--info); }
        .task.done .steps i { background: var(--ok); }
        .idle, .empty { text-align: center; color: var(--muted); font-size: 13px; font-weight: 600; padding: 28px 12px; border: 1.5px dashed var(--line); border-radius: 16px; }

        /* Member Poll Section */
        .q { font: 600 16px/1.45 var(--display); margin-bottom: 14px; }
        .opt { width: 100%; text-align: left; background: rgba(255,255,255,.05); border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; margin-bottom: 10px; font: 600 14px 'Manrope', sans-serif; color: var(--ink); cursor: pointer; display: block; transition: 0.3s; }
        .opt:hover { border-color: var(--accent-2); background: var(--accent-soft); }
        .voted { position: relative; overflow: hidden; background: rgba(255,255,255,.05); border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; margin-bottom: 10px; }
        .voted.mine { border-color: var(--accent-2); }
        .voted .fill { position: absolute; inset: 0 auto 0 0; background: linear-gradient(90deg, rgba(47,107,255,.5), rgba(56,189,248,.28)); transition: width 1.2s; }
        .voted .row { position: relative; display: flex; justify-content: space-between; gap: 10px; font-size: 14px; font-weight: 700; }
        .voted small { color: #b5c6e8; font-weight: 600; font-size: 11px; margin-right: 8px; }

        /* Fixed Bottom Navigation */
        .bnav { position: fixed; left: 50%; transform: translateX(-50%); bottom: 12px; width: calc(100% - 24px); max-width: 520px; z-index: 50;
            display: flex; justify-content: space-around; gap: 2px; background: rgba(8,18,52,.72); backdrop-filter: blur(22px); border: 1px solid var(--line);
            border-radius: 26px; padding: 7px; box-shadow: 0 18px 50px rgba(0,0,0,.55); }
        .bnav a { flex: 1; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 8px 4px; border-radius: 19px; color: var(--muted); font-size: 11px; font-weight: 700; transition: 0.3s; }
        .bnav a i { font-size: 17px; }
        .bnav a:hover { background: var(--accent-soft); color: #fff; }
        .bnav a.active { color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 8px 22px rgba(47,107,255,.6); }

        @media (min-width: 720px) {
            .wrap { padding: 40px 16px 124px; }
            .stat { padding: 16px 14px; } .stat .ic { width: 38px; height: 38px; font-size: 15px; }
            .stat small { font-size: 12px; } .stat h2 { font-size: 24px; }
            .hero h1 { font-size: 34px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
<main class="wrap">

    <!-- 1. Header Greeting & User Avatar -->
    <header class="hero">
        <div>
            <div class="brand"><span class="mark">α</span>Alpha HQ</div>
            <small><?= htmlspecialchars($greet) ?></small>
            <h1><?= htmlspecialchars($me['full_name'] ?? 'Unknown Member') ?></h1>
        </div>
        <img class="avatar" src="<?= $user_photo_path ?>" alt="User Avatar">
    </header>

    <?php if (isset($_GET['closed'])): ?>
        <div class="banner ok"><i class="fas fa-circle-check"></i> Operation closed successfully. The collected amount has been disbursed from Total Fund.</div>
    <?php endif; ?>
    <?php if ($finish_error !== ''): ?>
        <div class="banner err"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($finish_error) ?></div>
    <?php endif; ?>

    <!-- 2. Intelligence Summary Stats Cards -->
    <section class="stats" aria-label="Summary">
        <div class="stat">
            <div class="ic" style="background:var(--accent-soft);color:var(--info)"><i class="fa-solid fa-users"></i></div>
            <small>Members</small><h2><?= number_format($summary['total_all_members']) ?></h2>
        </div>
        <div class="stat" title="৳<?= number_format($available_funds) ?>">
            <div class="ic" style="background:var(--ok-bg);color:var(--ok)"><i class="fa-solid fa-sack-dollar"></i></div>
            <small>Total Fund</small><h2 style="color:var(--ok)">৳<?= short_num($available_funds) ?></h2>
        </div>
        <div class="stat">
            <div class="ic" style="background:var(--info-bg);color:var(--accent-2)"><i class="fa-solid fa-right-left"></i></div>
            <small>Transactions</small><h2><?= number_format($summary['total_all_success']) ?></h2>
        </div>
        <div class="stat">
            <div class="ic" style="background:var(--wait-bg);color:var(--wait)"><i class="fa-solid fa-bullseye"></i></div>
            <small>Missions</small><h2><?= $active_mission ? '01' : '00' ?></h2>
        </div>
    </section>
    
    <div class="strip">
        <div>Raised<b>৳<?= number_format($summary['total_raised']) ?></b></div>
        <div>Disbursed<b>৳<?= number_format($summary['total_disbursed']) ?></b></div>
    </div>

    <!-- 3. Active Mission / Requirement Section -->
    <section class="card">
        <h3><i class="fa-solid fa-file-lines" style="color:var(--accent)"></i> Current Requirement</h3>
        <?php if ($active_mission): ?>
            <div class="m-head">
                <div>
                    <div class="op">Op #<?= (int) $active_mission['id'] ?></div>
                    <div class="who">
                        <?php if ($target_user): ?>
                            <?= htmlspecialchars($target_user['full_name'] ?? 'Unknown') ?>
                            <small>Batch #<?= htmlspecialchars($target_user['batch_id'] ?? 'N/A') ?> · <?= htmlspecialchars($target_user['district'] ?? 'N/A') ?></small>
                        <?php else: ?>
                            Legacy Fund
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($is_super_admin): ?>
                <form method="POST" onsubmit="return confirm('Finish this operation? ৳<?= number_format($live_collected) ?> collected so far will be deducted from Total Fund. Continue?');">
                    <input type="hidden" name="op_id" value="<?= (int) $active_mission['id'] ?>">
                    <button type="submit" name="finish_operation" class="finish-btn"><i class="fas fa-flag-checkered"></i> Finish Event</button>
                </form>
                <?php endif; ?>
            </div>

            <p class="brief"><?= !empty($active_mission['briefing']) ? nl2br(htmlspecialchars($active_mission['briefing'])) : "No detailed briefing provided." ?></p>

            <div class="progress">
                <div class="ring" id="ring" style="--p:<?= $init_pct ?>"><span id="syncPercent"><?= $init_pct ?>%</span></div>
                <div class="txt">
                    <small>Collected so far</small>
                    <div class="bar"><div id="matrixBar" style="width:<?= $init_pct ?>%"></div></div>
                    <div class="amt"><span id="colAmt">৳<?= number_format($live_collected) ?></span><span>of ৳<?= number_format($active_mission['total_required']) ?></span></div>
                </div>
            </div>
        <?php else: ?>
            <p class="idle">No active operation right now.</p>
        <?php endif; ?>
    </section>

    <!-- 4. District Fund Distribution List -->
    <?php if ($active_mission): ?>
    <section class="card">
        <h3><i class="fa-solid fa-tower-broadcast" style="color:var(--ok)"></i> District Fund Distribution
            <span class="count"><?= $paid_count ?>/<?= count($districts) ?> Paid</span></h3>
        <div class="feed">
            <?php if (!empty($districts)): foreach ($districts as $dist):
                [$stage, $tc, $label] = district_stage($dist['status']); ?>
                <div class="task <?= $tc ?>">
                    <div class="top">
                        <b><?= htmlspecialchars($dist['district_name']) ?></b>
                        <span class="money">৳<?= number_format($dist['required_amount']) ?></span>
                    </div>
                    <div class="bottom">
                        <span class="chip st-<?= $tc ?>"><?= $label ?></span>
                        <span class="steps" title="Stage <?= $stage ?> of 3"><i></i><i></i><i></i></span>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <p class="idle">No districts assigned to this operation yet.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 5. Real-Time Member Polling -->
    <section class="card">
        <h3><i class="fa-solid fa-square-poll-vertical" style="color:var(--accent)"></i> Member Poll</h3>
        <?php
        $poll_query = mysqli_query($conn, "SELECT * FROM polls WHERE status='active' ORDER BY id DESC LIMIT 1");
        if ($poll_query && mysqli_num_rows($poll_query) > 0):
            $poll = mysqli_fetch_assoc($poll_query);
            $pid = (int) $poll['id'];
            $opts = json_decode($poll['options']);

            $v_stmt = mysqli_prepare($conn, "SELECT option_index FROM poll_votes WHERE poll_id = ? AND user_id = ? LIMIT 1");
            mysqli_stmt_bind_param($v_stmt, "ii", $pid, $user_id);
            mysqli_stmt_execute($v_stmt);
            $v_result = mysqli_stmt_get_result($v_stmt);
            $has_voted = mysqli_num_rows($v_result) > 0;
            $user_choice = $has_voted ? mysqli_fetch_assoc($v_result)['option_index'] : -1;
            mysqli_stmt_close($v_stmt);

            $total_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM poll_votes WHERE poll_id = ?");
            mysqli_stmt_bind_param($total_stmt, "i", $pid);
            mysqli_stmt_execute($total_stmt);
            $total_votes = mysqli_fetch_assoc(mysqli_stmt_get_result($total_stmt))['total'] ?: 0;
            mysqli_stmt_close($total_stmt);
        ?>
            <p class="q"><?= htmlspecialchars($poll['question']) ?></p>
            <form action="vote_process.php" method="POST">
                <input type="hidden" name="poll_id" value="<?= $pid ?>">
                <?php if (is_array($opts) || is_object($opts)): foreach ($opts as $idx => $val):
                    $idx = (int) $idx;
                    $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM poll_votes WHERE poll_id = ? AND option_index = ?");
                    mysqli_stmt_bind_param($count_stmt, "ii", $pid, $idx);
                    mysqli_stmt_execute($count_stmt);
                    $count = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['count'];
                    mysqli_stmt_close($count_stmt);
                    $percent = ($total_votes > 0) ? round(($count / $total_votes) * 100) : 0;
                ?>
                    <?php if (!$has_voted): ?>
                        <button type="submit" name="option_index" value="<?= $idx ?>" class="opt"><?= htmlspecialchars($val) ?></button>
                    <?php else: ?>
                        <div class="voted <?= ($user_choice == $idx) ? 'mine' : '' ?>">
                            <div class="fill" style="width: <?= $percent ?>%;"></div>
                            <div class="row">
                                <span><?= htmlspecialchars($val) ?> <?php if ($user_choice == $idx) echo '<i class="fa-solid fa-circle-check" style="color:var(--accent);margin-left:4px"></i>'; ?></span>
                                <span><small><?= (int) $count ?> votes</small><?= $percent ?>%</span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; endif; ?>
            </form>
            <div style="text-align:right;margin-top:8px"><small style="color:var(--muted);font-weight:600">Total responses: <?= (int) $total_votes ?></small></div>
        <?php else: ?>
            <p class="empty">No active polls right now.</p>
        <?php endif; ?>
    </section>

</main>

<!-- 6. Mobile Fixed Bottom Navigation Bar -->
<nav class="bnav" aria-label="Main Navigation">
    <a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i>Home</a>
    <a href="members.php"><i class="fa-solid fa-users"></i>Members</a>
    <?php if ($is_super_admin): ?>
        <a href="<?= $link_fund_allocation ?>"><i class="fa-solid fa-microchip"></i>Super Admin</a>
    <?php endif; ?>
    <a href="<?= $link_district_monitor ?>"><i class="fa-solid fa-compass"></i>District</a>
    <a href="our_mission.php"><i class="fa-solid fa-flag"></i>Mission</a>
</nav>

<!-- 7. AJAX Live Sync for Active Mission Progress -->
<script>
function syncMission() {
    fetch('check_operation_status.php')
        .then(r => r.json())
        .then(res => {
            if (res && res.active) {
                var p = res.progress_percent;
                var bar = document.getElementById('matrixBar'), 
                    pct = document.getElementById('syncPercent'),
                    amt = document.getElementById('colAmt'), 
                    ring = document.getElementById('ring');
                
                if (bar) bar.style.width = p + '%';
                if (pct) pct.innerText = p + '%';
                if (ring) ring.style.setProperty('--p', p);
                if (amt) amt.innerText = '৳' + new Intl.NumberFormat().format(res.data.collected_amount);
            }
        })
        .catch(e => console.error("Live Sync Error:", e));
}
setInterval(syncMission, 5000); 
syncMission();
</script>

</body>
</html>