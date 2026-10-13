<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

if (file_exists('db.php')) { include 'db.php'; $root = ''; }
elseif (file_exists('../db.php')) { include '../db.php'; $root = '../'; }
else { die("Database connection file missing."); }
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) { header("Location: {$root}login.php"); exit(); }
$uid = (int) $_SESSION['user_id'];

// Logout er por Back dile cache theke data dekha jabe na
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function dt_cell($v) {
    $t = $v ? strtotime($v) : false;
    return $t ? '<b>' . date('d M Y', $t) . '</b><em>' . date('h:i A', $t) . '</em>' : '<b>—</b>';
}
function one_user($conn, $id) {
    $u = null;
    if ($s = mysqli_prepare($conn, "SELECT id, full_name, batch_id FROM users WHERE id = ? LIMIT 1")) {
        mysqli_stmt_bind_param($s, "i", $id);
        if (mysqli_stmt_execute($s) && ($r = mysqli_stmt_get_result($s))) { $u = mysqli_fetch_assoc($r) ?: null; }
        mysqli_stmt_close($s);
    }
    return $u;
}

// ---- Viewer: je login kora (jekeu dekhte parbe, role lage na) ----
if (!one_user($conn, $uid)) { session_destroy(); header("Location: {$root}login.php"); exit(); }

// ---- Je member er profile khola hoyeche (member_details.php -> payment_history.php?id=) ----
$tid = (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) ? (int) $_GET['id'] : 0;
$owner = $tid > 0 ? one_user($conn, $tid) : null;
$is_self = ($tid === $uid);

// ---- Column detect (schema alada hole crash korbe na) ----
$cols = [];
if ($cr = mysqli_query($conn, "SHOW COLUMNS FROM payment_requests")) { while ($c = mysqli_fetch_assoc($cr)) { $cols[] = $c['Field']; } }
$hasTo = in_array('admin_id', $cols, true);
$doneCol = null; foreach (['approved_at', 'processed_at', 'updated_at'] as $c) { if (in_array($c, $cols, true)) { $doneCol = $c; break; } }
$reasonCol = null; foreach (['reject_reason', 'rejection_reason', 'admin_note', 'remark', 'note'] as $c) { if (in_array($c, $cols, true)) { $reasonCol = $c; break; } }

$sel = "p.*"; $join = "";
if ($hasTo) { $sel .= ", ut.full_name AS to_name"; $join .= " LEFT JOIN users ut ON ut.id = p.admin_id"; }
if (in_array('approved_by', $cols, true)) { $sel .= ", ua.full_name AS ap_name"; $join .= " LEFT JOIN users ua ON ua.id = p.approved_by"; }

// ---- Shudhu ei member er (sender er) payment: WHERE p.user_id = $tid. Je admin ke pathano, tar profile e dekhabe na ----
$rows = [];
if ($owner) {
    $sql = "SELECT $sel FROM payment_requests p $join WHERE p.user_id = ?" . ($hasTo ? " AND (p.admin_id IS NULL OR p.admin_id <> ?)" : "") . " ORDER BY p.id DESC LIMIT 300";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        if ($hasTo) { mysqli_stmt_bind_param($stmt, "ii", $tid, $tid); } else { mysqli_stmt_bind_param($stmt, "i", $tid); }
        if (mysqli_stmt_execute($stmt) && ($res = mysqli_stmt_get_result($stmt))) {
            while ($r = mysqli_fetch_assoc($res)) { if ((int) $r['user_id'] === $tid) { $rows[] = $r; } }
        }
        mysqli_stmt_close($stmt);
    }
}

