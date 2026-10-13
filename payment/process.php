<?php
session_start();
error_reporting(0);
include '../db.php'; // ডাটাবেজ কানেকশন

// ১. অথেন্টিকেশন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id   = $_SESSION['user_id'];
    $pay_month = mysqli_real_escape_string($conn, $_POST['pay_month']);
    $pay_year  = mysqli_real_escape_string($conn, $_POST['pay_year']);
    $amount    = mysqli_real_escape_string($conn, $_POST['amount']);
    $trx_id    = mysqli_real_escape_string($conn, $_POST['trx_id']);

    // ২. ডুপ্লিকেট পেমেন্ট প্রিভেনশন লজিক
    // চেক করা হচ্ছে এই ইউজার একই মাস ও বছরে অলরেডি পেমেন্ট করেছে কি না
    $check_query = "SELECT id FROM payments 
                    WHERE user_id = '$user_id' 
                    AND pay_month = '$pay_month' 
                    AND pay_year = '$pay_year' 
                    AND (status = 'pending' OR status = 'approved')";
    
    $check_res = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_res) > 0) {
        // যদি ডাটা পাওয়া যায়, তবে প্রসেস থামিয়ে ইউজারকে এলার্ট দিবে
        echo "<script>
                alert('ACCESS DENIED: এই মাসের জন্য আপনার একটি পেমেন্ট অলরেডি ডাটাবেজে সংরক্ষিত আছে। পুনরায় এন্ট্রি সম্ভব নয়।');
                window.location.href='index.php';
              </script>";
        exit();
    }

    // ৩. ইমেজ আপলোড হ্যান্ডলিং
    $upload_dir = "uploads/";
    
    // ফোল্ডার না থাকলে তৈরি করবে
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // ফাইলের নাম ইউনিক করার জন্য টাইমস্ট্যাম্প যোগ করা
    $file_basename = basename($_FILES["proof_img"]["name"]);
    $file_extension = strtolower(pathinfo($file_basename, PATHINFO_EXTENSION));
    $new_file_name = "TRX_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
    $target_file = $upload_dir . $new_file_name;

    // ফাইলটি সার্ভারে মুভ করা
    if (move_uploaded_file($_FILES["proof_img"]["tmp_name"], $target_file)) {
        
        // ৪. ডাটাবেজে ফাইনাল এন্ট্রি
        $insert_query = "INSERT INTO payments (user_id, pay_month, pay_year, amount, trx_id, proof_img, status) 
                         VALUES ('$user_id', '$pay_month', '$pay_year', '$amount', '$trx_id', '$new_file_name', 'pending')";

        if (mysqli_query($conn, $insert_query)) {
            // সাকসেস হলে রিসিপ্ট পেজে নিয়ে যাবে
            header("Location: success.php");
            exit();
        } else {
            echo "Database Error: " . mysqli_error($conn);
        }
    } else {
        echo "<script>
                alert('ERROR: ইমেজ আপলোড করতে সমস্যা হয়েছে। দয়া করে পুনরায় চেষ্টা করুন।');
                window.location.href='index.php';
              </script>";
    }
}
?>