<?php
session_start();
include '../db.php'; // আপনার ডাটাবেস কানেকশন ফাইল

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['action'])) {
    
    // ১. ডাটা ক্লিনিং (সিকিউরিটির জন্য)
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $action = $_POST['action'];
    
    // ২. স্ট্যাটাস নির্ধারণ
    if ($action === 'approve') {
        $new_status = 'approved';
        $msg = "Payment Approved Successfully!";
    } else {
        $new_status = 'rejected';
        $msg = "Payment Rejected!";
    }

    // ৩. ডাটাবেস আপডেট কুয়েরি
    // নিশ্চিত করুন আপনার টেবিলের নাম 'payments' এবং কলামের নাম 'status'
    $sql = "UPDATE payments SET status = '$new_status' WHERE id = '$id'";

    if (mysqli_query($conn, $sql)) {
        // ৪. সাকসেস হলে ড্যাশবোর্ডে ফেরত পাঠানো (একটি সুন্দর মেসেজ সহ)
        echo "
        <div style='background:#000; color:#fff; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif;'>
            <div style='border: 2px solid #fb8c00; padding: 40px; border-radius: 30px; text-align:center; background:#0a0a0b;'>
                <div style='font-size: 50px; color:#00e676; margin-bottom:20px;'>✓</div>
                <h2 style='margin:0;'>Action Completed</h2>
                <p style='color:#71717a;'>$msg</p>
                <script>
                    setTimeout(function(){
                        window.location.href = '../dashboard.php'; 
                    }, 1500);
                </script>
            </div>
        </div>";
    } else {
        // ভুল হলে এরর দেখানো
        echo "Error updating record: " . mysqli_error($conn);
    }
} else {
    // সরাসরি পেজ এক্সেস করতে চাইলে রিডাইরেক্ট
    header("Location: ../dashboard.php");
    exit();
}
?>