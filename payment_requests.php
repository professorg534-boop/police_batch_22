<?php
// payment_request.php te include hobe. Shudhu logged-in sender er nijer request dekhabe.
if (!isset($conn, $user_id)) { return; }
$__uid = (int) $user_id;

if (!function_exists('rs_h')) {
    function rs_h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('rs_dt')) {
    function rs_dt($v) {
        $t = $v ? strtotime($v) : false;
        return $t ? '<b>' . date('d M Y', $t) . '</b><em>' . date('h:i A', $t) . '</em>' : '<b>—</b>';
    }
}

$__cols = [];
if ($r = mysqli_query($conn, "SHOW COLUMNS FROM payment_requests")) {
    while ($c = mysqli_fetch_assoc($r)) { $__cols[] = $c['Field']; }
}
$__doneCol = null;   // accept/reject somoy
foreach (['approved_at', 'processed_at', 'updated_at'] as $c) { if (in_array($c, $__cols, true)) { $__doneCol = $c; break; } }
$__reasonCol = null; // reject er karon (thakle)
foreach (['reject_reason', 'rejection_reason', 'admin_note', 'remark', 'note'] as $c) { if (in_array($c, $__cols, true)) { $__reasonCol = $c; break; } }

$sel = "p.*, ut.full_name AS to_name";
$join = " LEFT JOIN users ut ON ut.id = p.admin_id";
if (in_array('approved_by', $__cols, true)) { $sel .= ", ua.full_name AS ap_name"; $join .= " LEFT JOIN users ua ON ua.id = p.approved_by"; }

