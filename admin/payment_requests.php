<?php
session_start();

// ১. ডাটাবেস কানেকশন চেক
if (file_exists('db.php')) { 
    include 'db.php'; 
} elseif (file_exists('../db.php')) { 
    include '../db.php'; 
} else { 
    die("Database connection file missing."); 
}

// ২. সেশন সিকিউরিটি চেক
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
$user_id = (int) $_SESSION['user_id'];

// ৩. ইউজারের তথ্য আহরণ
$me_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($me_stmt, "i", $user_id);
mysqli_stmt_execute($me_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($me_stmt));
mysqli_stmt_close($me_stmt);

if (!$user_data) { 
    session_destroy(); 
    header("Location: login.php"); 
    exit(); 
}

$my_district = $user_data['district'] ?? 'N/A';
$is_admin = in_array(strtolower($user_data['role'] ?? ''), ['admin', 'super admin', 'super_admin', 'superadmin']);

$msg = "";
$msg_type = "";

// ৪. পেমেন্ট রিকোয়েস্ট জমা নেওয়ার প্রসেসিং (Form Handler)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $req_type = trim($_POST['request_type'] ?? 'monthly');
    $amount = floatval($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? '');
    $trxid = trim($_POST['trxid'] ?? '');
    $pay_year = trim($_POST['pay_year'] ?? date('Y'));
    
    // সিলেক্ট করা মাসসমূহ
    $raw_months = isset($_POST['pay_month']) ? (array) $_POST['pay_month'] : [];
    $pay_months = implode(', ', array_map('trim', $raw_months));

    // ভ্যালিডেশন
    if ($amount <= 0) {
        $msg = "Please enter a valid amount greater than 0.";
        $msg_type = "error";
    } elseif (empty($method)) {
        $msg = "Please select a payment method.";
        $msg_type = "error";
    } elseif (empty($trxid)) {
        $msg = "Transaction ID (TrxID) is required.";
        $msg_type = "error";
    } else {
        // স্ক্রিনশট আপলোড প্রসেসিং
        $screenshot_filename = "";
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['screenshot']['tmp_name'];
            $file_name = $_FILES['screenshot']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed_exts)) {
                $new_filename = 'pay_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_dir = file_exists('uploads') ? 'uploads/' : '../uploads/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                    $screenshot_filename = $new_filename;
                }
            }
        }

        // ডাটাবেসে রিকোয়েস্ট সেভ করা
        $stmt = mysqli_prepare($conn, "
            INSERT INTO payment_requests 
            (user_id, district, amount, status, request_type, method, trxid, pay_month, pay_year, screenshot, created_at) 
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())
        ");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isdssssss", $user_id, $my_district, $amount, $req_type, $method, $trxid, $pay_months, $pay_year, $screenshot_filename);
            
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Payment request submitted successfully! Pending admin approval.";
                $msg_type = "success";
            } else {
                $msg = "Failed to submit payment request. Please try again.";
                $msg_type = "error";
            }
            mysqli_stmt_close($stmt);
        } else {
            $msg = "Database query error: " . mysqli_error($conn);
            $msg_type = "error";
        }
    }
}

// প্রোফাইল ছবির পাথ ডাইনামিক ফিল্টার
$user_photo_path = 'https://cdn-icons-png.flaticon.com/512/6522/6522516.png';
if (!empty($user_data['photo'])) {
    if (file_exists('uploads/' . $user_data['photo'])) {
        $user_photo_path = 'uploads/' . htmlspecialchars($user_data['photo']);
    } elseif (file_exists('../uploads/' . $user_data['photo'])) {
        $user_photo_path = '../uploads/' . htmlspecialchars($user_data['photo']);
    }
}

