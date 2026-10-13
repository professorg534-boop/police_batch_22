<?php
session_start();

// ডাটাবেজ ফাইল কানেকশন চেক
if (file_exists('../db.php')) { 
    include '../db.php'; 
} elseif (file_exists('db.php')) { 
    include 'db.php'; 
} else { 
    die("Database connection file missing."); 
}

// সেশন ও লগইন চেক
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$my_id = (int) $_SESSION['user_id'];

// বর্তমান ইউজারের রোল ও একাউন্ট স্ট্যাটাস ভ্যালিডেশন
$me_stmt = mysqli_prepare($conn, "SELECT role, status FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($me_stmt, "i", $my_id);
mysqli_stmt_execute($me_stmt);
$me_result = mysqli_stmt_get_result($me_stmt);
$me = $me_result ? mysqli_fetch_assoc($me_result) : null;
mysqli_stmt_close($me_stmt);

if (!$me) { 
    session_destroy(); 
    header("Location: login.php"); 
    exit(); 
}

$my_role = strtolower(trim($me['role'] ?? 'member'));
$is_admin = in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin']);
$is_superadmin = in_array($my_role, ['super admin', 'super_admin', 'superadmin']);

// view mode ভ্যালিডেশন
$view_mode = $_GET['view'] ?? 'active';
if ($view_mode === 'pending' && !$is_admin) {
    $view_mode = 'active';
}

// ---- POST ACTION HANDLER (100% Mobile Safe Approval & Delete) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && isset($_POST['target_user_id']) && $is_admin) {
    $target_id = (int) $_POST['target_user_id'];
    $act = $_POST['action_type'];

    if ($act === 'approve') {
        $app_stmt = mysqli_prepare($conn, "UPDATE users SET status = 'active' WHERE id = ?");
        if ($app_stmt) {
            mysqli_stmt_bind_param($app_stmt, "i", $target_id);
            mysqli_stmt_execute($app_stmt);
            mysqli_stmt_close($app_stmt);
        }
        header("Location: members.php?view=pending&msg=approved");
        exit();
    } elseif ($act === 'remove') {
        $rem_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        if ($rem_stmt) {
            mysqli_stmt_bind_param($rem_stmt, "i", $target_id);
            mysqli_stmt_execute($rem_stmt);
            mysqli_stmt_close($rem_stmt);
        }
        $redirect_view = ($view_mode === 'pending') ? "view=pending&" : "";
        header("Location: members.php?" . $redirect_view . "msg=removed");
        exit();
    }
}

// বিভাগ ও জেলাসমূহের মাস্টার ডাটা
$districts = [
    "Dhaka" => ["Dhaka", "Gazipur", "Narayanganj", "Tangail", "Faridpur", "Manikganj", "Munshiganj", "Rajbari", "Madaripur", "Gopalganj", "Shariatpur", "Kishoreganj", "Narsingdi"],
    "Chittagong" => ["Chittagong", "Cox's Bazar", "Cumilla", "Feni", "Brahmanbaria", "Noakhali", "Lakshmipur", "Chandpur", "Rangamati", "Khagrachari", "Bandarban"],
    "Rajshahi" => ["Rajshahi", "Bogra", "Pabna", "Naogaon", "Natore", "Sirajganj", "Chapai Nawabganj", "Joypurhat"],
    "Khulna" => ["Khulna", "Jashore", "Kushtia", "Satkhira", "Bagerhat", "Chuadanga", "Meherpur", "Narail", "Magura", "Jhenaidah"],
    "Barisal" => ["Barisal", "Bhola", "Patuakhali", "Pirojpur", "Jhalokati", "Barguna"],
    "Sylhet" => ["Sylhet", "Moulvibazar", "Habiganj", "Sunamganj"],
    "Rangpur" => ["Rangpur", "Dinajpur", "Kurigram", "Gaibandha", "Nilphamari", "Panchagarh", "Thakurgaon", "Lalmonirhat"],
    "Mymensingh" => ["Mymensingh", "Jamalpur", "Netrokona", "Sherpur"]
];

// ফিল্টারিং ভ্যালিডেশন
$selected_div = $_GET['division'] ?? "";
if ($selected_div !== "" && !array_key_exists($selected_div, $districts)) {
    $selected_div = "";
}

$selected_dist = $_GET['district'] ?? "";
if ($selected_div === "" || ($selected_dist !== "" && !in_array($selected_dist, $districts[$selected_div], true))) {
    $selected_dist = "";
}

