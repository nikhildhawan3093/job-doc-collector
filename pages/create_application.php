<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Application – Job Doc Collector</title>
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
            max-width: 500px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card h2 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            color: #555;
        }

        input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 1.1rem;
        }

        input:focus {
            outline: none;
            border-color: #4a90e2;
        }

        .actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 0.5rem;
        }

        button {
            flex: 1;
            padding: 0.7rem;
            background: #4a90e2;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
        }

        button:hover { background: #357abd; }

        .btn-cancel {
            flex: 1;
            padding: 0.7rem;
            background: #fff;
            color: #555;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            text-align: center;
            text-decoration: none;
        }

        .btn-cancel:hover { background: #f0f0f0; }
    </style>
</head>
<body>

<header>
    <h1>Job Doc Collector</h1>
    <a href="dashboard.php">← Dashboard</a>
</header>

<div class="container">
    <div class="card">
        <h2>New Application</h2>
        <form method="POST" action="save_application.php">
            <label for="candidate_name">Candidate Name</label>
            <input type="text" id="candidate_name" name="candidate_name" required autofocus>

            <label for="candidate_email">Email</label>
            <input type="email" id="candidate_email" name="candidate_email" required>

            <label for="role">Role</label>
            <input type="text" id="role" name="role" required>

            <div class="actions">
                <a class="btn-cancel" href="dashboard.php">Cancel</a>
                <button type="submit">Create Application</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
