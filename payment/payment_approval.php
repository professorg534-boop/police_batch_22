<?php
session_start();
if (file_exists('db.php')) { include 'db.php'; $root = ''; }
elseif (file_exists('../db.php')) { include '../db.php'; $root = '../'; }
else { die("Database connection file missing."); }
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) { header("Location: {$root}login.php"); exit(); }
$user_id = (int) $_SESSION['user_id'];
$self = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function db_all($conn, $sql, $types = '', ...$params) { // rows | false
    $st = mysqli_prepare($conn, $sql);
    if (!$st) { return false; }
    if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
    if (!mysqli_stmt_execute($st)) { mysqli_stmt_close($st); return false; }
    $res = mysqli_stmt_get_result($st); $out = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    mysqli_stmt_close($st);
    return $out;
}
function shot_url($f) {
    $f = trim((string) $f);
    if ($f === '') { return ''; }
    $c = [];
    if (strpos($f, 'uploads/') !== false) { $c[] = $f; $c[] = '../' . ltrim($f, '/'); }
    foreach (['uploads/', '../uploads/', 'uploads/payments/', '../uploads/payments/'] as $d) { $c[] = $d . $f; }
    foreach ($c as $p) { if (file_exists($p)) { return $p; } }
    return $c[0];
}

// ---- Admin / Super Admin only ----
$me = db_all($conn, "SELECT full_name, role, district FROM users WHERE id = ? LIMIT 1", "i", $user_id);
$me = $me ? $me[0] : null;
$role = strtolower(trim($me['role'] ?? ''));
if (!in_array($role, ['admin', 'super admin', 'super_admin', 'superadmin'], true)) { header("Location: {$root}dashboard.php"); exit(); }
$district = (string) ($me['district'] ?? '');

// ---- Needed columns (auto add) ----
$have = [];
if ($r = mysqli_query($conn, "SHOW COLUMNS FROM payment_requests")) { while ($c = mysqli_fetch_assoc($r)) { $have[$c['Field']] = true; } }
foreach (['admin_id' => 'INT NULL DEFAULT NULL', 'approved_by' => 'INT NULL DEFAULT NULL', 'updated_at' => 'DATETIME NULL DEFAULT NULL', 'reject_reason' => 'VARCHAR(255) NULL DEFAULT NULL'] as $col => $def) {
    if (empty($have[$col]) && mysqli_query($conn, "ALTER TABLE payment_requests ADD COLUMN `$col` $def")) { $have[$col] = true; }
}

// Amar kache pathano (admin_id) + admin_id nai emon amar district er request
$vis = "(p.admin_id = ? OR ((p.admin_id IS NULL OR p.admin_id = 0) AND LOWER(TRIM(p.district)) = LOWER(TRIM(?))))";

// ---- Accept / Reject (POST + CSRF) ----
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do'])) {
    $tokOk = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
    $do = ($_POST['do'] === 'approve') ? 'approve' : (($_POST['do'] === 'reject') ? 'reject' : '');
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))), function ($i) { return $i > 0; })));

    if (!$tokOk || $do === '' || !$ids || count($ids) > 24) {
        $error = "Invalid request. Please reload and try again.";
    } else {
        $set = ["status = ?"]; $types = 's'; $par = [$do === 'approve' ? 'approved' : 'rejected'];
        if (!empty($have['approved_by'])) { $set[] = "approved_by = ?"; $types .= 'i'; $par[] = $user_id; }
        if (!empty($have['updated_at']))  { $set[] = "updated_at = NOW()"; }
        if ($do === 'reject' && !empty($have['reject_reason'])) {
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 200) : substr($reason, 0, 200);
            $set[] = "reject_reason = ?"; $types .= 's'; $par[] = $reason;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE payment_requests p SET " . implode(', ', $set) . " WHERE p.id IN ($ph) AND LOWER(p.status) = 'pending' AND p.user_id <> ? AND $vis";
        $types .= str_repeat('i', count($ids)) . 'iis';
        $par = array_merge($par, $ids, [$user_id, $user_id, $district]);

        $st = mysqli_prepare($conn, $sql);
        if ($st) {
            mysqli_stmt_bind_param($st, $types, ...$par);
            mysqli_stmt_execute($st);
            $n = mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);
            if ($n > 0) { header("Location: $self?done=" . ($do === 'approve' ? 'approved' : 'rejected')); exit(); }
            $error = "Nothing changed. It may already be processed or not assigned to you.";
        } else {
            error_log("payment_approval update: " . mysqli_error($conn));
            $error = "Database error: " . mysqli_error($conn);
        }
    }
}
$done = in_array($_GET['done'] ?? '', ['approved', 'rejected'], true) ? $_GET['done'] : '';

