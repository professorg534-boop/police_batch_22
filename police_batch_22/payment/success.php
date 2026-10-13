<?php
session_start();
error_reporting(0); 
include '../db.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$u_res = mysqli_query($conn, "SELECT full_name, batch_id FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($u_res);

$current_month = date('F'); 
$current_year  = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Ledger | Premium Alpha</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg: #050505;
            --surface: #0d0d0d;
            --primary: #ffffff;
            --accent: #6366f1;
            --border: rgba(255, 255, 255, 0.08);
            --text-dim: #888888;
        }

        * { margin:0; padding:0; box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        
        body { 
            background: var(--bg);
            font-family: 'Inter', sans-serif;
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: radial-gradient(circle at 50% -10%, #1a1a1a 0%, transparent 60%);
        }

        /* Top Navigation */
        nav {
            padding: 25px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(20px);
            position: sticky;
            top: 0; z-index: 100;
        }

        .logo { font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700; letter-spacing: -1px; }
        .logo span { color: var(--accent); }

        .user-badge {
            display: flex; align-items: center; gap: 12px;
            background: var(--surface); padding: 8px 16px;
            border-radius: 100px; border: 1px solid var(--border);
        }

        /* Main Content */
        .wrapper { max-width: 1200px; margin: 60px auto; padding: 0 40px; }

        .hero { margin-bottom: 50px; }
        .hero h1 { font-family: 'Space Grotesk', sans-serif; font-size: 48px; font-weight: 700; letter-spacing: -2px; line-height: 1; }
        .hero p { color: var(--text-dim); margin-top: 15px; font-size: 16px; }

        /* Payment Card */
        .glass-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
        }

        .form-layout { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }

        .input-box { display: flex; flex-direction: column; gap: 10px; }
        .input-box label { font-size: 11px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; }

        input, select {
            background: #000;
            border: 1px solid var(--border);
            padding: 16px 20px;
            border-radius: 14px;
            color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        input:focus, select:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }

        /* Premium Upload Area */
        .upload-area {
            grid-column: span 1;
            border: 1px dashed var(--border);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            background: rgba(255,255,255,0.02);
        }
        .upload-area:hover { border-color: #fff; background: rgba(255,255,255,0.05); }

        .btn-confirm {
            grid-column: span 4;
            background: #fff;
            color: #000;
            border: none;
            padding: 20px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-confirm:hover { transform: scale(0.99); background: #f0f0f0; }

        /* History Table */
        .history { margin-top: 80px; }
        .history h3 { font-family: 'Space Grotesk'; font-size: 24px; margin-bottom: 30px; }

        .premium-table { width: 100%; border-collapse: collapse; }
        .premium-table th { text-align: left; padding: 20px; color: var(--text-dim); font-size: 12px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        .premium-table td { padding: 25px 20px; border-bottom: 1px solid var(--border); transition: 0.2s; }
        .premium-table tr:hover td { background: rgba(255,255,255,0.02); }

        .status {
            padding: 6px 14px; border-radius: 100px; font-size: 10px; font-weight: 700;
            letter-spacing: 0.5px; display: inline-block;
        }
        .pending { background: rgba(255, 171, 0, 0.1); color: #ffab00; border: 1px solid rgba(255, 171, 0, 0.2); }
        .success { background: rgba(0, 255, 127, 0.1); color: #00ff7f; border: 1px solid rgba(0, 255, 127, 0.2); }

        .amt { font-family: 'Space Grotesk'; font-weight: 700; font-size: 18px; }
    </style>
</head>
<body>

<nav>
    <div class="logo">ALPHA<span>.SYSTEMS</span></div>
    <div class="user-badge">
        <div style="width: 8px; height: 8px; background: #00ff7f; border-radius: 50%; box-shadow: 0 0 10px #00ff7f;"></div>
        <span style="font-size: 13px; font-weight: 600;"><?php echo $user['full_name']; ?></span>
    </div>
</nav>

<div class="wrapper">
    <div class="hero">
        <p style="text-transform: uppercase; letter-spacing: 3px; font-size: 10px; font-weight: 800; color: var(--accent);">Financial Control</p>
        <h1>Contribution Portal</h1>
        <p>Log your operational transactions into the secure ledger.</p>
    </div>

    <div class="glass-card">
        <form action="process.php" method="POST" enctype="multipart/form-data" class="form-layout">
            
            <div class="input-box">
                <label>Billing Year</label>
                <select name="pay_year">
                    <?php 
                    for($y = $current_year + 1; $y >= $current_year - 1; $y--) {
                        $selected = ($y == $current_year) ? "selected" : "";
                        echo "<option value='$y' $selected>$y Cycle</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="input-box">
                <label>Month</label>
                <select name="pay_month" required>
                    <?php 
                    $months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    foreach($months as $m) {
                        $selected = ($m == $current_month) ? "selected" : "";
                        echo "<option value='$m' $selected>$m</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="input-box">
                <label>Credits (BDT)</label>
                <input type="number" name="amount" placeholder="0.00" required>
            </div>

            <div class="input-box">
                <label>Reference Hash</label>
                <input type="text" name="trx_id" placeholder="Trx ID" required>
            </div>

            <div style="grid-column: span 3;" class="input-box">
                <label>Electronic Receipt (Screenshot)</label>
                <input type="file" name="proof_img" accept="image/*" id="file" style="display:none;" required>
                <label for="file" class="upload-area" id="file-label">
                    <span style="font-size: 13px; color: var(--text-dim);"><i class="fas fa-paperclip" style="margin-right: 10px;"></i> Attach Payment Proof</span>
                </label>
            </div>

            <div style="grid-column: span 1;" class="input-box">
                <label style="opacity:0">Action</label>
                <button type="submit" class="btn-confirm">AUTHORIZE</button>
            </div>

        </form>
    </div>

    <div class="history">
        <h3><i class="fas fa-layer-group" style="margin-right: 15px; font-size: 20px; color: var(--accent);"></i> Recent Logs</h3>
        <table class="premium-table">
            <thead>
                <tr>
                    <th>Temporal Period</th>
                    <th>Identifier</th>
                    <th>Amount</th>
                    <th>Audit Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $p_res = mysqli_query($conn, "SELECT * FROM payments WHERE user_id = '$user_id' ORDER BY id DESC LIMIT 5");
                while($pay = mysqli_fetch_assoc($p_res)):
                    $is_ok = ($pay['status'] == 'approved' || $pay['status'] == 'success');
                ?>
                <tr>
                    <td>
                        <div style="font-weight: 700;"><?php echo $pay['pay_month']; ?></div>
                        <div style="font-size: 11px; color: var(--text-dim);"><?php echo $pay['pay_year']; ?></div>
                    </td>
                    <td style="font-family: monospace; color: var(--text-dim); font-size: 13px;"><?php echo $pay['trx_id']; ?></td>
                    <td class="amt">৳<?php echo number_format($pay['amount']); ?></td>
                    <td>
                        <span class="status <?php echo $is_ok ? 'success' : 'pending'; ?>">
                            <?php echo $is_ok ? 'VERIFIED' : 'PENDING AUDIT'; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.getElementById('file').onchange = function () {
        document.getElementById('file-label').innerHTML = "<span style='color: #00ff7f'><i class='fas fa-check'></i> DOCUMENT_READY.PNG</span>";
        document.getElementById('file-label').style.borderColor = "#00ff7f";
    };
</script>

</body>
</html>

