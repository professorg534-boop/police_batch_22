<?php
session_start();
include '../db.php';

// Admin check logic
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id='$my_id'"));
$my_role = strtolower($me['role'] ?? 'member');

if (!in_array($my_role, ['admin', 'super admin', 'super_admin', 'superadmin'])) {
    header("Location: ../index.php"); exit();
}

// Fetching pending users with signup data
$sql = "SELECT * FROM users WHERE status='pending' ORDER BY id DESC";
$res = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Gate | Authorization Terminal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #050505; --card: #0e0e10; --accent: #6366f1; 
            --danger: #ff3b30; --success: #34c759; --border: rgba(255, 255, 255, 0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: #fff; padding-bottom: 50px; }

        header { 
            padding: 25px 50px; display: flex; justify-content: space-between; align-items: center;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 1000;
        }

        .container { max-width: 850px; margin: 40px auto; padding: 0 20px; }

        .request-card { 
            background: var(--card); border: 1px solid var(--border); border-radius: 35px; 
            padding: 40px; margin-bottom: 30px; transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative; overflow: hidden;
        }
        .request-card::before { content: ""; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent); opacity: 0.5; }
        .request-card:hover { border-color: var(--accent); transform: translateY(-5px); }

        .user-top { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; }
        .avatar-box { 
            width: 70px; height: 70px; border-radius: 22px; background: #111; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 28px; font-weight: 800; color: var(--accent); border: 1px solid var(--border); 
        }
        
        .user-meta h2 { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .user-meta .gender-tag { font-size: 10px; padding: 4px 12px; border-radius: 50px; background: rgba(255,255,255,0.05); color: #94a3b8; font-weight: 700; text-transform: uppercase; }

        /* GRID FOR PERSONNEL DETAILS */
        .details-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .info-box { background: rgba(255,255,255,0.02); padding: 18px 22px; border-radius: 18px; border: 1px solid var(--border); }
        .info-box label { display: block; font-size: 9px; color: var(--accent); font-weight: 800; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px; }
        .info-box p { font-size: 14px; color: #eee; font-weight: 600; }

        .btn-group { display: flex; gap: 15px; margin-top: 35px; }
        .btn { 
            flex: 1; padding: 18px; border-radius: 20px; border: none; 
            font-size: 14px; font-weight: 800; cursor: pointer; transition: 0.3s; 
            text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-acc { background: #fff; color: #000; }
        .btn-acc:hover { background: var(--success); color: #fff; box-shadow: 0 10px 20px rgba(52, 199, 89, 0.2); }
        
        .btn-rej { background: #151518; color: var(--danger); border: 1px solid rgba(255,59,48,0.2); }
        .btn-rej:hover { background: var(--danger); color: #fff; }

        .toast { 
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); 
            padding: 15px 35px; border-radius: 50px; font-weight: 800; 
            display: none; z-index: 2000; font-size: 13px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>

<div id="toast" class="toast"></div>

<header>
    <div style="display: flex; align-items: center; gap: 15px;">
        <i class="fas fa-shield-halved" style="color: var(--accent); font-size: 22px;"></i>
        <h1 style="font-size: 18px; font-weight: 800; letter-spacing: 1px;">PERSONNEL CLEARANCE</h1>
    </div>
    <a href="../members.php" style="color: #666; text-decoration: none; font-size: 13px; font-weight: 700;">RETURN <i class="fas fa-arrow-right"></i></a>
</header>

<div class="container">
    <?php while($u = mysqli_fetch_assoc($res)): ?>
        <div class="request-card" id="card-<?php echo $u['id']; ?>">
            
            <div class="user-top">
                <div class="avatar-box">
                    <?php if(($u['gender'] ?? '') == 'Female'): ?>
                        <i class="fas fa-venus"></i>
                    <?php else: ?>
                        <i class="fas fa-mars"></i>
                    <?php endif; ?>
                </div>
                <div class="user-meta">
                    <span class="gender-tag"><?php echo $u['gender'] ?? 'Personnel'; ?></span>
                    <h2><?php echo htmlspecialchars($u['full_name']); ?></h2>
                </div>
            </div>

            <div class="details-grid">
                <div class="info-box">
                    <label>BP Number</label>
                    <p><?php echo htmlspecialchars($u['bp_number'] ?? 'N/A'); ?></p>
                </div>
                <div class="info-box">
                    <label>Batch ID</label>
                    <p><?php echo htmlspecialchars($u['batch_id']); ?></p>
                </div>
                <div class="info-box">
                    <label>Mobile Number</label>
                    <p><?php echo htmlspecialchars($u['mobile_number'] ?? $u['phone'] ?? 'N/A'); ?></p>
                </div>
                <div class="info-box">
                    <label>Division</label>
                    <p><?php echo htmlspecialchars($u['division']); ?></p>
                </div>
                <div class="info-box">
                    <label>Home District</label>
                    <p><?php echo htmlspecialchars($u['district']); ?></p>
                </div>
                <div class="info-box">
                    <label>Access Role</label>
                    <p>BATCH 22 MEMBER</p>
                </div>
            </div>

            <div class="btn-group">
                <button onclick="process('approve', <?php echo $u['id']; ?>)" class="btn btn-acc">
                    <i class="fas fa-user-check"></i> AUTHORIZE JOIN
                </button>
                <button onclick="process('reject', <?php echo $u['id']; ?>)" class="btn btn-rej">
                    <i class="fas fa-user-xmark"></i> DENY ACCESS
                </button>
            </div>
        </div>
    <?php endwhile; ?>

    <?php if(mysqli_num_rows($res) == 0): ?>
        <div style="text-align: center; margin-top: 150px; opacity: 0.1;">
            <i class="fas fa-user-shield" style="font-size: 80px; margin-bottom: 20px;"></i>
            <h3 style="letter-spacing: 3px;">GATE SECURED</h3>
        </div>
    <?php endif; ?>
</div>

<script>
    function process(type, id) {
        // ১. ব্যাকগ্রাউন্ডে ডেটাবেস আপডেট হবে (process.php এর মাধ্যমে)
        fetch(`../process.php?action=${type}&id=${id}`)
        .then(() => {
            const t = document.getElementById('toast');
            const c = document.getElementById(`card-${id}`);
            
            // ২. সাকসেস মেসেজ (Toast) দেখানো
            t.innerText = type === 'approve' ? 'IDENTITY AUTHORIZED' : 'REQUEST REMOVED';
            t.style.background = type === 'approve' ? '#34c759' : '#ff3b30';
            t.style.display = 'block';
            
            // ৩. কার্ডটি এনিমেশন দিয়ে ছোট হয়ে মুছে যাবে
            c.style.transition = "all 0.5s cubic-bezier(0.4, 0, 0.2, 1)";
            c.style.opacity = '0';
            c.style.transform = 'scale(0.9) translateX(-20px)';
            c.style.marginBottom = '0px';
            c.style.paddingTop = '0px';
            c.style.paddingBottom = '0px';
            c.style.height = '0px';
            c.style.overflow = 'hidden';
            c.style.border = 'none';

            // ৪. ৫ মিলি-সেকেন্ড পর কার্ডটি ডম (DOM) থেকে রিমুভ হবে
            setTimeout(() => { 
                c.remove(); 
                
                // যদি আর কোন পেন্ডিং কার্ড না থাকে, তবেই কেবল পেজ রিলোড হবে
                if(document.querySelectorAll('.request-card').length === 0) {
                    location.reload(); 
                }
            }, 500);
            
            // ৫. ২ সেকেন্ড পর টোস্ট মেসেজ গায়েব হবে
            setTimeout(() => { t.style.display = 'none'; }, 2000);
        })
        .catch(err => {
            alert("Error connecting to server.");
        });
    }
</script>
</body>
</html>