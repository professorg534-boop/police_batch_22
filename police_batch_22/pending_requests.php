<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// অ্যাপ্রুভ বা রিজেক্ট লজিক
if(isset($_GET['action']) && isset($_GET['id'])){
    $id = $_GET['id'];
    $status = ($_GET['action'] == 'approve') ? 'active' : 'rejected';
    mysqli_query($conn, "UPDATE users SET status='$status' WHERE id='$id'");
    header("Location: pending_requests.php");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Approvals | Personnel HQ</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* আপনার members.php এর CSS এখানে কপি করে বসান */
        :root { --primary: #6366f1; --bg: #020617; --surface: rgba(15, 23, 42, 0.7); --border: rgba(255, 255, 255, 0.08); }
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; color: #fff; padding: 40px; }
        .req-card { background: var(--surface); border: 1px solid var(--border); padding: 25px; border-radius: 24px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .btn-approve { background: #10b981; color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 12px; margin-left: 10px; }
        .btn-reject { background: #f43f5e; color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 12px; }
    </style>
</head>
<body>

    <div style="max-width: 800px; margin: 0 auto;">
        <a href="members.php" style="color: var(--primary); text-decoration: none; font-weight: 800; font-size: 12px;"><i class="fas fa-arrow-left"></i> BACK TO DIRECTORY</a>
        <h1 style="margin: 20px 0 40px; font-weight: 800; letter-spacing: -1px;">PENDING REGISTRATIONS</h1>

        <?php
        $res = mysqli_query($conn, "SELECT * FROM users WHERE status='pending' ORDER BY id DESC");
        if(mysqli_num_rows($res) > 0){
            while($u = mysqli_fetch_assoc($res)){
                ?>
                <div class="req-card">
                    <div>
                        <h3 style="margin-bottom: 5px;"><?php echo $u['full_name']; ?></h3>
                        <p style="font-size: 12px; color: #94a3b8;">
                            BP: <?php echo $u['bp_number']; ?> | ID: <?php echo $u['batch_id']; ?> | Ph: <?php echo $u['mobile_number']; ?>
                        </p>
                    </div>
                    <div>
                        <a href="?action=reject&id=<?php echo $u['id']; ?>" class="btn-reject">REJECT</a>
                        <a href="?action=approve&id=<?php echo $u['id']; ?>" class="btn-approve">APPROVE</a>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<p style='text-align:center; opacity:0.5;'>No pending requests found.</p>";
        }
        ?>
    </div>

</body>
</html>