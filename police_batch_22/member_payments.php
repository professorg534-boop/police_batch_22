<?php
session_start();
include 'db.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? 'member';

/** * সংশোধিত কুয়েরি: অ্যাডমিন টেবিলের সাথে সঠিক JOIN নিশ্চিত করা হয়েছে।
 * আপনার ডাটাবেসে যদি approved_by কলামে অ্যাডমিন আইডি থাকে তবে সেটি কাজ করবে।
 */
$query = "SELECT p.*, 
          u_admin.full_name as admin_name, 
          u_admin.role as admin_role
          FROM payment_requests p 
          LEFT JOIN users u_admin ON (p.approved_by = u_admin.id OR p.admin_id = u_admin.id)
          WHERE p.user_id = '$current_user_id' 
          ORDER BY p.id DESC"; 

$result = mysqli_query($conn, $query);
$count = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Ledger | Elite Audit</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&family=Space+Grotesk:wght@500;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { 
            --bg: #03040a; --surface: #0a0b14; --accent: #6366f1; 
            --success: #00ffa3; --error: #ff2d55; --warning: #ffb400; 
            --glass: rgba(255, 255, 255, 0.02); --border: rgba(255, 255, 255, 0.06); 
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: var(--bg); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; 
            min-height: 100vh; padding: 40px 20px;
        }

        .container { max-width: 900px; margin: auto; }

        .audit-card { 
            background: var(--surface); border: 1px solid var(--border); border-radius: 24px; 
            margin-bottom: 30px; position: relative; overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }

        /* Admin Info Section - Highlighted */
        .admin-box {
            background: rgba(99, 102, 241, 0.05);
            border-left: 3px solid var(--accent);
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .label { font-size: 10px; font-weight: 800; color: #555; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; display: block; }
        .data-val { font-size: 13px; font-weight: 700; color: #eee; }

        .ledger-content { padding: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }

        .amt-tag { font-family: 'Space Grotesk'; font-size: 32px; color: var(--success); font-weight: 700; }
        
        .month-pill { 
            display: inline-block; padding: 5px 12px; border-radius: 8px; background: var(--glass);
            border: 1px solid var(--border); color: var(--accent); font-size: 11px; font-weight: 800; margin: 3px;
        }

        .status-badge { 
            padding: 6px 15px; border-radius: 10px; font-size: 10px; font-weight: 900; 
            text-transform: uppercase; letter-spacing: 1px;
        }
        .approved { background: rgba(0, 255, 163, 0.1); color: var(--success); border: 1px solid var(--success); }
        .pending { background: rgba(255, 180, 0, 0.1); color: var(--warning); border: 1px solid var(--warning); }
        .rejected { background: rgba(255, 45, 85, 0.1); color: var(--error); border: 1px solid var(--error); }

        .proof-section { background: #070810; border-radius: 20px; padding: 20px; border: 1px solid var(--border); }
        .trx-code { font-family: 'JetBrains Mono'; color: var(--accent); font-size: 13px; }

        @media (max-width: 600px) { .ledger-content { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <h1 style="font-family: 'Space Grotesk'; font-size: 30px; margin-bottom: 40px; text-align: center;">Transaction <span style="color: var(--accent);">Intelligence</span></h1>

    <?php if($count > 0): ?>
        <?php while($row = mysqli_fetch_assoc($result)): 
            $status = strtolower($row['status']);
            $months = explode(',', $row['pay_month']);
            $can_view = ($current_user_id == $row['user_id'] || $current_role == 'admin' || $current_role == 'super_admin');
        ?>
        <div class="audit-card">
            <div class="admin-box">
                <div>
                    <span class="label">Verification Status</span>
                    <span class="status-badge <?php echo $status; ?>"><?php echo $status; ?></span>
                </div>
                <div style="text-align: right;">
                    <span class="label">Processed By</span>
                    <span class="data-val" style="color: var(--accent);">
                        <?php 
                        if($status != 'pending') {
                            echo '<i class="fas fa-user-shield"></i> ' . htmlspecialchars($row['admin_name'] ?? 'System Agent');
                        } else {
                            echo '<i class="fas fa-hourglass-half"></i> In Queue';
                        }
                        ?>
                    </span>
                    <p style="font-size: 10px; color: #444; font-weight: 700; margin-top: 2px;">
                        <?php 
                        // অ্যাডমিন এক্সেপ্ট করার সঠিক সময়
                        if($status != 'pending' && !empty($row['updated_at'])) {
                            echo date('d M, Y | h:i A', strtotime($row['updated_at']));
                        }
                        ?>
                    </p>
                </div>
            </div>

            <div class="ledger-content">
                <div>
                    <span class="label">Payment Initiated</span>
                    <span class="data-val"><?php echo date('d F, Y', strtotime($row['created_at'])); ?></span>
                    <span style="font-size: 11px; color: #444; font-weight: 700;"><?php echo date('h:i:s A', strtotime($row['created_at'])); ?></span>

                    <div style="margin-top: 25px;">
                        <span class="label">Amount Paid</span>
                        <div class="amt-tag">৳<?php echo number_format($row['amount']); ?></div>
                    </div>

                    <div style="margin-top: 20px;">
                        <span class="label">Billing Cycle (<?php echo $row['pay_year']; ?>)</span>
                        <?php foreach($months as $m): ?>
                            <span class="month-pill"><?php echo trim($m); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="proof-section">
                    <?php if($can_view): ?>
                        <span class="label">Transaction Receipt</span>
                        <div style="margin-top: 10px; display: flex; justify-content: space-between;">
                            <div>
                                <span style="font-size: 9px; color: #333; font-weight: 800;">METHOD</span>
                                <p style="font-weight: 800; font-size: 12px;"><?php echo strtoupper($row['method']); ?></p>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 9px; color: #333; font-weight: 800;">TRX ID</span>
                                <p class="trx-code"><?php echo $row['trxid']; ?></p>
                            </div>
                        </div>

                        <?php if(!empty($row['screenshot'])): ?>
                            <a href="uploads/<?php echo $row['screenshot']; ?>" target="_blank">
                                <div style="margin-top: 15px; width: 100%; height: 100px; background: url('uploads/<?php echo $row['screenshot']; ?>') center/cover; border-radius: 12px; border: 1px solid var(--border);"></div>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px 0; color: var(--error);">
                            <i class="fas fa-lock" style="font-size: 20px;"></i>
                            <p style="font-size: 10px; font-weight: 800; margin-top: 10px;">ENCRYPTED DATA</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 100px; opacity: 0.2;">
            <i class="fas fa-folder-open" style="font-size: 50px; margin-bottom: 20px;"></i>
            <h3>No Records Found</h3>
        </div>
    <?php endif; ?>
</div>

</body>
</html>