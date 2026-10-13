<?php
include 'db.php';
session_start();

// লগইন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_district = $_SESSION['district'];

// ১. প্রথমে ওই জেলার এডমিনদের তথ্য আনা (যাতে সবার উপরে থাকে)
$admin_query = "SELECT * FROM users WHERE district = '$user_district' AND user_role = 'district_admin'";
$admin_result = mysqli_query($conn, $admin_query);

// ২. তারপর ওই জেলার সাধারণ মেম্বারদের তথ্য আনা
$member_query = "SELECT * FROM users WHERE district = '$user_district' AND user_role = 'member' ORDER BY full_name ASC";
$member_result = mysqli_query($conn, $member_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>District Members | BP Batch 2022</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; margin: 0; }
        .container { max-width: 1000px; margin: 30px auto; padding: 20px; }
        
        .district-header { 
            background: #1e272e; color: white; padding: 20px; 
            border-radius: 10px; text-align: center; margin-bottom: 30px;
        }

        .admin-section { margin-bottom: 30px; }
        .admin-card { 
            background: #ffffff; border-left: 5px solid #1e90ff; 
            padding: 15px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 10px;
        }
        .admin-badge { 
            background: #1e90ff; color: white; padding: 4px 12px; 
            border-radius: 20px; font-size: 12px; font-weight: bold;
        }

        .member-list-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h3 { color: #2f3542; border-bottom: 2px solid #eee; padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #f1f1f1; }
        th { color: #888; font-size: 13px; text-transform: uppercase; }
        
        .batch-id { font-family: monospace; color: #555; background: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
        .back-btn { text-decoration: none; color: #1e90ff; font-weight: bold; display: inline-block; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    
    <div class="district-header">
        <h1>District: <?php echo $user_district; ?></h1>
        <p>Total Members Found: <?php echo (mysqli_num_rows($admin_result) + mysqli_num_rows($member_result)); ?></p>
    </div>

    <div class="admin-section">
        <h3>District Administrator</h3>
        <?php if(mysqli_num_rows($admin_result) > 0): ?>
            <?php while($admin = mysqli_fetch_assoc($admin_result)): ?>
                <div class="admin-card">
                    <div>
                        <strong><?php echo $admin['full_name']; ?></strong><br>
                        <span class="batch-id"><?php echo $admin['batch_id']; ?></span>
                    </div>
                    <span class="admin-badge">DISTRICT ADMIN</span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #999;">No admin assigned for this district yet.</p>
        <?php endif; ?>
    </div>

    <div class="member-list-card">
        <h3>Member Directory</h3>
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Batch ID</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($member = mysqli_fetch_assoc($member_result)): ?>
                <tr>
                    <td><strong><?php echo $member['full_name']; ?></strong></td>
                    <td><span class="batch-id"><?php echo $member['batch_id']; ?></span></td>
                    <td>Member</td>
                    <td><button style="cursor:pointer; border:1px solid #ddd; background:white; padding:5px 10px; border-radius:4px;">View Profile</button></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>