// ---- Load pending ----
$rows = db_all($conn,
    "SELECT p.*, u.full_name, u.batch_id FROM payment_requests p JOIN users u ON p.user_id = u.id
     WHERE LOWER(p.status) = 'pending' AND p.user_id <> ? AND $vis ORDER BY p.id DESC",
    "iis", $user_id, $user_id, $district);
$load_failed = ($rows === false);
if ($load_failed) { $error = $error ?: "Could not load requests: " . mysqli_error($conn); $rows = []; }

// Ek payment er alada month row gulo ek card e
$cards = [];
foreach ($rows as $r) {
    $trx = trim((string) ($r['trxid'] ?? ''));
    $k = $r['user_id'] . '|' . ($trx !== '' ? $trx : 'row' . $r['id']) . '|' . ($r['admin_id'] ?? '') . '|' . ($r['pay_year'] ?? '');
    if (!isset($cards[$k])) { $cards[$k] = ['ids' => [], 'months' => [], 'amount' => 0, 'r' => $r]; }
    $cards[$k]['ids'][] = (int) $r['id'];
    $cards[$k]['amount'] += (float) ($r['amount'] ?? 0);
    foreach (explode(',', (string) ($r['pay_month'] ?? '')) as $m) {
        $m = trim($m);
        if ($m !== '' && !in_array($m, $cards[$k]['months'], true)) { $cards[$k]['months'][] = $m; }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA HQ | Payment Approvals</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--ink:#eaf1ff;--muted:#8ea3cc;--accent:#2f6bff;--accent-2:#38bdf8;--soft:rgba(47,107,255,.18);--line:rgba(130,170,255,.16);--ok:#34d399;--ok-bg:rgba(52,211,153,.14);--wait:#fbbf24;--wait-bg:rgba(251,191,36,.14);--bad:#f87171;--bad-bg:rgba(248,113,113,.14);--d:'Sora',sans-serif}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{min-height:100vh;color:var(--ink);font-family:'Manrope',sans-serif;background:radial-gradient(900px 520px at 90% -8%,rgba(47,107,255,.42),transparent 60%),radial-gradient(700px 520px at -12% 32%,rgba(56,189,248,.16),transparent 60%),linear-gradient(180deg,#061030,#030817) fixed}
.wrap{max-width:680px;margin:0 auto;padding:18px 12px 120px}
.bar{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.back{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 15px;border-radius:12px;background:var(--accent);color:#fff;text-decoration:none;font-size:13px;font-weight:700;flex-shrink:0;box-shadow:0 8px 20px rgba(47,107,255,.4)}
.bar h1{font:700 20px var(--d);flex:1}
.cnt{font-size:11px;font-weight:800;color:var(--wait);background:var(--wait-bg);padding:5px 12px;border-radius:999px;white-space:nowrap}
.banner{padding:13px 16px;border-radius:16px;font-size:13px;font-weight:700;margin-bottom:16px;display:flex;gap:10px;align-items:center;border:1px solid rgba(248,113,113,.3);background:var(--bad-bg);color:var(--bad);line-height:1.45}
.card{background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.03));border:1px solid var(--line);backdrop-filter:blur(16px);border-radius:24px;padding:18px;margin-bottom:14px;box-shadow:0 20px 45px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.07)}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.top h3{font:700 16px var(--d);word-break:break-word}
.top small{font-size:11px;font-weight:700;color:var(--accent-2)}
.amt{font:700 24px var(--d);color:var(--ok);white-space:nowrap}
.info{background:rgba(0,0,0,.22);border:1px solid var(--line);border-radius:16px;padding:6px 14px;margin:14px 0}
.row{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid var(--line);font-size:13px}
.row:first-child{border-top:0}
.row span{color:var(--muted);font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;flex-shrink:0;padding-top:2px}
.row b{font-weight:700;text-align:right;word-break:break-all}
.row b.t{color:var(--accent-2);font-family:monospace}
.months{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
.pill{padding:4px 11px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid var(--line);color:var(--accent-2);font-size:11px;font-weight:700}
.btn{flex:1;padding:14px;border-radius:14px;border:none;font:700 12px var(--d);cursor:pointer;text-align:center}
.proof{width:100%;margin-bottom:10px;background:rgba(255,255,255,.08);color:#fff;border:1px solid var(--line)}
.acts{display:flex;gap:10px}
.ok{background:linear-gradient(135deg,#10b981,var(--ok));color:#032b1d;box-shadow:0 10px 24px rgba(52,211,153,.3)}
.no{background:var(--bad-bg);color:var(--bad);border:1px solid rgba(248,113,113,.3)}
.empty{text-align:center;padding:60px 20px;border-radius:24px;background:rgba(12,22,52,.65);border:1px solid var(--line);color:var(--muted)}
.empty i{font-size:38px;margin-bottom:12px;opacity:.6}
.ov{position:fixed;inset:0;background:rgba(3,8,23,.9);backdrop-filter:blur(10px);z-index:100;display:none;align-items:center;justify-content:center;padding:16px}
.ov.show{display:flex}
.box{background:#0a1431;border:1px solid var(--line);padding:30px 24px;border-radius:28px;text-align:center;max-width:400px;width:100%;animation:pop .35s ease}
@keyframes pop{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}
.box h2{font:700 22px var(--d)}
.box textarea{display:none;width:100%;margin-top:16px;background:rgba(0,0,0,.35);border:1px solid var(--line);border-radius:14px;color:#fff;padding:12px;font:600 13px 'Manrope',sans-serif;resize:none;outline:none}
.cb{display:flex;gap:12px;margin-top:22px}
.c{flex:1;padding:14px;border-radius:14px;border:none;font:700 12px var(--d);cursor:pointer;color:#fff;text-decoration:none;display:block}
.c-yes{background:linear-gradient(135deg,var(--accent),var(--accent-2))}.c-no{background:rgba(255,255,255,.08)}
.sicon{width:74px;height:74px;border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;font-size:30px;border:1px solid}
.i-ok{background:var(--ok-bg);color:var(--ok);border-color:var(--ok)}.i-no{background:var(--bad-bg);color:var(--bad);border-color:var(--bad)}
#proofModal img{max-width:100%;max-height:85vh;border-radius:16px}
#proofModal .x{position:absolute;top:12px;right:20px;font-size:38px;cursor:pointer;line-height:1}
.bnav{position:fixed;left:50%;transform:translateX(-50%);bottom:12px;width:calc(100% - 24px);max-width:520px;z-index:50;display:flex;justify-content:space-around;gap:2px;background:rgba(8,18,52,.72);backdrop-filter:blur(22px);border:1px solid var(--line);border-radius:26px;padding:7px;box-shadow:0 18px 50px rgba(0,0,0,.55)}
.bnav a{flex:1;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border-radius:19px;color:var(--muted);font-size:11px;font-weight:700}
.bnav a i{font-size:17px}
.bnav a.active{color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 8px 22px rgba(47,107,255,.6)}
@media(prefers-reduced-motion:reduce){.box{animation:none}}
</style>
</head>
<body>

<?php if ($done): ?>
<div class="ov show"><div class="box">
    <div class="sicon <?= $done === 'approved' ? 'i-ok' : 'i-no' ?>"><i class="fas <?= $done === 'approved' ? 'fa-check' : 'fa-xmark' ?>"></i></div>
    <h2><?= $done === 'approved' ? 'Payment Accepted' : 'Payment Rejected' ?></h2>
    <p style="color:var(--muted);margin:10px 0 22px;font-size:13px">The member will see this update on their profile.</p>
    <a href="<?= h($self) ?>" class="c c-yes">CONTINUE</a>
</div></div>
<?php endif; ?>

<form method="POST" id="actForm" class="ov" onclick="if(event.target===this)closeConfirm()">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="do" id="fDo"><input type="hidden" name="ids" id="fIds">
    <div class="box">
        <h2 id="cTitle">Confirm</h2>
        <p style="color:var(--muted);margin-top:8px;font-size:13px">Are you sure you want to proceed?</p>
        <textarea name="reason" id="fReason" rows="2" maxlength="200" placeholder="Reason (optional)"></textarea>
        <div class="cb"><button type="button" class="c c-no" onclick="closeConfirm()">CANCEL</button><button type="submit" class="c c-yes" id="cGo">CONFIRM</button></div>
    </div>
</form>

<div id="proofModal" class="ov" onclick="if(event.target===this)closeProof()"><span class="x" onclick="closeProof()">&times;</span><img id="modalImg" src="" alt="Proof"></div>

<main class="wrap">
    <div class="bar">
        <a href="district_administry.php" class="back"><i class="fas fa-arrow-left"></i> Back</a>
        <h1>Payment Approvals</h1>
        <?php if ($cards): ?><span class="cnt"><?= count($cards) ?> pending</span><?php endif; ?>
    </div>

    <?php if ($error): ?><div class="banner"><i class="fas fa-triangle-exclamation"></i><span><?= h($error) ?></span></div><?php endif; ?>

    <?php foreach ($cards as $c): $r = $c['r']; $shot = shot_url($r['screenshot'] ?? ''); $t = !empty($r['created_at']) ? strtotime($r['created_at']) : false; ?>
    <section class="card">
        <div class="top">
            <div><h3><?= h($r['full_name']) ?></h3><small>Batch #<?= h($r['batch_id']) ?></small></div>
            <div class="amt">৳<?= number_format($c['amount']) ?></div>
        </div>

        <div class="info">
            <div class="row"><span>Transaction ID</span><b class="t"><?= h($r['trxid']) ?></b></div>
            <div class="row"><span>Method</span><b><?= h(strtoupper((string) $r['method'])) ?></b></div>
            <div class="row"><span>Sent</span><b><?= $t ? date('d M Y, h:i A', $t) : '—' ?></b></div>
            <div class="row"><span>Type</span><b><?= h(ucfirst((string) ($r['request_type'] ?? 'monthly'))) ?><?= !empty($r['pay_year']) ? ' · ' . h($r['pay_year']) : '' ?></b></div>
        </div>

        <?php if ($c['months']): ?><div class="months"><?php foreach ($c['months'] as $m): ?><span class="pill"><?= h($m) ?></span><?php endforeach; ?></div><?php endif; ?>

        <?php if ($shot): ?><button type="button" class="btn proof" data-src="<?= h($shot) ?>" onclick="showProof(this.dataset.src)"><i class="fas fa-image"></i> View Screenshot</button><?php endif; ?>

        <div class="acts">
            <button type="button" class="btn ok" data-ids="<?= h(implode(',', $c['ids'])) ?>" data-do="approve" data-t="Accept Payment?" onclick="openConfirm(this)"><i class="fas fa-check"></i> Accept</button>
            <button type="button" class="btn no" data-ids="<?= h(implode(',', $c['ids'])) ?>" data-do="reject" data-t="Reject Payment?" onclick="openConfirm(this)"><i class="fas fa-xmark"></i> Reject</button>
        </div>
    </section>
    <?php endforeach; ?>

    <?php if (!$cards && !$load_failed): ?>
    <div class="empty"><i class="fas fa-inbox"></i><h3 style="color:#fff">No pending payment requests</h3><p style="font-size:13px;margin-top:4px">New requests sent to you will appear here.</p></div>
    <?php endif; ?>
</main>

<nav class="bnav" aria-label="Main Navigation">
    <a href="<?= h($root) ?>dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
    <a href="district_monitor.php"><i class="fa-solid fa-compass"></i>District</a>
    <a href="payment_request.php"><i class="fa-solid fa-wallet"></i>Deposits</a>
    <a href="district_administry.php" class="active"><i class="fa-solid fa-shield-halved"></i>Administry</a>
</nav>

<script>
var $ = function (i) { return document.getElementById(i); };
function showProof(s) { $('modalImg').src = s; $('proofModal').classList.add('show'); }
function closeProof() { $('proofModal').classList.remove('show'); }
function openConfirm(b) {
    $('cTitle').textContent = b.dataset.t;
    $('fDo').value = b.dataset.do;
    $('fIds').value = b.dataset.ids;
    $('fReason').style.display = b.dataset.do === 'reject' ? 'block' : 'none';
    $('fReason').value = '';
    $('actForm').classList.add('show');
}
function closeConfirm() { $('actForm').classList.remove('show'); }
$('actForm').addEventListener('submit', function () {
    setTimeout(function () { $('cGo').disabled = true; $('cGo').textContent = 'PLEASE WAIT…'; }, 0);
});
</script>
</body>
</html>