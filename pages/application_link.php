<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$token = $_GET['token'] ?? '';
$magic_link = 'http://localhost/php/job-doc-collector/pages/upload.php?token=' . urlencode($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Created – Job Doc Collector</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        header {
            background: #4a90e2;
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 { font-size: 1.2rem; }

        header a {
            color: #fff;
            text-decoration: none;
            font-size: 0.9rem;
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
        }

        header a:hover { background: rgba(255,255,255,0.35); }

        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }

        .icon {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
        }

        .card h2 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .card p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .link-box {
            display: flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .link-box input {
            flex: 1;
            padding: 0.6rem 0.8rem;
            border: none;
            font-size: 0.9rem;
            color: #333;
            background: #f7f9fc;
            outline: none;
        }

        .link-box button {
            padding: 0.6rem 1rem;
            background: #4a90e2;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .link-box button:hover { background: #357abd; }

        .btn-dashboard {
            display: inline-block;
            padding: 0.7rem 1.5rem;
            background: #4a90e2;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-dashboard:hover { background: #357abd; }

        .copied {
            color: #27ae60;
            font-size: 0.85rem;
            margin-top: -1rem;
            margin-bottom: 1rem;
            display: none;
        }
    </style>
</head>
<body>

<header>
    <h1>Job Doc Collector</h1>
    <a href="dashboard.php">← Dashboard</a>
</header>

<div class="container">
    <div class="card">
        <div class="icon">✅</div>
        <h2>Application Created!</h2>
        <p>Share this magic link with the candidate to upload their documents.</p>

        <div class="link-box">
            <input type="text" id="magic-link" value="<?= htmlspecialchars($magic_link) ?>" readonly>
            <button onclick="copyLink()">Copy</button>
        </div>
        <p class="copied" id="copied-msg">Link copied to clipboard!</p>

        <a class="btn-dashboard" href="dashboard.php">Go to Dashboard</a>
    </div>
</div>

<script>
function copyLink() {
    const input = document.getElementById('magic-link');
    input.select();
    document.execCommand('copy');
    const msg = document.getElementById('copied-msg');
    msg.style.display = 'block';
    setTimeout(() => msg.style.display = 'none', 2000);
}
</script>

</body>
</html>