$sql = "SELECT $sel FROM payment_requests p $join WHERE p.user_id = ? AND (p.admin_id IS NULL OR p.admin_id <> ?) ORDER BY p.id DESC LIMIT 60";
$groups = [];
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $__uid, $__uid);
    mysqli_stmt_execute($stmt);
    if ($res = mysqli_stmt_get_result($stmt)) {
        while ($row = mysqli_fetch_assoc($res)) {
            if ((int) $row['user_id'] !== $__uid) { continue; }
            $st = strtolower(trim((string) ($row['status'] ?? 'pending')));
            $state = in_array($st, ['approved', 'success', 'paid'], true) ? 'ok' : ($st === 'pending' ? 'wait' : 'bad');
            $trx = trim((string) ($row['trxid'] ?? ''));
            $key = ($trx !== '' ? $trx : 'row' . $row['id']) . '|' . ($row['admin_id'] ?? '') . '|' . $state . '|' . ($row['pay_year'] ?? '');
            if (!isset($groups[$key])) {
                $groups[$key] = ['state' => $state, 'amount' => 0, 'months' => [], 'year' => $row['pay_year'] ?? '', 'type' => $row['request_type'] ?? 'monthly',
                                 'method' => $row['method'] ?? '', 'to' => $row['to_name'] ?? '', 'by' => '', 'by_id' => '', 'sent' => null, 'done' => null, 'reason' => ''];
            }
            $g = $groups[$key];
            $g['amount'] += (float) ($row['amount'] ?? 0);
            $m = trim((string) ($row['pay_month'] ?? ''));
            if ($m !== '') { foreach (explode(',', $m) as $mm) { $mm = trim($mm); if ($mm !== '' && !in_array($mm, $g['months'], true)) { $g['months'][] = $mm; } } }
            $g['sent'] = $row['created_at'] ?? $g['sent'];
            if ($state !== 'wait') {
                if ($__doneCol && !empty($row[$__doneCol])) { $g['done'] = $row[$__doneCol]; }
                if (!empty($row['ap_name'])) { $g['by'] = $row['ap_name']; }
                if (!empty($row['approved_by'])) { $g['by_id'] = $row['approved_by']; }
                if ($__reasonCol && !empty($row[$__reasonCol])) { $g['reason'] = $row[$__reasonCol]; }
            }
            $groups[$key] = $g;
        }
    }
    mysqli_stmt_close($stmt);
}
$groups = array_slice($groups, 0, 5);
if (empty($groups)) { return; }
?>
<style>
.rs-head { font: 700 13px var(--display); color: var(--muted); margin: 0 4px 12px; display: flex; align-items: center; gap: 8px; }
.rs { position: relative; overflow: hidden; border-radius: 22px; padding: 16px; margin-bottom: 14px; border: 1px solid var(--line); backdrop-filter: blur(16px); box-shadow: 0 16px 38px rgba(0,0,0,.32); }
.rs::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; }
.rs-wait { background: linear-gradient(135deg, rgba(251,191,36,.16), rgba(251,191,36,.04)); border-color: rgba(251,191,36,.32); }
.rs-wait::before { background: var(--wait); }
.rs-ok { background: linear-gradient(135deg, rgba(52,211,153,.2), rgba(56,189,248,.06)); border-color: rgba(52,211,153,.4); }
.rs-ok::before { background: var(--ok); }
.rs-bad { background: linear-gradient(135deg, rgba(248,113,113,.18), rgba(248,113,113,.04)); border-color: rgba(248,113,113,.36); }
.rs-bad::before { background: var(--danger); }
.rs-top { display: flex; align-items: center; gap: 12px; }
.rs-ic { width: 42px; height: 42px; border-radius: 14px; display: grid; place-items: center; font-size: 19px; flex-shrink: 0; }
.rs-wait .rs-ic { background: var(--wait-bg); color: var(--wait); }
.rs-ok .rs-ic { background: var(--ok-bg); color: var(--ok); animation: rsPop .5s ease-out 1; }
.rs-bad .rs-ic { background: var(--danger-bg); color: var(--danger); }
.rs-top h4 { font: 700 15px var(--display); flex: 1; min-width: 0; }
.rs-wait h4 { color: var(--wait); } .rs-ok h4 { color: var(--ok); } .rs-bad h4 { color: var(--danger); }
.rs-amt { font: 700 20px var(--display); color: #fff; }
.rs-msg { margin: 12px 0; font-size: 13px; line-height: 1.55; font-weight: 600; color: #dbe6ff; }
.rs-msg b { color: #fff; }
.rs-reason { margin: -2px 0 12px; padding: 9px 12px; border-radius: 12px; background: var(--danger-bg); color: var(--danger); font-size: 12px; font-weight: 700; }
.rs-grid { display: grid; grid-template-columns: 1.1fr 1fr 1fr; border: 1px solid var(--line); border-radius: 14px; background: rgba(0,0,0,.22); overflow: hidden; }
.rs-grid > div { padding: 9px 10px; min-width: 0; }
.rs-grid > div + div { border-left: 1px solid var(--line); }
.rs-grid span { display: block; font-size: 9.5px; font-weight: 800; color: var(--muted); margin-bottom: 3px; }
.rs-grid b { display: block; font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rs-grid em { display: block; font-style: normal; font-size: 10.5px; font-weight: 600; color: var(--accent-2); margin-top: 1px; }
.rs-grid .w { color: var(--wait); }
.rs-tags { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 12px; }
.rs-pill { padding: 3px 9px; border-radius: 8px; background: rgba(255,255,255,.06); border: 1px solid var(--line); color: var(--accent-2); font-size: 11px; font-weight: 700; }
.rs-meta { font-size: 11px; font-weight: 700; color: var(--muted); }
@keyframes rsPop { 0% { transform: scale(.5); opacity: 0; } 70% { transform: scale(1.15); } 100% { transform: scale(1); opacity: 1; } }
@media (prefers-reduced-motion: reduce) { .rs-ic { animation: none !important; } }
</style>

<section aria-label="Your request status">
    <div class="rs-head"><i class="fas fa-bell"></i> Your request status</div>

    <?php foreach ($groups as $g):
        $s = $g['state'];
        $to = $g['to'] !== '' ? $g['to'] : 'the admin';
        $by = $g['by'] !== '' ? $g['by'] : $to;
        $idTxt = $g['by_id'] !== '' ? ' (ID #' . rs_h($g['by_id']) . ')' : '';
        $amt = '৳' . number_format($g['amount']);
        $what = $g['months'] ? implode(', ', array_map('rs_h', $g['months'])) . ' ' . rs_h($g['year']) : 'a special donation';
        $icon = $s === 'ok' ? 'fa-circle-check' : ($s === 'wait' ? 'fa-hourglass-half' : 'fa-circle-xmark');
        $title = $s === 'ok' ? 'Payment accepted' : ($s === 'wait' ? 'Payment pending' : 'Payment rejected');
        $label = $s === 'ok' ? 'Accepted' : ($s === 'wait' ? 'Status' : 'Rejected');
    ?>
    <article class="rs rs-<?= $s ?>">
        <div class="rs-top">
            <div class="rs-ic"><i class="fas <?= $icon ?>"></i></div>
            <h4><?= $title ?></h4>
            <div class="rs-amt"><?= $amt ?></div>
        </div>

        <p class="rs-msg">
            <?php if ($s === 'wait'): ?>
                Your request of <b><?= $amt ?></b> for <b><?= $what ?></b> was sent to <b><?= rs_h($to) ?></b>. It is waiting for admin approval.
            <?php elseif ($s === 'ok'): ?>
                <b><?= rs_h($by) ?></b><?= $idTxt ?> accepted your <b><?= $amt ?></b> payment for <b><?= $what ?></b>. Your deposit is confirmed.
            <?php else: ?>
                <b><?= rs_h($by) ?></b><?= $idTxt ?> rejected your <b><?= $amt ?></b> request for <b><?= $what ?></b>. Please check the details and send it again.
            <?php endif; ?>
        </p>
        <?php if ($s === 'bad' && $g['reason'] !== ''): ?><div class="rs-reason"><i class="fas fa-circle-info"></i> <?= rs_h($g['reason']) ?></div><?php endif; ?>

        <div class="rs-grid">
            <div><span>Sent to</span><b><?= rs_h($to) ?></b></div>
            <div><span>Sent</span><?= rs_dt($g['sent']) ?></div>
            <div><span><?= $label ?></span><?php if ($s === 'wait' || !$g['done']): ?><b class="w"><?= $s === 'wait' ? 'Waiting…' : '—' ?></b><?php else: ?><?= rs_dt($g['done']) ?><?php endif; ?></div>
        </div>

        <div class="rs-tags">
            <?php foreach ($g['months'] as $m): ?><span class="rs-pill"><?= rs_h($m) ?></span><?php endforeach; ?>
            <span class="rs-meta"><?= rs_h($g['year']) ?><?= $g['method'] !== '' ? ' · ' . rs_h($g['method']) : '' ?> · <?= rs_h(strtolower($g['type'])) ?></span>
        </div>
    </article>
    <?php endforeach; ?>
</section>