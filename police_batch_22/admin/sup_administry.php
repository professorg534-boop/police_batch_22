<?php
// ১. সেশন এবং এরর রিপোর্টিং অন
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// ২. ডাটাবেস কানেকশন
include '../db.php'; 

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

/**
 * ৩. সিকিউরিটি চেক
 * আপনার ডাটাবেস স্ক্রিনশট অনুযায়ী রোল 'superadmin' ব্যবহার করা হয়েছে।
 */
$current_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!isset($_SESSION['user_id']) || $current_role !== 'superadmin') {
    echo "<div style='background:#111; color:#eee; padding:50px; font-family:sans-serif; text-align:center;'>";
    echo "<h1 style='color:#ff4444;'>Access Denied</h1>";
    echo "<p>Your current session role is: <b>" . ($_SESSION['role'] ?? 'None') . "</b></p>";
    echo "<p>But this page requires: <b>superadmin</b></p>";
    echo "<a href='../login.php' style='color:#6366f1;'>Please Login Again</a>";
    echo "</div>";
    exit();
}

// ৪. একশন লজিক (Approve / Reject)
if (isset($_POST['action'])) {
    $c_id = mysqli_real_escape_string($conn, $_POST['contribution_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $update_sql = "UPDATE district_contributions SET status = 'confirmed' WHERE id = '$c_id'";
        $msg_text = "MISSION APPROVED SUCCESSFULLY";
    } else {
        // রিজেক্ট করলে ডাটা রিসেট হবে যেন জেলা এডমিন আবার পাঠাতে পারে
        $update_sql = "UPDATE district_contributions SET status = 'pending', trxid = NULL, proof_img = NULL WHERE id = '$c_id'";
        $msg_text = "MISSION REJECTED & RESET";
    }

    if (mysqli_query($conn, $update_sql)) {
        $msg = $msg_text;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUPER ADMIN | COMMAND CENTER</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Inter:wght@400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --bg: #030305; --card: #0a0a0f; --accent: #6366f1; --border: rgba(255,255,255,0.05); --success: #00ffaa; --error: #ff4444; }
        * { margin:0; padding:0; box-sizing: border-box; }
        body { background: var(--bg); color: #fff; font-family: 'Inter', sans-serif; padding: 30px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .header h1 { font-family: 'Space Grotesk'; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; }
        .status-tag { font-size: 10px; color: var(--accent); font-weight: 800; letter-spacing: 3px; }
        .req-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .req-table th { padding: 15px 20px; text-align: left; font-size: 11px; text-transform: uppercase; color: #444; letter-spacing: 2px; }
        .req-table td { background: var(--card); padding: 20px; font-size: 14px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); vertical-align: middle; }
        .req-table td:first-child { border-left: 1px solid var(--border); border-radius: 15px 0 0 15px; }
        .req-table td:last-child { border-right: 1px solid var(--border); border-radius: 0 15px 15px 0; }
        .req-table tr:hover td { border-color: var(--accent); }
        .trx-id { font-family: 'JetBrains Mono'; color: #facc15; font-size: 12px; background: rgba(0,0,0,0.4); padding: 5px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.03); }
        .btn { padding: 10px 18px; border-radius: 10px; border: none; font-weight: 800; cursor: pointer; transition: 0.3s; font-size: 11px; }
        .btn-ok { background: var(--success); color: #000; }
        .btn-no { background: rgba(255, 68, 68, 0.1); color: var(--error); border: 1px solid rgba(255, 68, 68, 0.2); margin-left: 5px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.4); }
        .proof-btn { color: var(--accent); text-decoration: none; font-weight: 700; font-size: 11px; border: 1px solid var(--border); padding: 8px 12px; border-radius: 8px; }
        .proof-btn:hover { background: var(--accent); color: #fff; }
        .alert { background: rgba(0, 255, 170, 0.1); color: var(--success); padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--success); text-align: center; font-weight: 700; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <span class="status-tag">SYSTEM HQ</span>
            <h1>SUPER ADMIN TERMINAL</h1>
        </div>
        <div style="text-align: right; color: #333; font-size: 10px; font-weight: 800;">
            SECURED ENCRYPTED CONNECTION
        </div>
    </div>

    <?php if(isset($msg)): ?>
        <div class="alert"><i class="fa-solid fa-circle-check"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <table class="req-table">
        <thead>
            <tr>
                <th>Operational Sector</th>
                <th>Amount</th>
                <th>Transaction ID</th>
                <th>Proof Artifact</th>
                <th>Target Recipient</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            /**
             * আপনার ডিস্ট্রিক্ট এডমিন প্যানেল 'waiting_super_admin' নামে ডাটা পাঠাচ্ছে
             * তাই এখানে WHERE dc.status = 'waiting_super_admin' ব্যবহার করা হয়েছে।
             */
            $query = "SELECT dc.*, u.full_name, fo.target_batch_id 
                      FROM district_contributions dc
                      JOIN fund_operations fo ON dc.op_id = fo.id
                      LEFT JOIN users u ON fo.target_batch_id = u.batch_id
                      WHERE dc.status = 'waiting_super_admin'
                      ORDER BY dc.id ASC";
            $result = mysqli_query($conn, $query);

            if(mysqli_num_rows($result) > 0):
                while($row = mysqli_fetch_assoc($result)):
            ?>
            <tr>
                <td style="font-weight: 800; color: #fff;"><?php echo strtoupper($row['district_name']); ?></td>
                <td style="font-weight: 800; color: var(--success); font-size: 16px;">
                    ৳ <?php echo number_format($row['required_amount'], 2); ?>
                </td>
                <td><span class="trx-id"><?php echo $row['trxid']; ?></span></td>
                <td>
                    <a href="../uploads/proofs/<?php echo $row['proof_img']; ?>" target="_blank" class="proof-btn">
                        <i class="fa-solid fa-image"></i> VIEW PROOF
                    </a>
                </td>
                <td>
                    <div style="font-weight: 600; color: #eee;"><?php echo $row['full_name'] ?? 'General Unit'; ?></div>
                    <small style="color: #444; font-weight: 700;">#BATCH_<?php echo $row['target_batch_id']; ?></small>
                </td>
                <td>
                    <form method="POST" style="display: flex;">
                        <input type="hidden" name="contribution_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="action" value="approve" class="btn btn-ok" onclick="return confirm('Approve this?')">APPROVE</button>
                        <button type="submit" name="action" value="reject" class="btn btn-no" onclick="return confirm('Reject this?')"><i class="fa-solid fa-xmark"></i></button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="6" style="text-align:center; padding: 100px; color: #222;">
                    <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 30px; margin-bottom: 10px;"></i>
                    <p style="letter-spacing: 2px; font-weight: 700;">NO PENDING MISSION REQUESTS FOUND</p>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>