<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("Invalid application ID.");

$result      = pg_query_params($conn, "SELECT * FROM applications WHERE id = \$1 AND created_by = \$2",
    [$id, $_SESSION['user_id']]);
$application = pg_fetch_assoc($result);
if (!$application) die("Application not found.");

$doc_result = pg_query_params($conn, "SELECT * FROM documents WHERE application_id = \$1 ORDER BY uploaded_at ASC", [$id]);
$documents  = pg_fetch_all($doc_result) ?: [];
$uploaded_types = array_column($documents, 'document_type');

$doc_types  = ['resume', 'cover_letter', 'id_proof'];
$doc_labels = ['resume' => 'Resume', 'cover_letter' => 'Cover Letter', 'id_proof' => 'ID Proof'];

$magic_link = 'http://localhost/php/job-doc-collector/pages/upload.php?token=' . urlencode($application['token']);

$aadhaar_doc = null;
foreach ($documents as $d) { if ($d['document_type'] === 'aadhaar') { $aadhaar_doc = $d; break; } }
$aadhaar_data = $aadhaar_doc
    ? pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM aadhaar_data WHERE document_id = \$1", [$aadhaar_doc['id']]))
    : null;

$resume_doc = null;
foreach ($documents as $d) { if ($d['document_type'] === 'resume') { $resume_doc = $d; break; } }
$resume_data = $resume_doc
    ? pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM resume_data WHERE document_id = \$1", [$resume_doc['id']]))
    : null;

$pdf_report = $aadhaar_doc
    ? pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM pdf_reports WHERE document_id = \$1", [$aadhaar_doc['id']]))
    : null;

$report_generated = isset($_GET['report']) && $_GET['report'] === 'generated';

$user_email   = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr($user_email, 0, 1));

$all_doc_types = ['resume', 'cover_letter', 'id_proof', 'aadhaar'];
$uploaded_count = count($uploaded_types);
$pct = round(($uploaded_count / 4) * 100);

