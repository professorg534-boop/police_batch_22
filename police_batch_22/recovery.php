<?php
include 'db.php';

// ডাটাবেসে থাকা সব মেম্বার এবং এডমিনের লিস্ট দেখাবে
$sql = "SELECT full_name, batch_id, password, status FROM users";
$result = mysqli_query($conn, $sql);

echo "<body style='font-family: Arial; background: #f0f2f5; padding: 50px;'>";
echo "<div style='background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
echo "<h2>Member Login Recovery List</h2>";
echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #38bdf8; color: white;'>
        <th>Full Name</th>
        <th>Login ID (Batch ID)</th>
        <th>Password</th>
        <th>Account Status</th>
      </tr>";

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $color = ($row['status'] == 'active') ? 'green' : 'red';
        echo "<tr>
                <td style='padding: 10px;'>".$row['full_name']."</td>
                <td style='padding: 10px; font-weight: bold;'>".$row['batch_id']."</td>
                <td style='padding: 10px;'>".$row['password']."</td>
                <td style='padding: 10px; color: $color; font-weight: bold;'>".$row['status']."</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='4' style='text-align:center; padding: 20px;'>ডাটাবেসে কোনো মেম্বার খুঁজে পাওয়া যায়নি।</td></tr>";
}
echo "</table>";
echo "<br><a href='index.php' style='text-decoration:none; color: #38bdf8; font-weight: bold;'>← Go to Login Page</a>";
echo "</div></body>";
?>