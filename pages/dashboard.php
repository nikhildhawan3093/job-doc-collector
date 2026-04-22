<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$total_docs = 4; // resume, cover_letter, id_proof, aadhaar

// Fetch all applications with upload count
$result = pg_query_params($conn, "
    SELECT
        a.id, a.candidate_name, a.candidate_email, a.role, a.created_at,
        COUNT(d.id) AS uploaded
    FROM applications a
    LEFT JOIN documents d ON d.application_id = a.id
    WHERE a.created_by = $1
    GROUP BY a.id
    ORDER BY a.created_at DESC
", [$user_id]);

$applications = pg_fetch_all($result) ?: [];

// For each application, fetch Aadhaar doc + extracted data + PDF report
$aadhaar_info = [];
foreach ($applications as $app) {
    $aid = $app['id'];

    // Aadhaar document
    $doc = pg_fetch_assoc(pg_query_params($conn,
        "SELECT * FROM documents WHERE application_id = $1 AND document_type = 'aadhaar'",
        [$aid]
    ));

    // Extracted Aadhaar data
    $data = null;
    if ($doc) {
        $data = pg_fetch_assoc(pg_query_params($conn,
            "SELECT * FROM aadhaar_data WHERE document_id = $1",
            [$doc['id']]
        ));
    }

    // PDF report
    $pdf = null;
    if ($doc) {
        $pdf = pg_fetch_assoc(pg_query_params($conn,
            "SELECT * FROM pdf_reports WHERE document_id = $1",
            [$doc['id']]
        ));
    }

    $aadhaar_info[$aid] = ['doc' => $doc, 'data' => $data, 'pdf' => $pdf];
}
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
            max-width: 960px;
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
            padding: 0.5rem 1.1rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn:hover { background: #357abd; }

        /* Application card */
        .app-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.2rem;
            overflow: hidden;
        }

        /* Card top row — clickable header */
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.2rem;
            cursor: pointer;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .card-header:hover { background: #f5f8ff; }

        .candidate-info h3 {
            font-size: 1rem;
            color: #333;
            margin-bottom: 0.15rem;
        }

        .candidate-info p {
            font-size: 0.83rem;
            color: #888;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        /* Progress bar */
        .progress-wrap {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 20px;
            height: 8px;
            width: 90px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #4a90e2;
            border-radius: 20px;
        }

        .progress-fill.complete { background: #27ae60; }

        .progress-text { font-size: 0.82rem; color: #555; }

        /* Card body — details grid */
        .card-body {
            border-top: 1px solid #f0f0f0;
            padding: 1rem 1.2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-block label {
            font-size: 0.75rem;
            color: #999;
            display: block;
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .detail-block span {
            font-size: 0.88rem;
            color: #333;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: bold;
        }

        .badge-clear    { background: #e6f9ef; color: #27ae60; }
        .badge-blurry   { background: #fdecea; color: #c0392b; }
        .badge-pending  { background: #f0f0f0; color: #888; }
        .badge-done     { background: #e6f9ef; color: #27ae60; }
        .badge-failed   { background: #fdecea; color: #c0392b; }
        .badge-skipped  { background: #fff8e1; color: #e67e22; }
        .badge-missing  { background: #fdecea; color: #c0392b; }

        /* Doc pills */
        .doc-pills { display: flex; flex-wrap: wrap; gap: 0.3rem; }

        .pill {
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 20px;
        }

        .pill-done    { background: #e6f9ef; color: #27ae60; }
        .pill-missing { background: #f0f0f0; color: #aaa; }

        /* PDF button */
        .btn-pdf {
            font-size: 0.8rem;
            padding: 0.3rem 0.7rem;
            background: #4a90e2;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-pdf:hover { background: #357abd; }

        .btn-pdf-dl {
            font-size: 0.8rem;
            padding: 0.3rem 0.7rem;
            background: #fff;
            color: #4a90e2;
            border: 1px solid #4a90e2;
            border-radius: 4px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-pdf-dl:hover { background: #eef3fb; }

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
        <?php foreach ($applications as $i => $app):
            $aid      = $app['id'];
            $uploaded = (int)$app['uploaded'];
            $pct      = round(($uploaded / $total_docs) * 100);
            $complete = $uploaded >= $total_docs;
            $info     = $aadhaar_info[$aid];
            $doc      = $info['doc'];
            $data     = $info['data'];
            $pdf      = $info['pdf'];

            $doc_types  = ['resume', 'cover_letter', 'id_proof', 'aadhaar'];
            $doc_labels = ['resume' => 'Resume', 'cover_letter' => 'Cover Letter', 'id_proof' => 'ID Proof', 'aadhaar' => 'Aadhaar'];

            // Fetch uploaded types for this application
            $up_result    = pg_query_params($conn, "SELECT document_type FROM documents WHERE application_id = $1", [$aid]);
            $up_types     = pg_fetch_all($up_result) ? array_column(pg_fetch_all(pg_query_params($conn, "SELECT document_type FROM documents WHERE application_id = $1", [$aid])), 'document_type') : [];
        ?>
        <div class="app-card">

            <!-- Clickable header -->
            <div class="card-header" onclick="window.location='application_detail.php?id=<?= $aid ?>'">
                <div class="candidate-info">
                    <h3><?= htmlspecialchars($app['candidate_name']) ?></h3>
                    <p><?= htmlspecialchars($app['candidate_email']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($app['role']) ?> &nbsp;·&nbsp; <?= date('d M Y', strtotime($app['created_at'])) ?></p>
                </div>
                <div class="header-right">
                    <div class="progress-wrap">
                        <div class="progress-bar">
                            <div class="progress-fill <?= $complete ? 'complete' : '' ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="progress-text"><?= $uploaded ?>/<?= $total_docs ?></span>
                    </div>
                </div>
            </div>

            <!-- Detail grid -->
            <div class="card-body">

                <!-- Uploaded Documents -->
                <div class="detail-block">
                    <label>Documents</label>
                    <div class="doc-pills">
                        <?php foreach ($doc_types as $type): ?>
                            <?php $done = in_array($type, $up_types); ?>
                            <span class="pill <?= $done ? 'pill-done' : 'pill-missing' ?>">
                                <?= $done ? '✔' : '✗' ?> <?= $doc_labels[$type] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Blur Status -->
                <div class="detail-block">
                    <label>Aadhaar Blur</label>
                    <?php if ($doc): ?>
                        <?php $bs = $doc['blur_status']; ?>
                        <span class="badge badge-<?= $bs ?>">
                            <?= $bs === 'clear' ? '✔ Clear' : ($bs === 'blurry' ? '⚠ Blurry' : ucfirst($bs)) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge badge-missing">Not uploaded</span>
                    <?php endif; ?>
                </div>

                <!-- Extracted Data -->
                <div class="detail-block">
                    <label>Extracted Data</label>
                    <?php if ($data): ?>
                        <span style="font-size:0.85rem;color:#333;display:block;"><?= htmlspecialchars($data['name']) ?></span>
                        <span style="font-size:0.82rem;color:#888;">
                            <?= chunk_split(htmlspecialchars($data['aadhaar_number']), 4, ' ') ?> &nbsp;·&nbsp; <?= htmlspecialchars($data['dob']) ?>
                        </span>
                    <?php elseif ($doc && $doc['processed_status'] === 'failed'): ?>
                        <span class="badge badge-failed">Extraction failed</span>
                    <?php elseif ($doc && $doc['processed_status'] === 'pending'): ?>
                        <span class="badge badge-pending">Pending</span>
                    <?php else: ?>
                        <span class="badge badge-missing">—</span>
                    <?php endif; ?>
                </div>

                <!-- PDF Report -->
                <div class="detail-block">
                    <label>PDF Report</label>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.2rem;">
                        <?php if ($doc && $doc['processed_status'] === 'done'): ?>
                            <a class="btn-pdf" href="generate_report.php?id=<?= $aid ?>">⬇ Generate</a>
                        <?php endif; ?>
                        <?php if ($pdf): ?>
                            <a class="btn-pdf-dl" href="../<?= htmlspecialchars($pdf['pdf_path']) ?>" download>Download</a>
                        <?php endif; ?>
                        <?php if (!$doc || $doc['processed_status'] !== 'done'): ?>
                            <span style="font-size:0.82rem;color:#aaa;">Not available</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
