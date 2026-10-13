<?php
session_start();
include '../db.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$user_res = mysqli_query($conn, "SELECT district, full_name FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_res);
$my_district = $user_data['district'];

$admin_query = "SELECT id, full_name, role FROM users 
                WHERE district = '$my_district' 
                AND (LOWER(role) LIKE '%admin%') 
                AND id != '$user_id'";
$admin_list = mysqli_query($conn, $admin_query);

$msg = "";

if(isset($_POST['send_request'])) {
    $pay_year = mysqli_real_escape_string($conn, $_POST['pay_year']);
    $pay_months_array = isset($_POST['pay_months']) ? $_POST['pay_months'] : [];
    
    if(empty($pay_months_array)) {
        $msg = "<div class='alert error'>Access Denied: Please select months!</div>";
    } else {
        $has_duplicate = false;
        foreach($pay_months_array as $m) {
            $check = mysqli_query($conn, "SELECT id FROM payment_requests WHERE user_id = '$user_id' AND pay_year = '$pay_year' AND pay_month LIKE '%$m%' AND status != 'rejected'");
            if(mysqli_num_rows($check) > 0) { $has_duplicate = true; $msg = "<div class='alert error'>Conflict: $m ($pay_year) already submitted!</div>"; break; }
        }

        if(!$has_duplicate) {
            $pay_month = implode(', ', $pay_months_array);
            $admin_id = mysqli_real_escape_string($conn, $_POST['admin_id']);
            $method = mysqli_real_escape_string($conn, $_POST['method']);
            $amount = mysqli_real_escape_string($conn, $_POST['amount']);
            $trxid = mysqli_real_escape_string($conn, $_POST['trxid']);

            if(isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] == 0) {
                $file_ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
                $file_name = "PAY_" . time() . "_" . $user_id . "." . $file_ext;
                $target_directory = "../uploads/payments/" . $file_name;
                if (!is_dir('../uploads/payments/')) { mkdir('../uploads/payments/', 0777, true); }
                if(move_uploaded_file($_FILES['screenshot']['tmp_name'], $target_directory)) {
                    $insert = "INSERT INTO payment_requests (user_id, district, admin_id, pay_month, pay_year, method, amount, trxid, screenshot, status, created_at) VALUES ('$user_id', '$my_district', '$admin_id', '$pay_month', '$pay_year', '$method', '$amount', '$trxid', '$file_name', 'pending', NOW())";
                    if(mysqli_query($conn, $insert)) { $msg = "<div class='alert success'>Submission Successful!</div>"; }
                }
            }
        }
    }
}