$months_list = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPHA HQ | Payment Deposit</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --ink: #eaf1ff; --muted: #8ea3cc; --accent: #2f6bff; --accent-2: #38bdf8; --accent-soft: rgba(47,107,255,.18);
            --card-bg: rgba(12, 22, 52, 0.65); --line: rgba(130,170,255,.16);
            --ok: #34d399; --ok-bg: rgba(52,211,153,.14); --wait: #fbbf24; --wait-bg: rgba(251,191,36,.14);
            --danger: #f87171; --danger-bg: rgba(248,113,113,.14);
            --display: 'Sora', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; color: var(--ink); font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(900px 520px at 90% -8%, rgba(47,107,255,.42), transparent 60%),
                radial-gradient(700px 520px at -12% 32%, rgba(56,189,248,.16), transparent 60%),
                linear-gradient(180deg, #061030 0%, #030817 100%) fixed; }

        .wrap { max-width: 680px; margin: 0 auto; padding: 22px 12px 120px; }

        /* Header / Hero */
        .hero { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 22px; padding: 0 4px; }
        .brand { display: flex; align-items: center; gap: 8px; font: 700 13px var(--display); color: var(--accent-2); margin-bottom: 8px; }
        .mark { width: 26px; height: 26px; border-radius: 9px; display: grid; place-items: center; color: #fff; font: 700 15px var(--display);
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 6px 18px rgba(47,107,255,.55); }
        .hero h1 { font: 700 24px/1.2 var(--display); letter-spacing: -.5px; }
        .avatar { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid rgba(96,165,250,.7); box-shadow: 0 0 0 4px rgba(47,107,255,.18); }

        .banner { padding: 12px 16px; border-radius: 16px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border: 1px solid var(--line); }
        .banner.success { background: var(--ok-bg); color: var(--ok); border-color: rgba(52,211,153,.3); }
        .banner.error { background: var(--danger-bg); color: var(--danger); border-color: rgba(248,113,113,.3); }

        .card { background: linear-gradient(180deg, rgba(255,255,255,.075), rgba(255,255,255,.03)); border: 1px solid var(--line);
            backdrop-filter: blur(16px); border-radius: 24px; padding: 22px 18px; margin-bottom: 20px;
            box-shadow: 0 20px 45px rgba(0,0,0,.35); }
        .card h3 { font: 700 15px var(--display); margin-bottom: 18px; display: flex; align-items: center; gap: 9px; color: #fff; }

        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        
        input[type="text"], input[type="number"], select {
            width: 100%; background: rgba(0,0,0,.35); border: 1px solid var(--line); border-radius: 14px;
            padding: 12px 14px; color: #fff; font: 600 14px 'Manrope', sans-serif; outline: none; transition: 0.3s;
        }
        input:focus, select:focus { border-color: var(--accent-2); box-shadow: 0 0 15px rgba(56,189,248,.2); }

        /* Request Type Switcher */
        .type-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px; }
        .type-btn {
            background: rgba(0,0,0,.3); border: 1px solid var(--line); border-radius: 14px; padding: 12px;
            text-align: center; cursor: pointer; transition: 0.3s; color: var(--muted); font: 700 13px 'Manrope', sans-serif;
        }
        .type-btn.active { background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; border-color: var(--accent-2); box-shadow: 0 6px 18px rgba(47,107,255,.4); }

        /* Month Selector Checkboxes */
        .months-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .month-item { position: relative; }
        .month-item input { display: none; }
        .month-item label {
            display: block; background: rgba(0,0,0,.25); border: 1px solid var(--line); border-radius: 10px;
            padding: 9px 4px; text-align: center; font-size: 11px; font-weight: 700; color: var(--muted); cursor: pointer; transition: 0.2s; margin: 0; text-transform: none;
        }
        .month-item input:checked + label { background: var(--accent-soft); border-color: var(--accent-2); color: #fff; }

        /* File Upload Box */
        .file-box {
            border: 1.5px dashed var(--line); border-radius: 16px; padding: 20px; text-align: center;
            background: rgba(0,0,0,.2); cursor: pointer; transition: 0.3s; position: relative;
        }
        .file-box:hover { border-color: var(--accent-2); background: rgba(47,107,255,.05); }
        .file-box i { font-size: 24px; color: var(--accent-2); margin-bottom: 8px; }
        .file-box p { font-size: 12px; color: var(--muted); font-weight: 600; }
        .file-box input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }

        .btn-submit {
            width: 100%; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff;
            border: none; padding: 14px; border-radius: 16px; font: 700 14px var(--display);
            letter-spacing: .5px; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 25px rgba(47,107,255,.4);
        }
        .btn-submit:hover { transform: translateY(-2px); filter: brightness(1.1); }

        .history-link { display: flex; align-items: center; justify-content: space-between; text-decoration: none; color: var(--accent-2); font-weight: 700; font-size: 13px; }

        /* Bottom Fixed Navigation Bar */
        .bnav { position: fixed; left: 50%; transform: translateX(-50%); bottom: 12px; width: calc(100% - 24px); max-width: 520px; z-index: 50;
            display: flex; justify-content: space-around; gap: 2px; background: rgba(8,18,52,.72); backdrop-filter: blur(22px); border: 1px solid var(--line);
            border-radius: 26px; padding: 7px; box-shadow: 0 18px 50px rgba(0,0,0,.55); }
        .bnav a { flex: 1; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 8px 4px; border-radius: 19px; color: var(--muted); font-size: 11px; font-weight: 700; transition: 0.3s; }
        .bnav a i { font-size: 17px; }
        .bnav a:hover { background: var(--accent-soft); color: #fff; }
        .bnav a.active { color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2)); box-shadow: 0 8px 22px rgba(47,107,255,.6); }

        @media (max-width: 480px) {
            .months-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<main class="wrap">

    <header class="hero">
        <div>
            <div class="brand"><span class="mark">α</span>Alpha HQ</div>
            <h1>Deposit Funds</h1>
        </div>
        <img class="avatar" src="<?= $user_photo_path ?>" alt="Avatar">
    </header>

    <?php if (!empty($msg)): ?>
        <div class="banner <?= $msg_type ?>">
            <i class="fas fa-<?= $msg_type === 'success' ? 'circle-check' : 'triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- History Link Card -->
    <a href="member_payments.php" class="card history-link">
        <span><i class="fas fa-receipt" style="margin-right: 8px;"></i> View Transaction History</span>
        <i class="fas fa-chevron-right"></i>
    </a>

    <form method="POST" enctype="multipart/form-data">
        
        <div class="card">
            <h3><i class="fas fa-wallet" style="color:var(--accent-2)"></i> Payment Details</h3>

            <!-- Deposit Type -->
            <label>Request Type</label>
            <div class="type-grid">
                <div class="type-btn active" id="btnMonthly" onclick="setType('monthly')">Monthly Deposit</div>
                <div class="type-btn" id="btnDonation" onclick="setType('donation')">Special Donation</div>
            </div>
            <input type="hidden" name="request_type" id="requestType" value="monthly">

            <!-- Month & Year Selection (For Monthly Deposit) -->
            <div id="monthSec">
                <div class="form-group">
                    <label>Select Billing Year</label>
                    <select name="pay_year">
                        <option value="2026" selected>2026</option>
                        <option value="2025">2025</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Month(s)</label>
                    <div class="months-grid">
                        <?php foreach ($months_list as $m): ?>
                            <div class="month-item">
                                <input type="checkbox" name="pay_month[]" value="<?= $m ?>" id="m_<?= $m ?>">
                                <label for="m_<?= $m ?>"><?= $m ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Amount -->
            <div class="form-group">
                <label>Deposit Amount (৳)</label>
                <input type="number" name="amount" placeholder="e.g. 500" step="any" min="1" required>
            </div>

            <!-- Payment Method -->
            <div class="form-group">
                <label>Payment Method</label>
                <select name="method" required>
                    <option value="" disabled selected>Choose Method...</option>
                    <option value="bKash">bKash</option>
                    <option value="Nagad">Nagad</option>
                    <option value="Rocket">Rocket</option>
                    <option value="Bank">Bank Transfer</option>
                </select>
            </div>

            <!-- Transaction ID -->
            <div class="form-group">
                <label>Transaction ID (TrxID)</label>
                <input type="text" name="trxid" placeholder="e.g. BX9823KJ10" required>
            </div>

            <!-- Screenshot Upload -->
            <div class="form-group">
                <label>Payment Proof (Screenshot)</label>
                <div class="file-box">
                    <i class="fas fa-cloud-arrow-up"></i>
                    <p id="fileLabel">Click to upload image receipt (Optional)</p>
                    <input type="file" name="screenshot" accept="image/*" onchange="showFileName(this)">
                </div>
            </div>

            <button type="submit" name="submit_payment" class="btn-submit">
                Submit Payment Request <i class="fas fa-paper-plane" style="margin-left: 6px;"></i>
            </button>

        </div>

    </form>

</main>

<!-- Bottom Fixed Navigation Bar -->
<nav class="bnav" aria-label="Main Navigation">
    <a href="../dashboard.php"><i class="fa-solid fa-house"></i>Home</a>
    <a href="district_monitor.php"><i class="fa-solid fa-compass"></i>District</a>
    <a href="payment_requests.php" class="active" aria-current="page"><i class="fa-solid fa-wallet"></i>Deposits</a>
    <?php if ($is_admin): ?>
        <a href="district_administry.php"><i class="fa-solid fa-shield-halved"></i>Administry</a>
    <?php endif; ?>
</nav>

<script>
function setType(type) {
    document.getElementById('requestType').value = type;
    const btnM = document.getElementById('btnMonthly');
    const btnD = document.getElementById('btnDonation');
    const monthSec = document.getElementById('monthSec');

    if (type === 'monthly') {
        btnM.classList.add('active');
        btnD.classList.remove('active');
        monthSec.style.display = 'block';
    } else {
        btnD.classList.add('active');
        btnM.classList.remove('active');
        monthSec.style.display = 'none';
    }
}

function showFileName(input) {
    const label = document.getElementById('fileLabel');
    if (input.files && input.files[0]) {
        label.innerText = 'Selected: ' + input.files[0].name;
        label.style.color = '#34d399';
    } else {
        label.innerText = 'Click to upload image receipt (Optional)';
        label.style.color = '#8ea3cc';
    }
}
</script>

</body>
</html>