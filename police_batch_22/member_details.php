<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id'])) { echo "Invalid or missing ID."; exit(); }

$id = (int) $_GET['id'];
$my_id = (int) $_SESSION['user_id'];

// লগইন করা ইউজারের রোল বের করা (Prepared Statement)
$me_stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($me_stmt, "i", $my_id);
mysqli_stmt_execute($me_stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($me_stmt));
mysqli_stmt_close($me_stmt);

$my_role = strtolower($me['role'] ?? 'member');
$is_admin = in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin']);
$is_self = ($my_id === $id);
$can_edit = $is_admin || $is_self; // শুধু "Update Profile" বাটনের জন্য — বাকি সব তথ্য সবাই দেখতে পাবে (স্বচ্ছতার স্বার্থে)

// টার্গেট ইউজারের ডাটা লোড করা (Prepared Statement)
$user_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, "i", $id);
mysqli_stmt_execute($user_stmt);
$u = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);

if (!$u) { echo "Personnel Not Found."; exit(); }

// পেমেন্ট ক্যালকুলেশন — সবার জন্য উন্মুক্ত (transparency)
// LOWER(status) IN (...) ব্যবহার করা হচ্ছে dashboard.php ও district_monitor.php এর সাথে সামঞ্জস্য রাখতে
// request_type অনুযায়ী Monthly Dues আর Extra Donation আলাদা করে গণনা হচ্ছে
$pay_stmt = mysqli_prepare($conn, "SELECT
        SUM(CASE WHEN COALESCE(request_type, 'monthly') = 'monthly' THEN amount ELSE 0 END) as monthly_total,
        SUM(CASE WHEN request_type = 'donation' THEN amount ELSE 0 END) as donation_total
    FROM payment_requests WHERE user_id = ? AND LOWER(status) IN ('approved', 'success')");
mysqli_stmt_bind_param($pay_stmt, "i", $id);
mysqli_stmt_execute($pay_stmt);
$pay_row = mysqli_fetch_assoc(mysqli_stmt_get_result($pay_stmt));
$total_funds = $pay_row['monthly_total'] ? $pay_row['monthly_total'] : 0;
$total_donations = $pay_row['donation_total'] ? $pay_row['donation_total'] : 0;
mysqli_stmt_close($pay_stmt);

$pend_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as pending_total FROM payment_requests WHERE user_id = ? AND LOWER(status) = 'pending'");
mysqli_stmt_bind_param($pend_stmt, "i", $id);
mysqli_stmt_execute($pend_stmt);
$pending_count = mysqli_fetch_assoc(mysqli_stmt_get_result($pend_stmt))['pending_total'];
mysqli_stmt_close($pend_stmt);

$statusLower = strtolower($u['status'] ?? 'pending');
$batchDigits = str_split(preg_replace('/\D/', '', (string) ($u['batch_id'] ?? '000000')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Dossier · <?= htmlspecialchars($u['full_name'] ?? 'Unknown') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    :root {
        --ink: #04060b;
        --panel: #0a0e18;
        --panel-2: #0d1220;
        --line: rgba(255,255,255,0.09);
        --line-strong: rgba(255,255,255,0.16);
        --indigo: #6366f1;
        --brass: #c9a43c;
        --brass-dim: rgba(201,164,60,0.15);
        --text: #f2f4fa;
        --muted: #838da3;
        --success: #22c55e;
        --pending: #eab308;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background: var(--ink);
        background-image: radial-gradient(ellipse 900px 500px at 15% -10%, rgba(99,102,241,0.12), transparent 60%);
        color: var(--text);
        font-family: 'Plus Jakarta Sans', sans-serif;
        min-height: 100vh;
        padding: 40px 20px;
    }
    .wrap { max-width: 1080px; margin: 0 auto; }

    .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 14px; }
    .crumb { font-size: 12px; color: var(--muted); font-weight: 600; letter-spacing: 0.3px; }
    .crumb b { color: var(--text); }
    .back-link {
        display: inline-flex; align-items: center; gap: 8px;
        color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 600;
        padding: 9px 16px; border: 1px solid var(--line); border-radius: 10px;
        transition: 0.2s;
    }
    .back-link:hover { color: var(--text); border-color: var(--line-strong); background: rgba(255,255,255,0.03); }

    .dossier {
        border: 1px solid var(--line);
        border-radius: 22px;
        background: var(--panel);
        display: grid;
        grid-template-columns: 300px 1fr;
        overflow: hidden;
    }

    /* ===== LEFT: ID STRIP ===== */
    .id-strip {
        background: var(--panel-2);
        border-right: 1px solid var(--line);
        padding: 34px 28px;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .status-flag {
        position: absolute; top: 22px; right: -1px;
        font-size: 9px; font-weight: 700; letter-spacing: 1.5px;
        padding: 6px 14px 6px 12px;
        border-radius: 8px 0 0 8px;
        text-transform: uppercase;
    }
    .status-flag.active { background: rgba(34,197,94,0.15); color: var(--success); }
    .status-flag.pending { background: rgba(234,179,8,0.15); color: var(--pending); }

    .photo-frame {
        width: 128px; height: 128px;
        position: relative;
        margin: 10px 0 20px;
    }
    .photo-frame img, .photo-frame .ph {
        width: 100%; height: 100%; object-fit: cover;
        background: #131a2b; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-family: 'Space Grotesk'; font-size: 46px; font-weight: 600; color: var(--muted);
    }
    .photo-frame::before, .photo-frame::after,
    .corner-tl, .corner-br { content: ""; position: absolute; width: 18px; height: 18px; }
    .corner-tl { top: -6px; left: -6px; border-top: 2px solid var(--brass); border-left: 2px solid var(--brass); border-radius: 4px 0 0 0; }
    .corner-br { bottom: -6px; right: -6px; border-bottom: 2px solid var(--brass); border-right: 2px solid var(--brass); border-radius: 0 0 4px 0; }

    .p-name { font-family: 'Space Grotesk'; font-size: 21px; font-weight: 700; letter-spacing: -0.3px; line-height: 1.25; }
    .p-role { margin-top: 6px; font-size: 11px; color: var(--brass); font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }

    .divider { width: 100%; height: 1px; background: var(--line); margin: 24px 0 20px; }

    .barcode-block { width: 100%; text-align: left; }
    .barcode-label { font-size: 10px; color: var(--muted); font-weight: 700; letter-spacing: 1px; margin-bottom: 8px; }
    .barcode { display: flex; gap: 3px; height: 34px; margin-bottom: 8px; }
    .barcode span { flex: 1; background: var(--text); border-radius: 1px; }
    .barcode span:nth-child(3n) { background: var(--brass); }
    .barcode span:nth-child(5n) { opacity: 0.35; }
    .batch-number { font-family: 'Space Grotesk'; font-size: 15px; font-weight: 600; letter-spacing: 3px; color: var(--text); }

    .bp-block { width: 100%; text-align: left; margin-top: 20px; }
    .bp-label { font-size: 10px; color: var(--muted); font-weight: 700; letter-spacing: 1px; margin-bottom: 4px; }
    .bp-value { font-family: 'Space Grotesk'; font-size: 16px; font-weight: 600; color: var(--success); }

    /* ===== RIGHT: FIELD SHEET ===== */
    .sheet { padding: 34px 38px; }

    .sheet-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; gap: 16px; flex-wrap: wrap; }
    .sheet-head h1 { font-family: 'Space Grotesk'; font-size: 24px; font-weight: 700; }
    .sheet-head p { font-size: 13px; color: var(--muted); margin-top: 4px; }

    .fund-pair { display: flex; gap: 32px; flex-wrap: wrap; }
    .fund-item .f-label { font-size: 11px; color: var(--muted); font-weight: 600; }
    .fund-item .f-val { font-family: 'Space Grotesk'; font-size: 26px; font-weight: 700; margin-top: 4px; }
    .fund-item .f-val.success { color: var(--success); }
    .fund-item .f-val.pending { color: var(--pending); }
    .fund-item .f-val.donation { color: var(--brass); }
    .fund-item.donation-stat { padding-left: 24px; border-left: 1px dashed var(--line); }
    .fund-item .f-label i { margin-right: 5px; font-size: 10px; }

    .section-label {
        font-size: 11px; color: var(--brass); font-weight: 700; letter-spacing: 1px;
        margin: 30px 0 4px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--line);
    }

    .field-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 14px 0; border-bottom: 1px solid var(--line);
        gap: 20px;
    }
    .field-row:last-child { border-bottom: none; }
    .field-row .f-key { font-size: 13px; color: var(--muted); font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .field-row .f-key i { color: var(--indigo); width: 16px; text-align: center; font-size: 13px; }
    .field-row .f-val2 { font-size: 14px; font-weight: 700; color: var(--text); text-align: right; word-break: break-word; }

    .actions { display: flex; gap: 14px; margin-top: 34px; flex-wrap: wrap; }
    .btn {
        display: inline-flex; align-items: center; gap: 10px;
        padding: 14px 24px; border-radius: 12px;
        font-weight: 700; font-size: 13px; text-decoration: none;
        transition: 0.2s;
    }
    .btn-primary { background: var(--brass); color: #1a1400; }
    .btn-primary:hover { background: #ddb84a; transform: translateY(-1px); }
    .btn-outline { border: 1px solid var(--line-strong); color: var(--text); }
    .btn-outline:hover { background: rgba(255,255,255,0.04); border-color: var(--muted); }

    @media (max-width: 760px) {
        body { padding: 20px 14px; }
        .dossier { grid-template-columns: 1fr; }
        .id-strip { border-right: none; border-bottom: 1px solid var(--line); }
        .sheet { padding: 28px 22px; }
        .fund-pair { gap: 24px; }
        .actions { flex-direction: column; }
        .btn { width: 100%; justify-content: center; }
    }
</style>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div class="crumb">Personnel Registry / <b><?= htmlspecialchars($u['full_name'] ?? 'Unknown') ?></b></div>
        <a href="members.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to directory</a>
    </div>

    <div class="dossier">
        <!-- LEFT: ID STRIP -->
        <div class="id-strip">
            <span class="status-flag <?= $statusLower === 'active' ? 'active' : 'pending' ?>"><?= htmlspecialchars(ucfirst($statusLower)) ?></span>

            <div class="photo-frame">
                <div class="corner-tl"></div><div class="corner-br"></div>
                <?php if (!empty($u['photo'])): ?>
                    <img src="uploads/<?= htmlspecialchars($u['photo']) ?>" alt="Profile">
                <?php else: ?>
                    <div class="ph"><?= htmlspecialchars(strtoupper(substr($u['full_name'] ?? 'U', 0, 1))) ?></div>
                <?php endif; ?>
            </div>

            <div class="p-name"><?= htmlspecialchars($u['full_name'] ?? 'Unknown') ?></div>
            <div class="p-role"><?= htmlspecialchars($u['role'] ?? 'Member') ?></div>

            <div class="divider"></div>

            <div class="barcode-block">
                <div class="barcode-label">BATCH IDENTITY</div>
                <div class="barcode">
                    <?php foreach ($batchDigits as $i => $d): ?><span></span><?php endforeach; ?>
                </div>
                <div class="batch-number">#<?= htmlspecialchars($u['batch_id'] ?? 'N/A') ?></div>
            </div>

            <div class="bp-block">
                <div class="bp-label">BP NUMBER</div>
                <div class="bp-value"><?= htmlspecialchars($u['bp_number'] ?? 'N/A') ?></div>
            </div>
        </div>

        <!-- RIGHT: FIELD SHEET -->
        <div class="sheet">
            <div class="sheet-head">
                <div>
                    <h1>Fund Activity</h1>
                    <p>Contribution record for this member</p>
                </div>
            </div>

            <div class="fund-pair">
                <div class="fund-item">
                    <div class="f-label">Verified Contributions</div>
                    <div class="f-val success">৳<?= number_format($total_funds) ?></div>
                </div>
                <?php if ($total_donations > 0): ?>
                <div class="fund-item donation-stat">
                    <div class="f-label"><i class="fas fa-gift"></i>Extra Support</div>
                    <div class="f-val donation">৳<?= number_format($total_donations) ?></div>
                </div>
                <?php endif; ?>
                <div class="fund-item">
                    <div class="f-label">Pending Requests</div>
                    <div class="f-val pending"><?= (int) $pending_count ?></div>
                </div>
            </div>

            <div class="section-label">IDENTITY &amp; POSTING</div>

            <div class="field-row">
                <span class="f-key"><i class="fas fa-phone-volume"></i> Contact Number</span>
                <span class="f-val2"><?= htmlspecialchars($u['mobile_number'] ?? 'N/A') ?></span>
            </div>
            <div class="field-row">
                <span class="f-key"><i class="fas fa-location-dot"></i> District</span>
                <span class="f-val2"><?= htmlspecialchars($u['district'] ?? 'N/A') ?></span>
            </div>
            <div class="field-row">
                <span class="f-key"><i class="fas fa-map"></i> Division</span>
                <span class="f-val2"><?= htmlspecialchars($u['division'] ?? 'N/A') ?></span>
            </div>
            <div class="field-row">
                <span class="f-key"><i class="fas fa-user-tag"></i> Gender</span>
                <span class="f-val2"><?= htmlspecialchars($u['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="field-row">
                <span class="f-key"><i class="fas fa-tint"></i> Blood Group</span>
                <span class="f-val2"><?= htmlspecialchars($u['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="field-row">
                <span class="f-key"><i class="fas fa-calendar-check"></i> Joined</span>
                <span class="f-val2"><?= !empty($u['created_at']) ? date('d M, Y', strtotime($u['created_at'])) : 'N/A' ?></span>
            </div>
            <div class="field-row">
                <span class="f-key"><i class="fas fa-clock"></i> Last Login</span>
                <span class="f-val2"><?= !empty($u['last_login']) ? date('d M, Y · h:i A', strtotime($u['last_login'])) : 'Never logged in' ?></span>
            </div>

            <div class="actions">
                <a href="member_payments.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-file-invoice-dollar"></i> Transaction Log</a>
                <?php if ($can_edit): ?>
                <a href="edit_member.php?id=<?= $id ?>" class="btn btn-outline"><i class="fas fa-user-gear"></i> Update Profile</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>