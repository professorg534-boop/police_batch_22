<?php
session_start();
include '../db.php'; 

// --- এই অংশটুকু পরিবর্তন করুন ---
$show_success = false;
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $p_id = mysqli_real_escape_string($conn, $_POST['id']);
    $status = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
    
    $update_query = "UPDATE payments SET status = '$status' WHERE id = '$p_id'";
    if (mysqli_query($conn, $update_query)) {
        $show_success = true;
        $msg = ($status === 'approved') ? "Payment Authorized" : "Payment Declined";
    }
}
// --- পরিবর্তন শেষ ---

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
$data = [];
$user_data = [];

if (!empty($id)) {
    $payment_res = mysqli_query($conn, "SELECT * FROM payments WHERE id = '$id'");
    if ($payment_res && mysqli_num_rows($payment_res) > 0) {
        $data = mysqli_fetch_assoc($payment_res);
        $u_id = $data['user_id'] ?? 0;
        $user_res = mysqli_query($conn, "SELECT * FROM users WHERE id = '$u_id'");
        $user_data = mysqli_fetch_assoc($user_res);
    }
}

if (empty($data)) {
    die("<div style='background:#050505; color:#fff; height:100vh; display:flex; align-items:center; justify-content:center; font-family:sans-serif;'><h2>Access Denied: Record Missing</h2></div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Payment Verification | Infinity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');

        :root {
            --accent: #fb8c00;
            --accent-glow: rgba(251, 140, 0, 0.4);
            --bg: #050506;
            --surface: #0f0f12;
            --text-main: #ffffff;
            --text-dim: #84848d;
            --border-color: rgba(255, 255, 255, 0.06);
        }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(251, 140, 0, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(0, 210, 255, 0.03) 0px, transparent 50%);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .premium-container {
            width: 100%;
            max-width: 480px;
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: 32px;
            position: relative;
            padding: 40px;
            box-shadow: 0 40px 100px -20px rgba(0,0,0,0.8);
            overflow: hidden;
        }

        /* Top Accent Bar */
        .premium-container::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
        }

        .verification-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            background: rgba(251, 140, 0, 0.1);
            color: var(--accent);
            border-radius: 100px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 15px;
            border: 1px solid rgba(251, 140, 0, 0.2);
        }

        .header-title {
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .profile-card {
            display: flex;
            align-items: center;
            gap: 18px;
            background: rgba(255, 255, 255, 0.02);
            padding: 20px;
            border-radius: 24px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .profile-card img {
            width: 60px; height: 60px;
            border-radius: 18px;
            object-fit: cover;
            border: 2px solid var(--accent);
            padding: 2px;
            background: var(--bg);
        }

        .user-name { font-weight: 700; font-size: 16px; margin-bottom: 4px; }
        .user-phone { color: var(--text-dim); font-size: 13px; font-weight: 500; }

        .data-list { margin-bottom: 30px; }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .data-label { color: var(--text-dim); font-size: 14px; font-weight: 500; }
        .data-value { font-weight: 700; color: var(--text-main); font-size: 15px; }

        .evidence-section {
            background: #000;
            border-radius: 24px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 35px;
        }

        .evidence-header {
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.03);
            font-size: 11px;
            font-weight: 700;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            justify-content: space-between;
        }

        .evidence-img {
            width: 100%;
            display: block;
            transition: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .evidence-img:hover { transform: scale(1.03); filter: brightness(1.1); }

        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            border: none;
            padding: 18px;
            border-radius: 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-accept {
            background: var(--accent);
            color: #000;
            box-shadow: 0 10px 30px -10px var(--accent-glow);
        }

        .btn-accept:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px -10px var(--accent-glow);
        }

        .btn-reject {
            background: #1a1a1f;
            color: #ff4d4d;
            border: 1px solid rgba(255, 77, 77, 0.1);
        }

        .btn-reject:hover { background: #222228; transform: translateY(-4px); }

        .footer-back {
            text-align: center;
            margin-top: 30px;
        }

        .footer-back a {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.3s;
        }

        .success-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.9); backdrop-filter: blur(8px);
    display: flex; justify-content: center; align-items: center;
    z-index: 10000; animation: fadeIn 0.3s ease;
}
.success-card {
    background: #0f0f12; border: 1px solid var(--accent);
    padding: 50px; border-radius: 40px; text-align: center;
    box-shadow: 0 0 50px var(--accent-glow);
}
.success-card i { font-size: 50px; color: var(--accent); margin-bottom: 20px; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .footer-back a:hover { color: var(--accent); }

        @media (max-width: 480px) {
            .premium-container { padding: 25px; border-radius: 0; height: 100vh; max-width: 100%; }
        }
    </style>
</head>
<body>

<body>
<?php if ($show_success): ?>
<div class="success-overlay">
    <div class="success-card">
        <i class="fa-solid fa-circle-check"></i>
        <h2 style="margin:0;"><?php echo $msg; ?></h2>
        <p style="color:#84848d;">Redirecting to dashboard...</p>
    </div>
</div>
<script>setTimeout(() => { window.location.href='../dashboard.php'; }, 2000);</script>
<?php endif; ?>

<div class="premium-container">
    <div class="verification-header">
        <div class="badge">Awaiting Review</div>
        <h1 class="header-title">Payment Intelligence</h1>
    </div>

    <div class="profile-card">
        <img src="../uploads/profiles/<?php echo $user_data['profile_pic'] ?? 'default.png'; ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=333&color=fff'">
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user_data['full_name'] ?? 'N/A Member'); ?></div>
            <div class="user-phone">ID: #<?php echo $data['user_id']; ?> • <?php echo htmlspecialchars($user_data['phone'] ?? 'Verified'); ?></div>
        </div>
    </div>

    <div class="data-list">
        <div class="data-row">
            <span class="data-label">Amount</span>
            <span class="data-value" style="color: var(--accent); font-size: 18px;">৳<?php echo number_format($data['amount'] ?? 0); ?></span>
        </div>
        <div class="data-row">
            <span class="data-label">Transaction Hash</span>
            <span class="data-value" style="font-family: monospace; letter-spacing: 1px;"><?php echo htmlspecialchars($data['trx_id'] ?? 'NULL'); ?></span>
        </div>
        <div class="data-row" style="border: none;">
            <span class="data-label">Network</span>
            <span class="data-value">Mobile Banking</span>
        </div>
    </div>

    <div class="evidence-section">
        <div class="evidence-header">
            <span>Attachment Analysis</span>
            <i class="fa-solid fa-expand"></i>
        </div>
        <?php 
        $img = $data['payment_screenshot'] ?? ''; 
        if($img): ?>
            <img src="../uploads/payments/<?php echo $img; ?>" class="evidence-img" alt="Proof">
        <?php else: ?>
            <div style="padding: 60px 0; text-align: center; color: var(--text-dim);">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 30px; margin-bottom: 10px;"></i>
                <div style="font-size: 12px;">No Evidence Found</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="action-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
    <form action="" method="POST"> <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
        <button type="submit" name="action" value="approve" class="btn btn-accept" style="width: 100%; cursor: pointer;">
            <i class="fa-solid fa-check-circle"></i> Approve
        </button>
    </form>
    
    <form action="" method="POST"> <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
        <button type="submit" name="action" value="reject" class="btn btn-reject" style="width: 100%; cursor: pointer;">
            <i class="fa-solid fa-circle-xmark"></i> Decline
        </button>
    </form>
    </div>

    <div class="footer-back">
        <a href="../dashboard.php">
            <i class="fa-solid fa-arrow-left" style="font-size: 10px; margin-right: 5px;"></i> 
            Exit Security Review
        </a>
    </div>
</div>

</body>
</html>