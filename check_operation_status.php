<?php
include '../db.php';

$op_query = "SELECT id, total_required, collected_amount, briefing FROM fund_operations WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
$result = mysqli_query($conn, $op_query);

if ($row = mysqli_fetch_assoc($result)) {
    $progress = ($row['collected_amount'] / $row['total_required']) * 100;
    $row['progress_percent'] = round($progress, 2);
    echo json_encode(['active' => true, 'data' => $row]);
} else {
    echo json_encode(['active' => false]);
}
?>