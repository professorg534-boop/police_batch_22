<?php
session_start();
if (file_exists('db.php')) { include 'db.php'; $root = ''; }
elseif (file_exists('../db.php')) { include '../db.php'; $root = '../'; }
else { die("Database connection file missing."); }
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) { header("Location: {$root}login.php"); exit(); }
$uid = (int) $_SESSION['user_id'];
$self = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function db_all($conn, $sql, $types = '', ...$p) { // rows | false
    $st = mysqli_prepare($conn, $sql);
    if (!$st) { return false; }
    if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$p); }
    if (!mysqli_stmt_execute($st)) { mysqli_stmt_close($st); return false; }
    $res = mysqli_stmt_get_result($st); $out = [];
    if ($res) { while ($r = mysqli_fetch_assoc($res)) { $out[] = $r; } }
    mysqli_stmt_close($st);
    return $out;
}
function member_name($conn, $b) {
    if ((string) $b === 'LEGACY_FUND') { return 'Legacy Fund'; }
    $m = db_all($conn, "SELECT full_name FROM users WHERE CONVERT(batch_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1", "s", (string) $b);
    return ($m && trim((string) $m[0]['full_name']) !== '') ? $m[0]['full_name'] : 'Unknown';
}

$me = db_all($conn, "SELECT full_name, role, district FROM users WHERE id = ? LIMIT 1", "i", $uid);
$me = $me ? $me[0] : null;
$role = strtolower(trim($me['role'] ?? ''));
$is_super = in_array($role, ['super admin', 'super_admin', 'superadmin'], true);
$is_admin = ($role === 'admin');
if (!$is_admin && !$is_super) { header("Location: {$root}dashboard.php"); exit(); }
$district = (string) ($me['district'] ?? '');

$flash = $_SESSION['adm_flash'] ?? null; unset($_SESSION['adm_flash']);
$err = '';

// ---- District fund -> Super Admin (district Admin only) ----
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_super'])) {
    $rid = (int) ($_POST['row_id'] ?? 0);
    $trx = trim($_POST['trxid'] ?? '');
    $f = $_FILES['proof_img'] ?? null;
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { $err = "Session expired. Please reload and try again."; }
    elseif ($rid <= 0 || $trx === '' || strlen($trx) > 60) { $err = "Invalid submission data."; }
    elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) { $err = "Screenshot upload failed. Please try again."; }
    else {
        $v = db_all($conn, "SELECT dc.required_amount FROM district_contributions dc INNER JOIN fund_operations fo ON dc.op_id = fo.id
                            WHERE dc.id = ? AND LOWER(TRIM(dc.district_name)) = LOWER(TRIM(?)) AND dc.status = 'pending' AND fo.status = 'active' LIMIT 1", "is", $rid, $district);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $mime = (@getimagesize($f['tmp_name']))['mime'] ?? '';
        if (!$v) { $err = "This request is no longer available."; }
        elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { $err = "Only JPG, PNG or WEBP images are allowed."; }
        elseif ($f['size'] > 5 * 1024 * 1024) { $err = "File too large (max 5MB)."; }
        else {
            $dir = $root . 'uploads/proofs/';
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $name = 'TRX_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . $name)) { $err = "Could not save the uploaded file."; }
            else {
                $ok = false;
                $st = mysqli_prepare($conn, "UPDATE district_contributions SET status = 'waiting_super_admin', trxid = ?, proof_img = ? WHERE id = ? AND LOWER(TRIM(district_name)) = LOWER(TRIM(?)) AND status = 'pending'");
                if ($st) {
                    mysqli_stmt_bind_param($st, "ssis", $trx, $name, $rid, $district);
                    mysqli_stmt_execute($st);
                    $ok = mysqli_stmt_affected_rows($st) > 0;
                    mysqli_stmt_close($st);
                }
                if ($ok) {
                    $_SESSION['adm_flash'] = ['ok', '৳' . number_format((float) $v[0]['required_amount'], 2) . ' sent to Super Admin for verification.'];
                    header("Location: $self"); exit();
                }
                @unlink($dir . $name);
                $err = "Could not update this request. It may already be processed.";
            }
        }
    }
}

