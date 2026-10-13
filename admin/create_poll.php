<?php
die("THIS_IS_NEW_PAGE");
// ১. এরর ট্র্যাকিং অন রাখা (যাতে কোনো সমস্যা হলে সরাসরি স্ক্রিনে দেখায়)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (file_exists('../db.php')) { include '../db.php'; }
elseif (file_exists('db.php')) { include 'db.php'; }
else { die("<div style='color:red; font-family:sans-serif; padding:20px;'>CRITICAL ERROR: db.php file missing.</div>"); }

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$user_id = (int) $_SESSION['user_id'];

// AJAX Endpoint: Batch ID দিয়ে মেম্বার সার্চ করার ব্যাকএন্ড সার্ভিস
if (isset($_GET['action']) && $_GET['action'] === 'search_batch') {
    header('Content-Type: application/json; charset=utf-8');
    $batch_query = mysqli_real_escape_string($conn, trim($_GET['batch_id'] ?? ''));
    
    if ($batch_query !== '') {
        $search_sql = "SELECT id, full_name, batch_id, district, role, mobile_number, photo FROM users WHERE batch_id = '$batch_query' OR id = '$batch_query' LIMIT 1";
        $search_res = mysqli_query($conn, $search_sql);
        if ($search_res && $member = mysqli_fetch_assoc($search_res)) {
            echo json_encode(['status' => 'success', 'data' => $member]);
            exit();
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'No personnel found with this Batch ID']);
    exit();
}

// Admin Check Verification
$me_sql = "SELECT role FROM users WHERE id = $user_id LIMIT 1";
$me_res = mysqli_query($conn, $me_sql);
$user_data = $me_res ? mysqli_fetch_assoc($me_res) : null;

$my_role = strtolower(trim($user_data['role'] ?? ''));
$is_admin = in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin']);
if (!$user_data || !$is_admin) {
    http_response_code(403);
    exit("Access Denied: High-Level Authorization Required.");
}