$pending_list = mysqli_query($conn, "SELECT p.*, a.full_name as admin_name FROM payment_requests p LEFT JOIN users a ON p.admin_id = a.id WHERE p.user_id = '$user_id' AND p.status = 'pending' ORDER BY p.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Terminal | Alpha</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&family=Space+Grotesk:wght@700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --bg: #03040a; --surface: #0a0b14; --accent: #6366f1; --success: #00ffa3; --error: #ff3366; --border: rgba(255,255,255,0.06); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; padding: 20px; }
        .container { max-width: 850px; margin: auto; }
        
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 30px; padding: 40px; box-shadow: 0 40px 80px rgba(0,0,0,0.6); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }

        label { font-size: 10px; font-weight: 800; color: var(--accent); letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 10px; display: block; }
        select, input { width: 100%; background: #000; border: 1px solid var(--border); padding: 16px; border-radius: 12px; color: #fff; outline: none; transition: 0.3s; }
        select:focus, input:focus { border-color: var(--accent); }

        /* Month Selection */
        .month-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-top: 10px; }
        .month-item input { display: none; }
        .month-item label { display: block; padding: 12px 5px; background: #000; border: 1px solid var(--border); border-radius: 10px; text-align: center; font-size: 11px; cursor: pointer; color: #444; font-weight: 700; transition: 0.2s; }
        .month-item input:checked + label { background: var(--accent); color: #fff; border-color: var(--accent); box-shadow: 0 0 20px rgba(99,102,241,0.25); }

        /* Method Selection */
        .method-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .method-item input { display: none; }
        .method-item label { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 15px; background: #000; border: 1px solid var(--border); border-radius: 15px; cursor: pointer; transition: 0.3s; }
        .method-item input:checked + label { border-color: var(--accent); background: rgba(99,102,241,0.08); }
        .method-item label i { font-size: 18px; color: #222; transition: 0.3s; }
        .method-item input:checked + label i { color: var(--accent); }
        .method-item label span { font-size: 9px; font-weight: 800; color: #333; }
        .method-item input:checked + label span { color: #fff; }

        /* --- NEW SLIM UPLOAD LINE --- */
        .upload-line-container { grid-column: span 2; margin-top: 10px; position: relative; }
        .upload-line {
            display: flex; align-items: center; justify-content: space-between;
            background: #000; border: 1px solid var(--border); padding: 12px 20px;
            border-radius: 12px; cursor: pointer; transition: 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
        }
        .upload-line:hover { background: #050505; }
        
        .upload-line i { color: var(--accent); font-size: 16px; }
        .upload-line span { font-size: 13px; font-weight: 600; color: #555; }
        
        /* The Blue Glow Line */
        .glow-border {
            position: absolute; bottom: 0; left: 0; height: 2px; width: 0%;
            background: linear-gradient(90deg, transparent, var(--accent), var(--success));
            transition: 0.8s ease;
        }
        .upload-line.active { border-color: rgba(99, 102, 241, 0.4); }
        .upload-line.active .glow-border { width: 100%; box-shadow: 0 0 15px var(--accent); }
        .upload-line.active span { color: var(--success); }

        .btn-submit { grid-column: span 2; background: var(--accent); color: #fff; padding: 20px; border: none; border-radius: 15px; font-weight: 800; cursor: pointer; font-size: 16px; margin-top: 20px; letter-spacing: 1px; transition: 0.3s; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3); }

        .pending-item { background: rgba(255,180,0,0.02); border: 1px solid rgba(255,180,0,0.1); border-radius: 15px; padding: 15px; margin-top: 12px; display: flex; justify-content: space-between; align-items: center; }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; text-align: center; font-weight: 700; }
        .success { background: rgba(0,255,163,0.1); color: var(--success); }
        .error { background: rgba(255,51,102,0.1); color: var(--error); }

        @media (max-width: 600px) { .grid, .method-grid, .month-grid { grid-template-columns: repeat(3, 1fr); } .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <?php echo $msg; ?>

    <div class="card">
        <h2 style="font-family: 'Space Grotesk'; margin-bottom: 35px; letter-spacing: -1px;"><i class="fas fa-satellite-dish" style="color:var(--accent); font-size: 20px;"></i> ALPHA <span style="color:var(--accent);">TERMINAL</span></h2>
        
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="grid">
                <div>
                    <label>Fiscal Year</label>
                    <select name="pay_year">
                        <option value="2025">2025</option>
                        <option value="2026" selected>2026</option>
                    </select>
                </div>
                <div>
                    <label>Admin Protocol</label>
                    <select name="admin_id" required>
                        <option value="">Select Auditor</option>
                        <?php mysqli_data_seek($admin_list, 0); while($adm = mysqli_fetch_assoc($admin_list)): ?>
                            <option value="<?php echo $adm['id']; ?>"><?php echo $adm['full_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div style="grid-column: span 2;">
                    <label>Operational Months (৳50/Month)</label>
                    <div class="month-grid">
                        <?php
                        $months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                        foreach($months as $m) {
                            echo "<div class='month-item'><input type='checkbox' name='pay_months[]' value='$m' id='m_$m' class='month-check'><label for='m_$m'>$m</label></div>";
                        }
                        ?>
                    </div>
                </div>

                <div style="grid-column: span 2;">
                    <label>Payment Channel</label>
                    <div class="method-grid">
                        <div class="method-item"><input type="radio" name="method" value="bKash" id="bk" checked><label for="bk"><i class="fas fa-mobile-alt"></i><span>BKASH</span></label></div>
                        <div class="method-item"><input type="radio" name="method" value="Nagad" id="ng"><label for="ng"><i class="fas fa-wallet"></i><span>NAGAD</span></label></div>
                        <div class="method-item"><input type="radio" name="method" value="Rocket" id="rk"><label for="rk"><i class="fas fa-rocket"></i><span>ROCKET</span></label></div>
                        <div class="method-item"><input type="radio" name="method" value="Bank" id="bn"><label for="bn"><i class="fas fa-university"></i><span>BANK</span></label></div>
                    </div>
                </div>

                <div>
                    <label>Calculated Amount</label>
                    <input type="number" name="amount" id="amount_field" value="0" readonly style="color: var(--success); font-weight: 800; font-family: 'JetBrains Mono';">
                </div>
                <div>
                    <label>Digital Transaction ID</label>
                    <input type="text" name="trxid" placeholder="Ex: 8XJ9K2LM..." required style="font-family: 'JetBrains Mono';">
                </div>

                <div class="upload-line-container">
                    <label>Verification Screenshot</label>
                    <div class="upload-line" id="upload-bar" onclick="document.getElementById('scr').click()">
                        <div style="display:flex; align-items:center; gap:15px;">
                            <i class="fas fa-link"></i>
                            <span id="file-status">Attach receipt screenshot...</span>
                        </div>
                        <i class="fas fa-chevron-right" style="font-size: 10px; opacity: 0.3;"></i>
                        <div class="glow-border"></div>
                    </div>
                    <input type="file" name="screenshot" id="scr" hidden required onchange="handleFile(this)">
                </div>

                <button type="submit" name="send_request" class="btn-submit">INITIALIZE SUBMISSION</button>
            </div>
        </form>
    </div>

    <div style="margin-top: 40px;">
        <h3 style="font-size: 14px; margin-bottom: 15px; color: #333; letter-spacing: 1px;"><i class="fas fa-clock"></i> PENDING VERIFICATION</h3>
        <?php if(mysqli_num_rows($pending_list) > 0): ?>
            <?php while($p = mysqli_fetch_assoc($pending_list)): ?>
                <div class="pending-item">
                    <div>
                        <p style="font-weight: 800; font-size: 14px; color: var(--success);">৳<?php echo number_format($p['amount']); ?> <small style="color:#555; font-weight:400;">/ <?php echo $p['pay_month']; ?></small></p>
                        <p style="font-size: 10px; color: #444; font-family: 'JetBrains Mono';"><?php echo $p['trxid']; ?> (<?php echo $p['method']; ?>)</p>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 9px; font-weight: 900; color: #ffb400; text-transform: uppercase; background: rgba(255,180,0,0.1); padding: 4px 8px; border-radius: 4px;">Awaiting Review</span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// টাকা ক্যালকুলেশন
document.querySelectorAll('.month-check').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkedCount = document.querySelectorAll('.month-check:checked').length;
        document.getElementById('amount_field').value = checkedCount * 50;
    });
});

// স্মার্ট আপলোড বার লজিক
function handleFile(input) {
    const bar = document.getElementById('upload-bar');
    const statusText = document.getElementById('file-status');
    
    if (input.files && input.files[0]) {
        statusText.innerText = "Screenshot Loaded: " + input.files[0].name.substring(0, 20) + "...";
        bar.classList.add('active'); // ব্লু লাইন একটিভ করবে
    } else {
        statusText.innerText = "Attach receipt screenshot...";
        bar.classList.remove('active');
    }
}
</script>

</body>
</html>