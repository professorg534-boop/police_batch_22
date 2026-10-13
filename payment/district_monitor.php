<?php
session_start();

// db.php: root-এ থাকলে 'db.php', সাবফোল্ডারে (যেমন payment/) থাকলে '../db.php'
if (file_exists('db.php')) {
    include 'db.php';
    $root = '';
} elseif (file_exists('../db.php')) {
    include '../db.php';
    $root = '../';
} else {
    die("Database connection file missing.");
}

// prepare ফেইল করলে exception না ছুঁড়ে false ফেরত দিক, যাতে নিচের ফলব্যাক কুয়েরিগুলো কাজ করে
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) { 
    header("Location: {$root}login.php"); 
    exit(); 
}
$user_id = (int) $_SESSION['user_id'];

// লগইন করা ইউজারের তথ্য (Prepared Statement)
$me_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($me_stmt, "i", $user_id);
mysqli_stmt_execute($me_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($me_stmt));
mysqli_stmt_close($me_stmt);

if (!$user_data) { 
    session_destroy(); 
    header("Location: {$root}login.php"); 
    exit(); 
}

$my_district = $user_data['district'] ?? '';
$is_admin = in_array(strtolower(trim($user_data['role'] ?? '')), ['admin', 'super admin', 'super_admin', 'superadmin']);

// ---- Real-time Intelligence Stats (Prepared Statements) ----
// ১. মোট সদস্য সংখ্যা
$stat_stmt = mysqli_prepare($conn, "SELECT COUNT(id) as total FROM users WHERE district = ?");
mysqli_stmt_bind_param($stat_stmt, "s", $my_district);
mysqli_stmt_execute($stat_stmt);
$total_personnel = mysqli_fetch_assoc(mysqli_stmt_get_result($stat_stmt))['total'] ?? 0;
mysqli_stmt_close($stat_stmt);

// ২. মোট ফান্ড সংগ্রহ (JOIN ব্যবহার করে নিরাপদ স্কিমা কোয়েরি)
$fund_stmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(pr.amount), 0) as total_amt 
    FROM payment_requests pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE u.district = ? AND LOWER(pr.status) IN ('approved', 'success')
");
if (!$fund_stmt) {
    // ফলব্যাক: যদি payment_requests টেবিলেই সরাসরি district কলাম থাকে
    $fund_stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount), 0) as total_amt FROM payment_requests WHERE district = ? AND LOWER(status) IN ('approved', 'success')");
}
$total_funds = 0;
if ($fund_stmt) {
    mysqli_stmt_bind_param($fund_stmt, "s", $my_district);
    mysqli_stmt_execute($fund_stmt);
    $total_funds = mysqli_fetch_assoc(mysqli_stmt_get_result($fund_stmt))['total_amt'] ?? 0;
    mysqli_stmt_close($fund_stmt);
}

// ৩. মোট ডোনেশন সংগ্রহ
$total_district_donations = 0;
$donation_stmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(pr.amount), 0) as total_amt 
    FROM payment_requests pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE u.district = ? AND pr.request_type = 'donation' AND LOWER(pr.status) IN ('approved', 'success')
");
if ($donation_stmt) {
    mysqli_stmt_bind_param($donation_stmt, "s", $my_district);
    mysqli_stmt_execute($donation_stmt);
    $total_district_donations = mysqli_fetch_assoc(mysqli_stmt_get_result($donation_stmt))['total_amt'] ?? 0;
    mysqli_stmt_close($donation_stmt);
} else {
    error_log("district_monitor.php donation_stmt prepare failed: " . mysqli_error($conn));
}

