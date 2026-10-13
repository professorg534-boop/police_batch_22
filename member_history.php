<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

// ১. ডাটাবেস কানেকশন চেক
if (file_exists('db.php')) { 
    include 'db.php'; 
    $root = ''; 
} elseif (file_exists('../db.php')) { 
    include '../db.php'; 
    $root = '../'; 
} else { 
    die("Database connection file missing."); 
}

mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) { 
    header("Location: {$root}login.php"); 
    exit(); 
}

$viewer = (int) $_SESSION['user_id'];

// যার প্রোফাইল দেখা হচ্ছে (id না থাকলে নিজের id লোড হবে)
$uid = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : $viewer;

header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');

// ইউজার ডাটা ফাংশন
function user_row($conn, $id) {
    $row = null;
    if ($st = mysqli_prepare($conn, "SELECT id, full_name, batch_id, role, district, photo FROM users WHERE id = ? LIMIT 1")) {
        mysqli_stmt_bind_param($st, "i", $id);
        if (mysqli_stmt_execute($st) && ($res = mysqli_stmt_get_result($st))) { 
            $row = mysqli_fetch_assoc($res) ?: null; 
        }
        mysqli_stmt_close($st);
    }
    return $row;
}

$me = user_row($conn, $viewer);
if (!$me) { 
    session_destroy(); 
    header("Location: {$root}login.php"); 
    exit(); 
}

$owner = user_row($conn, $uid);
if (!$owner) { 
    echo "<div style='color:#fff; padding:20px; text-align:center; font-family:sans-serif;'>Personnel Not Found.</div>"; 
    exit(); 
}

$is_self = ($viewer === $uid);
$is_admin = in_array(strtolower(trim((string) ($me['role'] ?? ''))), ['admin', 'super admin', 'super_admin', 'superadmin'], true);

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function dt_cell($v) {
    $t = $v ? strtotime($v) : false;
    return $t ? '<b>' . date('d M Y', $t) . '</b><em>' . date('h:i A', $t) . '</em>' : '<b>—</b>';
}

// ডাটাবেজ কলাম চেকিং
$cols = [];
if ($cr = mysqli_query($conn, "SHOW COLUMNS FROM payment_requests")) { 
    while ($c = mysqli_fetch_assoc($cr)) { $cols[] = $c['Field']; } 
}

$hasTo = in_array('admin_id', $cols, true);
$hasBy = in_array('approved_by', $cols, true);

$doneCol = null; 
foreach (['approved_at', 'processed_at', 'updated_at'] as $c) { 
    if (in_array($c, $cols, true)) { $doneCol = $c; break; } 
}

$reasonCol = null; 
foreach (['reject_reason', 'rejection_reason', 'admin_note', 'remark', 'note'] as $c) { 
    if (in_array($c, $cols, true)) { $reasonCol = $c; break; } 
}

// ক্যোয়ারি গঠন
$sel = "p.*"; 
$join = "";
if ($hasTo) { $sel .= ", ut.full_name AS to_name, ut.mobile_number AS to_phone"; $join .= " LEFT JOIN users ut ON ut.id = p.admin_id"; }
if ($hasBy) { $sel .= ", ua.full_name AS ap_name"; $join .= " LEFT JOIN users ua ON ua.id = p.approved_by"; }

$sql = "SELECT $sel FROM payment_requests p $join WHERE p.user_id = ? ORDER BY p.id DESC LIMIT 300";
$rows = [];
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $uid);
    if (mysqli_stmt_execute($stmt) && ($res = mysqli_stmt_get_result($stmt))) {
        while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    }
    mysqli_stmt_close($stmt);
}

// স্ক্রিনশট ও আপলোড পাথ ফিল্টার
$upload_dir = file_exists('uploads') ? 'uploads/' : '../uploads/';

// পরিসংখ্যান হিসেব
$cnt = ['ok' => 0, 'wait' => 0, 'bad' => 0];
$sum = ['ok' => 0, 'wait' => 0];

