<?php
session_start();
include 'db.php';

// জেলা অ্যাডমিন লগইন করা আছে কি না চেক করা
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'district_admin') {
    die("অ্যাক্সেস ডিনাইড! শুধুমাত্র জেলা অ্যাডমিন এটি দেখতে পারবেন।");
}

$admin_district = $_SESSION['admin_district']; // অ্যাডমিনের নিজের জেলা

// মেম্বার কনফার্ম করার লজিক
if (isset($_GET['approve_id'])) {
    $id = $_GET['approve_id'];
    mysqli_query($conn, "UPDATE users SET status = 'active' WHERE id = '$id'");
    echo "<script>alert('Member Confirmed!'); window.location='district_admin.php';</script>";
}

$query = "SELECT * FROM users WHERE status = 'pending' AND district LIKE '%$admin_district%'";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Jela Admin Panel</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h2 { color: #1e293b; border-bottom: 2px solid #38bdf8; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8fafc; }
        .approve-btn { background: #10b981; color: white; padding: 5px 10px; text-decoration: none; border-radius: 5px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Join Requests: <?php echo $admin_district; ?></h2>
        <table>
            <tr>
                <th>Name</th>
                <th>BP Number</th>
                <th>Batch ID</th>
                <th>Action</th>
            </tr>
            <?php while($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?php echo $row['full_name']; ?></td>
                <td><?php echo $row['bp_number']; ?></td>
                <td><?php echo $row['batch_id']; ?></td>
                <td><a href="?approve_id=<?php echo $row['id']; ?>" class="approve-btn">Confirm Member</a></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</body>
</html>