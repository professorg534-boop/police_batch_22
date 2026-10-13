<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Portal | Batch 22</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #6366f1; --accent: #818cf8; --bg: #0f172a; --card: #1e293b; }
        
        /* ব্যাকগ্রাউন্ড ক্লিন এবং সলিড (নো শ্যাডো) */
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; color: white; }
        
        .card { background: var(--card); padding: 40px; border-radius: 28px; width: 100%; max-width: 600px; border: 1px solid #334155; position: relative; box-shadow: none; }
        .card::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 5px; background: linear-gradient(90deg, var(--primary), var(--accent)); }
        
        .header-area { text-align: center; margin-bottom: 35px; }
        .header-area h1 { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; margin: 0; text-transform: uppercase; color: #fff; }
        .header-area p { color: #94a3b8; font-size: 14px; margin-top: 8px; font-weight: 500; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        
        label { display: block; font-size: 11px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; margin-left: 5px; }
        input, select { width: 100%; padding: 14px 16px; border-radius: 14px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; outline: none; transition: 0.3s; font-size: 14px; }
        input:focus, select:focus { border-color: var(--primary); }
        
        .submit-btn { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 25px; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .submit-btn:hover { background: #4f46e5; }
        
        /* টু-লাইন প্রিমিয়াম লগইন সেকশন */
        .footer-link { text-align: center; margin-top: 30px; padding-top: 25px; border-top: 1px solid #334155; }
        .footer-link p { font-size: 14px; color: #94a3b8; margin: 0 0 15px 0; }
        
        .login-btn-link { 
            display: inline-block; 
            color: #fff; 
            text-decoration: none; 
            padding: 10px 25px; 
            border-radius: 12px; 
            font-size: 14px; 
            font-weight: 700; 
            border: 1px solid var(--primary); 
            background: rgba(99, 102, 241, 0.05);
            transition: 0.3s; 
        }
        .login-btn-link:hover { 
            background: var(--primary); 
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header-area">
        <h1>Personnel Portal</h1>
        <p>Batch 22 Central Management System</p>
    </div>
    
    <form action="register_process.php" method="POST">
        <div class="grid">
            <div class="full-width">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" name="full_name" placeholder="Enter Full Name" required>
            </div>
            
            <div>
                <label><i class="fas fa-id-badge"></i> BP Number</label>
                <input type="text" name="bp_number" placeholder="Enter BP" required>
            </div>
            <div>
                <label><i class="fas fa-phone"></i> Mobile Number</label>
                <input type="text" name="mobile_number" placeholder="017XXXXXXXX" required>
            </div>
            
<label style="display: block; font-size: 11px; font-weight: 700; color: #6366f1; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px;">
    <i class="fas fa-venus-mars"></i> Select Gender
</label>

<div style="display: flex; gap: 10px; margin-bottom: 20px;">
    <label style="flex: 1; position: relative; cursor: pointer;">
        <input type="radio" name="gender" value="Male" required style="display: none; peer;" id="male">
        <div class="gender-box" onclick="selectGender('male_label')" id="male_label" 
             style="background: #0f172a; border: 1px solid #334155; padding: 14px; border-radius: 12px; text-align: center; color: #94a3b8; transition: 0.3s; font-size: 14px; font-weight: 600;">
            <i class="fas fa-mars" style="margin-right: 5px; color: #60a5fa;"></i> Male
        </div>
    </label>

    <label style="flex: 1; position: relative; cursor: pointer;">
        <input type="radio" name="gender" value="Female" required style="display: none;" id="female">
        <div class="gender-box" onclick="selectGender('female_label')" id="female_label" 
             style="background: #0f172a; border: 1px solid #334155; padding: 14px; border-radius: 12px; text-align: center; color: #94a3b8; transition: 0.3s; font-size: 14px; font-weight: 600;">
            <i class="fas fa-venus" style="margin-right: 5px; color: #f472b6;"></i> Female
        </div>
    </label>
</div>

<script>
function selectGender(id) {
    document.getElementById('male_label').style.borderColor = '#334155';
    document.getElementById('male_label').style.background = '#0f172a';
    document.getElementById('female_label').style.borderColor = '#334155';
    document.getElementById('female_label').style.background = '#0f172a';
    
    document.getElementById(id).style.borderColor = '#6366f1';
    document.getElementById(id).style.background = 'rgba(99, 102, 241, 0.1)';
}
</script>

            <div>
                <label><i class="fas fa-map-marker-alt"></i> Division</label>
                <select name="division" id="division" onchange="updateDistricts()" required>
                    <option value="">Select Division</option>
                    <option value="Barishal">Barishal</option>
                    <option value="Chattogram">Chattogram</option>
                    <option value="Dhaka">Dhaka</option>
                    <option value="Khulna">Khulna</option>
                    <option value="Mymensingh">Mymensingh</option>
                    <option value="Rajshahi">Rajshahi</option>
                    <option value="Rangpur">Rangpur</option>
                    <option value="Sylhet">Sylhet</option>
                </select>
            </div>

            <div>
                <label><i class="fas fa-city"></i> District</label>
                <select name="district" id="district" required>
                    <option value="">Select District</option>
                </select>
            </div>

            <div>
                <label><i class="fas fa-hashtag"></i> Batch Identity (6 Digits)</label>
                <input type="text" name="batch_id" placeholder="Enter 6 Digit ID" pattern="\d{6}" maxlength="6" required>
            </div>
            
            <div></div> <div>
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <div>
                <label><i class="fas fa-shield-check"></i> Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="••••••••" required>
            </div>
        </div>
        
        <button type="submit" class="submit-btn">
            Initialize Join Request <i class="fas fa-arrow-right"></i>
        </button>
    </form>

    <div class="footer-link">
        <p>Already a verified member?</p> 
        <a href="login.php" class="login-btn-link">
            Login Here <i class="fas fa-sign-in-alt" style="margin-left:8px;"></i>
        </a>
    </div>
</div>

<script>
// ৮টি বিভাগের আন্ডারে সম্পূর্ণ ৬৪টি জেলার তালিকা
const districtData = {
    "Barishal": ["Barguna", "Barishal", "Bhola", "Jhalokati", "Patuakhali", "Pirojpur"],
    "Chattogram": ["Bandarban", "Brahmanbaria", "Chandpur", "Chattogram", "Cumilla", "Cox's Bazar", "Feni", "Khagrachari", "Lakshmipur", "Noakhali", "Rangamati"],
    "Dhaka": ["Dhaka", "Faridpur", "Gazipur", "Gopalganj", "Kishoreganj", "Madaripur", "Manikganj", "Munshiganj", "Narayanganj", "Narsingdi", "Rajbari", "Shariatpur", "Tangail"],
    "Khulna": ["Bagerhat", "Chuadanga", "Jashore", "Jhenaidah", "Khulna", "Kushtia", "Magura", "Meherpur", "Narail", "Satkhira"],
    "Mymensingh": ["Jamalpur", "Mymensingh", "Netrokona", "Sherpur"],
    "Rajshahi": ["Bogra", "Joypurhat", "Naogaon", "Natore", "Chapai Nawabganj", "Pabna", "Rajshahi", "Sirajganj"],
    "Rangpur": ["Dinajpur", "Gaibandha", "Kurigram", "Lalmonirhat", "Nilphamari", "Panchagarh", "Rangpur", "Thakurgaon"],
    "Sylhet": ["Habiganj", "Moulvibazar", "Sunamganj", "Sylhet"]
};

function updateDistricts() {
    const divisionSelect = document.getElementById("division");
    const districtSelect = document.getElementById("district");
    const selectedDivision = divisionSelect.value;
    districtSelect.innerHTML = '<option value="">Select District</option>';
    if (selectedDivision && districtData[selectedDivision]) {
        districtData[selectedDivision].forEach(district => {
            const option = document.createElement("option");
            option.value = district; option.textContent = district;
            districtSelect.appendChild(option);
        });
    }
}
</script>
</body>
</html>