// ---- Ek payment er alada month row gulo ek card e (current status shoho) ----
$cards = []; $cnt = ['ok' => 0, 'wait' => 0, 'bad' => 0]; $sum = ['ok' => 0, 'wait' => 0];
foreach ($rows as $r) {
    $st = strtolower(trim((string) ($r['status'] ?? 'pending')));
    $state = in_array($st, ['approved', 'success', 'paid'], true) ? 'ok' : ($st === 'pending' ? 'wait' : 'bad');
    $trx = trim((string) ($r['trxid'] ?? ''));
    $k = ($trx !== '' ? $trx : 'row' . $r['id']) . '|' . ($r['admin_id'] ?? '') . '|' . ($r['pay_year'] ?? '') . '|' . $state;
    if (!isset($cards[$k])) {
        $cards[$k] = ['state' => $state, 'amount' => 0, 'months' => [], 'year' => $r['pay_year'] ?? '', 'type' => $r['request_type'] ?? 'monthly', 'method' => $r['method'] ?? '',
                      'to' => $r['to_name'] ?? '', 'by' => $r['ap_name'] ?? '', 'by_id' => $r['approved_by'] ?? '', 'to_id' => $r['admin_id'] ?? '', 'sent' => null, 'done' => null, 'reason' => ''];
    }
    $g = $cards[$k];
    $g['amount'] += (float) ($r['amount'] ?? 0);
    foreach (explode(',', (string) ($r['pay_month'] ?? '')) as $m) { $m = trim($m); if ($m !== '' && !in_array($m, $g['months'], true)) { $g['months'][] = $m; } }
    $g['sent'] = $r['created_at'] ?? $g['sent'];
    if ($state !== 'wait') {
        if ($doneCol && !empty($r[$doneCol])) { $g['done'] = $r[$doneCol]; }
        if ($reasonCol && !empty($r[$reasonCol])) { $g['reason'] = $r[$reasonCol]; }
    }
    $cards[$k] = $g;
}
foreach ($cards as $g) { $cnt[$g['state']]++; if ($g['state'] !== 'bad') { $sum[$g['state']] += $g['amount']; } }