foreach ($rows as $r) {
    $st = strtolower(trim((string) ($r['status'] ?? 'pending')));
    $state = in_array($st, ['approved', 'success', 'paid'], true) ? 'ok' : ($st === 'pending' ? 'wait' : 'bad');
    $cnt[$state]++;
    if ($state !== 'bad') { $sum[$state] += (float) ($r['amount'] ?? 0); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History · <?= h($owner['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --card: rgba(12, 22, 52, 0.75);
            --accent: #2f6bff;
            --light: #38bdf8;
            --border: rgba(130, 170, 255, 0.16);
            --ok: #34d399;
            --ok-bg: rgba(52, 211, 153, 0.12);
            --wait: #fbbf24;
            --wait-bg: rgba(251, 191, 36, 0.12);
            --bad: #f87171;
            --bad-bg: rgba(248, 113, 113, 0.12);
            --muted: #8ea3cc;
            --text: #eaf1ff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: radial-gradient(1000px 600px at 50% -10%, rgba(47,107,255,.35), transparent 70%), linear-gradient(180deg,#061030,#030817) fixed; 
            color: var(--text); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            min-height: 100vh; 
            padding: 0 12px 60px; 
        }
        .wrap { max-width: 720px; margin: 0 auto; }
        .bar { 
            position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 12px; padding: 14px 0; 
            background: linear-gradient(180deg, #061030 80%, transparent); 
        }
        .back { 
            display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 14px; border-radius: 12px; 
            background: var(--accent); color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; flex-shrink: 0; 
        }
        .bar h1 { font: 700 18px 'Sora', sans-serif; color: #fff; }
        .sum { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 4px 0 14px; }
        .sum div { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 12px 10px; }
        .sum small { display: block; font-size: 10px; font-weight: 700; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; }
        .sum h3 { font: 700 16px 'Sora', sans-serif; }

        .chips { display: flex; gap: 8px; overflow-x: auto; margin-bottom: 14px; padding-bottom: 2px; }
        .chip { border: 1px solid var(--border); background: var(--card); color: var(--muted); font: 700 12px 'Plus Jakarta Sans', sans-serif; padding: 7px 14px; border-radius: 999px; cursor: pointer; white-space: nowrap; }
        .chip.on { background: linear-gradient(135deg, var(--accent), var(--light)); color: #fff; border-color: transparent; }

        .card { background: var(--card); border: 1px solid var(--border); border-left: 4px solid; border-radius: 18px; margin-bottom: 14px; overflow: hidden; backdrop-filter: blur(10px); }
        .s-ok { border-left-color: var(--ok); }
        .s-wait { border-left-color: var(--wait); }
        .s-bad { border-left-color: var(--bad); }

        .top { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 14px 8px; }
        .amt { font: 700 20px 'Sora', sans-serif; }
        .s-ok .amt { color: var(--ok); }
        .s-wait .amt { color: var(--wait); }
        .s-bad .amt { color: var(--bad); }

        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 99px; font-size: 10.5px; font-weight: 800; border: 1px solid; text-transform: uppercase; }
        .ok { background: var(--ok-bg); color: var(--ok); border-color: rgba(52,211,153,.3); }
        .wait { background: var(--wait-bg); color: var(--wait); border-color: rgba(251,191,36,.3); }
        .bad { background: var(--bad-bg); color: var(--bad); border-color: rgba(248,113,113,.3); }

        .detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; padding: 0 14px 10px; }
        .dg-box { background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px; }
        .dg-box label { display: block; font-size: 9px; font-weight: 800; color: var(--muted); text-transform: uppercase; margin-bottom: 2px; }
        .dg-box span { font-size: 12px; font-weight: 700; color: #fff; word-break: break-word; }

        .meta { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--border); background: rgba(10,20,50,.55); }
        .meta > div { padding: 8px 10px; min-width: 0; }
        .meta > div + div { border-left: 1px solid var(--border); }
        .meta small { display: block; font-size: 9px; font-weight: 700; color: var(--muted); margin-bottom: 2px; text-transform: uppercase; }
        .meta b { display: block; font-size: 11px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meta em { display: block; font-style: normal; font-size: 10px; color: var(--light); font-weight: 600; margin-top: 1px; }

        .reason { padding: 10px 14px; font-size: 11.5px; font-weight: 700; color: var(--bad); background: var(--bad-bg); border-top: 1px solid var(--border); display: flex; align-items: center; gap: 6px; }
        .screenshot-area { padding: 10px 14px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); }
        .screenshot-link { color: var(--light); text-decoration: none; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .screenshot-link:hover { text-decoration: underline; }

        .empty { text-align: center; padding: 60px 20px; background: var(--card); border: 1px solid var(--border); border-radius: 20px; color: var(--muted); }
        .empty i { font-size: 40px; margin-bottom: 12px; color: var(--light); }

        /* Modal Image Viewer */
        .modal { display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); justify-content: center; align-items: center; padding: 20px; }
        .modal img { max-width: 100%; max-height: 80vh; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        .modal-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 24px; cursor: pointer; }

        @media (max-width: 500px) {
            .detail-grid { grid-template-columns: 1fr; }
            .meta { grid-template-columns: 1fr; }
            .meta > div + div { border-left: none; border-top: 1px solid var(--border); }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="bar">
        <a href="members.php" class="back" onclick="if(history.length>1){history.back();return false;}">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div style="min-width:0">
            <h1>Payment History</h1>
            <small style="display:block;font-size:11px;font-weight:700;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <i class="fas fa-user"></i> <?= h($owner['full_name']) ?><?= !empty($owner['batch_id']) ? ' · #' . h($owner['batch_id']) : '' ?>
            </small>
        </div>
    </div>

    <!-- Summary Total Cards -->
    <div class="sum">
        <div>
            <small><i class="fas fa-circle-check" style="color:var(--ok)"></i> Accepted</small>
            <h3 style="color:var(--ok)">৳<?= number_format($sum['ok'], 2) ?></h3>
        </div>
        <div>
            <small><i class="fas fa-clock" style="color:var(--wait)"></i> Pending</small>
            <h3 style="color:var(--wait)">৳<?= number_format($sum['wait'], 2) ?></h3>
        </div>
        <div>
            <small><i class="fas fa-receipt" style="color:var(--light)"></i> Requests</small>
            <h3><?= count($rows) ?></h3>
        </div>
    </div>

    <?php if ($rows): ?>
        <div class="chips">
            <button class="chip on" data-f="all">All (<?= count($rows) ?>)</button>
            <button class="chip" data-f="wait">Pending (<?= $cnt['wait'] ?>)</button>
            <button class="chip" data-f="ok">Accepted (<?= $cnt['ok'] ?>)</button>
            <button class="chip" data-f="bad">Rejected (<?= $cnt['bad'] ?>)</button>
        </div>

        <?php foreach ($rows as $r):
            $st = strtolower(trim((string) ($r['status'] ?? 'pending')));
            $s = in_array($st, ['approved', 'success', 'paid'], true) ? 'ok' : ($st === 'pending' ? 'wait' : 'bad');
            
            $toName = !empty($r['to_name']) ? $r['to_name'] : 'Admin';
            $apName = !empty($r['ap_name']) ? $r['ap_name'] : $toName;
            
            $reqType = ucfirst(h($r['request_type'] ?? 'Monthly Deposit'));
            $months = !empty($r['pay_month']) ? h($r['pay_month']) : 'Special Donation';
            $payYear = !empty($r['pay_year']) ? h($r['pay_year']) : '—';
            $method = !empty($r['method']) ? h($r['method']) : '—';
            $trxid = !empty($r['trxid']) ? h($r['trxid']) : '—';
            $screenshot = !empty($r['screenshot']) ? $r['screenshot'] : '';
            $reason = ($s === 'bad' && $reasonCol && !empty($r[$reasonCol])) ? $r[$reasonCol] : '';
            $doneDate = ($s !== 'wait' && $doneCol && !empty($r[$doneCol])) ? $r[$doneCol] : null;
        ?>
        <article class="card s-<?= $s ?>" data-s="<?= $s ?>">
            <div class="top">
                <div class="amt">৳<?= number_format((float)($r['amount'] ?? 0), 2) ?></div>
                <span class="badge <?= $s ?>">
                    <i class="fas <?= $s === 'ok' ? 'fa-check-circle' : ($s === 'wait' ? 'fa-hourglass-half' : 'fa-times-circle') ?>"></i> 
                    <?= $s === 'ok' ? 'Accepted' : ($s === 'wait' ? 'Pending' : 'Rejected') ?>
                </span>
            </div>

            <!-- Requirement Fields Grid -->
            <div class="detail-grid">
                <div class="dg-box">
                    <label>Transaction ID (TrxID)</label>
                    <span style="color:var(--light); font-family:monospace;"><?= $trxid ?></span>
                </div>
                <div class="dg-box">
                    <label>Payment Method</label>
                    <span><?= $method ?></span>
                </div>
                <div class="dg-box">
                    <label>Month(s) & Year</label>
                    <span><?= $months ?><?= $payYear !== '—' ? ' (' . $payYear . ')' : '' ?></span>
                </div>
                <div class="dg-box">
                    <label>Deposit Type</label>
                    <span><?= $reqType ?></span>
                </div>
            </div>

            <!-- Meta Times & Admin Details -->
            <div class="meta">
                <div>
                    <small>Assigned Admin</small>
                    <b><i class="fas fa-user-shield" style="color:var(--light)"></i> <?= h($toName) ?></b>
                </div>
                <div>
                    <small>Submitted Date</small>
                    <?= dt_cell($r['created_at'] ?? null) ?>
                </div>
                <div>
                    <small><?= $s === 'ok' ? 'Approved Date' : ($s === 'bad' ? 'Rejected Date' : 'Status') ?></small>
                    <?php if ($s === 'wait'): ?>
                        <b style="color:var(--wait)">Pending Review</b>
                    <?php else: ?>
                        <?= dt_cell($doneDate) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Proof Screenshot Link -->
            <?php if (!empty($screenshot) && file_exists($upload_dir . $screenshot)): 
                $img_src = $upload_dir . rawurlencode($screenshot);
            ?>
                <div class="screenshot-area">
                    <span style="font-size:11px; font-weight:700; color:var(--muted);">Payment Proof:</span>
                    <a href="javascript:void(0)" class="screenshot-link" onclick="openModal('<?= $img_src ?>')">
                        <i class="fas fa-image"></i> View Receipt Image
                    </a>
                </div>
            <?php endif; ?>

            <!-- Rejection Reason if Failed -->
            <?php if ($s === 'bad' && !empty($reason)): ?>
                <div class="reason">
                    <i class="fas fa-exclamation-triangle"></i> Rejection Reason: <?= h($reason) ?>
                </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>

    <?php else: ?>
        <div class="empty">
            <i class="fas fa-folder-open"></i>
            <h3 style="color:var(--text)">No transactions found</h3>
            <p style="font-size:13px; margin-top:4px">This member has not submitted any payment requests yet.</p>
        </div>
    <?php endif; ?>

</div>

<!-- Modal for Image Preview -->
<div id="imgModal" class="modal" onclick="closeModal()">
    <span class="modal-close"><i class="fas fa-times"></i></span>
    <img id="modalImg" src="" alt="Proof Screenshot">
</div>

<script>
// Filter Chips
document.querySelectorAll('.chip').forEach(function (c) {
    c.onclick = function () {
        document.querySelectorAll('.chip').forEach(function (x) { x.classList.remove('on'); });
        c.classList.add('on');
        var f = c.dataset.f;
        document.querySelectorAll('.card').forEach(function (k) { 
            k.style.display = (f === 'all' || k.dataset.s === f) ? '' : 'none'; 
        });
    };
});

// Modal Image Functions
function openModal(src) {
    document.getElementById('modalImg').src = src;
    document.getElementById('imgModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('imgModal').style.display = 'none';
}
</script>
</body>
</html>