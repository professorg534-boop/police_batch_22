// পাসওয়ার্ড এনক্রিপ্ট করার সঠিক নিয়ম
$hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);

// ইনসার্ট কোয়েরিতে এই $hashed_password ব্যবহার করুন
$sql = "INSERT INTO users (full_name, bp_number, mobile_number, gender, division, district, batch_id, password, status) 
        VALUES ('$full_name', '$bp_number', '$mobile_number', '$gender', '$division', '$district', '$batch_id', '$hashed_password', 'active')";