<?php
ob_start();
session_start();

// Database connection include
if (file_exists('db.php')) { 
    include 'db.php'; 
} else {
    die("Error: db.php file not found in the current directory.");
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['launch_poll'])) {
    $question = mysqli_real_escape_string($conn, $_POST['question']);
    $options = array_filter($_POST['options']); // Remove empty options
    $options_json = json_encode(array_values($options));
    
    // Line 21 Secure Query
    $sql = "INSERT INTO polls (question, options, created_by, status, created_at) 
            VALUES ('$question', '$options_json', '$user_id', 'active', NOW())";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error_msg = "Database Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALPHA HQ | Create Poll</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg: #020408;
            --panel: rgba(13, 17, 23, 0.85);
            --accent: #6366f1;
            --neon: #00d4ff;
            --border: rgba(99, 102, 241, 0.2);
            --danger: #ff4d4d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: var(--bg);
            background-image: radial-gradient(circle at 50% 50%, #0f172a 0%, #020408 100%);
            color: #e2e8f0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        header {
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
        }

        .back-btn {
            color: #888;
            text-decoration: none;
            font-family: 'Orbitron';
            font-size: 11px;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }
        .back-btn:hover { color: var(--neon); transform: translateX(-5px); }

        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .poll-card {
            background: var(--panel);
            width: 100%;
            max-width: 600px;
            padding: 40px;
            border-radius: 30px;
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            backdrop-filter: blur(15px);
        }

        .input-group { margin-bottom: 25px; }
        .input-group label { 
            display: block; font-size: 10px; color: var(--accent); 
            text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; font-weight: 800;
        }

        input { 
            width: 100%; background: rgba(0,0,0,0.4); border: 1px solid var(--border);
            padding: 15px 20px; border-radius: 12px; color: #fff; font-size: 14px;
            transition: 0.3s;
        }
        input:focus { border-color: var(--neon); outline: none; box-shadow: 0 0 15px rgba(0, 212, 255, 0.1); }

        .option-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .remove-btn { 
            background: rgba(255, 77, 77, 0.1); border: 1px solid rgba(255, 77, 77, 0.2);
            color: var(--danger); width: 50px; border-radius: 10px; cursor: pointer; transition: 0.3s;
        }
        .remove-btn:hover { background: var(--danger); color: #fff; }

        .add-btn {
            background: transparent; border: 1px dashed var(--accent); color: var(--accent);
            width: 100%; padding: 12px; border-radius: 12px; cursor: pointer;
            font-size: 11px; font-weight: 800; margin-bottom: 25px; transition: 0.3s;
        }
        .add-btn:hover { background: rgba(99, 102, 241, 0.05); color: var(--neon); border-color: var(--neon); }

        .deploy-btn {
            width: 100%; padding: 20px; border: none; border-radius: 15px;
            background: linear-gradient(45deg, #4f46e5, #6366f1);
            color: #fff; font-family: 'Orbitron'; font-weight: 800; font-size: 14px;
            letter-spacing: 2px; cursor: pointer; transition: 0.4s;
        }
        .deploy-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4); }

        .err { color: var(--danger); font-size: 12px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>

<header>
    <a href="dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> RETURN TO HQ</a>
    <div style="font-family:'Orbitron'; font-weight:900; letter-spacing:3px;">ALPHA <span style="color:var(--accent)">POLL</span></div>
    <div></div>
</header>

<div class="container">
    <div class="poll-card">
        <h2 style="font-family:'Orbitron'; font-size: 14px; text-align:center; margin-bottom:30px; letter-spacing:3px;">INITIALIZE NEW SURVEY</h2>
        
        <?php if($error_msg): ?>
            <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Survey Question</label>
                <input type="text" name="question" placeholder="Enter objective question..." required>
            </div>

            <div class="input-group">
                <label>Operational Responses</label>
                <div id="options-area">
                    <div class="option-row">
                        <input type="text" name="options[]" placeholder="Option 1" required>
                    </div>
                    <div class="option-row">
                        <input type="text" name="options[]" placeholder="Option 2" required>
                    </div>
                </div>
            </div>

            <button type="button" class="add-btn" onclick="addOption()">+ ADD RESPONSE FIELD</button>
            <button type="submit" name="launch_poll" class="deploy-btn">LAUNCH DEPLOYMENT</button>
        </form>
    </div>
</div>

<script>
let count = 2;
function addOption() {
    count++;
    const area = document.getElementById('options-area');
    const div = document.createElement('div');
    div.className = 'option-row';
    div.innerHTML = `
        <input type="text" name="options[]" placeholder="Option ${count}" required>
        <button type="button" class="remove-btn" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    `;
    area.appendChild(div);
}
</script>

</body>
</html>