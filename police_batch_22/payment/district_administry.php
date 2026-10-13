<?php
session_start();
include '../db.php'; 

// ১. সিকিউরিটি গেটওয়ে
$user_id = $_SESSION['user_id'] ?? 0;
$check_user = mysqli_query($conn, "SELECT role, district, full_name FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($check_user);

if (!$user_data || strtolower($user_data['role']) !== 'admin') {
    exit("Access Denied");
}

$admin_district = $user_data['district'];
$show_success = false;
$submitted_amt = "";

// ২. ডাটা প্রসেসিং
if (isset($_POST['submit_to_super'])) {
    $row_id = mysqli_real_escape_string($conn, $_POST['row_id']);
    $trxid = mysqli_real_escape_string($conn, $_POST['trxid']);
    $submitted_amt = $_POST['display_amt'];
    
    $target_dir = "../uploads/proofs/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $file_ext = pathinfo($_FILES["proof_img"]["name"], PATHINFO_EXTENSION);
    $file_name = "TRX_" . time() . "_" . uniqid() . "." . $file_ext;
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["proof_img"]["tmp_name"], $target_file)) {
        $update_sql = "UPDATE district_contributions SET status = 'waiting_super_admin', trxid = '$trxid', proof_img = '$file_name' WHERE id = '$row_id'";
        if(mysqli_query($conn, $update_sql)) {
            // এরর এড়াতে জাভাস্ক্রিপ্ট রিডাইরেক্ট ব্যবহার করা হলো
            echo "<script>window.location.href='?status=success&amt=$submitted_amt';</script>";
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TERMINAL | <?php echo strtoupper($admin_district); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;500;700&family=JetBrains+Mono&family=Outfit:wght@300;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --bg: #030305; --card-bg: #0a0a0f; --accent: #6366f1; --success: #00ffaa; --warning: #ffcc00; --border: rgba(99, 102, 241, 0.15); --text: #94a3b8; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: #fff; font-family: 'Outfit', sans-serif; display: flex; justify-content: center; min-height: 100vh; padding: 50px 20px; overflow-x: hidden; }
        
        body::before { content: ''; position: absolute; top: -10%; left: -10%; width: 40%; height: 40%; background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%); z-index: -1; }

        .terminal-wrapper { max-width: 480px; width: 100%; position: relative; }

        .terminal-header { text-align: center; margin-bottom: 40px; position: relative; }
        .terminal-header span { color: var(--accent); font-family: 'Space Grotesk'; font-size: 10px; font-weight: 700; letter-spacing: 6px; text-transform: uppercase; display: block; margin-bottom: 8px; }
        .terminal-header h1 { font-family: 'Space Grotesk'; font-size: 26px; font-weight: 700; letter-spacing: 1px; color: #fff; text-shadow: 0 0 20px rgba(99, 102, 241, 0.3); }
        .header-bar { width: 60px; height: 2px; background: var(--accent); margin: 15px auto; border-radius: 10px; position: relative; overflow: hidden; }
        .header-bar::after { content: ''; position: absolute; left: -100%; width: 100%; height: 100%; background: #fff; animation: scan 2s infinite; }
        @keyframes scan { 0% { left: -100%; } 100% { left: 100%; } }

        .glass-terminal { 
            background: var(--card-bg); border: 1px solid var(--border); border-radius: 40px; 
            padding: 45px 35px; box-shadow: 0 50px 100px -20px rgba(0,0,0,0.8);
            backdrop-filter: blur(10px); position: relative;
        }

        .process-chain { position: relative; padding-left: 25px; margin-bottom: 30px; }
        .process-chain::before { content: ''; position: absolute; left: 0; top: 10px; height: 85%; width: 1px; border-left: 1px dashed rgba(99, 102, 241, 0.3); }
        
        .chain-item { margin-bottom: 25px; position: relative; }
        .chain-item::before { content: ''; position: absolute; left: -29px; top: 5px; width: 8px; height: 8px; background: var(--bg); border: 2px solid var(--accent); border-radius: 50%; box-shadow: 0 0 10px var(--accent); }
        
        .chain-item label { display: block; font-size: 9px; color: var(--text); font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 6px; }
        .chain-item h3 { font-size: 17px; font-weight: 600; color: #f1f5f9; font-family: 'Outfit'; }
        .chain-item .reason-tag { color: var(--warning); font-size: 13px; font-style: italic; opacity: 0.9; margin-top: 5px; display: block; }

        .amount-core { background: rgba(255, 255, 255, 0.02); border-radius: 25px; padding: 30px; border: 1px solid rgba(255, 255, 255, 0.03); text-align: center; margin-bottom: 35px; }
        .amount-core small { font-size: 9px; color: var(--text); letter-spacing: 4px; display: block; margin-bottom: 12px; }
        .amount-core h2 { font-family: 'Space Grotesk'; font-size: 42px; font-weight: 700; color: var(--success); text-shadow: 0 0 30px rgba(0, 255, 170, 0.2); }

        .input-wrapper { margin-bottom: 25px; }
        .input-wrapper label { display: block; font-size: 10px; color: var(--text); margin-bottom: 12px; letter-spacing: 1.5px; }
        .neo-input { 
            width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--border); 
            padding: 18px 20px; border-radius: 16px; color: #facc15; font-family: 'JetBrains Mono'; 
            font-size: 15px; outline: none; transition: 0.3s;
        }
        .neo-input:focus { border-color: var(--accent); box-shadow: 0 0 15px rgba(99, 102, 241, 0.15); background: rgba(0,0,0,0.5); }

        .smart-upload { 
            position: relative; background: #000; border: 1px solid var(--border); 
            padding: 22px; border-radius: 16px; cursor: pointer; text-align: center; 
            overflow: hidden; transition: 0.3s;
        }
        .smart-upload span { font-size: 13px; color: #555; }
        
        .liquid-line { 
            position: absolute; bottom: 0; left: -100%; width: 100%; height: 2px; 
            background: linear-gradient(90deg, transparent, var(--accent), #fff, var(--accent), transparent);
            animation: flow 3s infinite linear;
        }
        @keyframes flow { 0% { left: -100%; } 100% { left: 100%; } }

        .btn-deploy { 
            width: 100%; padding: 22px; border-radius: 20px; background: var(--accent); 
            border: none; color: #fff; font-family: 'Space Grotesk'; font-weight: 700; 
            font-size: 15px; letter-spacing: 3px; cursor: pointer; transition: 0.4s; margin-top: 15px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }
        .btn-deploy:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(99, 102, 241, 0.5); }

        .success-panel { text-align: center; animation: zoomIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .success-panel i { font-size: 70px; color: var(--success); margin-bottom: 25px; filter: drop-shadow(0 0 20px rgba(0,255,170,0.4)); }
        .status-badge { display: inline-block; padding: 10px 25px; background: rgba(255, 204, 0, 0.05); border: 1px solid rgba(255, 204, 0, 0.2); border-radius: 50px; color: var(--warning); font-size: 11px; font-weight: 700; letter-spacing: 1px; margin-top: 25px; }
        
        @keyframes zoomIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>

<div class="terminal-wrapper">

    <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="glass-terminal success-panel">
            <i class="fa-solid fa-shield-circle-check"></i>
            <h1 style="font-family: 'Space Grotesk'; font-size: 30px; margin-bottom: 15px;">MISSION SENT</h1>
            <p style="color: var(--text); font-size: 15px; line-height: 1.8;">
                মেম্বার কন্ট্রিবিউশন বাবদ <strong style="color: #fff;">৳ <?php echo $_GET['amt']; ?></strong> <br> সফলভাবে সুপার এডমিন বরাবর পাঠানো হয়েছে।
            </p>
            <div class="status-badge">
                <i class="fa-solid fa-circle-notch fa-spin"></i> AWAITING SUPER ADMIN VERIFICATION
            </div>
            <br><br>
            <a href="?" style="color: var(--accent); text-decoration: none; font-size: 11px; font-weight: 700; letter-spacing: 2px;">BACK TO TERMINAL</a>
        </div>

    <?php else: 
        $sql = "SELECT dc.*, fo.op_type, fo.target_batch_id, u.full_name 
                FROM district_contributions dc
                INNER JOIN fund_operations fo ON dc.op_id = fo.id
                LEFT JOIN users u ON fo.target_batch_id = u.batch_id
                WHERE dc.district_name = '$admin_district' AND dc.status = 'pending' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if($row = mysqli_fetch_assoc($res)):
            $formatted_amt = number_format($row['required_amount'], 2);
    ?>

    <div class="terminal-header">
        <span>Sector Admin Access</span>
        <h1><?php echo strtoupper($admin_district); ?> CONTROL UNIT</h1>
        <div class="header-bar"></div>
    </div>

    <div class="glass-terminal">
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="row_id" value="<?php echo $row['id']; ?>">
            <input type="hidden" name="display_amt" value="<?php echo $formatted_amt; ?>">

            <div class="process-chain">
                <div class="chain-item">
                    <label>Target Member</label>
                    <h3><?php echo $row['full_name']; ?></h3>
                </div>
                <div class="chain-item">
                    <label>Operational District</label>
                    <h3><?php echo strtoupper($admin_district); ?></h3>
                </div>
                <div class="chain-item">
                    <label>Assigned Batch</label>
                    <h3>#<?php echo $row['target_batch_id']; ?></h3>
                </div>
                <div class="chain-item" style="margin-bottom: 0;">
                    <label>Reason of Mission</label>
                    <span class="reason-tag"><?php echo $row['op_type']; ?></span>
                </div>
            </div>

            <div class="amount-core">
                <small>TOTAL SETTLEMENT</small>
                <h2>৳ <?php echo $formatted_amt; ?></h2>
            </div>

            <div class="input-wrapper">
                <label>AUTHENTICATION HASH (TRXID)</label>
                <input type="text" name="trxid" class="neo-input" placeholder="Enter Transaction ID" required autocomplete="off">
            </div>

            <div class="input-wrapper">
                <label>MISSION PROOF (SCREENSHOT)</label>
                <div class="smart-upload" onclick="document.getElementById('fileInp').click()">
                    <span id="fileName"><i class="fa-solid fa-fingerprint" style="margin-right: 10px; color: var(--accent);"></i> Authenticate Screenshot</span>
                    <div class="liquid-line"></div>
                    <input type="file" name="proof_img" id="fileInp" accept="image/*" required style="display: none;" onchange="updateName(this)">
                </div>
            </div>

            <button type="submit" name="submit_to_super" class="btn-deploy">DEPLOY TO SUPER ADMIN</button>
        </form>
    </div>

    <?php else: ?>
        <div class="glass-terminal" style="text-align:center; padding: 60px 20px;">
            <i class="fa-solid fa-satellite-dish" style="font-size: 50px; color: rgba(255,255,255,0.03);"></i>
            <p style="margin-top: 25px; color: var(--text); font-size: 13px; letter-spacing: 1px;">Terminal Secured. No pending transmission.</p>
        </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
function updateName(input) {
    if (input.files && input.files[0]) {
        document.getElementById('fileName').innerHTML = "<i class='fa-solid fa-circle-check' style='color:var(--success)'></i> " + input.files[0].name.substring(0,25);
    }
}
</script>

</body>
</html>