$nm = $owner ? (trim((string) $owner['full_name']) !== '' ? $owner['full_name'] : 'This member') : '';
$whose = $is_self ? 'your' : '<b>' . h($nm) . '</b>&rsquo;s';
$backHref = $owner ? 'member_details.php?id=' . $tid : 'members.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaction History</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--card:rgba(12,22,52,.65);--accent:#2f6bff;--light:#38bdf8;--border:rgba(130,170,255,.14);--ok:#34d399;--ok-bg:rgba(52,211,153,.12);--wait:#fbbf24;--wait-bg:rgba(251,191,36,.12);--bad:#f87171;--bad-bg:rgba(248,113,113,.12);--muted:#8ea3cc;--text:#eaf1ff}
*{margin:0;padding:0;box-sizing:border-box}
body{background:radial-gradient(1000px 600px at 50% -10%,rgba(47,107,255,.35),transparent 70%),linear-gradient(180deg,#061030,#030817) fixed;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;padding:0 12px 40px}
.wrap{max-width:720px;margin:0 auto}
.bar{position:sticky;top:0;z-index:10;display:flex;align-items:center;gap:12px;padding:14px 0;background:linear-gradient(180deg,#061030 70%,transparent)}
.back{display:inline-flex;align-items:center;gap:8px;height:40px;padding:0 16px;border-radius:12px;background:var(--accent);color:#fff;text-decoration:none;font-size:13px;font-weight:700;flex-shrink:0}
.bar h1{font:700 18px 'Sora',sans-serif}
.sum{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:4px 0 14px}
.sum div{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:12px 10px}
.sum small{display:block;font-size:10.5px;font-weight:700;color:var(--muted);margin-bottom:4px}
.sum h3{font:700 16px 'Sora',sans-serif}
.chips{display:flex;gap:8px;overflow-x:auto;margin-bottom:14px;padding-bottom:2px}
.chip{border:1px solid var(--border);background:var(--card);color:var(--muted);font:700 12px 'Plus Jakarta Sans',sans-serif;padding:8px 14px;border-radius:999px;cursor:pointer;white-space:nowrap}
.chip.on{background:linear-gradient(135deg,var(--accent),var(--light));color:#fff;border-color:transparent}
.card{background:var(--card);border:1px solid var(--border);border-left-width:4px;border-radius:18px;margin-bottom:14px;overflow:hidden}
.s-ok{border-left-color:var(--ok)}.s-wait{border-left-color:var(--wait)}.s-bad{border-left-color:var(--bad)}
.top{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 14px 4px}
.amt{font:700 22px 'Sora',sans-serif}
.s-ok .amt{color:var(--ok)}.s-wait .amt{color:var(--wait)}.s-bad .amt{color:var(--bad)}
.badge{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:99px;font-size:11px;font-weight:800;border:1px solid}
.ok{background:var(--ok-bg);color:var(--ok);border-color:rgba(52,211,153,.3)}
.wait{background:var(--wait-bg);color:var(--wait);border-color:rgba(251,191,36,.3)}
.bad{background:var(--bad-bg);color:var(--bad);border-color:rgba(248,113,113,.3)}
.msg{padding:2px 14px 12px;font-size:12.5px;font-weight:600;color:#cfdcff;line-height:1.5}
.msg b{color:#fff}
.meta,.done{display:grid;border-top:1px solid var(--border);background:rgba(10,20,50,.55)}
.meta{grid-template-columns:1.1fr 1fr 1fr}
.done{grid-template-columns:1.3fr .7fr 1fr}
.done.ok{background:var(--ok-bg)}.done.bad{background:var(--bad-bg)}
.meta>div,.done>div{padding:10px 12px;min-width:0}
.meta>div+div,.done>div+div{border-left:1px solid var(--border)}
.meta small,.done small{display:block;font-size:10px;font-weight:700;color:var(--muted);margin-bottom:3px}
.meta b,.done b{display:block;font-size:12px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.meta em{display:block;font-style:normal;font-size:11px;color:var(--light);font-weight:600;margin-top:1px}
.done.ok b{color:var(--ok)}.done.bad b{color:var(--bad)}
.w{color:var(--wait)!important}
.reason{padding:10px 14px;font-size:12px;font-weight:700;color:var(--bad);background:var(--bad-bg);border-top:1px solid var(--border)}
.months{padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;border-top:1px solid var(--border)}
.pill{padding:3px 9px;border-radius:8px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--light);font-size:11px;font-weight:700}
.type{font-size:11px;font-weight:700;color:var(--muted)}
.empty{text-align:center;padding:60px 20px;background:var(--card);border:1px solid var(--border);border-radius:20px;color:var(--muted)}
.empty i{font-size:40px;margin-bottom:12px}
@media(min-width:560px){.meta b,.done b{font-size:13px}.sum h3{font-size:20px}}
</style>
</head>
<body>
<div class="wrap">

<div class="bar">
    <a href="<?= h($backHref) ?>" class="back"><i class="fas fa-arrow-left"></i> Back</a>
    <div style="min-width:0"><h1>Transaction History</h1><?php if ($owner): ?><small style="display:block;font-size:11px;font-weight:700;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><i class="fas fa-user"></i> <?= h($owner['full_name']) ?><?= !empty($owner['batch_id']) ? ' · #' . h($owner['batch_id']) : '' ?></small><?php endif; ?></div>
</div>

<?php if (!$owner): ?>
<div class="empty">
    <i class="fas fa-user-slash"></i>
    <h3 style="color:var(--text)">Member not found</h3>
    <p style="font-size:13px;margin-top:4px">Open this page from a member&rsquo;s profile.</p>
</div>
<?php else: ?>

<div class="sum">
    <div><small><i class="fas fa-circle-check" style="color:var(--ok)"></i> Accepted</small><h3 style="color:var(--ok)">৳<?= number_format($sum['ok']) ?></h3></div>
    <div><small><i class="fas fa-clock" style="color:var(--wait)"></i> Pending</small><h3 style="color:var(--wait)">৳<?= number_format($sum['wait']) ?></h3></div>
    <div><small><i class="fas fa-receipt" style="color:var(--light)"></i> Requests</small><h3><?= count($cards) ?></h3></div>
</div>

<?php if ($cards): ?>
<div class="chips">
    <button class="chip on" data-f="all">All <?= count($cards) ?></button>
    <button class="chip" data-f="wait">Pending <?= $cnt['wait'] ?></button>
    <button class="chip" data-f="ok">Accepted <?= $cnt['ok'] ?></button>
    <button class="chip" data-f="bad">Rejected <?= $cnt['bad'] ?></button>
</div>

<?php foreach ($cards as $g):
    $s = $g['state'];
    $to = $g['to'] !== '' ? $g['to'] : '—';
    $by = $g['by'] !== '' ? $g['by'] : ($g['to'] !== '' ? $g['to'] : 'Admin');
    $byId = $g['by_id'] !== '' && $g['by_id'] !== null ? $g['by_id'] : $g['to_id'];
    $what = $g['months'] ? implode(', ', array_map('h', $g['months'])) . ' ' . h($g['year']) : 'a special donation';
?>
<article class="card s-<?= $s ?>" data-s="<?= $s ?>">
    <div class="top">
        <div class="amt">৳<?= number_format($g['amount']) ?></div>
        <span class="badge <?= $s ?>"><i class="fas <?= $s === 'ok' ? 'fa-check-circle' : ($s === 'wait' ? 'fa-hourglass-half' : 'fa-times-circle') ?>"></i> <?= $s === 'ok' ? 'Accepted' : ($s === 'wait' ? 'Pending' : 'Rejected') ?></span>
    </div>
    <p class="msg">
        <?php if ($s === 'wait'): ?>Waiting for <b><?= h($to) ?></b> to review <?= $whose ?> payment for <b><?= $what ?></b>.
        <?php elseif ($s === 'ok'): ?><b><?= h($by) ?></b> accepted <?= $whose ?> payment for <b><?= $what ?></b>.
        <?php else: ?><b><?= h($by) ?></b> rejected <?= $whose ?> request for <b><?= $what ?></b>.<?= $is_self ? ' You can send it again.' : '' ?><?php endif; ?>
    </p>

    <div class="meta">
        <div><small>Sent to</small><b><i class="fas fa-user-shield" style="color:var(--light)"></i> <?= h($to) ?></b></div>
        <div><small>Sent</small><?= dt_cell($g['sent']) ?></div>
        <div><small><?= $s === 'ok' ? 'Accepted' : ($s === 'wait' ? 'Status' : 'Rejected') ?></small>
            <?php if ($s === 'wait'): ?><b class="w">Waiting…</b><?php else: ?><?= dt_cell($g['done']) ?><?php endif; ?>
        </div>
    </div>

    <?php if ($s !== 'wait'): ?>
    <div class="done <?= $s ?>">
        <div><small><?= $s === 'ok' ? 'Approved by' : 'Rejected by' ?></small><b><?= h($by) ?></b></div>
        <div><small>Admin ID</small><b><?= $byId !== '' && $byId !== null ? '#' . h($byId) : '—' ?></b></div>
        <div><small>Amount</small><b>৳<?= number_format($g['amount']) ?></b></div>
    </div>
    <?php if ($s === 'bad' && $g['reason'] !== ''): ?><div class="reason"><i class="fas fa-circle-info"></i> <?= h($g['reason']) ?></div><?php endif; ?>
    <?php endif; ?>

    <div class="months">
        <?php foreach ($g['months'] as $m): ?><span class="pill"><?= h($m) ?></span><?php endforeach; ?>
        <span class="type"><?= h($g['year']) ?><?= $g['method'] !== '' ? ' · ' . h($g['method']) : '' ?> · <?= h(strtolower($g['type'])) ?></span>
    </div>
</article>
<?php endforeach; ?>

<?php else: ?>
<div class="empty">
    <i class="fas fa-folder-open"></i>
    <h3 style="color:var(--text)">No transactions yet</h3>
    <p style="font-size:13px;margin-top:4px"><?= $is_self ? 'Your payment requests will show up here.' : 'No payment requests from this member yet.' ?></p>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
<script>
document.querySelectorAll('.chip').forEach(function (c) {
    c.onclick = function () {
        document.querySelectorAll('.chip').forEach(function (x) { x.classList.remove('on'); });
        c.classList.add('on');
        var f = c.dataset.f;
        document.querySelectorAll('.card').forEach(function (k) { k.style.display = (f === 'all' || k.dataset.s === f) ? '' : 'none'; });
    };
});
</script>
</body>
</html>