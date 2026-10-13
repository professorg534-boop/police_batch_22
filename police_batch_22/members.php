<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id='$my_id'"));
$my_role = strtolower($me['role'] ?? 'member');
$is_admin = (in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin']));

$selected_div = $_GET['division'] ?? "Dhaka";
$selected_dist = $_GET['district'] ?? "";
$view_mode = $_GET['view'] ?? 'active';

// Pending Count
$p_count = 0;
if($is_admin) {
    $p_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status='pending'");
    $p_count = ($p_res) ? mysqli_fetch_assoc($p_res)['total'] : 0;
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Intel | Directory</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #050505; --card: #0e0e10; --accent: #007AFF; 
            --border: rgba(255, 255, 255, 0.08); --text-muted: #666;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: #fff; height: 100vh; display: flex; overflow: hidden; }

        /* --- UPDATED SLEEK SIDEBAR --- */
        .sidebar { 
            width: 260px; background: #000; border-right: 1px solid var(--border); 
            display: flex; flex-direction: column; padding: 25px 15px;
        }
        .brand { padding: 10px 15px; margin-bottom: 30px; font-size: 20px; font-weight: 800; color: var(--accent); display: flex; align-items: center; gap: 10px; }
        
        .side-label { font-size: 10px; font-weight: 800; color: #333; letter-spacing: 1.5px; padding: 10px 15px; text-transform: uppercase; }
        
        .nav-group { flex: 1; overflow-y: auto; scrollbar-width: none; margin-bottom: 20px; }
        .nav-group::-webkit-scrollbar { display: none; }

        .nav-item { 
            display: flex; align-items: center; gap: 12px; padding: 12px 15px; 
            border-radius: 12px; color: var(--text-muted); text-decoration: none; 
            font-size: 14px; font-weight: 600; transition: 0.3s; margin-bottom: 4px;
        }
        .nav-item i { font-size: 16px; width: 20px; text-align: center; }
        .nav-item:hover, .nav-item.active { background: #111; color: #fff; }
        .nav-item.active { border: 1px solid var(--border); color: var(--accent); }

        .side-footer { border-top: 1px solid var(--border); padding-top: 15px; }

        /* --- Main Content Area --- */
        .main { flex: 1; display: flex; flex-direction: column; position: relative; background: radial-gradient(circle at top right, #111, #050505); }
        
        header { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.4); backdrop-filter: blur(20px); }
        .search-wrap { position: relative; width: 400px; }
        .search-wrap input { width: 100%; background: #111; border: 1px solid var(--border); padding: 12px 20px 12px 50px; border-radius: 15px; color: #fff; outline: none; font-weight: 600; font-size: 14px; }
        .search-wrap i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--accent); }

        .dist-scroller { padding: 12px 40px; display: flex; gap: 10px; overflow-x: auto; scrollbar-width: none; border-bottom: 1px solid var(--border); }
        .chip { padding: 8px 18px; border-radius: 50px; background: #111; color: var(--text-muted); text-decoration: none; font-size: 12px; font-weight: 700; border: 1px solid var(--border); white-space: nowrap; transition: 0.3s; }
        .chip.active { background: #fff; color: #000; border-color: #fff; }

        /* --- Cards & Grid --- */
        .content { padding: 35px 40px; overflow-y: auto; flex: 1; padding-bottom: 100px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 25px; }

        .card { 
            background: var(--card); border: 1px solid var(--border); border-radius: 30px; padding: 35px; 
            position: relative; transition: 0.4s cubic-bezier(0.19, 1, 0.22, 1);
        }
        .card:hover { transform: translateY(-8px); border-color: var(--accent); box-shadow: 0 30px 60px rgba(0,0,0,0.5); }

        .avatar { width: 65px; height: 65px; border-radius: 20px; background: linear-gradient(135deg, #1a1a1c, #050505); display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 800; color: var(--accent); border: 1px solid var(--border); margin-bottom: 20px; }

        .role-badge { font-size: 9px; font-weight: 800; padding: 5px 14px; border-radius: 50px; text-transform: uppercase; letter-spacing: 1px; background: rgba(0,122,255,0.1); color: var(--accent); margin-bottom: 12px; display: inline-block; }
        .role-super { background: rgba(175, 82, 222, 0.1); color: #af52de; }

        .user-name { font-size: 24px; font-weight: 800; margin-bottom: 15px; letter-spacing: -0.5px; }
        
        .info-box { margin-bottom: 25px; }
        .info-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; font-size: 13px; color: #777; }
        .info-row i { color: var(--accent); width: 18px; }
        .info-row b { color: #ccc; font-weight: 700; }

        .btn-action { display: block; width: 100%; padding: 16px; background: #fff; color: #000; text-decoration: none; text-align: center; border-radius: 18px; font-weight: 800; font-size: 14px; transition: 0.3s; }
        .btn-action:hover { background: var(--accent); color: #fff; }

        /* Dropdown */
        .dots { position: absolute; top: 35px; right: 30px; color: #333; cursor: pointer; font-size: 20px; transition: 0.3s; }
        .dots:hover { color: #fff; }
        .dropdown { position: absolute; right: 35px; top: 75px; width: 220px; background: #0c0c0e; border: 1px solid var(--border); border-radius: 18px; display: none; flex-direction: column; z-index: 1000; box-shadow: 0 30px 60px rgba(0,0,0,0.8); overflow: hidden; }
        .dropdown a { padding: 14px 20px; font-size: 13px; text-decoration: none; color: #eee; border-bottom: 1px solid var(--border); font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .dropdown a:hover { background: #161618; color: var(--accent); }

        /* Floating Pending */
        .pending-float { 
            position: absolute; bottom: 25px; left: 50%; transform: translateX(-50%);
            background: rgba(255, 59, 48, 0.1); backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 59, 48, 0.2); padding: 12px 30px; border-radius: 50px;
            display: flex; align-items: center; gap: 20px; z-index: 2000;
        }
        .p-text { color: #ff3b30; font-weight: 800; font-size: 13px; }
        .p-btn { background: #ff3b30; color: #fff; text-decoration: none; padding: 7px 18px; border-radius: 20px; font-size: 11px; font-weight: 800; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand"><i class="fas fa-cube"></i> ALPHA CORE</div>
        
        <div class="nav-group">
            <div class="side-label">Navigation</div>
            <a href="dashboard.php" class="nav-item"><i class="fas fa-house-user"></i> Dashboard</a>
            
            <div class="side-label" style="margin-top:20px;">Sectors</div>
            <?php foreach($districts as $div => $dists): ?>
                <a href="?division=<?php echo $div; ?>" class="nav-item <?php echo ($selected_div == $div) ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> <?php echo $div; ?>
                </a>
            <?php endforeach; ?>
        </div>

    </aside>

    <main class="main">
        <header>
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <form method="GET"><input type="text" name="search" placeholder="Search identities..."></form>
            </div>
            <div style="font-weight: 800; color: var(--accent); font-size: 14px;"><?php echo strtoupper($selected_div); ?> REGION</div>
        </header>

        <div class="dist-scroller">
            <a href="?division=<?php echo $selected_div; ?>" class="chip <?php echo ($selected_dist == "") ? 'active' : ''; ?>">All Units</a>
            <?php foreach($districts[$selected_div] as $d): ?>
                <a href="?division=<?php echo $selected_div; ?>&district=<?php echo $d; ?>" class="chip <?php echo ($selected_dist == $d) ? 'active' : ''; ?>"><?php echo $d; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="content">
            <div class="grid">
                <?php
                $f = $selected_dist ? "AND district='$selected_dist'" : "";
                $sql = ($view_mode == 'pending') ? "SELECT * FROM users WHERE status='pending'" : "SELECT * FROM users WHERE status='active' AND division='$selected_div' $f ORDER BY full_name ASC";
                $res = mysqli_query($conn, $sql);
                
                while($u = mysqli_fetch_assoc($res)): 
                    $role = strtolower($u['role'] ?? 'member');
                ?>
                    <div class="card">
                        <?php if($is_admin): ?>
                            <div class="dots" onclick="toggleM(event, 'u-<?php echo $u['id']; ?>')"><i class="fas fa-ellipsis-v"></i></div>
                            <div id="u-<?php echo $u['id']; ?>" class="dropdown">
                                <?php if($role != 'superadmin'): ?><a href="update_role.php?id=<?php echo $u['id']; ?>&role=superadmin"><i class="fas fa-crown"></i> Super Admin</a><?php endif; ?>
                                <?php if($role != 'admin'): ?><a href="update_role.php?id=<?php echo $u['id']; ?>&role=admin"><i class="fas fa-shield-alt"></i> Admin</a><?php endif; ?>
                                <?php if($role != 'member'): ?><a href="update_role.php?id=<?php echo $u['id']; ?>&role=member"><i class="fas fa-user"></i> Member</a><?php endif; ?>
                                <a href="delete_user.php?id=<?php echo $u['id']; ?>" style="color: #ff3b30;"><i class="fas fa-trash-alt"></i> Delete Access</a>
                            </div>
                        <?php endif; ?>

                        <div class="avatar"><?php echo substr($u['full_name'] ?? 'U', 0, 1); ?></div>
                        <span class="role-badge <?php echo (strpos($role, 'super') !== false) ? 'role-super' : ''; ?>"><?php echo $u['role'] ?: 'Member'; ?></span>
                        <h2 class="user-name"><?php echo htmlspecialchars($u['full_name'] ?? 'Unknown'); ?></h2>
                        
                        <div class="info-box">
                            <div class="info-row"><i class="fas fa-id-card"></i> ID: <b><?php echo $u['batch_id'] ?? 'N/A'; ?></b></div>
                            <div class="info-row"><i class="fas fa-map-pin"></i> Station: <b><?php echo $u['district'] ?? 'Unknown'; ?></b></div>
                            <div class="info-row"><i class="fas fa-phone-flip"></i> Contact: <b><?php echo $u['phone'] ?? 'Protected'; ?></b></div>
                        </div>

                        <?php if($view_mode == 'pending'): ?>
                            <a href="process.php?action=approve&id=<?php echo $u['id']; ?>" class="btn-action" style="background: var(--accent); color: #fff;">AUTHORIZE ACCESS</a>
                        <?php else: ?>
                            <a href="member_details.php?id=<?php echo $u['id']; ?>" class="btn-action">VIEW DOSSIER</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <?php if($is_admin && $p_count > 0 && $view_mode != 'pending'): ?>
    <div class="pending-float">
        <span class="p-text"><i class="fas fa-user-clock"></i> <?php echo $p_count; ?> NEW REQUESTS</span>
        
        <a href="admin/join_request.php" class="p-btn">MANAGE</a>
    </div>
<?php endif; ?>
    </main>

    <script>
        function toggleM(e, id) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown').forEach(d => { if(d.id !== id) d.style.display='none'; });
            let m = document.getElementById(id); m.style.display = (m.style.display === 'flex') ? 'none' : 'flex';
        }
        window.onclick = () => document.querySelectorAll('.dropdown').forEach(d => d.style.display='none');
    </script>
</body>
</html>