$initials = strtoupper(substr($application['candidate_name'], 0, 1));
$nameParts = explode(' ', $application['candidate_name']);
if (count($nameParts) > 1) $initials = strtoupper($nameParts[0][0] . end($nameParts)[0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($application['candidate_name']) ?> – Job Doc Collector</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .doc-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .85rem 0;
            border-bottom: 1px solid var(--border);
            gap: 1rem;
        }
        .doc-row:last-child { border-bottom: none; }
        .doc-row-left { display: flex; align-items: center; gap: .75rem; }
        .doc-type-icon {
            width: 36px; height: 36px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem;
            flex-shrink: 0;
        }
        .hero-card {
            background: linear-gradient(135deg, #4338CA 0%, #6366F1 100%);
            border-radius: var(--r-lg);
            padding: 1.75rem;
            color: #fff;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .hero-card::before {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,.07);
            top: -60px; right: -60px;
        }
        .hero-card::after {
            content: '';
            position: absolute;
            width: 120px; height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
            bottom: -30px; right: 80px;
        }
        .hero-avatar {
            width: 60px; height: 60px;
            border-radius: 14px;
            background: rgba(255,255,255,.2);
            border: 2px solid rgba(255,255,255,.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; font-weight: 800; color: #fff;
            flex-shrink: 0;
        }
        .hero-name { font-size: 1.3rem; font-weight: 800; letter-spacing: -.02em; }
        .hero-meta { font-size: .83rem; opacity: .8; margin-top: .25rem; display: flex; gap: .75rem; flex-wrap: wrap; }
        .hero-meta span { display: flex; align-items: center; gap: .3rem; }
        .hero-progress-wrap { margin-top: 1.25rem; }
        .hero-progress-label { font-size: .78rem; opacity: .75; margin-bottom: .4rem; display: flex; justify-content: space-between; }
        .hero-progress { height: 7px; background: rgba(255,255,255,.2); border-radius: 99px; overflow: hidden; }
        .hero-progress-bar { height: 100%; background: rgba(255,255,255,.9); border-radius: 99px; transition: width .5s ease; }
    </style>
</head>
<body>

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
            </div>
            <a href="dashboard.php" class="btn-nav"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>
</header>

<div class="page-md">

    <!-- Breadcrumb -->
    <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--text-2);margin-bottom:1.5rem;">
        <a href="dashboard.php" style="color:var(--text-2);text-decoration:none;">Dashboard</a>
        <i class="fa-solid fa-chevron-right" style="font-size:.65rem;color:var(--text-3);"></i>
        <span style="color:var(--text);"><?= htmlspecialchars($application['candidate_name']) ?></span>
    </div>

    <?php if ($report_generated): ?>
    <div class="alert alert-success fade-up" style="margin-bottom:1.25rem;">
        <i class="fa-solid fa-circle-check" style="flex-shrink:0;"></i>
        PDF report generated successfully.
    </div>
    <?php endif; ?>

    <!-- Hero Card -->
    <div class="hero-card fade-up">
        <div style="display:flex;align-items:flex-start;gap:1.1rem;position:relative;z-index:1;">
            <div class="hero-avatar"><?= $initials ?></div>
            <div style="flex:1;min-width:0;">
                <div class="hero-name"><?= htmlspecialchars($application['candidate_name']) ?></div>
                <div class="hero-meta">
                    <span><i class="fa-regular fa-envelope"></i><?= htmlspecialchars($application['candidate_email']) ?></span>
                    <span><i class="fa-solid fa-briefcase"></i><?= htmlspecialchars($application['role']) ?></span>
                    <span><i class="fa-regular fa-calendar"></i><?= date('d M Y', strtotime($application['created_at'])) ?></span>
                </div>
            </div>
            <?php if ($aadhaar_doc && $aadhaar_doc['processed_status'] === 'done'): ?>
            <a href="generate_report.php?id=<?= $id ?>"
               style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);
                      color:#fff;padding:.45rem 1rem;border-radius:var(--r-sm);
                      font-size:.82rem;font-weight:600;text-decoration:none;
                      display:flex;align-items:center;gap:.4rem;white-space:nowrap;
                      transition:all .2s;backdrop-filter:blur(4px);flex-shrink:0;"
               onmouseover="this.style.background='rgba(255,255,255,.3)'"
               onmouseout="this.style.background='rgba(255,255,255,.2)'">
                <i class="fa-solid fa-file-pdf"></i> Generate PDF
            </a>
            <?php endif; ?>
        </div>
        <div class="hero-progress-wrap" style="position:relative;z-index:1;">
            <div class="hero-progress-label">
                <span>Document Submission Progress</span>
                <span><?= $uploaded_count ?>/4 uploaded</span>
            </div>
            <div class="hero-progress">
                <div class="hero-progress-bar" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Document Status -->
    <div class="card fade-up" style="animation-delay:.05s;">
        <div class="card-head">
            <div class="card-title">
                <div class="card-icon"><i class="fa-solid fa-paperclip"></i></div>
                Uploaded Documents
            </div>
            <?php if ($uploaded_count < 4): ?>
            <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> <?= 4 - $uploaded_count ?> pending</span>
            <?php else: ?>
            <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> All uploaded</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php
            $all_labels = ['resume' => ['Resume','fa-file-lines','si-primary'], 'cover_letter' => ['Cover Letter','fa-envelope-open-text','si-neutral'], 'id_proof' => ['ID Proof','fa-id-card','si-warning'], 'aadhaar' => ['Aadhaar Card','fa-fingerprint','si-success']];
            foreach ($all_labels as $type => [$label, $icon, $ic_cls]):
                $doc = null;
                foreach ($documents as $d) { if ($d['document_type'] === $type) { $doc = $d; break; } }
            ?>
            <div class="doc-row">
                <div class="doc-row-left">
                    <div class="doc-type-icon <?= $ic_cls ?>" style="background:var(--<?= str_replace(['si-primary','si-success','si-warning','si-neutral'],['primary-light','success-light','warning-light','bg'], $ic_cls) ?>);
                         color:var(--<?= str_replace(['si-primary','si-success','si-warning','si-neutral'],['primary','success','warning','text-2'], $ic_cls) ?>);">
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div>
                        <div style="font-size:.9rem;font-weight:600;color:var(--text);"><?= $label ?></div>
                        <?php if ($doc): ?>
                        <div style="font-size:.75rem;color:var(--text-3);">
                            Uploaded <?= date('d M Y, H:i', strtotime($doc['uploaded_at'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <?php if ($doc): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Uploaded</span>
                        <a class="btn btn-ghost btn-sm" href="../<?= htmlspecialchars($doc['file_url']) ?>" download>
                            <i class="fa-solid fa-download"></i> Download
                        </a>
                    <?php else: ?>
                        <span class="badge badge-neutral"><i class="fa-solid fa-xmark"></i> Missing</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Resume Extracted Data -->
    <?php if ($resume_doc): ?>
    <div class="card fade-up" style="animation-delay:.1s;">
        <div class="card-head">
            <div class="card-title">
                <div class="card-icon" style="background:#EFF6FF;color:#2563EB;"><i class="fa-solid fa-file-lines"></i></div>
                Resume Extracted Data
            </div>
            <?php
                $rs = $resume_doc['processed_status'];
                if ($rs === 'done')       echo '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Verified</span>';
                elseif ($rs === 'failed') echo '<span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Failed</span>';
                else                     echo '<span class="badge badge-neutral"><i class="fa-solid fa-clock"></i> Pending</span>';
            ?>
        </div>
        <div class="card-body">
        <?php if ($resume_data): ?>
            <!-- Personal Info -->
            <div class="section-label">Personal Information</div>
            <div class="info-grid" style="margin-bottom:1.25rem;">
                <div><span class="info-label">Full Name</span><span class="info-val"><?= htmlspecialchars($resume_data['name']) ?></span></div>
                <div><span class="info-label">Email</span><span class="info-val"><?= htmlspecialchars($resume_data['email']) ?></span></div>
                <div><span class="info-label">Phone</span><span class="info-val"><?= htmlspecialchars($resume_data['phone']) ?></span></div>
                <?php if ($resume_data['address']): ?>
                <div class="full"><span class="info-label">Address</span><span class="info-val"><?= htmlspecialchars($resume_data['address']) ?></span></div>
                <?php endif; ?>
                <?php if ($resume_data['linkedin']): ?>
                <div><span class="info-label">LinkedIn</span>
                    <span class="info-val" style="color:var(--primary);"><?= htmlspecialchars($resume_data['linkedin']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($resume_data['github']): ?>
                <div><span class="info-label">GitHub</span>
                    <span class="info-val" style="color:var(--primary);"><?= htmlspecialchars($resume_data['github']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($resume_data['education']): ?>
            <div class="section-label">Education</div>
            <div style="font-size:.875rem;color:var(--text);margin-bottom:1.25rem;">
                <?= htmlspecialchars($resume_data['education']) ?>
            </div>
            <?php endif; ?>

            <?php if ($resume_data['skills']): ?>
            <div class="section-label">Skills</div>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1.25rem;">
                <?php foreach (explode(',', $resume_data['skills']) as $skill): ?>
                    <?php $s = trim($skill); if ($s): ?>
                    <span style="background:var(--primary-light);color:var(--primary);
                                 padding:.2rem .65rem;border-radius:var(--r-full);
                                 font-size:.75rem;font-weight:600;"><?= htmlspecialchars($s) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($resume_data['latest_company'] || $resume_data['latest_role']): ?>
            <div class="section-label">Latest Experience</div>
            <div style="background:var(--bg);border-radius:var(--r-sm);padding:1rem;border:1px solid var(--border);">
                <div class="info-grid">
                    <div><span class="info-label">Company</span><span class="info-val"><?= htmlspecialchars($resume_data['latest_company']) ?></span></div>
                    <div><span class="info-label">Role</span><span class="info-val"><?= htmlspecialchars($resume_data['latest_role']) ?></span></div>
                    <div><span class="info-label">Start Date</span><span class="info-val"><?= htmlspecialchars($resume_data['latest_start_date']) ?></span></div>
                    <div><span class="info-label">End Date</span><span class="info-val"><?= htmlspecialchars($resume_data['latest_end_date'] ?: 'Present') ?></span></div>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-warning" style="margin-bottom:1.25rem;">
                <i class="fa-solid fa-triangle-exclamation" style="flex-shrink:0;"></i>
                <?= $rs === 'failed' ? 'Extraction failed or validation errors.' : 'Data not yet extracted.' ?>
                You can enter the data manually below.
            </div>

            <!-- Manual entry form -->
            <form onsubmit="saveResumeManual(event, <?= $resume_doc['id'] ?>)">
                <div class="section-label">Personal Information</div>
                <div class="info-grid" style="margin-bottom:1rem;">
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Name *</label>
                        <input class="form-control" id="rm-name" placeholder="Full Name" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Email *</label>
                        <input class="form-control" id="rm-email" placeholder="email@example.com" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Phone *</label>
                        <input class="form-control" id="rm-phone" placeholder="+91 XXXXX XXXXX" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Education</label>
                        <input class="form-control" id="rm-education" placeholder="Degree, Institution, Year"></div>
                    <div class="form-group" style="margin-bottom:0;grid-column:1/-1;"><label class="form-label" style="font-size:.78rem;">Skills</label>
                        <input class="form-control" id="rm-skills" placeholder="PHP, JavaScript, MySQL…"></div>
                    <div class="form-group" style="margin-bottom:0;grid-column:1/-1;"><label class="form-label" style="font-size:.78rem;">Address</label>
                        <input class="form-control" id="rm-address" placeholder="City, State, Country"></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">LinkedIn</label>
                        <input class="form-control" id="rm-linkedin" placeholder="linkedin.com/in/username"></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">GitHub</label>
                        <input class="form-control" id="rm-github" placeholder="github.com/username"></div>
                </div>
                <div class="section-label">Latest Experience</div>
                <div class="info-grid" style="margin-bottom:1.25rem;">
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Company *</label>
                        <input class="form-control" id="rm-latest_company" placeholder="Company Name" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Role *</label>
                        <input class="form-control" id="rm-latest_role" placeholder="Job Title" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Start Date</label>
                        <input class="form-control" id="rm-latest_start_date" placeholder="Jan 2022"></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">End Date</label>
                        <input class="form-control" id="rm-latest_end_date" placeholder="Dec 2024 or Present"></div>
                </div>
                <button type="submit" class="btn btn-primary" id="resume-manual-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Data
                </button>
                <p class="save-feedback" id="resume-save-msg"></p>
            </form>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Aadhaar Extracted Data -->
    <?php if ($aadhaar_doc): ?>
    <div class="card fade-up" style="animation-delay:.15s;">
        <div class="card-head">
            <div class="card-title">
                <div class="card-icon" style="background:#F0FDF4;color:#059669;"><i class="fa-solid fa-fingerprint"></i></div>
                Aadhaar Extracted Data
            </div>
            <?php
                $status = $aadhaar_doc['processed_status'];
                $blur   = $aadhaar_doc['blur_status'];
                if ($status === 'done')        echo '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Verified</span>';
                elseif ($status === 'failed')  echo '<span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Failed</span>';
                else                           echo '<span class="badge badge-neutral"><i class="fa-solid fa-clock"></i> ' . ucfirst($status) . '</span>';
            ?>
        </div>
        <div class="card-body">

        <?php if ($aadhaar_data): ?>
            <div class="info-grid" style="margin-bottom:1.25rem;" id="aadhaar-view">
                <div>
                    <span class="info-label">Aadhaar Number</span>
                    <span class="field-view" id="view-aadhaar_number">
                        <?= htmlspecialchars(implode(' ', str_split($aadhaar_data['aadhaar_number'], 4))) ?>
                    </span>
                    <input class="field-edit" id="edit-aadhaar_number"
                           value="<?= htmlspecialchars($aadhaar_data['aadhaar_number']) ?>" placeholder="XXXX XXXX XXXX">
                </div>
                <div>
                    <span class="info-label">Name on Card</span>
                    <span class="field-view" id="view-name"><?= htmlspecialchars($aadhaar_data['name']) ?></span>
                    <input class="field-edit" id="edit-name"
                           value="<?= htmlspecialchars($aadhaar_data['name']) ?>" placeholder="Full Name">
                </div>
                <div>
                    <span class="info-label">Date of Birth</span>
                    <span class="field-view" id="view-dob"><?= htmlspecialchars($aadhaar_data['dob']) ?></span>
                    <input class="field-edit" id="edit-dob"
                           value="<?= htmlspecialchars($aadhaar_data['dob']) ?>" placeholder="DD/MM/YYYY">
                </div>
                <div>
                    <span class="info-label">Blur Status</span>
                    <span class="info-val">
                        <?php
                        if ($blur === 'clear')   echo '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Clear</span>';
                        elseif ($blur === 'blurry') echo '<span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Blurry</span>';
                        else echo '<span class="badge badge-neutral">' . ucfirst($blur) . '</span>';
                        ?>
                    </span>
                </div>
                <div>
                    <span class="info-label">Extracted On</span>
                    <span class="info-val"><?= date('d M Y', strtotime($aadhaar_data['extracted_at'])) ?></span>
                </div>
            </div>

            <div class="edit-actions">
                <button class="btn btn-ghost btn-sm" onclick="startEdit()" id="btn-edit-aadhaar">
                    <i class="fa-solid fa-pen"></i> Edit
                </button>
                <button class="btn btn-primary btn-sm" onclick="saveEdit(<?= $aadhaar_data['id'] ?>)" id="btn-save-aadhaar" style="display:none;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <button class="btn btn-ghost btn-sm" onclick="cancelEdit()" id="btn-cancel-aadhaar" style="display:none;">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
            </div>
            <p class="save-feedback" id="aadhaar-save-msg"></p>

        <?php else: ?>
            <div class="alert alert-warning" style="margin-bottom:1.25rem;">
                <i class="fa-solid fa-triangle-exclamation" style="flex-shrink:0;"></i>
                <?php
                if ($status === 'failed')     echo 'OCR extraction failed or data could not be validated.';
                elseif ($blur === 'blurry')   echo 'Image is blurry — re-upload a clearer image or enter data manually.';
                elseif ($status === 'skipped'||$blur === 'skipped') echo 'Blur check was skipped (PDF uploaded).';
                else                          echo 'Data not yet extracted.';
                ?>
                Enter the data manually below.
            </div>

            <!-- Manual form -->
            <form id="manual-aadhaar-form" onsubmit="saveManual(event, <?= $aadhaar_doc['id'] ?>)">
                <div class="info-grid" style="margin-bottom:1.25rem;">
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Aadhaar Number *</label>
                        <input class="form-control" id="manual-aadhaar_number" placeholder="XXXX XXXX XXXX" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Name *</label>
                        <input class="form-control" id="manual-name" placeholder="Full Name" required></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label" style="font-size:.78rem;">Date of Birth *</label>
                        <input class="form-control" id="manual-dob" placeholder="DD/MM/YYYY" required></div>
                </div>
                <button type="submit" class="btn btn-primary" id="aadhaar-manual-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Aadhaar Data
                </button>
                <p class="save-feedback" id="aadhaar-manual-msg"></p>
            </form>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- PDF Report -->
    <?php if ($aadhaar_doc && $aadhaar_doc['processed_status'] === 'done'): ?>
    <div class="card fade-up" style="animation-delay:.2s;">
        <div class="card-head">
            <div class="card-title">
                <div class="card-icon" style="background:#FEF2F2;color:#DC2626;"><i class="fa-solid fa-file-pdf"></i></div>
                PDF Report
            </div>
            <?php if ($pdf_report): ?>
            <span class="badge badge-success">
                <i class="fa-solid fa-circle-check"></i>
                Generated <?= date('d M Y', strtotime($pdf_report['generated_at'])) ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--text-2);margin-bottom:1.1rem;">
                Generate a comprehensive PDF report containing candidate information, Aadhaar details, and resume data.
            </p>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <a href="generate_report.php?id=<?= $id ?>" class="btn btn-primary">
                    <i class="fa-solid fa-rotate"></i> Generate &amp; Download
                </a>
                <?php if ($pdf_report): ?>
                <a href="../<?= htmlspecialchars($pdf_report['pdf_path']) ?>" download class="btn btn-outline">
                    <i class="fa-solid fa-download"></i> Last Report
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Magic Link -->
    <div class="card fade-up" style="animation-delay:.25s;">
        <div class="card-head">
            <div class="card-title">
                <div class="card-icon"><i class="fa-solid fa-link"></i></div>
                Magic Upload Link
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--text-2);margin-bottom:.9rem;">
                Share this link with the candidate to let them upload or re-upload documents.
            </p>
            <div class="link-box">
                <input type="text" id="magic-link" value="<?= htmlspecialchars($magic_link) ?>" readonly>
                <button onclick="copyLink()"><i class="fa-regular fa-copy"></i> Copy</button>
            </div>
            <p id="copied-msg" style="font-size:.8rem;color:var(--success);margin-top:.4rem;height:1rem;
               display:flex;align-items:center;gap:.3rem;"></p>
        </div>
    </div>

</div><!-- page-md -->

<script>
/* ── Copy link ── */
function copyLink() {
    document.getElementById('magic-link').select();
    document.execCommand('copy');
    const msg = document.getElementById('copied-msg');
    msg.innerHTML = '<i class="fa-solid fa-circle-check"></i> Copied to clipboard!';
    setTimeout(() => msg.innerHTML = '', 2500);
}

/* ── Aadhaar inline edit ── */
function startEdit() {
    ['aadhaar_number','name','dob'].forEach(f => {
        document.getElementById('view-' + f).style.display = 'none';
        document.getElementById('edit-' + f).style.display = 'block';
    });
    document.getElementById('btn-edit-aadhaar').style.display   = 'none';
    document.getElementById('btn-save-aadhaar').style.display   = 'inline-flex';
    document.getElementById('btn-cancel-aadhaar').style.display = 'inline-flex';
}

function cancelEdit() {
    ['aadhaar_number','name','dob'].forEach(f => {
        const view = document.getElementById('view-' + f);
        const edit = document.getElementById('edit-' + f);
        edit.value = view.textContent.trim().replace(/\s+/g,' ');
        view.style.display = '';
        edit.style.display = 'none';
    });
    document.getElementById('btn-edit-aadhaar').style.display   = 'inline-flex';
    document.getElementById('btn-save-aadhaar').style.display   = 'none';
    document.getElementById('btn-cancel-aadhaar').style.display = 'none';
    const m = document.getElementById('aadhaar-save-msg');
    m.style.display = 'none';
}

async function saveEdit(id) {
    const btn = document.getElementById('btn-save-aadhaar');
    const msg = document.getElementById('aadhaar-save-msg');
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    btn.disabled  = true;

    const body = new FormData();
    body.append('aadhaar_data_id', id);
    body.append('aadhaar_number',  document.getElementById('edit-aadhaar_number').value.trim());
    body.append('name',            document.getElementById('edit-name').value.trim());
    body.append('dob',             document.getElementById('edit-dob').value.trim());

    const text = await fetch('update_aadhaar_data.php', { method:'POST', body }).then(r => r.text());
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
    btn.disabled  = false;

    if (text.trim() === 'ok') {
        ['aadhaar_number','name','dob'].forEach(f => {
            document.getElementById('view-' + f).textContent = document.getElementById('edit-' + f).value.trim();
        });
        cancelEdit();
        msg.className = 'save-feedback ok';
        msg.innerHTML = '<i class="fa-solid fa-circle-check"></i> Data updated successfully.';
        msg.style.display = 'flex';
        setTimeout(() => msg.style.display = 'none', 3000);
    } else {
        msg.className = 'save-feedback err';
        msg.textContent = text.trim() || 'Failed to save. Please try again.';
        msg.style.display = 'block';
    }
}

/* ── Aadhaar manual ── */
async function saveManual(e, document_id) {
    e.preventDefault();
    const btn = document.getElementById('aadhaar-manual-btn');
    const msg = document.getElementById('aadhaar-manual-msg');
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    btn.disabled  = true;

    const body = new FormData();
    body.append('document_id',    document_id);
    body.append('aadhaar_number', document.getElementById('manual-aadhaar_number').value.trim());
    body.append('name',           document.getElementById('manual-name').value.trim());
    body.append('dob',            document.getElementById('manual-dob').value.trim());

    const text = await fetch('update_aadhaar_data.php', { method:'POST', body }).then(r => r.text());
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Aadhaar Data';
    btn.disabled  = false;

    if (text.trim() === 'ok') {
        location.reload();
    } else {
        msg.className = 'save-feedback err';
        msg.textContent = text.trim() || 'Failed to save.';
        msg.style.display = 'block';
    }
}

/* ── Resume manual ── */
async function saveResumeManual(e, document_id) {
    e.preventDefault();
    const btn = document.getElementById('resume-manual-btn');
    const msg = document.getElementById('resume-save-msg');
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    btn.disabled  = true;

    const body = new FormData();
    body.append('document_id', document_id);
    ['name','email','phone','skills','education',
     'latest_company','latest_role','latest_start_date','latest_end_date',
     'address','linkedin','github'].forEach(f => {
        const el = document.getElementById('rm-' + f);
        if (el) body.append(f, el.value.trim());
    });

    const text = await fetch('update_resume_data.php', { method:'POST', body }).then(r => r.text());
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Data';
    btn.disabled  = false;

    if (text.trim() === 'ok') {
        location.reload();
    } else {
        msg.className = 'save-feedback err';
        msg.textContent = text.trim() || 'Failed to save.';
        msg.style.display = 'block';
    }
}
</script>

</body>
</html>
