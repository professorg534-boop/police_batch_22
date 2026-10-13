<?php
session_start();
include '../db.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// এডমিন ভেরিফিকেশন
$user_res = mysqli_query($conn, "SELECT full_name, role, district FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_res);

$role = strtolower($user_data['role'] ?? '');
if(!($role == 'admin' || $role == 'super admin' || $role == 'superadmin')) {
    header("Location: ../dashboard.php"); exit();
}

$admin_district = $user_data['district'];

// অ্যাকশন হ্যান্ডলিং (প্রসেস হওয়ার পর সাকসেস মেসেজ দেখানোর জন্য)
$show_premium_msg = false;
$msg_type = "";
if(isset($_GET['action']) && isset($_GET['id'])) {
    $req_id = mysqli_real_escape_string($conn, $_GET['id']);
    $st = ($_GET['action'] == 'approve') ? 'approved' : 'rejected';
    
    if(mysqli_query($conn, "UPDATE payment_requests SET status = '$st', admin_id = '$user_id' WHERE id = '$req_id'")) {
        $show_premium_msg = true;
        $msg_type = $st;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Elite Terminal | Financial Audit</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #03040a; --surface: #0a0b14; --accent: #6366f1;
            --success: #00ff9d; --error: #ff3366; --border: rgba(255, 255, 255, 0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; padding: 40px 20px;
            background-image: radial-gradient(circle at 0% 0%, #1a1b3a 0%, transparent 40%);
        }

        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header */
        .header-nav { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 50px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .back-btn { text-decoration: none; color: #fff; font-size: 11px; font-weight: 800; letter-spacing: 2px; background: rgba(255,255,255,0.05); padding: 10px 20px; border-radius: 12px; transition: 0.3s; }
        .back-btn:hover { background: var(--error); }

        /* Grid Layout */
        .terminal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 25px; }

        /* Detail Card */
        .audit-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 32px;
            padding: 25px; position: relative; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .audit-card:hover { transform: scale(1.02); border-color: var(--accent); }

        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .member-profile h3 { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 4px; }
        .member-profile span { font-size: 11px; color: var(--accent); font-weight: 700; text-transform: uppercase; }

        .payment-info { background: rgba(255,255,255,0.02); border-radius: 20px; padding: 15px; margin-bottom: 20px; border: 1px solid var(--border); }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; }
        .label { color: #555; font-weight: 700; font-size: 10px; text-transform: uppercase; }
        .val { color: #eee; font-weight: 700; }

        .billing-cycle { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #222; }
        .month-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .month-tag { background: rgba(0, 255, 157, 0.1); color: var(--success); font-size: 10px; font-weight: 800; padding: 5px 12px; border-radius: 8px; border: 1px solid rgba(0, 255, 157, 0.2); }

        .amount-display { font-family: 'Space Grotesk'; font-size: 28px; color: var(--success); font-weight: 700; margin: 15px 0; }

        .card-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn { flex: 1; padding: 14px; border-radius: 14px; text-decoration: none; font-size: 11px; font-weight: 800; text-align: center; transition: 0.3s; cursor: pointer; border: none; }
        .btn-approve { background: var(--success); color: #000; }
        .btn-decline { background: rgba(255, 51, 102, 0.1); color: var(--error); border: 1px solid rgba(255, 51, 102, 0.2); }
        .btn-proof { background: #fff; color: #000; width: 100%; margin-bottom: 10px; }

        /* --- Custom Premium Confirm Box CSS --- */
        .confirm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.9); backdrop-filter: blur(10px); z-index: 11000; display: none; align-items: center; justify-content: center; }
        .confirm-box { background: #0d0d12; border: 1px solid var(--border); padding: 40px; border-radius: 35px; text-align: center; max-width: 400px; width: 90%; animation: confirmPop 0.4s ease; }
        @keyframes confirmPop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .confirm-btns { display: flex; gap: 15px; margin-top: 30px; }
        .c-btn { flex: 1; padding: 15px; border-radius: 15px; border: none; font-weight: 800; cursor: pointer; font-size: 12px; }
        .c-yes { background: var(--accent); color: #fff; }
        .c-no { background: rgba(255,255,255,0.05); color: #fff; }
        
        /* Success Overlay */
        .action-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.95); backdrop-filter: blur(15px); z-index: 12000; display: flex; align-items: center; justify-content: center; }
        .status-card { background: #0d0d12; border: 1px solid var(--border); padding: 50px; border-radius: 40px; text-align: center; }
        .status-icon { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 35px; }
        .icon-approved { background: rgba(0, 255, 157, 0.1); color: #00ff9d; border: 1px solid #00ff9d; }
        .icon-rejected { background: rgba(255, 51, 102, 0.1); color: #ff3366; border: 1px solid #ff3366; }

        #proofModal { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 40px; }
        #proofModal img { max-width: 100%; max-height: 90vh; border-radius: 20px; box-shadow: 0 0 100px var(--accent); }
        .close-modal { position: absolute; top: 30px; right: 40px; font-size: 40px; color: #fff; cursor: pointer; }
    </style>
</head>
<body>

<?php if($show_premium_msg): ?>
<div class="action-overlay">
    <div class="status-card">
        <div class="status-icon <?php echo ($msg_type == 'approved') ? 'icon-approved' : 'icon-rejected'; ?>">
            <i class="fas <?php echo ($msg_type == 'approved') ? 'fa-check' : 'fa-times'; ?>"></i>
        </div>
        <h2 style="font-family: 'Space Grotesk'; font-size: 28px;"><?php echo ($msg_type == 'approved') ? 'AUTHORIZED' : 'TERMINATED'; ?></h2>
        <p style="color: #666; margin: 15px 0 30px;">Protocol processed successfully.</p>
        <a href="payment_approval.php" style="background: var(--accent); color: #fff; padding: 15px 40px; border-radius: 15px; text-decoration: none; font-weight: 800;">CONTINUE</a>
    </div>
</div>
<?php endif; ?>

<div id="customConfirm" class="confirm-overlay">
    <div class="confirm-box">
        <h2 id="confirmTitle" style="font-family: 'Space Grotesk';">Confirm Action</h2>
        <p id="confirmText" style="color: #666; margin-top: 10px;">Are you sure you want to proceed?</p>
        <div class="confirm-btns">
            <button class="c-btn c-no" onclick="closeConfirm()">CANCEL</button>
            <a id="confirmLink" href="#" class="c-btn c-yes" style="text-decoration:none; display:flex; align-items:center; justify-content:center;">CONFIRM</a>
        </div>
    </div>
</div>

<div id="proofModal">
    <span class="close-modal" onclick="closeProof()">&times;</span>
    <img id="modalImg" src="" alt="Proof">
</div>

<div class="container">
    <div class="header-nav">
        <div>
            <a href="../dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> BACK TO DASHBOARD</a>
            <h1 style="font-family: 'Space Grotesk'; font-size: 35px; margin-top: 10px;">Payment Audit Center</h1>
        </div>
        <div style="text-align: right">
            <span style="font-size: 10px; color: #555; font-weight: 800; letter-spacing: 2px;">LOGISTICS SECTOR</span>
            <p style="color: var(--accent); font-weight: 800; font-size: 20px;"><?php echo strtoupper($admin_district); ?></p>
        </div>
    </div>

    <div class="terminal-grid">
        <?php
        $sql = "SELECT p.*, u.full_name, u.batch_id FROM payment_requests p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.district = '$admin_district' AND p.status = 'pending' 
                ORDER BY p.id DESC";
        $res = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($res)):
            $months = explode(',', $row['pay_month']);
        ?>
        <div class="audit-card">
            <div class="card-header">
                <div class="member-profile">
                    <h3><?php echo htmlspecialchars($row['full_name']); ?></h3>
                    <span>Batch ID: <?php echo $row['batch_id']; ?></span>
                </div>
            </div>

            <div class="payment-info">
                <div class="info-row"><span class="label">Transaction ID</span><span class="val" style="color: var(--accent);"><?php echo $row['trxid']; ?></span></div>
                <div class="info-row"><span class="label">Method</span><span class="val"><?php echo strtoupper($row['method']); ?></span></div>
                <div class="info-row"><span class="label">Date</span><span class="val"><?php echo date('d M, Y', strtotime($row['created_at'])); ?></span></div>
            </div>

            <div class="billing-cycle">
                <span class="label">Payment For (<?php echo $row['pay_year']; ?>)</span>
                <div class="month-list">
                    <?php foreach($months as $m): ?>
                        <span class="month-tag"><?php echo trim($m); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="amount-display">৳<?php echo number_format($row['amount']); ?></div>

            <button class="btn btn-proof" onclick="showProof('../uploads/payments/<?php echo $row['screenshot']; ?>')">VIEW SCREENSHOT</button>
            
            <div class="card-actions">
                <a href="javascript:void(0)" onclick="openConfirm('Accept Payment?', '?action=approve&id=<?php echo $row['id']; ?>')" class="btn btn-approve">ACCEPT</a>
                <a href="javascript:void(0)" onclick="openConfirm('Reject Payment?', '?action=decline&id=<?php echo $row['id']; ?>')" class="btn btn-decline">REJECT</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
    function showProof(src) { document.getElementById('modalImg').src = src; document.getElementById('proofModal').style.display = 'flex'; }
    function closeProof() { document.getElementById('proofModal').style.display = 'none'; }
    
    function openConfirm(title, url) {
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmLink').href = url;
        document.getElementById('customConfirm').style.display = 'flex';
    }
    function closeConfirm() { document.getElementById('customConfirm').style.display = 'none'; }

    window.onclick = function(event) {
        if (event.target == document.getElementById('proofModal')) closeProof();
        if (event.target == document.getElementById('customConfirm')) closeConfirm();
    }
</script>

</body>
</html>