// সার্চ ফিল্টার
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

// পেন্ডিং মেম্বার কাউন্ট
$p_count = 0;
if ($is_admin) {
    $p_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status='pending'");
    $p_count = $p_res ? (int) mysqli_fetch_assoc($p_res)['total'] : 0;
}

// মেম্বার কুয়েরি কন্ডিশন তৈরি
$conditions = [];
$params = [];
$types = '';

if ($view_mode === 'pending') {
    $conditions[] = "status = 'pending'";
} else {
    $conditions[] = "status = 'active'";
}

if ($search !== '') {
    $conditions[] = "(full_name LIKE ? OR bp_number LIKE ? OR batch_id LIKE ? OR mobile_number LIKE ?)";
    $likeTerm = '%' . $search . '%';
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $types .= 'ssss';
} elseif ($view_mode !== 'pending') {
    if ($selected_div !== "") {
        $conditions[] = "division = ?";
        $params[] = $selected_div;
        $types .= 's';

        if ($selected_dist !== "") {
            $conditions[] = "district = ?";
            $params[] = $selected_dist;
            $types .= 's';
        }
    }
}

$sql = "SELECT * FROM users WHERE " . implode(' AND ', $conditions) . " ORDER BY id DESC";
$stmt = mysqli_prepare($conn, $sql);

