<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid application ID.");
}

// Fetch application
$result      = pg_query_params($conn, "
    SELECT * FROM applications WHERE id = $1 AND created_by = $2
", [$id, $_SESSION['user_id']]);
$application = pg_fetch_assoc($result);

if (!$application) {
    die("Application not found.");
}

// Fetch documents
$doc_result = pg_query_params($conn, "
    SELECT * FROM documents WHERE application_id = $1 ORDER BY uploaded_at ASC
", [$id]);
$documents = pg_fetch_all($doc_result) ?: [];

$uploaded_types = array_column($documents, 'document_type');

$doc_types = ['resume', 'cover_letter', 'id_proof'];
$doc_labels = [
    'resume'       => 'Resume',
    'cover_letter' => 'Cover Letter',
    'id_proof'     => 'ID Proof',
];

$magic_link = 'http://localhost/php/job-doc-collector/pages/upload.php?token=' . urlencode($application['token']);

// Fetch Aadhaar extracted data if available
$aadhaar_doc = null;
foreach ($documents as $d) {
    if ($d['document_type'] === 'aadhaar') { $aadhaar_doc = $d; break; }
}
$aadhaar_data = null;
if ($aadhaar_doc) {
    $aadhaar_data = pg_fetch_assoc(pg_query_params($conn,
        "SELECT * FROM aadhaar_data WHERE document_id = $1",
        [$aadhaar_doc['id']]
    ));
}

// Fetch existing PDF report
$pdf_report = null;
if ($aadhaar_doc) {
    $pdf_report = pg_fetch_assoc(pg_query_params($conn,
        "SELECT * FROM pdf_reports WHERE document_id = $1",
        [$aadhaar_doc['id']]
    ));
}

$report_generated = isset($_GET['report']) && $_GET['report'] === 'generated';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Detail – Job Doc Collector</title>
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
            max-width: 700px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .card h2 {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem 1.5rem;
        }

        .info-item label {
            font-size: 0.8rem;
            color: #888;
            display: block;
            margin-bottom: 0.2rem;
        }

        .info-item span {
            font-size: 0.95rem;
            color: #333;
        }

        .doc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .doc-row:last-child { border-bottom: none; }

        .doc-name { font-size: 0.95rem; color: #333; }

        .badge-uploaded {
            background: #e6f9ef;
            color: #27ae60;
            padding: 0.25rem 0.7rem;
            border-radius: 20px;
            font-size: 0.82rem;
        }

        .badge-missing {
            background: #fdecea;
            color: #c0392b;
            padding: 0.25rem 0.7rem;
            border-radius: 20px;
            font-size: 0.82rem;
        }

        .doc-actions { display: flex; gap: 0.5rem; align-items: center; }

        .btn-view {
            font-size: 0.82rem;
            color: #4a90e2;
            text-decoration: none;
            padding: 0.25rem 0.6rem;
            border: 1px solid #4a90e2;
            border-radius: 4px;
        }

        .btn-view:hover { background: #eef3fb; }

        .link-box {
            display: flex;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 0.8rem;
        }

        .link-box input {
            flex: 1;
            padding: 0.55rem 0.8rem;
            border: none;
            font-size: 0.85rem;
            background: #f7f9fc;
            outline: none;
            color: #333;
        }

        .link-box button {
            padding: 0.55rem 1rem;
            background: #4a90e2;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .link-box button:hover { background: #357abd; }

        .copied {
            font-size: 0.82rem;
            color: #27ae60;
            margin-top: 0.4rem;
            display: none;
        }

        .progress-summary {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 0.8rem;
        }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 20px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: #4a90e2;
            border-radius: 20px;
            transition: width 0.3s;
        }

        .progress-fill.complete { background: #27ae60; }
    </style>
</head>
<body>

<header>
    <h1>Job Doc Collector</h1>
    <a href="dashboard.php">← Dashboard</a>
</header>

<div class="container">

    <!-- Candidate Info -->
    <div class="card">
        <h2>Candidate Info</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Name</label>
                <span><?= htmlspecialchars($application['candidate_name']) ?></span>
            </div>
            <div class="info-item">
                <label>Email</label>
                <span><?= htmlspecialchars($application['candidate_email']) ?></span>
            </div>
            <div class="info-item">
                <label>Role</label>
                <span><?= htmlspecialchars($application['role']) ?></span>
            </div>
            <div class="info-item">
                <label>Created</label>
                <span><?= date('d M Y', strtotime($application['created_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Documents -->
    <div class="card">
        <h2>Documents</h2>

        <?php
            $uploaded_count = count($uploaded_types);
            $total = count($doc_types);
            $pct   = ($uploaded_count / $total) * 100;
        ?>
        <p class="progress-summary"><?= $uploaded_count ?>/<?= $total ?> documents uploaded</p>
        <div class="progress-bar">
            <div class="progress-fill <?= $uploaded_count === $total ? 'complete' : '' ?>"
                 style="width: <?= $pct ?>%"></div>
        </div>

        <?php foreach ($doc_types as $type): ?>
            <?php
                $doc = null;
                foreach ($documents as $d) {
                    if ($d['document_type'] === $type) { $doc = $d; break; }
                }
            ?>
            <div class="doc-row">
                <span class="doc-name"><?= $doc_labels[$type] ?></span>
                <div class="doc-actions">
                    <?php if ($doc): ?>
                        <a class="btn-view" href="../<?= htmlspecialchars($doc['file_url']) ?>" download>Download</a>
                        <span class="badge-uploaded">✔ Uploaded</span>
                    <?php else: ?>
                        <span class="badge-missing">✗ Missing</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PDF Report -->
    <?php if ($aadhaar_doc && $aadhaar_doc['processed_status'] === 'done'): ?>
    <div class="card">
        <h2>PDF Report</h2>

        <?php if ($report_generated): ?>
            <p style="color:#27ae60;font-size:0.9rem;margin-bottom:0.8rem;">✔ Report generated successfully.</p>
        <?php endif; ?>

        <div style="display:flex;gap:0.8rem;align-items:center;flex-wrap:wrap;">
            <a href="generate_report.php?id=<?= $id ?>"
               style="padding:0.55rem 1.1rem;background:#4a90e2;color:#fff;border-radius:5px;text-decoration:none;font-size:0.9rem;">
                ⬇ Generate &amp; Download PDF
            </a>

            <?php if ($pdf_report): ?>
                <a href="../<?= htmlspecialchars($pdf_report['pdf_path']) ?>" download
                   style="padding:0.55rem 1.1rem;background:#fff;color:#4a90e2;border:1px solid #4a90e2;border-radius:5px;text-decoration:none;font-size:0.9rem;">
                    Last Report (<?= date('d M Y', strtotime($pdf_report['generated_at'])) ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Magic Link -->
    <div class="card">
        <h2>Magic Upload Link</h2>
        <p style="font-size:0.9rem;color:#666;">Share this link with the candidate to upload documents.</p>
        <div class="link-box">
            <input type="text" id="magic-link" value="<?= htmlspecialchars($magic_link) ?>" readonly>
            <button onclick="copyLink()">Copy</button>
        </div>
        <p class="copied" id="copied-msg">Copied!</p>
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
