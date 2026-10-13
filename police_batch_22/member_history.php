<?php
include 'db.php';
session_start();

// ইউজার লগইন করা আছে কিনা চেক করা
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$district = $_SESSION['district'];

// পেমেন্ট রিকোয়েস্ট প্রসেস করা
if (isset($_POST['send_request'])) {
    $month = $_POST['month_name'];
    
    // চেক করা হচ্ছে আগে থেকেই এই মাসের রিকোয়েস্ট আছে কিনা
    $check_sql = "SELECT * FROM payments WHERE user_id = '$user_id' AND month_name = '$month'";
    $check_res = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_res) == 0) {
        $insert_sql = "INSERT INTO payments (user_id, month_name, amount, status) VALUES ('$user_id', '$month', 50, 'pending')";
        mysqli_query($conn, $insert_sql);
        $msg = "<p style='color:green;'>Request sent for $month!</p>";
    } else {
        $msg = "<p style='color:red;'>Request already exists for $month!</p>";
    }
}

// ইউজারের পেমেন্ট ডাটা নিয়ে আসা
$payment_query = "SELECT * FROM payments WHERE user_id = '$user_id' ORDER BY id DESC";
$payment_result = mysqli_query($conn, $payment_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History | BP Batch 2022</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; margin: 0; display: flex; }
        .sidebar { width: 250px; background: #1e272e; height: 100vh; color: white; padding: 20px; position: fixed; }
        .main { margin-left: 270px; padding: 30px; width: 100%; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h2 { color: #2f3542; margin-top: 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; color: #555; text-transform: uppercase; font-size: 13px; }
        
        .status-pending { background: #fff4e5; color: #ff9800; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .status-approved { background: #e6fffa; color: #38b2ac; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        
        .request-section { display: flex; gap: 10px; align-items: center; margin-top: 15px; }
        select, button { padding: 10px; border-radius: 5px; border: 1px solid #ccc; }
        .btn-send { background: #1e90ff; color: white; border: none; cursor: pointer; font-weight: bold; }
        .btn-send:hover { background: #0077ed; }
        .logout-link { color: #ff4757; text-decoration: none; display: block; margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>BP Batch 2022</h3>
    <p style="font-size: 13px; color: #aaa;">District: <?php echo $district; ?></p>
    <hr>
    <a href="dashboard.php" style="color:white; text-decoration:none; display:block; margin-bottom:15px;">Dashboard</a>
    <a href="member_history.php" style="color:#1e90ff; text-decoration:none; display:block; margin-bottom:15px;">Payment History</a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="main">
    <div class="card">
        <h2>Submit Payment Request</h2>
        <p style="font-size: 14px; color: #666;">Select the running month and send a request to your District Admin after paying 50 BDT.</p>
        
        <?php if(isset($msg)) echo $msg; ?>

        <form method="POST" class="request-section">
            <select name="month_name" required>
                <option value="">-- Select Month --</option>
                <option value="January">January</option>
                <option value="February">February</option>
                <option value="March">March</option>
                <option value="April">April</option>
                <option value="May">May</option>
                <option value="June">June</option>
                <option value="July">July</option>
                <option value="August">August</option>
                <option value="September">September</option>
                <option value="October">October</option>
                <option value="November">November</option>
                <option value="December">December</option>
            </select>
            <button type="submit" name="send_request" class="btn-send">Send Approval Request</button>
        </form>
    </div>

    <div class="card">
        <h2>My Payment History</h2>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($payment_result)) { ?>
                <tr>
                    <td><strong><?php echo $row['month_name']; ?></strong></td>
                    <td>50.00 BDT</td>
                    <td><?php echo date('d M, Y', strtotime($row['payment_date'])); ?></td>
                    <td>
                        <span class="<?php echo ($row['status'] == 'pending') ? 'status-pending' : 'status-approved'; ?>">
                            <?php echo strtoupper($row['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>