$status_msg = "";
$status_type = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['launch_poll'])) {
    $question = trim($_POST['question'] ?? '');
    $raw_options = isset($_POST['options']) ? (array) $_POST['options'] : [];
    $duration_hours = isset($_POST['duration_hours']) ? (int) $_POST['duration_hours'] : 1;

    $duration_hours = max(1, min(168, $duration_hours));

    $options_array = [];
    foreach ($raw_options as $opt) {
        $opt = trim($opt);
        if ($opt !== '' && mb_strlen($opt) <= 150) {
            $options_array[] = $opt;
        }
    }
    $options_array = array_slice(array_unique($options_array), 0, 8);

    if ($question === '' || mb_strlen($question) > 300) {
        $status_msg = "Question is required (max 300 characters).";
        $status_type = "error";
    } elseif (count($options_array) < 2) {
        $status_msg = "At least 2 valid options are required.";
        $status_type = "error";
    } else {
        $options_json = json_encode(array_values($options_array), JSON_UNESCAPED_UNICODE);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));

        $q_safe = mysqli_real_escape_string($conn, $question);
        $opt_safe = mysqli_real_escape_string($conn, $options_json);

        $insert_sql = "INSERT INTO polls (question, options, created_by, status, expires_at, created_at) VALUES ('$q_safe', '$opt_safe', $user_id, 'active', '$expires_at', NOW())";

        if (mysqli_query($conn, $insert_sql)) {
            $status_msg = "POLL LAUNCHED: Mission Status Active.";
            $status_type = "success";
        } else {
            error_log("create_poll.php insert failed: " . mysqli_error($conn));
            $status_msg = "Database Error: " . mysqli_error($conn);
            $status_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ALPHA HQ | Tactical Poll Initialization</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&family=Orbitron:wght@700;900&family=JetBrains+Mono:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg: #03050d;
            --card-bg: rgba(10, 16, 36, 0.75);
            --accent: #2f6bff;
            --accent-light: #38bdf8;
            --neon: #00f2fe;
            --neon-dim: rgba(0, 242, 254, 0.12);
            --border: rgba(130, 170, 255, 0.18);
            --text-main: #eaf1ff;
            --text-dim: #8ea3cc;
            --danger: #ef4444;
            --success: #34d399;
            --glass: rgba(255, 255, 255, 0.02);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }

        body {
            background-color: var(--bg);
            background-image:
                radial-gradient(900px 500px at 10% 10%, rgba(47, 107, 255, 0.18) 0%, transparent 60%),
                radial-gradient(800px 500px at 90% 85%, rgba(0, 242, 254, 0.12) 0%, transparent 60%),
                linear-gradient(180deg, #061030 0%, #03050d 100%);
            background-attachment: fixed;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            padding: 24px 16px 80px;
        }

        .main-container {
            width: 100%; max-width: 1100px; margin: 0 auto;
            animation: panelFadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .nav-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 25px; padding: 0 4px; gap: 12px;
        }
        .nav-buttons { display: flex; align-items: center; gap: 10px; }
        .btn-nav {
            background: var(--card-bg); border: 1px solid var(--border); color: var(--accent-light);
            text-decoration: none; padding: 10px 18px; border-radius: 14px; font-size: 13px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(12px);
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-nav:hover { background: var(--accent); color: #fff; border-color: var(--accent); transform: translateY(-2px); }

        .layout-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }

        .premium-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.7);
            position: relative;
        }

        .header-block {
            text-align: left; margin-bottom: 35px; border-left: 4px solid var(--accent-light);
            padding-left: 20px; position: relative;
        }

        .header-block h1 {
            font-family: 'Orbitron', sans-serif; font-size: 22px; font-weight: 900;
            letter-spacing: 3px; color: #fff; text-transform: uppercase;
        }
        .header-block p { font-size: 12px; color: var(--text-dim); margin-top: 4px; font-weight: 600; }

        .form-group { margin-bottom: 28px; }

        .label-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }

        label {
            display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; color: var(--accent-light);
            text-transform: uppercase; letter-spacing: 1.5px;
        }

        .char-count { font-family: 'JetBrains Mono'; font-size: 10px; color: var(--text-dim); }

        .input-box { position: relative; }
        .input-box i {
            position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
            color: var(--accent-light); font-size: 15px; opacity: 0.8;
        }

        input[type="text"] {
            width: 100%; background: rgba(0, 0, 0, 0.4); border: 1px solid var(--border);
            padding: 16px 16px 16px 48px; border-radius: 16px; color: #fff;
            font-size: 14px; transition: all 0.3s ease; font-weight: 600;
        }

        input:focus { border-color: var(--neon); box-shadow: 0 0 20px var(--neon-dim); background: rgba(0, 0, 0, 0.6); }

        .batch-search-box {
            background: rgba(47, 107, 255, 0.08); border: 1px solid var(--border);
            border-radius: 20px; padding: 20px; margin-bottom: 30px;
        }
        .search-btn-inline {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: var(--accent); color: #fff; border: none; padding: 8px 16px;
            border-radius: 12px; font-weight: 700; font-size: 12px; cursor: pointer; transition: 0.3s;
        }
        .search-btn-inline:hover { background: var(--accent-light); color: #000; }

        .member-dossier-card {
            display: none; margin-top: 15px; padding: 16px; background: rgba(8, 16, 40, 0.9);
            border: 1px solid var(--border); border-radius: 18px; backdrop-filter: blur(10px);
            animation: panelFadeIn 0.4s ease;
        }
        .dossier-flex { display: flex; align-items: center; gap: 14px; }
        .dossier-avatar {
            width: 52px; height: 52px; border-radius: 14px; object-fit: cover;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            display: grid; place-items: center; font-size: 20px; font-weight: 800; color: #fff;
            border: 1px solid var(--border); flex-shrink: 0;
        }
        .dossier-info h4 { font-size: 15px; font-weight: 800; color: #fff; }
        .dossier-info p { font-size: 12px; color: var(--text-dim); margin-top: 2px; }
        .dossier-badge {
            display: inline-block; background: rgba(56, 189, 248, 0.15); color: var(--accent-light);
            padding: 3px 10px; border-radius: 99px; font-size: 10px; font-weight: 800; margin-top: 6px;
            text-transform: uppercase;
        }

        .option-item { display: flex; gap: 10px; margin-bottom: 10px; animation: itemSlideIn 0.3s ease-out; }

        .btn-remove { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.25); color: var(--danger); border-radius: 12px; width: 48px; cursor: pointer; transition: 0.3s; flex-shrink: 0; }
        .btn-remove:hover { background: var(--danger); color: #fff; }
        .btn-remove:disabled { opacity: 0.2; cursor: not-allowed; }

        .btn-attach {
            background: transparent; border: 1px dashed var(--accent-light); color: var(--accent-light);
            width: 100%; padding: 14px; border-radius: 14px; cursor: pointer;
            font-weight: 700; font-size: 12px; letter-spacing: 0.5px;
            transition: 0.3s; margin-top: 8px;
        }
        .btn-attach:hover:not(:disabled) { background: rgba(47, 107, 255, 0.15); color: #fff; border-color: var(--neon); }
        .btn-attach:disabled { opacity: 0.3; cursor: not-allowed; }

        .slider-panel {
            background: rgba(0, 0, 0, 0.35); border: 1px solid var(--border);
            padding: 24px; border-radius: 20px;
        }

        .time-monitor { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px; }
        .time-value { font-family: 'Orbitron'; font-size: 38px; font-weight: 700; color: var(--neon); line-height: 1; }
        .time-unit { font-size: 13px; color: var(--text-dim); margin-left: 6px; font-weight: 600; text-transform: uppercase; }

        input[type="range"] {
            -webkit-appearance: none; appearance: none; width: 100%; height: 6px; background: rgba(255,255,255,0.08);
            border-radius: 10px; cursor: pointer; margin-bottom: 16px;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none; width: 24px; height: 24px;
            background: #fff; border: 4px solid var(--accent);
            border-radius: 50%; cursor: pointer; transition: 0.2s;
            box-shadow: 0 0 15px var(--accent);
        }

        .duration-pills { display: flex; gap: 6px; flex-wrap: wrap; }
        .duration-pill {
            flex: 1; min-width: 50px; text-align: center; padding: 8px 4px; border-radius: 10px;
            border: 1px solid var(--border); background: var(--glass); color: var(--text-dim);
            font-size: 11px; font-weight: 800; cursor: pointer; transition: 0.25s;
        }
        .duration-pill:hover, .duration-pill.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        .deployment-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: #fff; border: none; padding: 18px; border-radius: 16px;
            font-family: 'Orbitron', sans-serif; font-weight: 800; font-size: 14px;
            letter-spacing: 2px; cursor: pointer; transition: 0.3s;
            margin-top: 15px; box-shadow: 0 10px 25px rgba(47, 107, 255, 0.4);
        }
        .deployment-btn:hover { transform: translateY(-2px); box-shadow: 0 15px 35px rgba(47, 107, 255, 0.6); filter: brightness(1.1); }

        .toast-notify {
            position: fixed; top: 25px; right: 25px; padding: 16px 22px;
            border-radius: 14px; font-weight: 700; font-size: 13px;
            display: flex; align-items: center; gap: 10px; max-width: 90vw;
            backdrop-filter: blur(12px); z-index: 1000;
            animation: toastSlideIn 0.4s ease forwards;
        }
        .toast-success { background: rgba(52, 211, 153, 0.15); border: 1px solid rgba(52, 211, 153, 0.3); color: var(--success); }
        .toast-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--danger); }

        .preview-panel {
            background: var(--card-bg); backdrop-filter: blur(30px); border: 1px solid var(--border);
            border-radius: 28px; padding: 28px; position: sticky; top: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .preview-label { font-family: 'JetBrains Mono'; font-size: 10px; color: var(--neon); letter-spacing: 1px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .preview-label .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--neon); box-shadow: 0 0 8px var(--neon); animation: pulseDot 1.5s infinite; }
        .preview-question { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 18px; line-height: 1.5; min-height: 40px; }
        .preview-question.placeholder { color: var(--text-dim); font-weight: 500; }
        .preview-option { background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; font-size: 12.5px; font-weight: 600; color: #e2e8f0; }
        .preview-empty { color: var(--text-dim); font-size: 12px; text-align: center; padding: 15px 0; }
        .preview-expiry { margin-top: 18px; padding-top: 15px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-dim); font-family: 'JetBrains Mono'; }
        .preview-expiry b { color: var(--neon); }

        @keyframes panelFadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes itemSlideIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes toastSlideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes pulseDot { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

        @media (max-width: 960px) {
            .layout-grid { grid-template-columns: 1fr; }
            .preview-panel { position: static; }
        }
        @media (max-width: 560px) {
            body { padding: 14px 10px; }
            .premium-card { padding: 24px 18px; border-radius: 22px; }
            .header-block h1 { font-size: 17px; letter-spacing: 2px; }
            .time-value { font-size: 30px; }
        }
    </style>
</head>
<body>

<div class="main-container">

    <div class="nav-header">
        <div class="nav-buttons">
            <a href="../dashboard.php" class="btn-nav"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="../index.php" class="btn-nav"><i class="fa-solid fa-house"></i> Home</a>
        </div>
        <div style="font-family:'Orbitron'; font-size:11px; color:var(--accent-light); font-weight:700;">HQ INTEL MODULE</div>
    </div>

    <?php if ($status_type === 'success'): ?>
        <div class="toast-notify toast-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($status_msg) ?></div>
        <script>setTimeout(() => { window.location.href = '../dashboard.php'; }, 2000);</script>
    <?php elseif ($status_type === 'error'): ?>
        <div class="toast-notify toast-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($status_msg) ?></div>
    <?php endif; ?>

    <div class="layout-grid">
        <div class="premium-card">
            <div class="header-block">
                <h1>POLL <span>INITIALIZATION</span></h1>
                <p>Deploy real-time inquiry across intelligence network</p>
            </div>

            <!-- Batch ID Search Section -->
            <div class="batch-search-box">
                <label><i class="fa-solid fa-magnifying-glass"></i> Search Personnel (Batch ID)</label>
                <div class="input-box" style="margin-top: 8px;">
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" id="batchSearchInput" placeholder="Enter Batch ID or User ID...">
                    <button type="button" class="search-btn-inline" onclick="searchMemberByBatch()">Search</button>
                </div>

                <div id="memberResultCard" class="member-dossier-card">
                    <div class="dossier-flex">
                        <img id="mPhoto" src="" class="dossier-avatar" style="display:none;" alt="Avatar">
                        <div id="mInitial" class="dossier-avatar">U</div>
                        <div class="dossier-info">
                            <h4 id="mName">Member Name</h4>
                            <p><i class="fa-solid fa-hashtag"></i> Batch ID: <b id="mBatch" style="color:#fff;">#---</b> | <i class="fa-solid fa-location-dot"></i> <span id="mDistrict">District</span></p>
                            <p style="margin-top:2px;"><i class="fa-solid fa-phone"></i> Contact: <span id="mPhone">Protected</span></p>
                            <span id="mRole" class="dossier-badge">Member</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Form -->
            <form method="POST" id="pollForm">
                <div class="form-group">
                    <div class="label-row">
                        <label><i class="fa-solid fa-clipboard-question"></i> Survey Objective (Question)</label>
                        <span class="char-count" id="qCount">0/300</span>
                    </div>
                    <div class="input-box">
                        <i class="fa-solid fa-crosshairs"></i>
                        <input type="text" name="question" id="questionInput" placeholder="Enter tactical inquiry description..." maxlength="300" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-row"><label><i class="fa-solid fa-list-check"></i> Response Parameters (Options)</label></div>
                    <div id="dynamic-options">
                        <div class="option-item">
                            <div class="input-box" style="width: 100%;">
                                <i class="fa-solid fa-satellite"></i>
                                <input type="text" name="options[]" class="opt-input" placeholder="Operational Option 1" maxlength="150" required>
                            </div>
                            <button type="button" class="btn-remove" onclick="removeParam(this)" disabled><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                        <div class="option-item">
                            <div class="input-box" style="width: 100%;">
                                <i class="fa-solid fa-satellite"></i>
                                <input type="text" name="options[]" class="opt-input" placeholder="Operational Option 2" maxlength="150" required>
                            </div>
                            <button type="button" class="btn-remove" onclick="removeParam(this)" disabled><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn-attach" id="attachBtn" onclick="addNewParam()"><i class="fa-solid fa-plus"></i> ATTACH NEW PARAMETER</button>
                </div>

                <div class="form-group">
                    <div class="label-row">
                        <label><i class="fa-solid fa-clock"></i> Operational Window (Expiry)</label>
                        <span id="expiry-timestamp" style="font-size: 10px; color: var(--neon); font-weight: 800; font-family: 'JetBrains Mono';">ENDS: --:-- --</span>
                    </div>
                    <div class="slider-panel">
                        <div class="time-monitor">
                            <div>
                                <span class="time-value" id="hours-display">1</span>
                                <span class="time-unit">Hours</span>
                            </div>
                            <i class="fa-solid fa-bolt" style="color: var(--neon); font-size: 18px;"></i>
                        </div>
                        <input type="range" name="duration_hours" id="duration-slider" min="1" max="168" value="1" oninput="updateTacticalTime(this.value, true)">
                        <div class="duration-pills">
                            <div class="duration-pill" data-h="1" onclick="updateTacticalTime(1)">1H</div>
                            <div class="duration-pill" data-h="6" onclick="updateTacticalTime(6)">6H</div>
                            <div class="duration-pill" data-h="24" onclick="updateTacticalTime(24)">1D</div>
                            <div class="duration-pill" data-h="72" onclick="updateTacticalTime(72)">3D</div>
                            <div class="duration-pill" data-h="168" onclick="updateTacticalTime(168)">7D</div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="launch_poll" class="deployment-btn">
                    ACTIVATE DEPLOYMENT <i class="fa-solid fa-paper-plane" style="margin-left: 8px;"></i>
                </button>
            </form>
        </div>

        <aside class="preview-panel">
            <div class="preview-label"><span class="dot"></span> LIVE PREVIEW</div>
            <div class="preview-question placeholder" id="previewQuestion">Your poll question will appear here...</div>
            <div id="previewOptions">
                <div class="preview-empty">Add options to see them here</div>
            </div>
            <div class="preview-expiry">This poll will auto-close in <b id="previewExpiry">1 hour</b></div>
        </aside>
    </div>
</div>

<script>
    async function searchMemberByBatch() {
        const batchVal = document.getElementById('batchSearchInput').value.trim();
        const dossierCard = document.getElementById('memberResultCard');

        if (!batchVal) {
            alert("Please enter a valid Batch ID or User ID");
            return;
        }

        try {
            const response = await fetch(`create_poll.php?action=search_batch&batch_id=${encodeURIComponent(batchVal)}`);
            const result = await response.json();

            if (result.status === 'success') {
                const u = result.data;
                document.getElementById('mName').innerText = u.full_name || 'Unknown';
                document.getElementById('mBatch').innerText = '#' + (u.batch_id || u.id);
                document.getElementById('mDistrict').innerText = u.district || 'N/A';
                document.getElementById('mPhone').innerText = u.mobile_number || 'N/A';
                document.getElementById('mRole').innerText = u.role || 'Member';

                const imgEl = document.getElementById('mPhoto');
                const initEl = document.getElementById('mInitial');

                if (u.photo) {
                    imgEl.src = '../uploads/' + u.photo;
                    imgEl.style.display = 'block';
                    initEl.style.display = 'none';
                } else {
                    imgEl.style.display = 'none';
                    initEl.style.display = 'grid';
                    initEl.innerText = (u.full_name || 'U').charAt(0).toUpperCase();
                }

                dossierCard.style.display = 'block';
            } else {
                dossierCard.style.display = 'none';
                alert(result.message || "No personnel found");
            }
        } catch (error) {
            console.error(error);
            alert("Error searching for personnel details");
        }
    }

    function updateTacticalTime(val, fromSlider) {
        val = parseInt(val);
        document.getElementById('hours-display').innerText = val;
        if (!fromSlider) document.getElementById('duration-slider').value = val;

        document.querySelectorAll('.duration-pill').forEach(p => {
            p.classList.toggle('active', parseInt(p.dataset.h) === val);
        });

        let now = new Date();
        now.setHours(now.getHours() + val);
        let dateStr = now.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true, day: 'numeric', month: 'short' });
        document.getElementById('expiry-timestamp').innerText = "ENDS: " + dateStr;

        const readable = val < 24 ? val + ' hour' + (val > 1 ? 's' : '') : Math.round(val / 24) + ' day' + (val >= 48 ? 's' : '');
        document.getElementById('previewExpiry').innerText = readable;
    }

    function refreshRemoveButtons() {
        const items = document.querySelectorAll('#dynamic-options .option-item');
        items.forEach(item => {
            item.querySelector('.btn-remove').disabled = (items.length <= 2);
        });
        document.getElementById('attachBtn').disabled = (items.length >= 8);
    }

    function removeParam(btn) {
        btn.parentElement.remove();
        refreshRemoveButtons();
        updatePreview();
    }

    function addNewParam() {
        const container = document.getElementById('dynamic-options');
        if (container.children.length >= 8) return;
        const count = container.children.length + 1;
        const div = document.createElement('div');
        div.className = 'option-item';
        div.innerHTML = `
            <div class="input-box" style="width: 100%;">
                <i class="fa-solid fa-satellite"></i>
                <input type="text" name="options[]" class="opt-input" placeholder="Operational Option ${count}" maxlength="150" required>
            </div>
            <button type="button" class="btn-remove" onclick="removeParam(this)"><i class="fa-solid fa-trash-can"></i></button>
        `;
        container.appendChild(div);
        div.querySelector('.opt-input').addEventListener('input', updatePreview);
        refreshRemoveButtons();
        updatePreview();
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function updatePreview() {
        const q = document.getElementById('questionInput').value.trim();
        const qEl = document.getElementById('previewQuestion');
        if (q === '') {
            qEl.textContent = 'Your poll question will appear here...';
            qEl.classList.add('placeholder');
        } else {
            qEl.textContent = q;
            qEl.classList.remove('placeholder');
        }

        document.getElementById('qCount').innerText = q.length + '/300';

        const opts = Array.from(document.querySelectorAll('.opt-input')).map(i => i.value.trim()).filter(v => v !== '');
        const optsContainer = document.getElementById('previewOptions');
        if (opts.length === 0) {
            optsContainer.innerHTML = '<div class="preview-empty">Add options to see them here</div>';
        } else {
            optsContainer.innerHTML = opts.map(o => `<div class="preview-option">${escapeHtml(o)}</div>`).join('');
        }
    }

    document.getElementById('questionInput').addEventListener('input', updatePreview);
    document.querySelectorAll('.opt-input').forEach(el => el.addEventListener('input', updatePreview));

    refreshRemoveButtons();
    updateTacticalTime(1);
    updatePreview();
</script>

</body>
</html>