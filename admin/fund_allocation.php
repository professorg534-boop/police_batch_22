<?php
session_start();

if (file_exists('../db.php')) { include '../db.php'; $root = '../'; }
elseif (file_exists('db.php')) { include 'db.php'; $root = ''; }
else { die("Database connection file missing."); }
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) { header("Location: {$root}login.php"); exit(); }
$user_id = (int) $_SESSION['user_id'];
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// ---- Admin check ----
$my_role = '';
if ($st = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($st, "i", $user_id);
    mysqli_stmt_execute($st);
    $r = mysqli_stmt_get_result($st);
    if ($r && $row = mysqli_fetch_assoc($r)) { $my_role = strtolower(trim($row['role'] ?? '')); }
    mysqli_stmt_close($st);
}
if (!in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin'], true)) {
    http_response_code(403);
    exit("Access Denied: Administrative authority required.");
}

// ---- Batch ID search (AJAX) — ei file er bhitorei, alada get_user_by_batch.php lagbe na ----
if (isset($_GET['search_member'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $out = ['success' => false, 'exact' => false, 'users' => [], 'message' => ''];
    $q = trim((string) $_GET['search_member']);
    if ($q === '' || mb_strlen($q) > 30) { $out['message'] = 'Enter a Batch ID.'; echo json_encode($out); exit(); }
    $like = '%' . addcslashes($q, '\\%_') . '%';
    $st = mysqli_prepare($conn, "SELECT * FROM users WHERE TRIM(CAST(batch_id AS CHAR)) LIKE ?
                                 ORDER BY (TRIM(CAST(batch_id AS CHAR)) = ?) DESC, CHAR_LENGTH(batch_id) ASC, batch_id ASC LIMIT 8");
    if (!$st) {
        error_log("fund_allocation.php search prepare failed: " . mysqli_error($conn));
        $out['message'] = 'Search is unavailable right now.';
        echo json_encode($out); exit();
    }
    mysqli_stmt_bind_param($st, "ss", $like, $q);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $pick = function ($r, $keys) { foreach ($keys as $k) { if (isset($r[$k]) && trim((string) $r[$k]) !== '') { return trim((string) $r[$k]); } } return ''; };
    while ($res && $r = mysqli_fetch_assoc($res)) {
        $out['users'][] = [
            'batch_id' => $pick($r, ['batch_id']), 'full_name' => $pick($r, ['full_name']), 'bp_number' => $pick($r, ['bp_number', 'bp']),
            'mobile' => $pick($r, ['mobile_number', 'phone']), 'gender' => $pick($r, ['gender', 'sex']), 'blood_group' => $pick($r, ['blood_group', 'blood']),
            'division' => $pick($r, ['division']), 'district' => $pick($r, ['district']), 'role' => $pick($r, ['role']), 'photo' => $pick($r, ['photo']),
        ];
    }
    mysqli_stmt_close($st);
    $out['success'] = count($out['users']) > 0;
    $out['exact'] = $out['success'] && strcasecmp($out['users'][0]['batch_id'], $q) === 0;
    if (!$out['success']) { $out['message'] = 'No member found for this Batch ID.'; }
    echo json_encode($out); exit();
}

// ---- District name matching: spelling/case/space farak hole-o ek district dhora hobe ----
function dkey($s) {
    static $alias = ['comilla' => 'cumilla', 'jessore' => 'jashore', 'khagrachhari' => 'khagrachari', 'coxbazar' => 'coxsbazar', 'bogura' => 'bogra',
                     'chattogram' => 'chittagong', 'barishal' => 'barisal', 'jhalakathi' => 'jhalokati', 'maulvibazar' => 'moulvibazar',
                     'netrakona' => 'netrokona', 'narshingdi' => 'narsingdi'];
    $k = strtolower(preg_replace('/[^a-z]/i', '', (string) $s));
    if ($k === '') { $k = mb_strtolower(trim((string) $s)); }
    return $alias[$k] ?? $k;
}
// Taka ke paisa te bhag kora: total ta thik amount er soman thake (largest remainder)
function split_paisa(array $bals, $amount) {
    $out = array_fill(0, count($bals), 0);
    $total = array_sum($bals);
    if ($total <= 0 || $amount <= 0) { return $out; }
    $ord = []; $used = 0;
    foreach ($bals as $i => $b) {
        if ($b <= 0) { continue; }
        $x = ($b * $amount) / $total; $f = (int) floor($x);
        $out[$i] = $f; $used += $f; $ord[] = [$x - $f, $i];
    }
    usort($ord, function ($a, $b) { return ($b[0] <=> $a[0]) ?: ($a[1] <=> $b[1]); });
    $left = (int) $amount - $used;
    for ($j = 0; $j < count($ord) && $left > 0; $j++, $left--) { $out[$ord[$j][1]]++; }
    for ($j = count($ord) - 1; $j >= 0 && $left < 0; $j--) { if ($out[$ord[$j][1]] > 0) { $out[$ord[$j][1]]--; $left++; } }
    return $out;
}

// members.php / registration er district talika (64 ti)
$all_64_districts = [
    "Dhaka", "Gazipur", "Narayanganj", "Tangail", "Faridpur", "Manikganj", "Munshiganj", "Rajbari", "Madaripur", "Gopalganj", "Shariatpur", "Kishoreganj", "Narsingdi",
    "Chittagong", "Cox's Bazar", "Cumilla", "Feni", "Brahmanbaria", "Noakhali", "Lakshmipur", "Chandpur", "Rangamati", "Khagrachari", "Bandarban",
    "Rajshahi", "Bogra", "Pabna", "Naogaon", "Natore", "Sirajganj", "Chapai Nawabganj", "Joypurhat",
    "Khulna", "Jashore", "Kushtia", "Satkhira", "Bagerhat", "Chuadanga", "Meherpur", "Narail", "Magura", "Jhenaidah",
    "Barisal", "Bhola", "Patuakhali", "Pirojpur", "Jhalokati", "Barguna",
    "Sylhet", "Moulvibazar", "Habiganj", "Sunamganj",
    "Rangpur", "Dinajpur", "Kurigram", "Gaibandha", "Nilphamari", "Panchagarh", "Thakurgaon", "Lalmonirhat",
    "Mymensingh", "Jamalpur", "Netrokona", "Sherpur"
];
$map = [];
foreach ($all_64_districts as $n) { $map[dkey($n)] = ['name' => $n, 'paisa' => 0]; }

// Database e users ra je spelling e ache (admin er district er sathe mile jabe)
$spell = [];
if ($ur = mysqli_query($conn, "SELECT TRIM(district) AS d, COUNT(*) AS c FROM users WHERE district IS NOT NULL AND TRIM(district) <> '' GROUP BY TRIM(district)")) {
    while ($r = mysqli_fetch_assoc($ur)) {
        $k = dkey($r['d']);
        if (!isset($spell[$k]) || (int) $r['c'] > $spell[$k][1]) { $spell[$k] = [$r['d'], (int) $r['c']]; }
    }
}
foreach ($map as $k => &$m) { if (isset($spell[$k]) && strcasecmp($spell[$k][0], $m['name']) !== 0) { $m['name'] = $spell[$k][0]; } }
unset($m);

// Prottek jelar approved funding (payment_requests.district, na thakle member er district)
$bq = mysqli_query($conn, "SELECT COALESCE(NULLIF(TRIM(pr.district), ''), NULLIF(TRIM(u.district), ''), '') AS d, COALESCE(SUM(pr.amount), 0) AS t
                           FROM payment_requests pr LEFT JOIN users u ON u.id = pr.user_id
                           WHERE LOWER(pr.status) IN ('approved', 'success') GROUP BY d");
if (!$bq) {
    $bq = mysqli_query($conn, "SELECT TRIM(district) AS d, COALESCE(SUM(amount), 0) AS t FROM payment_requests WHERE LOWER(status) IN ('approved', 'success') GROUP BY d");
}
$unassigned_paisa = 0;
while ($bq && $r = mysqli_fetch_assoc($bq)) {
    $p = (int) round((float) $r['t'] * 100);
    $dn = trim((string) $r['d']);
    if ($dn === '') { $unassigned_paisa += $p; continue; }
    $k = dkey($dn);
    if (!isset($map[$k])) { $map[$k] = ['name' => $dn, 'paisa' => 0]; }
    $map[$k]['paisa'] += $p;
}
uasort($map, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
$districts_data = array_values($map);
$bals = array_column($districts_data, 'paisa');
$total_paisa = (int) array_sum($bals);
$total_funds = $total_paisa / 100;

// ---- Deployment ----
$allowed_types = ['Emergency Support (Batchmate/Family)', 'Legacy Tribute (Deceased Batchmates)'];
$deploy_error = '';
$old_type = $allowed_types[0]; $old_amount = ''; $old_brief = ''; $old_target = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deploy_poll'])) {
    $op_type    = (string) ($_POST['op_type'] ?? '');
    $target_id  = trim((string) ($_POST['target_id'] ?? ''));
    $req_amount = round((float) ($_POST['total_req_amount'] ?? 0), 2);
    $briefing   = trim((string) ($_POST['briefing'] ?? ''));

    $old_type   = in_array($op_type, $allowed_types, true) ? $op_type : $allowed_types[0];
    $old_amount = ($req_amount > 0 && is_finite($req_amount)) ? (string) $req_amount : '';
    $old_brief  = $briefing;
    $old_target = ($target_id !== 'LEGACY_FUND') ? $target_id : '';
    $req_paisa  = is_finite($req_amount) ? (int) round($req_amount * 100) : 0;

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $deploy_error = "Session expired. Reload the page and try again.";
    } elseif (!in_array($op_type, $allowed_types, true)) {
        $deploy_error = "Please choose a valid selection reason.";
    } elseif ($req_paisa <= 0) {
        $deploy_error = "Enter an amount greater than zero.";
    } elseif ($req_paisa > $total_paisa) {
        $deploy_error = "The amount is more than the total funding (৳" . number_format($total_funds, 2) . ").";
    } elseif ($briefing === '') {
        $deploy_error = "Add the context for this deployment.";
    } elseif (strpos($op_type, 'Legacy Tribute') !== false) {
        $target_id = 'LEGACY_FUND';
    } else {
        $found = '';
        if ($target_id !== '' && ($chk = mysqli_prepare($conn, "SELECT batch_id FROM users WHERE TRIM(CAST(batch_id AS CHAR)) = ? LIMIT 1"))) {
            mysqli_stmt_bind_param($chk, "s", $target_id);
            mysqli_stmt_execute($chk);
            $cr = mysqli_stmt_get_result($chk);
            $ex = $cr ? mysqli_fetch_assoc($cr) : null;
            mysqli_stmt_close($chk);
            if ($ex) { $found = trim((string) $ex['batch_id']); }
        }
        if ($found === '') { $deploy_error = "Search and select a valid member by Batch ID."; } else { $target_id = $found; }
    }

    if ($deploy_error === '') {
        $shares = split_paisa($bals, $req_paisa);
        $percentage = ($req_paisa / $total_paisa) * 100;

        mysqli_begin_transaction($conn);
        try {
            $insert_op = mysqli_prepare($conn, "INSERT INTO fund_operations (target_batch_id, op_type, total_required, percentage_load, briefing, status, collected_amount, created_at) VALUES (?, ?, ?, ?, ?, 'active', 0, NOW())");
            if (!$insert_op) { throw new Exception(mysqli_error($conn)); }
            mysqli_stmt_bind_param($insert_op, "ssdds", $target_id, $op_type, $req_amount, $percentage, $briefing);
            if (!mysqli_stmt_execute($insert_op)) { throw new Exception(mysqli_stmt_error($insert_op)); }
            $op_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insert_op);

            $insert_dc = mysqli_prepare($conn, "INSERT INTO district_contributions (op_id, district_name, required_amount, paid_amount, status) VALUES (?, ?, ?, 0, 'pending')");
            if (!$insert_dc) { throw new Exception(mysqli_error($conn)); }
            $d_name = ''; $d_share = 0.0;
            mysqli_stmt_bind_param($insert_dc, "isd", $op_id, $d_name, $d_share);
            foreach ($districts_data as $i => $dist) {
                $d_name  = $dist['name'];
                $d_share = $shares[$i] / 100;
                if (!mysqli_stmt_execute($insert_dc)) { throw new Exception(mysqli_stmt_error($insert_dc)); }
            }
            mysqli_stmt_close($insert_dc);

            mysqli_commit($conn);
            echo "<script>alert('Deployment Successful! Units Notified.'); window.location.href='" . h($root) . "dashboard.php';</script>";
            exit();
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log("fund_allocation.php deploy failed: " . $e->getMessage());
            $deploy_error = "Deployment failed. Nothing was saved. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>ALPHA HQ | Fund Deployment</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--ink:#eaf1ff;--muted:#8ea3cc;--accent:#2f6bff;--accent-2:#38bdf8;--accent-soft:rgba(47,107,255,.18);--line:rgba(130,170,255,.16);--ok:#34d399;--wait:#fbbf24;--info:#60a5fa;--danger:#f87171;--danger-bg:rgba(248,113,113,.14);--surface:rgba(255,255,255,.04);--card-bg:#0b1739;--display:'Sora',sans-serif}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:100%;overflow-x:hidden;background:#030817;color:var(--ink);font-family:'Manrope',sans-serif}
body{min-height:100vh;background:radial-gradient(900px 520px at 90% -8%,rgba(47,107,255,.35),transparent 60%),radial-gradient(700px 520px at -12% 32%,rgba(56,189,248,.15),transparent 60%),linear-gradient(180deg,#061030,#030817) fixed;padding-bottom:95px}
.wrap{width:100%;max-width:680px;margin:0 auto;padding:16px 12px}
.hero{margin-bottom:14px;padding:0 4px}
.brand{display:flex;align-items:center;gap:8px;font:700 12px var(--display);color:var(--accent-2);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.mark{width:22px;height:22px;border-radius:6px;display:grid;place-items:center;color:#fff;font:700 12px var(--display);background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 12px rgba(47,107,255,.4)}
.hero h1{font:700 22px/1.2 var(--display);letter-spacing:-.4px;color:#fff}
.banner{padding:10px 12px;border-radius:12px;font-size:12px;font-weight:600;margin-bottom:12px;border:1px solid rgba(248,113,113,.3);background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;gap:8px}
.card{background:var(--card-bg);border:1px solid var(--line);border-radius:16px;padding:14px;margin-bottom:12px;box-shadow:0 12px 30px rgba(0,0,0,.25)}
.card h3{font:700 14px var(--display);margin-bottom:10px;display:flex;align-items:center;gap:8px;color:#fff}
label,.lbl{display:block;font-size:11px;font-weight:700;color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px}
input,select,textarea{width:100%;background:rgba(0,0,0,.25);border:1px solid var(--line);border-radius:10px;padding:10px 12px;color:var(--ink);font:600 13px 'Manrope',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s}
input::placeholder,textarea::placeholder{color:#5a6e94;font-weight:500}
input:focus,select:focus,textarea:focus{border-color:var(--accent-2);box-shadow:0 0 0 3px rgba(56,189,248,.15)}
select option{background:#081232;color:#fff}
textarea{resize:vertical;min-height:75px;line-height:1.4}
.top-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px}
.top-row .card-inner{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:10px}
.top-row.single{grid-template-columns:1fr}
.top-row.single #searchBox{display:none}
.placeholder{text-align:center;color:var(--muted);font-size:12px;font-weight:600;padding:16px 10px;border:1.5px dashed var(--line);border-radius:12px}
.placeholder.bad{color:var(--danger);border-color:rgba(248,113,113,.4)}
.m-top{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.m-top img{width:44px;height:44px;border-radius:12px;object-fit:cover;border:2px solid rgba(96,165,250,.6)}
.chip{display:inline-block;font-size:10px;font-weight:700;padding:3px 8px;border-radius:999px;background:var(--accent-soft);color:var(--info);text-transform:uppercase}
.chip.sel{background:rgba(52,211,153,.16);color:var(--ok);margin-left:6px}
.f-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
.f{background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:6px 8px;min-width:0}
.f span{display:block;font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:2px}
.f b{font-size:12px;font-weight:700;word-break:break-word;color:#fff}
.sugg{display:flex;flex-direction:column;gap:6px}
.s-item{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;text-align:left;cursor:pointer;background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:10px 12px;color:#fff;font:700 13px 'Manrope',sans-serif}
.s-item:hover,.s-item:active{border-color:var(--accent-2);background:var(--accent-soft)}
.s-item small{display:block;color:var(--muted);font-size:11px;font-weight:600;margin-top:2px}
.s-item .bid{font:700 13px var(--display);color:var(--info);white-space:nowrap}
.amt-row{display:grid;grid-template-columns:1.2fr 1fr;gap:10px}
.amt-row input{font:700 16px var(--display);color:var(--accent-2)}
.pct{background:rgba(0,0,0,.25);border:1px solid var(--line);border-radius:10px;padding:8px 10px;font:700 15px var(--display);min-height:41px;display:flex;align-items:center;word-break:break-all;color:var(--ok)}
.pct.over{color:var(--danger);border-color:rgba(248,113,113,.4)}
.meter{height:4px;background:rgba(255,255,255,.08);border-radius:4px;margin-top:8px;overflow:hidden}
.meter div{height:100%;width:0;background:linear-gradient(90deg,var(--accent),var(--accent-2));border-radius:4px;transition:width .3s ease}
.hint{margin-top:8px;font-size:11.5px;font-weight:700;color:var(--muted)}
.hint b{color:var(--ok)}.hint.over,.hint.over b{color:var(--danger)}
.map-head{display:flex;justify-content:space-between;font-size:10px;font-weight:700;color:var(--muted);padding:0 4px 6px;text-transform:uppercase}
.map-list{max-height:300px;overflow-y:auto;padding-right:2px;scrollbar-width:thin}
.d-row{display:flex;align-items:center;justify-content:space-between;gap:10px;background:var(--surface);border:1px solid var(--line);border-radius:10px;margin-bottom:6px;padding:8px 10px}
.d-row.zero{opacity:.5}
.d-name b{display:block;font:700 12px var(--display);text-transform:uppercase;letter-spacing:.2px;color:#fff}
.d-name small{color:var(--muted);font-size:10px}
.d-name small b{display:inline;font:700 10px 'Manrope',sans-serif;text-transform:none;color:var(--ok)}
.d-share{font:700 13px var(--display);color:var(--wait);text-align:right;white-space:nowrap}
.map-total{display:flex;justify-content:space-between;padding:8px 4px 0;font-size:11px;font-weight:700;color:var(--muted);border-top:1px dashed var(--line);margin-top:6px}
.map-total b{font:700 13px var(--display);color:#fff}
.note{margin-top:8px;font-size:11px;font-weight:600;color:var(--wait)}
.btn-submit{width:100%;padding:12px;border:0;border-radius:12px;cursor:pointer;color:#fff;font:700 13px var(--display);background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 8px 20px rgba(47,107,255,.35);margin-top:4px;transition:transform .1s,filter .2s}
.btn-submit:active{transform:scale(.98)}.btn-submit:hover{filter:brightness(1.1)}
.bnav{position:fixed;left:50%;transform:translateX(-50%);bottom:8px;width:calc(100% - 16px);max-width:480px;z-index:1000;display:flex;justify-content:space-around;background:rgba(6,16,48,.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--line);border-radius:16px;padding:4px;box-shadow:0 10px 30px rgba(0,0,0,.6)}
.bnav a{flex:1;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 2px;border-radius:10px;color:var(--muted);font-size:9px;font-weight:700;text-align:center;transition:color .2s,background .2s}
.bnav a i{font-size:15px}.bnav a:hover{color:#fff}
.bnav a.active{color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 12px rgba(47,107,255,.4)}
@media(max-width:360px){.wrap{padding:10px 8px}.f-grid{grid-template-columns:1fr}.amt-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<main class="wrap">

    <header class="hero">
        <div class="brand"><span class="mark">α</span> Alpha HQ</div>
        <h1>Fund deployment</h1>
    </header>

    <?php if ($deploy_error !== ''): ?>
        <div class="banner"><i class="fas fa-triangle-exclamation"></i><span><?= h($deploy_error) ?></span></div>
    <?php endif; ?>

    <form method="POST" id="deployForm">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

        <!-- 1. Selection reason + Batch ID search -->
        <div class="top-row" id="topRow">
            <div class="card-inner">
                <label for="missionType">Selection reason</label>
                <select name="op_type" id="missionType" onchange="handleObjectiveChange()" required>
                    <option value="<?= h($allowed_types[0]) ?>" <?= $old_type === $allowed_types[0] ? 'selected' : '' ?>>Emergency support</option>
                    <option value="<?= h($allowed_types[1]) ?>" <?= $old_type === $allowed_types[1] ? 'selected' : '' ?>>Legacy tribute</option>
                </select>
            </div>
            <div class="card-inner" id="searchBox">
                <label for="batchSearch">Search Batch ID</label>
                <input type="text" id="batchSearch" placeholder="Type Batch ID..." autocomplete="off" inputmode="text">
                <input type="hidden" name="target_id" id="hiddenBatch" value="<?= h($old_target) ?>">
            </div>
        </div>

        <!-- 2. Member details -->
        <section class="card">
            <h3><i class="fa-solid fa-id-card" style="color:var(--accent-2)"></i> Member details</h3>
            <div id="infoBody"><p class="placeholder">Search a Batch ID. The member's full details will appear here.</p></div>
        </section>

        <!-- 3. Amount + automatic percentage -->
        <section class="card">
            <div class="amt-row">
                <div>
                    <label for="fundingIn">Taka amount (৳)</label>
                    <input type="number" name="total_req_amount" id="fundingIn" placeholder="0.00" step="0.01" min="0" value="<?= h($old_amount) ?>" required>
                </div>
                <div>
                    <span class="lbl">Percentage of total funding</span>
                    <div class="pct" id="pctBox">0.0000%</div>
                </div>
            </div>
            <div class="meter"><div id="meterFill"></div></div>
            <div class="hint" id="amtHint">Total funding: <b>৳<?= number_format($total_funds, 2) ?></b></div>
        </section>

        <!-- 4. Context -->
        <section class="card">
            <label for="briefing">Context for this deployment</label>
            <textarea name="briefing" id="briefing" placeholder="Why is this fund needed?" required><?= h($old_brief) ?></textarea>
        </section>

        <!-- 5. Regional distribution map -->
        <section class="card">
            <h3><i class="fa-solid fa-map-location-dot" style="color:var(--wait)"></i> Regional distribution map (<?= count($districts_data) ?> Districts)</h3>
            <div class="map-head"><span>District / Funding</span><span>Share amount</span></div>
            <div class="map-list">
                <?php foreach ($districts_data as $d): ?>
                <div class="d-row<?= $d['paisa'] <= 0 ? ' zero' : '' ?>" data-p="<?= (int) $d['paisa'] ?>">
                    <div class="d-name">
                        <b><?= h($d['name']) ?></b>
                        <small>Funding <b>৳<?= number_format($d['paisa'] / 100, 2) ?></b></small>
                    </div>
                    <div class="d-share">৳0.00</div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="map-total"><span>Total allocation</span><b id="shareTotal">৳0.00</b></div>
            <?php if ($unassigned_paisa > 0): ?>
            <div class="note"><i class="fas fa-circle-info"></i> ৳<?= number_format($unassigned_paisa / 100, 2) ?> approved funding has no district, so it is not included above.</div>
            <?php endif; ?>
        </section>

        <button type="submit" name="deploy_poll" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Initialize deployment</button>
    </form>
</main>

<nav class="bnav" aria-label="Main Navigation">
    <a href="fund_allocation.php" class="active"><i class="fa-solid fa-chart-pie"></i> This page</a>
    <a href="<?= h($root) ?>dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
    <a href="sup_administry.php"><i class="fa-solid fa-tower-observation"></i> Super admin</a>
    <a href="create_poll.php"><i class="fa-solid fa-square-plus"></i> Create poll</a>
</nav>

<script>
const ROOT = <?= json_encode($root) ?>;
const TOTAL = <?= (int) $total_paisa ?>; // paisa
const OLD_TARGET = <?= json_encode($old_target) ?>;
const DEFAULT_PHOTO = 'https://cdn-icons-png.flaticon.com/512/6522/6522516.png';
const $ = id => document.getElementById(id);
const HINT_DEFAULT = $('amtHint').innerHTML;

function esc(s) { const d = document.createElement('div'); d.textContent = (s === null || s === undefined) ? '' : s; return d.innerHTML; }
function money(p) { return '৳' + (p / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function isLegacy() { return $('missionType').value.includes('Legacy Tribute'); }
function setInfoMessage(t, bad) { $('infoBody').innerHTML = '<p class="placeholder' + (bad ? ' bad' : '') + '">' + esc(t) + '</p>'; }

// ---- Member search ----
function renderMember(u) {
    const photo = u.photo ? ROOT + 'uploads/' + encodeURIComponent(u.photo) : DEFAULT_PHOTO;
    const val = x => (x !== null && x !== undefined && String(x).trim() !== '') ? esc(x) : '—';
    const fields = [['Name', u.full_name], ['BP Number', u.bp_number], ['Contact', u.mobile], ['Batch ID', u.batch_id ? '#' + u.batch_id : ''],
                    ['Gender', u.gender], ['Blood Group', u.blood_group], ['Division', u.division], ['District', u.district]];
    $('infoBody').innerHTML =
        '<div class="m-top"><img src="' + photo + '" alt="" onerror="this.onerror=null;this.src=\'' + DEFAULT_PHOTO + '\'">' +
        '<div><span class="chip">' + esc(u.role || 'Member') + '</span><span class="chip sel"><i class="fas fa-check"></i> Selected</span></div></div>' +
        '<div class="f-grid">' + fields.map(f => '<div class="f"><span>' + f[0] + '</span><b>' + val(f[1]) + '</b></div>').join('') + '</div>';
}
function pick(u) { $('hiddenBatch').value = u.batch_id; renderMember(u); }
function renderList(users) {
    $('infoBody').innerHTML = '<div class="sugg">' + users.map((u, i) =>
        '<button type="button" class="s-item" data-i="' + i + '"><span>' + esc(u.full_name || 'Unknown') + '<small>' + esc(u.district || '—') + ' · ' + esc(u.role || 'Member') + '</small></span><span class="bid">#' + esc(u.batch_id) + '</span></button>').join('') + '</div>';
    $('infoBody').querySelectorAll('.s-item').forEach(b => b.onclick = () => { const u = users[+b.dataset.i]; $('batchSearch').value = u.batch_id; pick(u); });
}

let sTimer = null, sSeq = 0;
function runSearch(q) {
    const seq = ++sSeq;
    $('hiddenBatch').value = '';
    setInfoMessage('Searching member records...');
    clearTimeout(sTimer);
    sTimer = setTimeout(async () => {
        try {
            const res = await fetch('?search_member=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (seq !== sSeq) return;
            if (!data.success) { setInfoMessage(data.message || 'No member found for this Batch ID.', true); return; }
            if (data.exact) { pick(data.users[0]); } else { renderList(data.users); }
        } catch (e) {
            if (seq === sSeq) setInfoMessage('Search failed. Reload the page and try again.', true);
        }
    }, 250);
}

function handleObjectiveChange() {
    const legacy = isLegacy();
    $('topRow').classList.toggle('single', legacy);
    sSeq++;
    if (legacy) {
        $('hiddenBatch').value = 'LEGACY_FUND';
        setInfoMessage('Legacy tribute has no single recipient. The amount goes directly to the legacy fund.');
    } else {
        $('hiddenBatch').value = '';
        const q = $('batchSearch').value.trim();
        if (q) { runSearch(q); } else { setInfoMessage("Search a Batch ID. The member's full details will appear here."); }
    }
}

$('batchSearch').addEventListener('input', function () {
    const q = this.value.trim();
    $('hiddenBatch').value = '';
    if (!q) { sSeq++; clearTimeout(sTimer); setInfoMessage("Search a Batch ID. The member's full details will appear here."); return; }
    runSearch(q);
});

// ---- Percentage + per-district share (paisa te, total thik amount er soman) ----
function split(bals, A) {
    const out = bals.map(() => 0), T = bals.reduce((a, b) => a + b, 0);
    if (T <= 0 || A <= 0) return out;
    const ord = []; let used = 0;
    bals.forEach((b, i) => { if (b > 0) { const x = b * A / T, f = Math.floor(x); out[i] = f; used += f; ord.push([x - f, i]); } });
    ord.sort((a, b) => (b[0] - a[0]) || (a[1] - b[1]));
    let left = A - used;
    for (let j = 0; j < ord.length && left > 0; j++, left--) out[ord[j][1]]++;
    for (let j = ord.length - 1; j >= 0 && left < 0; j--) { if (out[ord[j][1]] > 0) { out[ord[j][1]]--; left++; } }
    return out;
}

const ROWS = Array.from(document.querySelectorAll('.d-row'));
const BALS = ROWS.map(r => parseInt(r.dataset.p, 10) || 0);

function recalc() {
    const A = Math.round((parseFloat($('fundingIn').value) || 0) * 100);
    const pct = TOTAL > 0 ? (A / TOTAL) * 100 : 0;
    const over = A > TOTAL;
    $('pctBox').textContent = pct.toFixed(4) + '%';
    $('pctBox').classList.toggle('over', over);
    $('meterFill').style.width = Math.min(pct, 100) + '%';
    const hint = $('amtHint');
    hint.classList.toggle('over', over && A > 0);
    hint.innerHTML = (over && A > 0) ? (TOTAL > 0 ? 'Amount is more than the total funding: <b>' + money(TOTAL) + '</b>' : 'No approved funding available yet.') : HINT_DEFAULT;
    const shares = split(BALS, A);
    let sum = 0;
    ROWS.forEach((row, i) => { sum += shares[i]; row.querySelector('.d-share').textContent = money(shares[i]); });
    $('shareTotal').textContent = money(sum);
}
$('fundingIn').addEventListener('input', recalc);

$('deployForm').addEventListener('submit', function (e) {
    if (!isLegacy() && !$('hiddenBatch').value) {
        e.preventDefault();
        setInfoMessage('Select a valid member first. Search and tap a Batch ID.', true);
        $('batchSearch').focus();
    }
});

window.addEventListener('DOMContentLoaded', () => {
    handleObjectiveChange();
    if (!isLegacy() && OLD_TARGET) { $('batchSearch').value = OLD_TARGET; runSearch(OLD_TARGET); }
    recalc();
});
</script>
</body>
</html>