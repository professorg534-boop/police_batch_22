<?php
session_start();
include '../db.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

// --- DATA INTELLIGENCE ---
// সিস্টেমের মোট অনুমোদিত ফান্ডের হিসাব
$grand_query = "SELECT SUM(amount) as grand_total FROM payment_requests WHERE status IN ('Approved', 'success')";
$grand_res = mysqli_query($conn, $grand_query);
$grand_data = mysqli_fetch_assoc($grand_res);
$system_grand_total = ($grand_data['grand_total'] > 0) ? (float)$grand_data['grand_total'] : 1.0; 

// জেলা ভিত্তিক ফান্ডের তালিকা
$dist_query = "SELECT district, SUM(amount) as dist_total FROM payment_requests WHERE status IN ('Approved', 'success') GROUP BY district ORDER BY dist_total DESC";
$dist_res = mysqli_query($conn, $dist_query);
$districts_data = [];
while($row = mysqli_fetch_assoc($dist_res)) { $districts_data[] = $row; }

// --- DEPLOYMENT LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deploy_poll'])) {
    $op_type    = mysqli_real_escape_string($conn, $_POST['op_type']);
    $target_id  = mysqli_real_escape_string($conn, $_POST['target_id']);
    $req_amount = floatval($_POST['total_req_amount']);
    $briefing   = mysqli_real_escape_string($conn, $_POST['briefing']);
    
    $percentage = ($req_amount / $system_grand_total) * 100;

    // ১. মেইন অপারেশন টেবিল এন্ট্রি
    $insert_op = "INSERT INTO fund_operations (target_batch_id, op_type, total_required, percentage_load, briefing, status, collected_amount, created_at) 
                  VALUES ('$target_id', '$op_type', '$req_amount', '$percentage', '$briefing', 'active', 0, NOW())";
    
    if (mysqli_query($conn, $insert_op)) {
        $op_id = mysqli_insert_id($conn);

        // ২. ডিস্ট্রিক্ট ডিমান্ড জেনারেশন
        foreach ($districts_data as $dist) {
            $d_name = $dist['district'];
            $d_share = ($dist['dist_total'] * $percentage) / 100;
            mysqli_query($conn, "INSERT INTO district_contributions (op_id, district_name, required_amount, status) 
                                 VALUES ('$op_id', '$d_name', '$d_share', 'pending')");
        }
        
        echo "<script>alert('Deployment Successful! Units Notified.'); window.location.href='dashboard.php';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPHA COMMAND | Fund Deployment</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg: #010204;
            --panel: #0a0c10;
            --accent: #3b82f6;
            --neon: #00d4ff;
            --border: rgba(59, 130, 246, 0.15);
            --input-bg: #050608;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background-color: var(--bg);
            background-image: radial-gradient(at 50% 50%, #0f172a 0%, #010204 100%);
            color: #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif;
            height: 100vh; display: flex; overflow: hidden;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 85px; background: rgba(0, 0, 0, 0.8); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; align-items: center; padding: 40px 0;
            backdrop-filter: blur(20px); z-index: 100;
        }
        .nav-item { 
            width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; 
            border-radius: 12px; color: #475569; margin-bottom: 25px; transition: 0.4s; 
            text-decoration: none; border: 1px solid transparent;
        }
        .nav-item:hover, .nav-item.active { 
            color: var(--neon); background: rgba(0, 212, 255, 0.05); border-color: var(--border); 
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.1); 
        }

        /* Workspace Grid */
        .workspace { flex: 1; display: grid; grid-template-columns: 1fr 450px; height: 100vh; }
        .config-area { padding: 50px 70px; overflow-y: auto; position: relative; }
        
        .back-btn { 
            position: absolute; top: 40px; left: 70px; color: #64748b; text-decoration: none; 
            font-size: 11px; font-weight: 700; font-family: 'Orbitron'; display: flex; align-items: center; 
            gap: 10px; transition: 0.3s;
        }
        .back-btn:hover { color: var(--neon); }

        .config-header { margin: 60px 0 40px 0; }
        .config-header h1 { font-family: 'Orbitron', sans-serif; font-size: 26px; letter-spacing: 3px; color: #fff; }

        /* Tactical Card */
        .tactical-card {
            background: var(--panel); border: 1px solid var(--border); border-radius: 28px;
            padding: 40px; box-shadow: 0 30px 60px rgba(0,0,0,0.6);
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; transition: 0.5s ease; }
        .input-box label { display: block; font-size: 9px; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 12px; }
        
        input, select, textarea {
            width: 100%; background: var(--input-bg); border: 1px solid var(--border); 
            padding: 16px; border-radius: 14px; color: #fff; font-size: 14px; outline: none; 
            transition: 0.3s;
        }
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%233b82f6'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 15px center; background-size: 18px;
        }
        select option { background: #0a0c10; color: #fff; }
        input:focus, select:focus { border-color: var(--neon); box-shadow: 0 0 15px rgba(0, 212, 255, 0.1); }

        /* Target HUD */
        #targetHUD { display: none; margin-bottom: 25px; animation: slideIn 0.5s ease; }
        .hud-display {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.05), transparent);
            border: 1px solid var(--border); border-radius: 20px; padding: 25px; display: flex; gap: 25px; align-items: center;
        }
        .hud-avatar { width: 75px; height: 75px; border-radius: 15px; border: 2px solid var(--neon); padding: 3px; background: #000; object-fit: cover; }
        .hud-data h3 { font-family: 'Orbitron'; font-size: 18px; color: #fff; margin-bottom: 6px; }
        .hud-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 10px; font-size: 10px; color: #94a3b8; }
        .hud-grid b { color: var(--neon); }

        /* Amount Engine */
        .amount-engine {
            background: #000; border-radius: 24px; padding: 35px; border: 1px solid var(--border); text-align: center; margin-bottom: 25px;
        }
        .amount-engine input { font-family: 'Orbitron'; font-size: 42px; font-weight: 700; color: var(--neon); text-align: center; border: none; background: transparent; letter-spacing: 2px; }
        .status-meter { height: 4px; background: rgba(255,255,255,0.03); border-radius: 10px; margin: 20px 0; overflow: hidden; }
        .meter-fill { height: 100%; background: linear-gradient(90deg, var(--accent), var(--neon)); width: 0%; transition: 0.6s cubic-bezier(0.22, 1, 0.36, 1); }

        /* Impact Sidebar */
        .dist-panel { background: rgba(5, 6, 8, 0.7); border-left: 1px solid var(--border); padding: 50px 35px; overflow-y: auto; backdrop-filter: blur(30px); }
        .panel-title { font-family: 'Orbitron'; font-size: 11px; color: var(--accent); letter-spacing: 3px; margin-bottom: 40px; opacity: 0.8; }

        .dist-card {
            background: rgba(255,255,255,0.02); border: 1px solid transparent; border-radius: 18px; padding: 18px 22px; margin-bottom: 15px;
            display: flex; justify-content: space-between; align-items: center; transition: 0.3s;
        }
        .dist-card:hover { border-color: var(--accent); background: rgba(59, 130, 246, 0.03); transform: translateX(-5px); }
        .unit-name { font-weight: 700; font-size: 13px; color: #f8fafc; }
        .unit-wallet { font-size: 10px; color: #475569; margin-top: 3px; }
        .unit-share { font-family: 'Orbitron'; font-size: 14px; font-weight: 700; color: #fbbf24; }

        .btn-deploy {
            width: 100%; padding: 22px; background: linear-gradient(45deg, #2563eb, #3b82f6); border: none; border-radius: 18px; color: #fff;
            font-family: 'Orbitron'; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.4s; letter-spacing: 2px;
        }
        .btn-deploy:hover { transform: translateY(-3px); box-shadow: 0 20px 40px rgba(37, 99, 235, 0.3); }

        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div style="color: var(--neon); font-size: 26px; margin-bottom: 50px;"><i class="fa-solid fa-shield-halved"></i></div>
    <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i></a>
    <a href="fund_allocation.php" class="nav-item active"><i class="fa-solid fa-chart-pie"></i></a>
    <a href="sup_administry.php" class="nav-item"><i class="fa-solid fa-tower-observation"></i></a>
    <a href="create_poll.php" class="nav-item"><i class="fa-solid fa-plus-square"></i></a>
</aside>

<main class="workspace">
    <section class="config-area">
        <a href="dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> RETURN TO COMMAND BASE</a>
        
        <header class="config-header">
            <h1>Capital Distribution</h1>
        </header>

        <form method="POST">
            <div class="tactical-card">
                <div class="form-row" id="topRow">
                    <div class="input-box">
                        <label>Mission Objective</label>
                        <select name="op_type" id="missionType" onchange="handleObjectiveChange()" required>
                            <option value="Emergency Support (Batchmate/Family)">Emergency Support (Batchmate/Family)</option>
                            <option value="Legacy Tribute (Deceased Batchmates)">Legacy Tribute (Deceased Batchmates)</option>
                        </select>
                    </div>
                    <div class="input-box" id="targetInputBox">
                        <label>Target Identity (ID)</label>
                        <input type="text" id="batchSearch" placeholder="Search Batch ID..." autocomplete="off">
                        <input type="hidden" name="target_id" id="hiddenBatch">
                    </div>
                </div>

                <div id="targetHUD">
                    <div class="hud-display" id="hudContent"></div>
                </div>

                <div class="amount-engine">
                    <label style="font-size: 9px; color: var(--accent); letter-spacing: 2px;">TOTAL ALLOCATION (৳)</label>
                    <input type="number" name="total_req_amount" id="fundingIn" placeholder="0.00" step="any" required>
                    <div class="status-meter"><div class="meter-fill" id="meterFill"></div></div>
                    <p id="systemLoad" style="font-size: 11px; color: #475569; font-weight: 600;">SYSTEM LOAD: 0.0000%</p>
                </div>

                <div class="input-box">
                    <label>Operational Briefing</label>
                    <textarea name="briefing" rows="3" placeholder="Context for this deployment..." required></textarea>
                </div>

                <button type="submit" name="deploy_poll" class="btn-deploy">Initialize Deployment</button>
            </div>
        </form>
    </section>

    <section class="dist-panel">
        <div class="panel-title">REGIONAL CONTRIBUTION MAP</div>
        <div id="impactList">
            <?php foreach($districts_data as $d): ?>
            <div class="dist-card" data-val="<?php echo $d['dist_total']; ?>">
                <div>
                    <div class="unit-name"><?php echo strtoupper($d['district']); ?> UNIT</div>
                    <div class="unit-wallet">Available: ৳<?php echo number_format($d['dist_total'], 2); ?></div>
                </div>
                <div class="unit-share">৳0.00</div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<script>
const GRAND_ASSET = <?php echo $system_grand_total; ?>;

// ১. মিশন অবজেক্টিভ অনুযায়ী টার্গেট বক্স কন্ট্রোল
function handleObjectiveChange() {
    const type = document.getElementById('missionType').value;
    const targetBox = document.getElementById('targetInputBox');
    const hud = document.getElementById('targetHUD');
    const hiddenInput = document.getElementById('hiddenBatch');
    const topRow = document.getElementById('topRow');

    if (type.includes('Legacy Tribute')) {
        targetBox.style.display = 'none';
        hud.style.display = 'none';
        topRow.style.gridTemplateColumns = '1fr'; // সিলেক্ট বক্স ফুল উইডথ হয়ে যাবে
        hiddenInput.value = 'LEGACY_FUND'; // ডাটাবেসের জন্য ডিফল্ট ভ্যালু
    } else {
        targetBox.style.display = 'block';
        topRow.style.gridTemplateColumns = '1fr 1fr';
        hiddenInput.value = ''; // ইউজারকে আবার আইডি দিতে হবে
    }
}

// ২. ক্যালকুলেশন ইঞ্জিন
document.getElementById('fundingIn').addEventListener('input', function() {
    let amount = parseFloat(this.value) || 0;
    let load = (amount / GRAND_ASSET) * 100;
    document.getElementById('meterFill').style.width = Math.min(load, 100) + '%';
    document.getElementById('systemLoad').innerText = `SYSTEM LOAD: ${load.toFixed(4)}% OF TOTAL ASSETS`;

    document.querySelectorAll('.dist-card').forEach(card => {
        let balance = parseFloat(card.getAttribute('data-val'));
        let share = (balance * load) / 100;
        card.querySelector('.unit-share').innerText = '৳' + share.toLocaleString(undefined, {minimumFractionDigits: 2});
    });
});

// ৩. ব্যাচ আইডি সার্চ লজিক
document.getElementById('batchSearch').addEventListener('keyup', async function() {
    let id = this.value.trim();
    if(id.length < 2) { document.getElementById('targetHUD').style.display = 'none'; return; }
    try {
        const res = await fetch('get_user_by_batch.php?id=' + id);
        const data = await res.json();
        if(data.success) {
            document.getElementById('targetHUD').style.display = 'block';
            document.getElementById('hiddenBatch').value = data.user.batch_id;
            document.getElementById('hudContent').innerHTML = `
                <img src="../uploads/${data.user.photo || 'default.png'}" class="hud-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/6522/6522516.png'">
                <div class="hud-data">
                    <h3>${data.user.full_name}</h3>
                    <div class="hud-grid">
                        <span>BATCH ID: <b>#${data.user.batch_id}</b></span>
                        <span>DISTRICT: <b>${data.user.district}</b></span>
                    </div>
                </div>`;
        } else {
            document.getElementById('targetHUD').style.display = 'none';
        }
    } catch(e) {}
});
</script>

</body>
</html>