if ($params) {
    $bindArgs = [$stmt, $types];
    foreach ($params as $key => $value) {
        $bindArgs[] = &$params[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
}

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ALPHA CORE | Members Directory</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #060913;
            --card-bg: rgba(18, 26, 52, 0.88);
            --border: rgba(130, 170, 255, 0.2);
            --accent: #2f6bff;
            --accent-light: #38bdf8;
            --muted: #8ea3cc;
            --text: #eaf1ff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            min-height: 100vh; 
            padding-bottom: 100px; 
            -webkit-tap-highlight-color: transparent;
        }

        .container { max-width: 900px; margin: 0 auto; padding: 14px 10px; }

        /* Top Bar Search */
        .top-bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 14px; }
        .search-form { flex: 1; display: flex; gap: 6px; }
        .search-input-wrap { flex: 1; position: relative; }
        .search-input-wrap input {
            width: 100%; 
            background: var(--card-bg); 
            border: 1px solid var(--border);
            padding: 10px 12px 10px 36px; 
            border-radius: 12px; 
            color: #fff;
            font-size: 13px; 
            font-weight: 600; 
            outline: none;
        }
        .search-input-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--accent-light); }

        .btn-search {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border: none; 
            color: #fff; 
            padding: 0 14px; 
            border-radius: 12px;
            font-size: 12px; 
            font-weight: 700; 
            cursor: pointer; 
            white-space: nowrap;
            display: flex; 
            align-items: center; 
            gap: 4px;
        }

        .btn-home {
            background: rgba(47,107,255,0.2);
            border: 1px solid var(--border);
            color: #fff; 
            text-decoration: none; 
            padding: 10px 14px; 
            border-radius: 12px;
            font-size: 12px; 
            font-weight: 700; 
            white-space: nowrap; 
            display: flex; 
            align-items: center; 
            gap: 5px;
        }

        /* Banners */
        .alert-box {
            padding: 12px; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 12px;
            margin-bottom: 12px; 
            display: flex; 
            align-items: center; 
            gap: 8px;
        }
        .alert-success { background: rgba(52, 211, 153, 0.15); border: 1px solid #34d399; color: #34d399; }
        .alert-danger { background: rgba(248, 113, 113, 0.15); border: 1px solid #f87171; color: #f87171; }

        .all-members-btn {
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px;
            width: 100%; 
            padding: 12px; 
            margin-bottom: 16px;
            background: linear-gradient(135deg, rgba(47,107,255,0.22), rgba(56,189,248,0.1));
            border: 1px solid var(--border); 
            border-radius: 14px;
            color: #fff; 
            text-decoration: none; 
            font-weight: 800; 
            font-size: 13px;
        }
        .all-members-btn.active {
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-color: var(--accent-light);
        }

        /* Headers & Grids */
        .sec-header { 
            font-size: 12px; 
            font-weight: 800; 
            color: var(--accent-light); 
            text-transform: uppercase; 
            margin-bottom: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
        }
        .sec-header a { color: var(--muted); text-decoration: none; font-size: 11px; }

        .division-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 16px; }
        .div-card {
            background: var(--card-bg); 
            border: 1px solid var(--border);
            padding: 12px 6px; 
            border-radius: 12px; 
            text-align: center;
            text-decoration: none; 
            color: var(--text); 
            font-weight: 700; 
            font-size: 12px;
            display: flex;
            flex-direction: column; 
            align-items: center; 
            gap: 4px;
        }
        .div-card i { font-size: 15px; color: var(--accent-light); }
        .div-card.active {
            background: linear-gradient(135deg, rgba(47,107,255,0.4), rgba(56,189,248,0.2));
            border-color: var(--accent-light); 
            color: #fff;
        }

        .district-box {
            background: rgba(10, 18, 40, 0.9); 
            border: 1px solid var(--border);
            border-radius: 14px; 
            padding: 12px; 
            margin-bottom: 16px;
        }
        .district-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .dist-chip {
            padding: 6px 10px; 
            border-radius: 8px; 
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border); 
            color: var(--muted); 
            text-decoration: none;
            font-size: 11px; 
            font-weight: 700;
        }
        .dist-chip.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* Member Card Layout - Isolated Touch Controls */
        .member-list { display: flex; flex-direction: column; gap: 10px; }
        .member-card {
            background: var(--card-bg); 
            border: 1px solid var(--border);
            border-radius: 14px; 
            padding: 12px; 
            position: relative;
            display: flex; 
            flex-direction: column; 
            gap: 10px;
        }

        .m-row-main { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            gap: 8px; 
        }

        .m-info-left { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            min-width: 0; 
            cursor: pointer; 
            flex: 1; 
        }

        .m-avatar {
            width: 42px; 
            height: 42px; 
            border-radius: 10px; 
            object-fit: cover;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            display: grid; 
            place-items: center; 
            font-size: 15px; 
            font-weight: 800; 
            color: #fff;
            flex-shrink: 0; 
            border: 1px solid var(--border);
        }

        .m-details { min-width: 0; flex: 1; }
        .m-name { font-size: 14px; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .m-sub { font-size: 11px; color: var(--accent-light); font-weight: 700; margin-top: 1px; }

        /* Isolated Form Actions Container */
        .m-action-btns {
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 10;
        }

        .btn-view {
            background: rgba(47,107,255,0.22); 
            border: 1px solid var(--border);
            color: var(--accent-light); 
            text-decoration: none; 
            padding: 8px 12px;
            border-radius: 8px; 
            font-size: 11px; 
            font-weight: 800; 
            white-space: nowrap;
            display: inline-flex; 
            align-items: center; 
            gap: 4px; 
            cursor: pointer;
        }

        /* 100% Mobile Clickable Form Buttons */
        .btn-submit-approve {
            background: #10b981; 
            border: none; 
            color: #000;
            padding: 8px 12px; 
            border-radius: 8px;
            font-size: 11px; 
            font-weight: 800; 
            white-space: nowrap; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px;
            cursor: pointer;
        }

        .btn-submit-remove {
            background: rgba(248, 113, 113, 0.25); 
            border: 1px solid rgba(248, 113, 113, 0.6); 
            color: #f87171;
            padding: 8px 12px; 
            border-radius: 8px;
            font-size: 11px; 
            font-weight: 800; 
            white-space: nowrap; 
            display: inline-flex; 
            align-items: center; 
            gap: 4px;
            cursor: pointer;
        }

        .m-extra-info {
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            gap: 6px;
            padding-top: 8px; 
            border-top: 1px solid rgba(130,170,255,0.1); 
            font-size: 11px; 
            color: var(--muted);
        }
        .m-extra-info i { color: var(--accent-light); margin-right: 3px; }

        .role-badge {
            background: rgba(96,165,250,0.15); 
            color: var(--accent-light);
            padding: 2px 8px; 
            border-radius: 99px; 
            font-size: 9px; 
            font-weight: 800; 
            text-transform: uppercase;
        }

        /* Accordion Details Box */
        .m-full-details {
            display: none; 
            background: #04091a;
            border: 1px solid var(--border); 
            border-radius: 10px; 
            padding: 10px; 
            margin-top: 4px;
        }

        .details-title {
            font-size: 10px; 
            font-weight: 800; 
            color: var(--accent-light);
            text-transform: uppercase; 
            margin-bottom: 8px; 
            display: flex;
            align-items: center; 
            gap: 5px; 
            border-bottom: 1px dashed var(--border); 
            padding-bottom: 4px;
        }

        .d-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
        .d-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(130,170,255,0.08); border-radius: 6px; padding: 6px; }
        .d-item label { display: block; font-size: 9px; font-weight: 700; color: var(--muted); text-transform: uppercase; margin-bottom: 1px; }
        .d-item span { font-size: 11px; font-weight: 700; color: #fff; word-break: break-word; }

        .dots-btn { color: var(--muted); cursor: pointer; padding: 6px 8px; font-size: 16px; }
        .dropdown-menu {
            position: absolute; 
            right: 10px; 
            top: 38px; 
            width: 170px; 
            background: #09122a;
            border: 1px solid var(--border); 
            border-radius: 10px; 
            display: none; 
            flex-direction: column;
            z-index: 100; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.9);
        }
        .dropdown-menu a { 
            padding: 9px 12px; 
            font-size: 11px; 
            text-decoration: none; 
            color: var(--text); 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            border-bottom: 1px solid var(--border); 
        }

        .pending-float {
            position: fixed; 
            bottom: 14px; 
            left: 50%; 
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.95); 
            border: 1px solid #fbbf24; 
            padding: 8px 16px; 
            border-radius: 50px;
            display: flex; 
            align-items: center; 
            gap: 12px; 
            z-index: 999; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.6);
        }
        .p-text { color: #fbbf24; font-weight: 800; font-size: 11px; }
        .p-btn { background: #fbbf24; color: #000; text-decoration: none; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 800; }

        /* Responsive Mobile Layout Fixes */
        @media (max-width: 600px) {
            .division-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .div-card { padding: 10px 4px; font-size: 11px; }
            .m-row-main { flex-direction: column; align-items: flex-start; gap: 8px; }
            .m-info-left { width: 100%; }
            .m-action-btns { width: 100%; justify-content: flex-start; flex-wrap: wrap; }
            .btn-view, .btn-submit-approve, .btn-submit-remove { flex: 1; min-height: 38px; justify-content: center; text-align: center; }
            .d-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="container">

    <!-- Header Search Bar & Navigation Buttons -->
    <div class="top-bar">
        <form class="search-form" method="GET">
            <?php if ($view_mode === 'pending'): ?>
                <input type="hidden" name="view" value="pending">
            <?php endif; ?>
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search Name, ID, Contact...">
            </div>
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i>
            </button>
        </form>
        <a href="dashboard.php" class="btn-home"><i class="fas fa-house"></i> Home</a>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'approved'): ?>
        <div class="alert-box alert-success">
            <i class="fas fa-check-circle"></i> Member registration approved successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'removed'): ?>
        <div class="alert-box alert-danger">
            <i class="fas fa-trash-alt"></i> Member request removed permanently.
        </div>
    <?php endif; ?>

    <!-- ALL MEMBERS DIRECTORY BUTTON -->
    <a href="members.php" class="all-members-btn <?= ($selected_div === '' && $search === '' && $view_mode !== 'pending') ? 'active' : '' ?>">
        <i class="fas fa-users"></i> ALL MEMBERS DIRECTORY
    </a>

    <!-- 8 Divisions Grid Selection -->
    <div class="sec-header">
        <span><i class="fas fa-layer-group"></i> Select Division</span>
        <?php if ($selected_div !== '' || $search !== ''): ?>
            <a href="members.php"><i class="fas fa-rotate-left"></i> Reset Selection</a>
        <?php endif; ?>
    </div>

    <div class="division-grid">
        <?php foreach ($districts as $divName => $dists): ?>
            <a href="?division=<?= urlencode($divName) ?>" class="div-card <?= ($selected_div === $divName) ? 'active' : '' ?>">
                <i class="fas fa-map-location-dot"></i>
                <span><?= htmlspecialchars($divName) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Dynamic District Chips Selection -->
    <?php if ($selected_div !== ''): ?>
        <div class="district-box">
            <div class="sec-header" style="margin-bottom:0;">
                <span><i class="fas fa-location-dot"></i> Districts in <?= htmlspecialchars($selected_div) ?></span>
            </div>
            <div class="district-grid">
                <a href="?division=<?= urlencode($selected_div) ?>" class="dist-chip <?= ($selected_dist === "") ? 'active' : '' ?>">
                    All Units
                </a>
                <?php foreach ($districts[$selected_div] as $dName): ?>
                    <a href="?division=<?= urlencode($selected_div) ?>&district=<?= urlencode($dName) ?>" class="dist-chip <?= ($selected_dist === $dName) ? 'active' : '' ?>">
                        <?= htmlspecialchars($dName) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Member Directory List Header -->
    <div class="sec-header">
        <span>
            <?php if ($view_mode === 'pending'): ?>
                Pending Approval Requests
            <?php elseif ($search !== ''): ?>
                Search Results
            <?php elseif ($selected_dist !== ''): ?>
                Personnel in <?= htmlspecialchars($selected_dist) ?>
            <?php elseif ($selected_div !== ''): ?>
                Personnel in <?= htmlspecialchars($selected_div) ?>
            <?php else: ?>
                All Registered Personnel
            <?php endif; ?>
        </span>
        <span style="color:var(--muted);"><?= mysqli_num_rows($res) ?> Found</span>
    </div>

    <!-- Member Directory Cards -->
    <div class="member-list">
        <?php if (mysqli_num_rows($res) === 0): ?>
            <div style="text-align: center; padding: 40px 10px; color: var(--muted); background: var(--card-bg); border-radius: 14px; border:1px solid var(--border);">
                <i class="fas fa-user-slash" style="font-size: 26px; margin-bottom: 6px; color:var(--accent-light);"></i>
                <p style="font-weight: 700; font-size: 12px;">No personnel found for this selection.</p>
            </div>
        <?php else: ?>
            <?php while ($u = mysqli_fetch_assoc($res)):
                $uRole = strtolower($u['role'] ?? 'member');
                $initial = mb_strtoupper(mb_substr($u['full_name'] ?? 'U', 0, 1));
                $uId = (int) $u['id'];
            ?>
                <div class="member-card">
                    <!-- Row 1: Profile Avatar, Name, ID & Form Action Buttons -->
                    <div class="m-row-main">
                        <div class="m-info-left" onclick="toggleDetails('det-<?= $uId ?>')">
                            <?php if (!empty($u['photo'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($u['photo']) ?>" class="m-avatar" alt="Photo" onerror="this.src='https://cdn-icons-png.flaticon.com/512/6522/6522516.png'">
                            <?php else: ?>
                                <div class="m-avatar"><?= htmlspecialchars($initial) ?></div>
                            <?php endif; ?>

                            <div class="m-details">
                                <div class="m-name"><?= htmlspecialchars($u['full_name'] ?? 'Unknown') ?></div>
                                <div class="m-sub"><i class="fas fa-id-card"></i> ID: #<?= htmlspecialchars($u['batch_id'] ?? 'N/A') ?></div>
                            </div>
                        </div>

                        <div class="m-action-btns">
                            <!-- Full Details Toggle Button -->
                            <button type="button" class="btn-view" onclick="toggleDetails('det-<?= $uId ?>')">
                                <i class="fas fa-address-card"></i> Details
                            </button>

                            <?php if ($view_mode === 'pending' && $is_admin): ?>
                                <!-- Approve Form Submit Button -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve and activate member account?');">
                                    <input type="hidden" name="action_type" value="approve">
                                    <input type="hidden" name="target_user_id" value="<?= $uId ?>">
                                    <button type="submit" class="btn-submit-approve">
                                        <i class="fas fa-user-check"></i> Approve
                                    </button>
                                </form>

                                <!-- Remove Form Submit Button -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete registration request?');">
                                    <input type="hidden" name="action_type" value="remove">
                                    <input type="hidden" name="target_user_id" value="<?= $uId ?>">
                                    <button type="submit" class="btn-submit-remove">
                                        <i class="fas fa-user-minus"></i> Remove
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="member_details.php?id=<?= $uId ?>" class="btn-view">
                                    Profile <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Superadmin Role Menu -->
                            <?php if ($is_superadmin && $view_mode !== 'pending'): ?>
                                <div class="dots-btn" onclick="toggleM(event, 'u-<?= $uId ?>')">
                                    <i class="fas fa-ellipsis-v"></i>
                                </div>
                                <div id="u-<?= $uId ?>" class="dropdown-menu">
                                    <?php if ($uRole !== 'superadmin'): ?>
                                        <a href="update_role.php?id=<?= $uId ?>&role=superadmin" onclick="return confirm('Promote to Super Admin?');">
                                            <i class="fas fa-crown" style="color:#af52de;"></i> Super Admin
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($uRole !== 'admin'): ?>
                                        <a href="update_role.php?id=<?= $uId ?>&role=admin" onclick="return confirm('Make Admin?');">
                                            <i class="fas fa-shield-halved" style="color:var(--accent-light);"></i> Admin
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($uRole !== 'member'): ?>
                                        <a href="update_role.php?id=<?= $uId ?>&role=member" onclick="return confirm('Demote to Member?');">
                                            <i class="fas fa-user"></i> Member
                                        </a>
                                    <?php endif; ?>
                                    <form method="POST" style="display:block;">
                                        <input type="hidden" name="action_type" value="remove">
                                        <input type="hidden" name="target_user_id" value="<?= $uId ?>">
                                        <button type="submit" style="background:none; border:none; color:#ff4d4d; padding:9px 12px; font-size:11px; font-weight:700; width:100%; text-align:left; cursor:pointer;" onclick="return confirm('Delete user account permanently?');">
                                            <i class="fas fa-trash-alt"></i> Delete Account
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Row 2: Contact Info -->
                    <div class="m-extra-info">
                        <div><i class="fas fa-phone"></i> <?= htmlspecialchars($u['mobile_number'] ?? $u['phone'] ?? 'N/A') ?></div>
                        <div><i class="fas fa-map-pin"></i> <?= htmlspecialchars($u['district'] ?? 'Unknown') ?></div>
                        <div><span class="role-badge"><?= htmlspecialchars($u['role'] ?: 'Member') ?></span></div>
                    </div>

                    <!-- Row 3: Expanded Full Details Box -->
                    <div id="det-<?= $uId ?>" class="m-full-details">
                        <div class="details-title">
                            <i class="fas fa-id-badge"></i> Member Submitted Details
                        </div>
                        <div class="d-grid">
                            <div class="d-item">
                                <label>Full Name</label>
                                <span><?= htmlspecialchars($u['full_name'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>Batch ID</label>
                                <span><?= htmlspecialchars($u['batch_id'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>BP Number</label>
                                <span><?= htmlspecialchars($u['bp_number'] ?? $u['bp'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>Mobile Number</label>
                                <span><?= htmlspecialchars($u['mobile_number'] ?? $u['phone'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>Gender</label>
                                <span><?= htmlspecialchars($u['gender'] ?? $u['sex'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>Blood Group</label>
                                <span><?= htmlspecialchars($u['blood_group'] ?? $u['blood'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>Division</label>
                                <span><?= htmlspecialchars($u['division'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>District</label>
                                <span><?= htmlspecialchars($u['district'] ?? '—') ?></span>
                            </div>
                            <div class="d-item">
                                <label>Role</label>
                                <span><?= htmlspecialchars($u['role'] ?? 'Member') ?></span>
                            </div>
                            <div class="d-item">
                                <label>Account Status</label>
                                <span style="color: <?= ($u['status'] ?? '') === 'active' ? '#34d399' : '#fbbf24' ?>;">
                                    <?= ucfirst(htmlspecialchars($u['status'] ?? 'pending')) ?>
                                </span>
                            </div>
                            <?php if (!empty($u['created_at'])): ?>
                            <div class="d-item" style="grid-column: span 1;">
                                <label>Registration Date</label>
                                <span><?= date('d M Y', strtotime($u['created_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

</main>

<!-- Floating Admin Alert -->
<?php if ($is_admin && $p_count > 0 && $view_mode !== 'pending'): ?>
    <div class="pending-float">
        <span class="p-text"><i class="fas fa-user-clock"></i> <?= (int) $p_count ?> Pending Requests</span>
        <a href="?view=pending" class="p-btn">Manage</a>
    </div>
<?php endif; ?>

<script>
    function toggleDetails(id) {
        let el = document.getElementById(id);
        if (el) {
            let isVisible = window.getComputedStyle(el).display !== 'none';
            document.querySelectorAll('.m-full-details').forEach(d => {
                if (d.id !== id) d.style.display = 'none';
            });
            el.style.display = isVisible ? 'none' : 'block';
        }
    }

    function toggleM(e, id) {
        if (e && e.stopPropagation) e.stopPropagation();
        document.querySelectorAll('.dropdown-menu').forEach(d => { 
            if (d.id !== id) d.style.display = 'none'; 
        });
        let m = document.getElementById(id);
        if (m) {
            let isVisible = window.getComputedStyle(m).display !== 'none';
            m.style.display = isVisible ? 'none' : 'flex';
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-menu') && !e.target.closest('.dots-btn')) {
            document.querySelectorAll('.dropdown-menu').forEach(d => d.style.display = 'none');
        }
    });
</script>

</body>
</html>