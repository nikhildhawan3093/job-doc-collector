<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'] ?? '';
$total_docs = 4;

$result = pg_query_params($conn, "
    SELECT a.id, a.candidate_name, a.candidate_email, a.role, a.created_at,
           COUNT(d.id) AS uploaded
    FROM applications a
    LEFT JOIN documents d ON d.application_id = a.id
    WHERE a.created_by = \$1
    GROUP BY a.id
    ORDER BY a.created_at DESC
", [$user_id]);

$applications = pg_fetch_all($result) ?: [];

// Per-app enrichment: aadhaar, resume, pdf
$app_info = [];
foreach ($applications as $app) {
    $aid = $app['id'];

    $aadhaar_doc  = pg_fetch_assoc(pg_query_params($conn,
        "SELECT * FROM documents WHERE application_id = \$1 AND document_type = 'aadhaar'", [$aid]));
    $aadhaar_data = $aadhaar_doc
        ? pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM aadhaar_data WHERE document_id = \$1", [$aadhaar_doc['id']]))
        : null;
    $pdf = $aadhaar_doc
        ? pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM pdf_reports WHERE document_id = \$1", [$aadhaar_doc['id']]))
        : null;
    $resume_doc  = pg_fetch_assoc(pg_query_params($conn,
        "SELECT * FROM documents WHERE application_id = \$1 AND document_type = 'resume'", [$aid]));
    $resume_data = $resume_doc
        ? pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM resume_data WHERE document_id = \$1", [$resume_doc['id']]))
        : null;

    // uploaded doc types
    $up_res  = pg_query_params($conn, "SELECT document_type FROM documents WHERE application_id = \$1", [$aid]);
    $up_rows = pg_fetch_all($up_res) ?: [];
    $up_types = array_column($up_rows, 'document_type');

    $app_info[$aid] = compact('aadhaar_doc','aadhaar_data','resume_doc','resume_data','pdf','up_types');
}

// Stats
$total_apps    = count($applications);
$total_uploads = array_sum(array_column($applications, 'uploaded'));
$total_reports = count(array_filter($app_info, fn($i) => $i['pdf'] !== null && $i['pdf'] !== false));
$complete_apps = count(array_filter($applications, fn($a) => (int)$a['uploaded'] >= $total_docs));

$doc_types  = ['resume' => 'Resume', 'cover_letter' => 'Cover Letter', 'id_proof' => 'ID Proof', 'aadhaar' => 'Aadhaar'];
$user_initial = strtoupper(substr($user_email, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Job Doc Collector</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- Header -->
<header class="app-header">
    <div class="header-inner">
        <a class="brand" href="dashboard.php">
            <div class="brand-icon"><i class="fa-solid fa-file-shield"></i></div>
            <div>
                <span class="brand-text">Job Doc Collector</span>
                <span class="brand-sub">Hiring Manager Portal</span>
            </div>
        </a>
        <div class="header-right">
            <div class="header-user">
                <div class="avatar"><?= $user_initial ?></div>
                <span style="display:none;"> <?= htmlspecialchars($user_email) ?></span>
            </div>
            <a href="logout.php" class="btn-nav"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
</header>

<!-- Page -->
<div class="page">

    <!-- Page header -->
    <div class="page-head">
        <div>
            <h1 class="page-title">Applications</h1>
            <p class="page-subtitle">Manage candidate documents and track submission progress</p>
        </div>
        <a href="create_application.php" class="btn btn-primary btn-lg">
            <i class="fa-solid fa-plus"></i> New Application
        </a>
    </div>

    <!-- Stats row -->
    <div class="stats-grid">
        <div class="stat-card fade-up" style="animation-delay:.05s">
            <div class="stat-icon si-primary"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="stat-num"><?= $total_apps ?></div>
                <div class="stat-lbl">Total Applications</div>
            </div>
        </div>
        <div class="stat-card fade-up" style="animation-delay:.1s">
            <div class="stat-icon si-success"><i class="fa-solid fa-check-double"></i></div>
            <div>
                <div class="stat-num"><?= $complete_apps ?></div>
                <div class="stat-lbl">Fully Submitted</div>
            </div>
        </div>
        <div class="stat-card fade-up" style="animation-delay:.15s">
            <div class="stat-icon si-warning"><i class="fa-solid fa-file-arrow-up"></i></div>
            <div>
                <div class="stat-num"><?= $total_uploads ?></div>
                <div class="stat-lbl">Documents Collected</div>
            </div>
        </div>
        <div class="stat-card fade-up" style="animation-delay:.2s">
            <div class="stat-icon si-neutral"><i class="fa-solid fa-file-pdf"></i></div>
            <div>
                <div class="stat-num"><?= $total_reports ?></div>
                <div class="stat-lbl">Reports Generated</div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <?php if (!empty($applications)): ?>
    <div style="margin-bottom:1.25rem;position:relative;max-width:380px;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--text-3);font-size:.85rem;"></i>
        <input type="text" class="form-control" id="search-input"
               placeholder="Search by name, email or role…"
               style="padding-left:2.4rem;" oninput="filterApps()">
    </div>
    <?php endif; ?>

    <!-- Application cards -->
    <?php if (empty($applications)): ?>
    <div class="empty-state fade-up" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:4rem 2rem;text-align:center;">
        <div style="width:64px;height:64px;background:var(--primary-light);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:1.75rem;margin-bottom:1rem;color:var(--primary);">
            <i class="fa-solid fa-folder-open"></i>
        </div>
        <h3 style="font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:.4rem;">No applications yet</h3>
        <p style="color:var(--text-2);font-size:.9rem;margin-bottom:1.5rem;">Create your first application and send the magic link to a candidate.</p>
        <a href="create_application.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Application</a>
    </div>

    <?php else: ?>
    <div id="apps-list">
    <?php foreach ($applications as $i => $app):
        $aid        = $app['id'];
        $uploaded   = (int)$app['uploaded'];
        $pct        = round(($uploaded / $total_docs) * 100);
        $complete   = $uploaded >= $total_docs;
        $info       = $app_info[$aid];
        $up_types   = $info['up_types'];
        $aadhaar_doc  = $info['aadhaar_doc'];
        $aadhaar_data = $info['aadhaar_data'];
        $resume_data  = $info['resume_data'];
        $resume_doc   = $info['resume_doc'];
        $pdf          = $info['pdf'];

        $initials = strtoupper(substr($app['candidate_name'], 0, 1));
        $nameParts = explode(' ', $app['candidate_name']);
        if (count($nameParts) > 1) $initials = strtoupper($nameParts[0][0] . end($nameParts)[0]);

        // Avatar gradient by index
        $gradients = [
            'linear-gradient(135deg,#4F46E5,#818CF8)',
            'linear-gradient(135deg,#059669,#34D399)',
            'linear-gradient(135deg,#D97706,#FBBF24)',
            'linear-gradient(135deg,#DC2626,#F87171)',
            'linear-gradient(135deg,#7C3AED,#A78BFA)',
            'linear-gradient(135deg,#0891B2,#67E8F9)',
        ];
        $grad = $gradients[$i % count($gradients)];
    ?>
    <div class="app-card fade-up" style="animation-delay:<?= $i * .04 ?>s"
         data-name="<?= strtolower(htmlspecialchars($app['candidate_name'])) ?>"
         data-email="<?= strtolower(htmlspecialchars($app['candidate_email'])) ?>"
         data-role="<?= strtolower(htmlspecialchars($app['role'])) ?>">

        <!-- Clickable header -->
        <div class="app-card-head" onclick="location.href='application_detail.php?id=<?= $aid ?>'">
            <div style="display:flex;align-items:center;gap:.9rem;flex:1;min-width:0;">
                <div class="cand-avatar" style="background:<?= $grad ?>;"><?= $initials ?></div>
                <div class="cand-info" style="min-width:0;">
                    <h3><?= htmlspecialchars($app['candidate_name']) ?></h3>
                    <p>
                        <i class="fa-regular fa-envelope"></i>
                        <?= htmlspecialchars($app['candidate_email']) ?>
                        &nbsp;·&nbsp;
                        <i class="fa-solid fa-briefcase"></i>
                        <?= htmlspecialchars($app['role']) ?>
                        &nbsp;·&nbsp;
                        <i class="fa-regular fa-calendar"></i>
                        <?= date('d M Y', strtotime($app['created_at'])) ?>
                    </p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:1rem;flex-shrink:0;">
                <div style="display:flex;align-items:center;gap:.55rem;">
                    <div class="progress" style="width:80px;">
                        <div class="progress-bar <?= $complete ? 'done' : '' ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span style="font-size:.78rem;font-weight:600;color:<?= $complete ? 'var(--success)' : 'var(--text-2)' ?>;">
                        <?= $uploaded ?>/<?= $total_docs ?>
                    </span>
                </div>
                <?php if ($complete): ?>
                    <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Complete</span>
                <?php else: ?>
                    <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> In Progress</span>
                <?php endif; ?>
                <i class="fa-solid fa-chevron-right" style="color:var(--text-3);font-size:.75rem;"></i>
            </div>
        </div>

        <!-- Detail row -->
        <div class="app-card-body">

            <!-- Doc pills -->
            <div class="detail-block">
                <label><i class="fa-solid fa-paperclip" style="margin-right:.3rem;"></i>Documents</label>
                <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
                    <?php foreach ($doc_types as $type => $label): ?>
                        <?php $done = in_array($type, $up_types); ?>
                        <span class="pill <?= $done ? 'pill-done' : 'pill-missing' ?>">
                            <i class="fa-solid <?= $done ? 'fa-check' : 'fa-xmark' ?>"></i>
                            <?= $label ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Aadhaar blur -->
            <div class="detail-block">
                <label><i class="fa-solid fa-id-card" style="margin-right:.3rem;"></i>Aadhaar Status</label>
                <?php if ($aadhaar_doc):
                    $bs = $aadhaar_doc['blur_status'];
                    $ps = $aadhaar_doc['processed_status'];
                    if ($bs === 'clear')      echo '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Clear</span>';
                    elseif ($bs === 'blurry') echo '<span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Blurry</span>';
                    else                      echo '<span class="badge badge-neutral"><i class="fa-solid fa-minus"></i> ' . ucfirst($bs) . '</span>';
                    echo '&nbsp;';
                    if ($ps === 'done')        echo '<span class="badge badge-success"><i class="fa-solid fa-brain"></i> Extracted</span>';
                    elseif ($ps === 'failed')  echo '<span class="badge badge-danger">Failed</span>';
                endif; ?>
                <?php if (!$aadhaar_doc): ?>
                    <span class="badge badge-neutral"><i class="fa-solid fa-xmark"></i> Not uploaded</span>
                <?php endif; ?>
            </div>

            <!-- Aadhaar data -->
            <div class="detail-block">
                <label><i class="fa-solid fa-fingerprint" style="margin-right:.3rem;"></i>Aadhaar Data</label>
                <?php if ($aadhaar_data): ?>
                    <div class="dbval" style="font-weight:600;"><?= htmlspecialchars($aadhaar_data['name']) ?></div>
                    <div style="font-size:.78rem;color:var(--text-2);margin-top:.15rem;">
                        <?= chunk_split(htmlspecialchars($aadhaar_data['aadhaar_number']), 4, ' ') ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($aadhaar_data['dob']) ?>
                    </div>
                <?php elseif ($aadhaar_doc && $aadhaar_doc['processed_status'] === 'failed'): ?>
                    <span class="badge badge-danger">Extraction failed</span>
                <?php elseif ($aadhaar_doc): ?>
                    <span class="badge badge-neutral">Pending</span>
                <?php else: ?>
                    <span style="font-size:.82rem;color:var(--text-3);">—</span>
                <?php endif; ?>
            </div>

            <!-- Resume data -->
            <div class="detail-block">
                <label><i class="fa-solid fa-file-lines" style="margin-right:.3rem;"></i>Resume Data</label>
                <?php if ($resume_data): ?>
                    <div class="dbval" style="font-weight:600;"><?= htmlspecialchars($resume_data['name']) ?></div>
                    <div style="font-size:.78rem;color:var(--text-2);margin-top:.12rem;">
                        <?= htmlspecialchars($resume_data['email']) ?>
                    </div>
                    <?php if ($resume_data['latest_role'] || $resume_data['latest_company']): ?>
                    <div style="font-size:.78rem;color:var(--text-2);margin-top:.1rem;">
                        <?= htmlspecialchars($resume_data['latest_role']) ?>
                        <?php if ($resume_data['latest_company']): ?>
                            <span style="color:var(--text-3);">@ <?= htmlspecialchars($resume_data['latest_company']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($resume_data['skills']): ?>
                    <div style="font-size:.72rem;color:var(--primary);margin-top:.2rem;line-height:1.4;">
                        <?= htmlspecialchars(mb_substr($resume_data['skills'], 0, 70)) ?><?= strlen($resume_data['skills']) > 70 ? '…' : '' ?>
                    </div>
                    <?php endif; ?>
                <?php elseif ($resume_doc && $resume_doc['processed_status'] === 'failed'): ?>
                    <span class="badge badge-danger">Extraction failed</span>
                <?php elseif ($resume_doc): ?>
                    <span class="badge badge-neutral">Pending</span>
                <?php else: ?>
                    <span style="font-size:.82rem;color:var(--text-3);">—</span>
                <?php endif; ?>
            </div>

            <!-- PDF Report -->
            <div class="detail-block">
                <label><i class="fa-solid fa-file-pdf" style="margin-right:.3rem;"></i>PDF Report</label>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.15rem;">
                    <?php if ($aadhaar_doc && $aadhaar_doc['processed_status'] === 'done'): ?>
                        <a class="btn btn-primary btn-sm" href="generate_report.php?id=<?= $aid ?>">
                            <i class="fa-solid fa-rotate"></i> Generate
                        </a>
                    <?php endif; ?>
                    <?php if ($pdf): ?>
                        <a class="btn btn-outline btn-sm" href="../<?= htmlspecialchars($pdf['pdf_path']) ?>" download>
                            <i class="fa-solid fa-download"></i> Download
                        </a>
                    <?php endif; ?>
                    <?php if (!$aadhaar_doc || $aadhaar_doc['processed_status'] !== 'done'): ?>
                        <span style="font-size:.8rem;color:var(--text-3);">Needs Aadhaar</span>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <div id="no-results" style="display:none;text-align:center;padding:3rem;color:var(--text-2);
         background:var(--surface);border-radius:var(--r);border:1px solid var(--border);">
        <i class="fa-solid fa-magnifying-glass" style="font-size:1.5rem;margin-bottom:.5rem;opacity:.4;display:block;"></i>
        No applications match your search.
    </div>
    <?php endif; ?>

</div><!-- .page -->

<script>
function filterApps() {
    const q = document.getElementById('search-input').value.toLowerCase().trim();
    const cards = document.querySelectorAll('#apps-list .app-card');
    let visible = 0;
    cards.forEach(c => {
        const match = !q
            || c.dataset.name.includes(q)
            || c.dataset.email.includes(q)
            || c.dataset.role.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('no-results').style.display = visible ? 'none' : 'block';
}
</script>

</body>
</html>