// ---- Member payments waiting for me (payment_approval.php) ----
$vis = "(p.admin_id = ? OR ((p.admin_id IS NULL OR p.admin_id = 0) AND LOWER(TRIM(p.district)) = LOWER(TRIM(?))))";
$pc = db_all($conn, "SELECT COUNT(DISTINCT CONCAT(p.user_id,'|',COALESCE(NULLIF(p.trxid,''),p.id),'|',COALESCE(p.pay_year,''))) AS c, COALESCE(SUM(p.amount),0) AS s
                     FROM payment_requests p WHERE LOWER(p.status) = 'pending' AND p.user_id <> ? AND $vis", "iis", $uid, $uid, $district);
$pend_n = $pc ? (int) $pc[0]['c'] : 0;
$pend_amt = $pc ? (float) $pc[0]['s'] : 0;
$preview = db_all($conn, "SELECT u.full_name, SUM(p.amount) AS amt, MIN(p.created_at) AS sent, COUNT(*) AS n
                          FROM payment_requests p JOIN users u ON u.id = p.user_id
                          WHERE LOWER(p.status) = 'pending' AND p.user_id <> ? AND $vis
                          GROUP BY p.user_id, u.full_name, p.trxid, p.pay_year, p.admin_id ORDER BY MAX(p.id) DESC LIMIT 3", "iis", $uid, $uid, $district) ?: [];
$ap = db_all($conn, "SELECT COALESCE(SUM(amount),0) AS s FROM payment_requests WHERE LOWER(status) IN ('approved','success') AND approved_by = ?", "i", $uid);
$approved_amt = $ap ? (float) $ap[0]['s'] : 0;

// ---- District fund due + recent ----
$due = []; $recent = []; $due_total = 0;
if ($is_admin) {
    $due = db_all($conn, "SELECT dc.id, dc.required_amount, fo.op_type, fo.target_batch_id FROM district_contributions dc INNER JOIN fund_operations fo ON dc.op_id = fo.id
                          WHERE LOWER(TRIM(dc.district_name)) = LOWER(TRIM(?)) AND dc.status = 'pending' AND fo.status = 'active' AND dc.required_amount > 0
                          ORDER BY dc.id ASC LIMIT 20", "s", $district) ?: [];
    foreach ($due as &$d) { $d['member'] = member_name($conn, $d['target_batch_id']); $due_total += (float) $d['required_amount']; }
    unset($d);
    $recent = db_all($conn, "SELECT dc.required_amount, dc.status, fo.op_type FROM district_contributions dc INNER JOIN fund_operations fo ON dc.op_id = fo.id
                             WHERE LOWER(TRIM(dc.district_name)) = LOWER(TRIM(?)) AND dc.status <> 'pending' ORDER BY dc.id DESC LIMIT 4", "s", $district) ?: [];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALPHA HQ | Administry</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--ink:#eaf1ff;--muted:#8ea3cc;--accent:#2f6bff;--accent-2:#38bdf8;--soft:rgba(47,107,255,.18);--line:rgba(130,170,255,.16);--ok:#34d399;--ok-bg:rgba(52,211,153,.14);--wait:#fbbf24;--wait-bg:rgba(251,191,36,.14);--bad:#f87171;--bad-bg:rgba(248,113,113,.14);--d:'Sora',sans-serif}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{min-height:100vh;color:var(--ink);font-family:'Manrope',sans-serif;background:radial-gradient(900px 520px at 90% -8%,rgba(47,107,255,.42),transparent 60%),radial-gradient(700px 520px at -12% 32%,rgba(56,189,248,.16),transparent 60%),linear-gradient(180deg,#061030,#030817) fixed}
.wrap{max-width:680px;margin:0 auto;padding:22px 12px 120px}
.hero{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;padding:0 4px}
.brand{display:flex;align-items:center;gap:8px;font:700 13px var(--d);color:var(--accent-2);margin-bottom:8px}
.mark{width:26px;height:26px;border-radius:9px;display:grid;place-items:center;color:#fff;font:700 15px var(--d);background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 6px 18px rgba(47,107,255,.55)}
.hero h1{font:700 24px/1.2 var(--d);letter-spacing:-.5px}
.chip{font-size:11px;font-weight:800;color:var(--accent-2);background:var(--soft);padding:6px 12px;border-radius:999px;text-align:center;line-height:1.4}
.banner{padding:13px 16px;border-radius:16px;font-size:13px;font-weight:700;margin-bottom:18px;display:flex;gap:10px;align-items:center;border:1px solid;line-height:1.45}
.banner.ok{background:var(--ok-bg);color:var(--ok);border-color:rgba(52,211,153,.3)}
.banner.bad{background:var(--bad-bg);color:var(--bad);border-color:rgba(248,113,113,.3)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr));gap:8px;margin-bottom:18px}
.stat{background:rgba(12,22,52,.65);border:1px solid var(--line);border-radius:16px;padding:12px 10px}
.stat small{display:block;font-size:10.5px;font-weight:700;color:var(--muted);margin-bottom:5px}
.stat b{font:700 16px var(--d);word-break:break-word}
.card{position:relative;background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.03));border:1px solid var(--line);backdrop-filter:blur(16px);border-radius:24px;padding:20px 18px;margin-bottom:16px;box-shadow:0 20px 45px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.07)}
.card h2{font:700 15px var(--d);display:flex;align-items:center;gap:9px;margin-bottom:14px}
.card h2 .n{margin-left:auto;font-size:11px;background:var(--wait-bg);color:var(--wait);padding:3px 10px;border-radius:999px}
.prow{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 0;border-top:1px solid var(--line)}
.prow:first-of-type{border-top:0}
.prow b{font-size:13.5px;display:block}
.prow small{font-size:11px;color:var(--muted);font-weight:600}
.prow .a{font:700 15px var(--d);color:var(--ok)}
.empty{font-size:13px;color:var(--muted);font-weight:600;text-align:center;padding:14px 0}
.go{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;padding:13px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;font:700 13px var(--d);text-decoration:none;box-shadow:0 10px 24px rgba(47,107,255,.4)}
.tag{display:inline-block;font-size:10.5px;font-weight:800;color:var(--wait);background:var(--wait-bg);padding:3px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:.4px}
.due-h{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.due-h h3{font:700 16px var(--d);margin-top:8px;word-break:break-word}
.due-h small{font-size:11px;color:var(--muted);font-weight:700}
.due-a{font:700 26px var(--d);color:var(--ok);margin:12px 0}
label.l{display:block;font-size:10.5px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
input[type=text]{width:100%;background:rgba(0,0,0,.35);border:1px solid var(--line);border-radius:14px;padding:13px 14px;color:#fff;font:600 14px 'Manrope',sans-serif;outline:none;margin-bottom:12px}
input[type=text]:focus{border-color:var(--accent-2);box-shadow:0 0 0 3px rgba(56,189,248,.15)}
.file{display:block;border:1.5px dashed var(--line);border-radius:14px;padding:15px;text-align:center;background:rgba(0,0,0,.2);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.file i{color:var(--accent-2);margin-right:8px}
.file input{display:none}
.btn{width:100%;border:none;padding:14px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;font:700 13px var(--d);cursor:pointer;box-shadow:0 10px 24px rgba(47,107,255,.4)}
.btn:disabled{opacity:.7;cursor:wait}
.st{font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:999px;text-transform:uppercase;white-space:nowrap}
.st.w{background:var(--wait-bg);color:var(--wait)}.st.o{background:var(--ok-bg);color:var(--ok)}.st.x{background:rgba(255,255,255,.08);color:var(--muted)}
.bnav{position:fixed;left:50%;transform:translateX(-50%);bottom:12px;width:calc(100% - 24px);max-width:520px;z-index:50;display:flex;justify-content:space-around;gap:2px;background:rgba(8,18,52,.72);backdrop-filter:blur(22px);border:1px solid var(--line);border-radius:26px;padding:7px;box-shadow:0 18px 50px rgba(0,0,0,.55)}
.bnav a{flex:1;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border-radius:19px;color:var(--muted);font-size:11px;font-weight:700}
.bnav a i{font-size:17px}
.bnav a.active{color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 8px 22px rgba(47,107,255,.6)}
</style>
</head>
<body>
<main class="wrap">

<header class="hero">
    <div>
        <div class="brand"><span class="mark">α</span>Alpha HQ</div>
        <h1>Administry</h1>
    </div>
    <div class="chip"><?= $is_super ? 'SUPER ADMIN' : 'ADMIN' ?><br><?= h(strtoupper($district !== '' ? $district : 'N/A')) ?></div>
</header>

<?php if ($flash): ?><div class="banner ok"><i class="fas fa-circle-check"></i><span><?= h($flash[1]) ?></span></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="banner bad"><i class="fas fa-triangle-exclamation"></i><span><?= h($err) ?></span></div><?php endif; ?>

<div class="stats">
    <div class="stat"><small><i class="fas fa-hourglass-half" style="color:var(--wait)"></i> To approve</small><b>৳<?= number_format($pend_amt) ?></b></div>
    <div class="stat"><small><i class="fas fa-circle-check" style="color:var(--ok)"></i> Approved by you</small><b>৳<?= number_format($approved_amt) ?></b></div>
    <?php if ($is_admin): ?><div class="stat"><small><i class="fas fa-paper-plane" style="color:var(--accent-2)"></i> Fund due</small><b>৳<?= number_format($due_total) ?></b></div><?php endif; ?>
</div>

<section class="card">
    <h2><i class="fas fa-wallet" style="color:var(--accent-2)"></i> Member Payments<?php if ($pend_n): ?><span class="n"><?= $pend_n ?> pending</span><?php endif; ?></h2>
    <?php if ($preview): foreach ($preview as $p): $t = strtotime((string) $p['sent']); ?>
        <div class="prow">
            <div><b><?= h($p['full_name']) ?></b><small><?= (int) $p['n'] > 1 ? (int) $p['n'] . ' months · ' : '' ?><?= $t ? date('d M, h:i A', $t) : '' ?></small></div>
            <div class="a">৳<?= number_format((float) $p['amt']) ?></div>
        </div>
    <?php endforeach; else: ?>
        <div class="empty"><i class="fas fa-check-double"></i> All caught up. No pending member payments.</div>
    <?php endif; ?>
    <a href="payment_approval.php" class="go"><i class="fas fa-clipboard-check"></i> Open Payment Approvals</a>
</section>

<?php if ($is_admin): ?>
<?php foreach ($due as $d): ?>
<section class="card">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" name="row_id" value="<?= (int) $d['id'] ?>">
        <input type="hidden" name="submit_to_super" value="1">
        <div class="due-h">
            <div><span class="tag"><?= h($d['op_type'] ?? 'Fund') ?></span><h3><?= h($d['member']) ?></h3></div>
            <small><?= ((string) $d['target_batch_id'] === 'LEGACY_FUND') ? '' : '#' . h($d['target_batch_id']) ?></small>
        </div>
        <div class="due-a">৳<?= number_format((float) $d['required_amount'], 2) ?></div>
        <label class="l">Transaction ID</label>
        <input type="text" name="trxid" placeholder="Enter TrxID" maxlength="60" required autocomplete="off">
        <label class="l">Payment proof</label>
        <label class="file"><i class="fas fa-cloud-arrow-up"></i><span>Attach screenshot (JPG, PNG, WEBP · max 5MB)</span><input type="file" name="proof_img" accept=".jpg,.jpeg,.png,.webp" required></label>
        <button type="submit" class="btn">Send to Super Admin <i class="fas fa-paper-plane" style="margin-left:6px"></i></button>
    </form>
</section>
<?php endforeach; ?>

<?php if (!$due): ?>
<section class="card"><h2><i class="fas fa-satellite-dish" style="color:var(--accent-2)"></i> District Fund</h2><div class="empty">No pending district fund requests.</div></section>
<?php endif; ?>

<?php if ($recent): ?>
<section class="card">
    <h2><i class="fas fa-clock-rotate-left" style="color:var(--accent-2)"></i> Recent Submissions</h2>
    <?php foreach ($recent as $r): $s = strtolower((string) $r['status']); $cls = $s === 'waiting_super_admin' ? 'w' : (in_array($s, ['paid', 'approved', 'completed', 'success'], true) ? 'o' : 'x'); ?>
        <div class="prow">
            <div><b><?= h($r['op_type'] ?? 'Fund') ?></b><small>৳<?= number_format((float) $r['required_amount'], 2) ?></small></div>
            <span class="st <?= $cls ?>"><?= $cls === 'w' ? 'Awaiting Super Admin' : h(str_replace('_', ' ', $s)) ?></span>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php endif; ?>

</main>

<nav class="bnav" aria-label="Main Navigation">
    <a href="<?= h($root) ?>dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
    <a href="district_monitor.php"><i class="fa-solid fa-compass"></i>District</a>
    <a href="payment_request.php"><i class="fa-solid fa-wallet"></i>Deposits</a>
    <a href="district_administry.php" class="active" aria-current="page"><i class="fa-solid fa-shield-halved"></i>Administry</a>
</nav>

<script>
document.addEventListener('change', function (e) {
    if (e.target.type === 'file' && e.target.files[0]) {
        e.target.parentNode.querySelector('span').textContent = e.target.files[0].name.substring(0, 28);
    }
});
document.addEventListener('submit', function (e) {
    var b = e.target.querySelector('.btn');
    if (b) setTimeout(function () { b.disabled = true; b.textContent = 'Sending…'; }, 0);
});
</script>
</body>
</html>