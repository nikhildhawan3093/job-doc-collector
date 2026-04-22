<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch applications with document upload count
$result = pg_query_params($conn, "
    SELECT
        a.id,
        a.candidate_name,
        a.candidate_email,
        a.role,
        a.created_at,
        COUNT(d.id) AS uploaded
    FROM applications a
    LEFT JOIN documents d ON d.application_id = a.id
    WHERE a.created_by = $1
    GROUP BY a.id
    ORDER BY a.created_at DESC
", [$user_id]);

$applications = pg_fetch_all($result) ?: [];

$total_docs = 3; // resume, cover_letter, id_proof
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Job Doc Collector</title>
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
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .top-bar h2 { font-size: 1.2rem; color: #333; }

        .btn {
            background: #4a90e2;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn:hover { background: #357abd; }

        table {
            width: 100%;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-collapse: collapse;
            overflow: hidden;
        }

        thead { background: #4a90e2; color: #fff; }

        th, td {
            padding: 0.85rem 1rem;
            text-align: left;
            font-size: 0.95rem;
        }

        tbody tr:nth-child(even) { background: #f7f9fc; }
        tbody tr:hover { background: #eef3fb; cursor: pointer; }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 20px;
            height: 10px;
            width: 120px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }

        .progress-fill {
            height: 100%;
            background: #4a90e2;
            border-radius: 20px;
        }

        .progress-fill.complete { background: #27ae60; }

        .progress-text {
            font-size: 0.85rem;
            color: #555;
            vertical-align: middle;
        }

        .empty {
            text-align: center;
            padding: 3rem;
            color: #888;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

<header>
    <h1>Job Doc Collector</h1>
    <a href="logout.php">Logout</a>
</header>

<div class="container">
    <div class="top-bar">
        <h2>Applications</h2>
        <a class="btn" href="create_application.php">+ New Application</a>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty">No applications yet. Create one to get started.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Candidate Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Upload Progress</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $i => $app): ?>
                    <?php
                        $uploaded = (int)$app['uploaded'];
                        $pct = ($uploaded / $total_docs) * 100;
                        $complete = $uploaded >= $total_docs;
                    ?>
                    <tr onclick="window.location='application_detail.php?id=<?= $app['id'] ?>'" style="cursor:pointer">
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($app['candidate_name']) ?></td>
                        <td><?= htmlspecialchars($app['candidate_email']) ?></td>
                        <td><?= htmlspecialchars($app['role']) ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill <?= $complete ? 'complete' : '' ?>"
                                     style="width: <?= $pct ?>%"></div>
                            </div>
                            <span class="progress-text"><?= $uploaded ?>/<?= $total_docs ?></span>
                        </td>
                        <td><?= date('d M Y', strtotime($app['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