// ৪. সফল লেনদেন সংখ্যা
$tx_stmt = mysqli_prepare($conn, "
    SELECT COUNT(pr.id) as total_count 
    FROM payment_requests pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE u.district = ? AND LOWER(pr.status) IN ('approved', 'success')
");
if (!$tx_stmt) {
    $tx_stmt = mysqli_prepare($conn, "SELECT COUNT(id) as total_count FROM payment_requests WHERE district = ? AND LOWER(status) IN ('approved', 'success')");
}
$verified_tx = 0;
if ($tx_stmt) {
    mysqli_stmt_bind_param($tx_stmt, "s", $my_district);
    mysqli_stmt_execute($tx_stmt);
    $verified_tx = mysqli_fetch_assoc(mysqli_stmt_get_result($tx_stmt))['total_count'] ?? 0;
    mysqli_stmt_close($tx_stmt);
}

// ৫. প্যান্ডিং রিকোয়েস্ট সংখ্যা (এডমিনদের জন্য)
$pending_count = 0;
if ($is_admin) {
    $pend_stmt = mysqli_prepare($conn, "
        SELECT COUNT(pr.id) as total 
        FROM payment_requests pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE u.district = ? AND LOWER(pr.status) = 'pending'
    ");
    if (!$pend_stmt) {
        $pend_stmt = mysqli_prepare($conn, "SELECT COUNT(id) as total FROM payment_requests WHERE district = ? AND LOWER(status) = 'pending'");
    }
    if ($pend_stmt) {
        mysqli_stmt_bind_param($pend_stmt, "s", $my_district);
        mysqli_stmt_execute($pend_stmt);
        $pending_count = mysqli_fetch_assoc(mysqli_stmt_get_result($pend_stmt))['total'] ?? 0;
        mysqli_stmt_close($pend_stmt);
    }
}

// ---- Regional Roster (LEFT JOIN Optimized Query) ----
$roster_stmt = mysqli_prepare($conn, "
    SELECT u.id, u.full_name, u.mobile_number, u.role, u.batch_id,
           COUNT(CASE WHEN LOWER(pr.status) IN ('approved', 'success') AND COALESCE(pr.request_type, 'monthly') = 'monthly' THEN pr.id END) as tx_count,
           COALESCE(SUM(CASE WHEN LOWER(pr.status) IN ('approved', 'success') AND COALESCE(pr.request_type, 'monthly') = 'monthly' THEN pr.amount END), 0) as total_paid,
           COALESCE(SUM(CASE WHEN LOWER(pr.status) IN ('approved', 'success') AND pr.request_type = 'donation' THEN pr.amount END), 0) as total_donation
    FROM users u
    LEFT JOIN payment_requests pr ON pr.user_id = u.id
    WHERE u.district = ?
    GROUP BY u.id, u.full_name, u.mobile_number, u.role, u.batch_id
    ORDER BY total_paid DESC
");

if (!$roster_stmt) {
    error_log("district_monitor.php roster_stmt prepare failed: " . mysqli_error($conn) . " — falling back to monthly-only query.");
    $roster_stmt = mysqli_prepare($conn, "
        SELECT u.id, u.full_name, u.mobile_number, u.role, u.batch_id,
               COUNT(CASE WHEN LOWER(pr.status) IN ('approved', 'success') THEN pr.id END) as tx_count,
               COALESCE(SUM(CASE WHEN LOWER(pr.status) IN ('approved', 'success') THEN pr.amount END), 0) as total_paid,
               0 as total_donation
        FROM users u
        LEFT JOIN payment_requests pr ON pr.user_id = u.id
        WHERE u.district = ?
        GROUP BY u.id, u.full_name, u.mobile_number, u.role, u.batch_id
        ORDER BY total_paid DESC
    ");
}

$member_list_res = false;
if ($roster_stmt) {
    mysqli_stmt_bind_param($roster_stmt, "s", $my_district);
    mysqli_stmt_execute($roster_stmt);
    $member_list_res = mysqli_stmt_get_result($roster_stmt);
}

function short_num($n) {
    $n = (float) $n;
    if ($n >= 10000000) return round($n / 10000000, 1) . 'Cr';
    if ($n >= 100000) return round($n / 100000, 1) . 'L';
    if ($n >= 1000) return round($n / 1000, 1) . 'K';
    return number_format($n);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPHA HQ | District Intelligence</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --ink: #eaf1ff; --muted: #8ea3cc; --accent: #2f6bff; --accent-2: #38bdf8; --accent-soft: rgba(47,107,255,.18);
            --line: rgba(130,170,255,.16);
            --ok: #34d399; --ok-bg: rgba(52,211,153,.14); --wait: #fbbf24; --wait-bg: rgba(251,191,36,.14);
            --info: #60a5fa; --info-bg: rgba(96,165,250,.16);
            --display: 'Sora', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; color: var(--ink); font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(900px 520px at 90% -8%, rgba(47,107,255,.42), transparent 60%),
                radial-gradient(700px 520px at -12% 32%, rgba(56,189,248,.16), transparent 60%),
                linear-gradient(180deg, #061030 0%, #030817 100%) fixed; }
        a:focus-visible { outline: 3px solid var(--accent-2); outline-offset: 2px; }

        .wrap { max-width: 980px; margin: 0 auto; padding: 22px 12px 124px; }

        /* Header / Hero */
        .hero { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 22px; padding: 0 4px; }
        .avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid rgba(96,165,250,.7); box-shadow: 0 0 0 4px rgba(47,107,255,.18), 0 10px 26px rgba(47,107,255,.45); }
        .brand { display: flex; align-items: center; gap: 8px; font: 700 13px var(--display); color: var(--accent-2); margin-bottom: 14px; }
        .mark { width: 26px; height: 26px; border-radius: 9px; display: grid; place-items: center; color: #fff; font: 700 15px var(--display);
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 6px 18px rgba(47,107,255,.55); }
        .hero h1 { font: 700 28px/1.15 var(--display); letter-spacing: -.6px; }
        .dname { margin-top: 6px; display: flex; align-items: center; gap: 9px; font: 700 20px var(--display); color: var(--accent-2); word-break: break-word; }
        .dname i { font-size: 16px; }

        /* Summary Stats Cards */
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-bottom: 18px; }
        .stat { background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03)); border: 1px solid var(--line);
            border-radius: 18px; padding: 12px 10px; min-width: 0; box-shadow: 0 12px 30px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.07); }
        .stat .ic { width: 30px; height: 30px; border-radius: 10px; display: grid; place-items: center; font-size: 13px; margin-bottom: 9px; }
        .stat small { display: block; color: var(--muted); font-size: 10.5px; font-weight: 700; line-height: 1.3; min-height: 2.6em; }
        .stat h2 { font: 700 20px var(--display); letter-spacing: -.4px; margin-top: 2px; white-space: nowrap; }

        .alert { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--wait); background: var(--wait-bg);
            border: 1px solid rgba(251,191,36,.3); border-radius: 16px; padding: 12px 14px; font-size: 13px; font-weight: 700; margin-bottom: 16px; }
        .alert i:last-child { margin-left: auto; }

        /* Table Card Section */
        .card { background: linear-gradient(180deg, rgba(255,255,255,.075), rgba(255,255,255,.03)); border: 1px solid var(--line);
            backdrop-filter: blur(16px); border-radius: 26px; padding: 20px 14px; margin-bottom: 16px;
            box-shadow: 0 24px 50px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.07); }
        .card h3 { font: 700 15px var(--display); margin: 0 4px 14px; display: flex; align-items: center; gap: 9px; }
        .count { margin-left: auto; font: 700 12px 'Manrope', sans-serif; color: var(--muted); }

        .tscroll { overflow-x: auto; margin: 0 -6px; padding: 0 6px; scrollbar-width: thin; scrollbar-color: rgba(96,165,250,.4) transparent; }
        table { width: 100%; border-collapse: collapse; min-width: 780px; }
        th { text-align: left; padding: 10px 14px; font-size: 11px; font-weight: 700; color: var(--muted); border-bottom: 1px solid var(--line); white-space: nowrap; }
        td { padding: 14px; font-size: 14px; border-bottom: 1px solid rgba(130,170,255,.08); vertical-align: middle; }
        th, td { border-right: 1px solid rgba(130,170,255,.10); }
        th:last-child, td:last-child { border-right: none; }
        th:first-child, td:first-child { position: sticky; left: 0; z-index: 1; background: #0c1a45; }
        tbody tr:hover td:not(:first-child) { background: rgba(47,107,255,.06); }
        tbody tr:hover td:first-child { background: #12235a; }
        
        .who { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .av { width: 38px; height: 38px; border-radius: 13px; flex-shrink: 0; display: grid; place-items: center; font: 700 15px var(--display); color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 6px 16px rgba(47,107,255,.4); }
        .name { font-weight: 700; word-break: break-word; }
        .sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .batch { font: 700 13px var(--display); color: var(--info); }
        .badge { background: var(--info-bg); color: var(--info); padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 700; display: inline-block; text-transform: capitalize; }
        .tx b { font-weight: 800; } .tx span { color: var(--muted); font-size: 12px; }
        .amt { font: 700 16px var(--display); color: var(--ok); }
        .special { font: 700 13px var(--display); color: var(--wait); background: var(--wait-bg); padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; }
        .special i { font-size: 9px; }
        .none { color: var(--muted); }
        .empty, td.empty { position: static !important; background: none !important; text-align: center; color: var(--muted); font-size: 13px; font-weight: 600; padding: 36px 12px; }

        /* Bottom Fixed Navigation Bar */
        .bnav { position: fixed; left: 50%; transform: translateX(-50%); bottom: 12px; width: calc(100% - 24px); max-width: 520px; z-index: 50;
            display: flex; justify-content: space-around; gap: 2px; background: rgba(8,18,52,.72); backdrop-filter: blur(22px); border: 1px solid var(--line);
            border-radius: 26px; padding: 7px; box-shadow: 0 18px 50px rgba(0,0,0,.55); }
        .bnav a { flex: 1; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 8px 4px; border-radius: 19px; color: var(--muted); font-size: 11px; font-weight: 700; }
        .bnav a i { font-size: 17px; }
        .bnav a:hover { background: var(--accent-soft); color: #fff; }
        .bnav a.active { color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 8px 22px rgba(47,107,255,.6); }

        @media (min-width: 720px) {
            .wrap { padding: 40px 16px 124px; }
            .hero h1 { font-size: 34px; }
            .stat { padding: 18px 16px; } .stat .ic { width: 38px; height: 38px; font-size: 15px; }
            .stat small { font-size: 12px; } .stat h2 { font-size: 26px; }
            .card { padding: 24px 20px; }
        }
    </style>
</head>
<body>
<main class="wrap">

    <header class="hero">
        <div>
            <div class="brand"><span class="mark">α</span>Alpha HQ</div>
            <h1>Regional Intelligence</h1>
            <div class="dname"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($my_district ?: 'N/A') ?></div>
        </div>
        <img class="avatar" src="<?= (!empty($user_data['photo'])) ? $root . 'uploads/' . htmlspecialchars($user_data['photo']) : 'https://cdn-icons-png.flaticon.com/512/6522/6522516.png' ?>" alt="User Avatar">
    </header>

    <!-- Summary Stats Cards Section -->
    <section class="stats" aria-label="District summary">
        <div class="stat">
            <div class="ic" style="background:var(--accent-soft);color:var(--info)"><i class="fa-solid fa-users"></i></div>
            <small>District Total<br>Personnel</small><h2><?= number_format($total_personnel) ?></h2>
        </div>
        <div class="stat" title="৳<?= number_format($total_funds) ?>">
            <div class="ic" style="background:var(--ok-bg);color:var(--ok)"><i class="fa-solid fa-sack-dollar"></i></div>
            <small>District Total<br>Fund</small><h2 style="color:var(--ok)">৳<?= short_num($total_funds) ?></h2>
        </div>
        <div class="stat">
            <div class="ic" style="background:var(--info-bg);color:var(--accent-2)"><i class="fa-solid fa-right-left"></i></div>
            <small>District Total<br>Transactions</small><h2><?= number_format($verified_tx) ?></h2>
        </div>
    </section>

    <?php if ($is_admin && $pending_count > 0): ?>
    <a class="alert" href="district_administry.php">
        <i class="fa-solid fa-bell"></i> <?= number_format($pending_count) ?> pending request<?= $pending_count == 1 ? '' : 's' ?> need review
        <i class="fa-solid fa-chevron-right"></i>
    </a>
    <?php endif; ?>

    <!-- Regional Roster Card & Table -->
    <section class="card">
        <h3><i class="fa-solid fa-list-check" style="color:var(--accent-2)"></i> Regional Roster
            <span class="count"><?= number_format($total_personnel) ?> members</span></h3>
        <div class="tscroll">
        <table>
            <thead>
                <tr>
                    <th>Name and contact</th>
                    <th>Batch id</th>
                    <th>Classification / Role</th>
                    <th>Transactions</th>
                    <th>Amount of money</th>
                    <th>Special transaction</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$member_list_res || mysqli_num_rows($member_list_res) === 0): ?>
                <tr><td colspan="6" class="empty">No personnel found in this district.</td></tr>
                <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($member_list_res)):
                    $nm = trim($row['full_name'] ?? '') !== '' ? $row['full_name'] : 'Unknown';
                    $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($nm, 0, 1)) : strtoupper(substr($nm, 0, 1));
                ?>
                <tr>
                    <td>
                        <div class="who">
                            <div class="av"><?= htmlspecialchars($initial) ?></div>
                            <div>
                                <div class="name"><?= htmlspecialchars($nm) ?></div>
                                <div class="sub"><?= htmlspecialchars($row['mobile_number'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Batch id"><?php if (!empty($row['batch_id'])): ?><span class="batch">#<?= htmlspecialchars($row['batch_id']) ?></span><?php else: ?><span class="none">—</span><?php endif; ?></td>
                    <td data-label="Classification / Role"><span class="badge"><?= htmlspecialchars($row['role'] ?: 'Member') ?></span></td>
                    <td data-label="Transactions" class="tx"><b><?= (int) $row['tx_count'] ?></b> <span>success</span></td>
                    <td data-label="Amount of money"><span class="amt">৳<?= number_format($row['total_paid'] ?? 0) ?></span></td>
                    <td data-label="Special transaction">
                        <?php if (($row['total_donation'] ?? 0) > 0): ?>
                            <span class="special"><i class="fas fa-plus"></i>৳<?= number_format($row['total_donation']) ?></span>
                        <?php else: ?>
                            <span class="none">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>

<!-- Bottom Fixed Navigation Links -->
<nav class="bnav" aria-label="Main">
    <a href="<?= $root ?>dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
    <a href="district_monitor.php" class="active" aria-current="page"><i class="fa-solid fa-compass"></i>District</a>
    <a href="payment_request.php"><i class="fa-solid fa-wallet"></i>Deposits</a>
    <?php if ($is_admin): ?>
    <a href="district_administry.php"><i class="fa-solid fa-shield-halved"></i>Administry</a>
    <?php endif; ?>
</nav>

</body>
</html>