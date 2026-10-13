<?php
die("THIS IS THE CORRECT FILE");
include 'db.php'; ?>
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

        * { box-sizing: border-box; }

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

        .field-error { color: #f87171; font-size: 12px; font-weight: 600; margin-top: 6px; margin-left: 5px; min-height: 16px; display: none; align-items: center; gap: 6px; }
        .field-error.show { display: flex; }

        input.invalid, select.invalid { border-color: #f87171 !important; background: rgba(248, 113, 113, 0.05); animation: shakeField 0.4s ease; }
        @keyframes shakeField { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }

        .gender-radio.invalid + .gender-box { border-color: #f87171; background: rgba(248, 113, 113, 0.05); }

        .submit-btn { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 25px; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .submit-btn:hover { background: #4f46e5; }
        .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* টু-লাইন প্রিমিয়াম লগইন সেকশন */
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

        /* Gender radio — visually hidden কিন্তু accessible ও validation-friendly */
        .gender-radio {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            pointer-events: none;
        }

        .gender-box {
            background: #0f172a; border: 1px solid #334155; padding: 14px; border-radius: 12px;
            text-align: center; color: #94a3b8; transition: 0.3s; font-size: 14px; font-weight: 600;
        }
        .gender-radio:checked + .gender-box {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            color: #fff;
        }
        .gender-radio:focus-visible + .gender-box {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        @media (max-width: 560px) {
            .grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .card { padding: 28px 20px; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header-area">
        <h1>Personnel Portal</h1>
        <p>Batch 22 Central Management System</p>
    </div>

    <form action="register_process.php" method="POST" id="signupForm" novalidate>
        <div class="grid">
            <div class="full-width">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" name="full_name" id="full_name" placeholder="Enter Full Name" required>
                <div class="field-error" id="err_full_name"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>

            <div>
                <label><i class="fas fa-id-badge"></i> BP Number</label>
                <input type="text" name="bp_number" id="bp_number" placeholder="Enter BP" required>
                <div class="field-error" id="err_bp_number"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>
            <div>
                <label><i class="fas fa-phone"></i> Mobile Number</label>
                <input type="text" name="mobile_number" id="mobile_number" placeholder="017XXXXXXXX" pattern="01[3-9]\d{8}" maxlength="11" required>
                <div class="field-error" id="err_mobile_number"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>

            <div class="full-width">
                <label><i class="fas fa-venus-mars"></i> Select Gender</label>
                <div style="display: flex; gap: 10px;">
                    <label style="flex: 1; cursor: pointer;">
                        <input class="gender-radio" type="radio" name="gender" value="Male" required id="male">
                        <div class="gender-box" id="male_label">
                            <i class="fas fa-mars" style="margin-right: 5px; color: #60a5fa;"></i> Male
                        </div>
                    </label>

                    <label style="flex: 1; cursor: pointer;">
                        <input class="gender-radio" type="radio" name="gender" value="Female" required id="female">
                        <div class="gender-box" id="female_label">
                            <i class="fas fa-venus" style="margin-right: 5px; color: #f472b6;"></i> Female
                        </div>
                    </label>
                </div>
                <div class="field-error" id="err_gender"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>

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
                <div class="field-error" id="err_division"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>

            <div>
                <label><i class="fas fa-city"></i> District</label>
                <select name="district" id="district" required>
                    <option value="">Select District</option>
                </select>
                <div class="field-error" id="err_district"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>

            <div>
                <label><i class="fas fa-hashtag"></i> Batch Identity (6 Digits)</label>
                <input type="text" name="batch_id" id="batch_id" placeholder="TRC NO / Batch ID" pattern="\d{6}" maxlength="6" required>
                <div class="field-error" id="err_batch_id"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>

            <div>
                <label><i class="fas fa-tint"></i> Blood Group</label>
                <select name="blood_group" id="blood_group" required>
                    <option value="">Select Blood Group</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                </select>
                <div class="field-error" id="err_blood_group"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>

            <div>
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" id="password" placeholder="••••••••" minlength="6" required>
                <div class="field-error" id="err_password"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>
            <div>
                <label><i class="fas fa-shield-check"></i> Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required>
                <div class="field-error" id="err_confirm_password"><i class="fas fa-circle-exclamation"></i> <span></span></div>
            </div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">
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

// ---- স্টাইলিশ, সম্পূর্ণ ইংরেজি ফর্ম ভ্যালিডেশন ----

function showError(fieldEl, errorId, message) {
    const errBox = document.getElementById(errorId);
    errBox.querySelector("span").textContent = message;
    errBox.classList.add("show");
    if (fieldEl) fieldEl.classList.add("invalid");
}

function clearError(fieldEl, errorId) {
    const errBox = document.getElementById(errorId);
    errBox.classList.remove("show");
    errBox.querySelector("span").textContent = "";
    if (fieldEl) fieldEl.classList.remove("invalid");
}

function validateForm() {
    let isValid = true;
    let firstInvalidField = null;

    function check(condition, fieldEl, errorId, message) {
        if (!condition) {
            showError(fieldEl, errorId, message);
            if (!firstInvalidField) firstInvalidField = fieldEl;
            isValid = false;
        } else {
            clearError(fieldEl, errorId);
        }
    }

    const fullName = document.getElementById("full_name");
    check(fullName.value.trim() !== "", fullName, "err_full_name", "Full name is required.");

    const bpNumber = document.getElementById("bp_number");
    check(bpNumber.value.trim() !== "", bpNumber, "err_bp_number", "BP number is required.");

    const mobile = document.getElementById("mobile_number");
    check(/^01[3-9]\d{8}$/.test(mobile.value.trim()), mobile, "err_mobile_number", "Enter a valid mobile number (e.g. 017XXXXXXXX).");

    const genderSelected = document.querySelector('input[name="gender"]:checked');
    check(!!genderSelected, null, "err_gender", "Please select a gender.");
    document.querySelectorAll(".gender-radio").forEach(r => r.classList.toggle("invalid", !genderSelected));

    const division = document.getElementById("division");
    check(division.value !== "", division, "err_division", "Please select a division.");

    const district = document.getElementById("district");
    check(district.value !== "", district, "err_district", "Please select a district.");

    const batchId = document.getElementById("batch_id");
    check(/^\d{6}$/.test(batchId.value.trim()), batchId, "err_batch_id", "Batch ID must be exactly 6 digits.");

    const bloodGroup = document.getElementById("blood_group");
    check(bloodGroup.value !== "", bloodGroup, "err_blood_group", "Please select a blood group.");

    const password = document.getElementById("password");
    check(password.value.length >= 6, password, "err_password", "Password must be at least 6 characters.");

    const confirmPassword = document.getElementById("confirm_password");
    check(
        confirmPassword.value !== "" && confirmPassword.value === password.value,
        confirmPassword,
        "err_confirm_password",
        confirmPassword.value === "" ? "Please confirm your password." : "Passwords do not match."
    );

    if (!isValid && firstInvalidField) {
        firstInvalidField.scrollIntoView({ behavior: "smooth", block: "center" });
        firstInvalidField.focus();
    }

    return isValid;
}

document.getElementById("signupForm").addEventListener("submit", function(e) {
    if (!validateForm()) {
        e.preventDefault();
    }
});

// টাইপ করার সাথে সাথে সেই ফিল্ডের এরর মুছে যাবে (রিয়েল-টাইম ফিডব্যাক)
["full_name","bp_number","mobile_number","division","district","batch_id","blood_group","password","confirm_password"].forEach(id => {
    const el = document.getElementById(id);
    el.addEventListener("input", () => clearError(el, "err_" + id));
    el.addEventListener("change", () => clearError(el, "err_" + id));
});
document.querySelectorAll('input[name="gender"]').forEach(r => {
    r.addEventListener("change", () => {
        clearError(null, "err_gender");
        document.querySelectorAll(".gender-radio").forEach(x => x.classList.remove("invalid"));
    });
});
</script>
</body>
</html>