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

// Fetch resume extracted data if available
$resume_doc = null;
foreach ($documents as $d) {
    if ($d['document_type'] === 'resume') { $resume_doc = $d; break; }
}
$resume_data = null;
if ($resume_doc) {
    $resume_data = pg_fetch_assoc(pg_query_params($conn,
        "SELECT * FROM resume_data WHERE document_id = $1",
        [$resume_doc['id']]
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

        .aadhaar-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem 1.5rem;
        }

        .aadhaar-grid .info-item.full { grid-column: 1 / -1; }

        .field-view { font-size: 0.95rem; color: #333; }

        .field-edit {
            display: none;
            width: 100%;
            padding: 0.35rem 0.5rem;
            border: 1px solid #4a90e2;
            border-radius: 4px;
            font-size: 0.9rem;
            outline: none;
        }

        .edit-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }

        .btn-edit {
            padding: 0.4rem 0.9rem;
            background: #fff;
            color: #4a90e2;
            border: 1px solid #4a90e2;
            border-radius: 5px;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .btn-save {
            display: none;
            padding: 0.4rem 0.9rem;
            background: #4a90e2;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .btn-cancel {
            display: none;
            padding: 0.4rem 0.9rem;
            background: #fff;
            color: #888;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .save-msg {
            font-size: 0.82rem;
            margin-top: 0.5rem;
            display: none;
        }

        .save-msg.success { color: #27ae60; }
        .save-msg.error   { color: #c0392b; }

        .badge-pending  { background: #fff8e1; color: #f39c12; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; }
        .badge-failed   { background: #fdecea; color: #c0392b; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; }
        .badge-done     { background: #e6f9ef; color: #27ae60; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; }
        .badge-skipped  { background: #f0f0f0; color: #888;    padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; }
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

    <!-- Resume Extracted Data -->
    <?php if ($resume_doc): ?>
    <div class="card">
        <h2>Resume Extracted Data
            <?php
                $rs = $resume_doc['processed_status'];
                if ($rs === 'done')       echo '<span class="badge-done" style="margin-left:0.5rem;">✔ Verified</span>';
                elseif ($rs === 'failed') echo '<span class="badge-failed" style="margin-left:0.5rem;">✗ Failed</span>';
                else                     echo '<span class="badge-pending" style="margin-left:0.5rem;">Pending</span>';
            ?>
        </h2>

        <?php if ($resume_data): ?>
        <div class="info-grid">
            <div class="info-item">
                <label>Name</label>
                <span><?= htmlspecialchars($resume_data['name']) ?></span>
            </div>
            <div class="info-item">
                <label>Email</label>
                <span><?= htmlspecialchars($resume_data['email']) ?></span>
            </div>
            <div class="info-item">
                <label>Phone</label>
                <span><?= htmlspecialchars($resume_data['phone']) ?></span>
            </div>
            <div class="info-item">
                <label>Education</label>
                <span><?= htmlspecialchars($resume_data['education']) ?></span>
            </div>
            <div class="info-item" style="grid-column:1/-1;">
                <label>Skills</label>
                <span><?= htmlspecialchars($resume_data['skills']) ?></span>
            </div>
            <?php if ($resume_data['address']): ?>
            <div class="info-item" style="grid-column:1/-1;">
                <label>Address</label>
                <span><?= htmlspecialchars($resume_data['address']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($resume_data['linkedin']): ?>
            <div class="info-item">
                <label>LinkedIn</label>
                <span><?= htmlspecialchars($resume_data['linkedin']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($resume_data['github']): ?>
            <div class="info-item">
                <label>GitHub</label>
                <span><?= htmlspecialchars($resume_data['github']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($resume_data['latest_company'] || $resume_data['latest_role']): ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #eee;">
            <p style="font-size:0.8rem;color:#888;margin-bottom:0.6rem;text-transform:uppercase;letter-spacing:0.04em;">Latest Experience</p>
            <div class="info-grid">
                <div class="info-item">
                    <label>Company</label>
                    <span><?= htmlspecialchars($resume_data['latest_company']) ?></span>
                </div>
                <div class="info-item">
                    <label>Role</label>
                    <span><?= htmlspecialchars($resume_data['latest_role']) ?></span>
                </div>
                <div class="info-item">
                    <label>Start Date</label>
                    <span><?= htmlspecialchars($resume_data['latest_start_date']) ?></span>
                </div>
                <div class="info-item">
                    <label>End Date</label>
                    <span><?= htmlspecialchars($resume_data['latest_end_date']) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <p style="font-size:0.9rem;color:#888;">
            <?= $rs === 'failed' ? 'Extraction failed or validation errors. You can enter data manually below.' : 'Data not yet extracted.' ?>
        </p>
        <!-- Manual entry form -->
        <form style="margin-top:1rem;" onsubmit="saveResumeManual(event, <?= $resume_doc['id'] ?>)">
            <div class="info-grid" style="margin-bottom:1rem;">
                <div class="info-item"><label>Name</label><input class="field-edit" style="display:block;" id="rm-name" placeholder="Full Name" required></div>
                <div class="info-item"><label>Email</label><input class="field-edit" style="display:block;" id="rm-email" placeholder="email@example.com" required></div>
                <div class="info-item"><label>Phone</label><input class="field-edit" style="display:block;" id="rm-phone" placeholder="+91 XXXXX XXXXX" required></div>
                <div class="info-item"><label>Education</label><input class="field-edit" style="display:block;" id="rm-education" placeholder="Degree, Institution, Year"></div>
                <div class="info-item" style="grid-column:1/-1;"><label>Skills</label><input class="field-edit" style="display:block;" id="rm-skills" placeholder="PHP, JavaScript, ..."></div>
                <div class="info-item"><label>Latest Company</label><input class="field-edit" style="display:block;" id="rm-latest_company" placeholder="Company Name" required></div>
                <div class="info-item"><label>Latest Role</label><input class="field-edit" style="display:block;" id="rm-latest_role" placeholder="Job Title" required></div>
                <div class="info-item"><label>Start Date</label><input class="field-edit" style="display:block;" id="rm-latest_start_date" placeholder="Jan 2022"></div>
                <div class="info-item"><label>End Date</label><input class="field-edit" style="display:block;" id="rm-latest_end_date" placeholder="Dec 2024 or Present"></div>
                <div class="info-item" style="grid-column:1/-1;"><label>Address</label><input class="field-edit" style="display:block;" id="rm-address" placeholder="City, State, Country"></div>
                <div class="info-item"><label>LinkedIn</label><input class="field-edit" style="display:block;" id="rm-linkedin" placeholder="linkedin.com/in/username"></div>
                <div class="info-item"><label>GitHub</label><input class="field-edit" style="display:block;" id="rm-github" placeholder="github.com/username"></div>
            </div>
            <button type="submit" class="btn-save" style="display:inline-block;">Save Manually</button>
            <p class="save-msg" id="resume-save-msg"></p>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Aadhaar Extracted Data -->
    <?php if ($aadhaar_doc): ?>
    <div class="card">
        <h2>Aadhaar Extracted Data
            <?php
                $status = $aadhaar_doc['processed_status'];
                if ($status === 'done')         echo '<span class="badge-done" style="margin-left:0.5rem;">✔ Verified</span>';
                elseif ($status === 'failed')   echo '<span class="badge-failed" style="margin-left:0.5rem;">✗ Failed</span>';
                elseif ($status === 'skipped')  echo '<span class="badge-skipped" style="margin-left:0.5rem;">— Skipped</span>';
                else                            echo '<span class="badge-pending" style="margin-left:0.5rem;">Pending</span>';
            ?>
        </h2>

        <?php if ($aadhaar_data): ?>
        <div class="aadhaar-grid">
            <div class="info-item">
                <label>Aadhaar Number</label>
                <span class="field-view" id="view-aadhaar_number"><?= htmlspecialchars($aadhaar_data['aadhaar_number']) ?></span>
                <input class="field-edit" id="edit-aadhaar_number" type="text" value="<?= htmlspecialchars($aadhaar_data['aadhaar_number']) ?>" placeholder="XXXX XXXX XXXX">
            </div>
            <div class="info-item">
                <label>Name</label>
                <span class="field-view" id="view-name"><?= htmlspecialchars($aadhaar_data['name']) ?></span>
                <input class="field-edit" id="edit-name" type="text" value="<?= htmlspecialchars($aadhaar_data['name']) ?>" placeholder="Full Name">
            </div>
            <div class="info-item">
                <label>Date of Birth</label>
                <span class="field-view" id="view-dob"><?= htmlspecialchars($aadhaar_data['dob']) ?></span>
                <input class="field-edit" id="edit-dob" type="text" value="<?= htmlspecialchars($aadhaar_data['dob']) ?>" placeholder="DD/MM/YYYY">
            </div>
        </div>

        <div class="edit-actions">
            <button class="btn-edit" onclick="startEdit()">✎ Edit</button>
            <button class="btn-save" onclick="saveEdit(<?= $aadhaar_data['id'] ?>)">Save</button>
            <button class="btn-cancel" onclick="cancelEdit()">Cancel</button>
        </div>
        <p class="save-msg" id="save-msg"></p>

        <?php else: ?>
        <p style="font-size:0.9rem;color:#888;">
            <?php if ($status === 'failed'): ?>
                OCR extraction failed or data could not be validated. You can enter the data manually below.
            <?php elseif ($status === 'skipped'): ?>
                Blur check was skipped (PDF). Data not yet extracted. You can enter the data manually below.
            <?php else: ?>
                Data not yet extracted.
            <?php endif; ?>
        </p>

        <!-- Manual entry form -->
        <form id="manual-form" style="margin-top:1rem;" onsubmit="saveManual(event, <?= $aadhaar_doc['id'] ?>)">
            <div class="aadhaar-grid" style="margin-bottom:1rem;">
                <div class="info-item">
                    <label>Aadhaar Number</label>
                    <input class="field-edit" style="display:block;" id="manual-aadhaar_number" type="text" placeholder="XXXX XXXX XXXX" required>
                </div>
                <div class="info-item">
                    <label>Name</label>
                    <input class="field-edit" style="display:block;" id="manual-name" type="text" placeholder="Full Name" required>
                </div>
                <div class="info-item">
                    <label>Date of Birth</label>
                    <input class="field-edit" style="display:block;" id="manual-dob" type="text" placeholder="DD/MM/YYYY" required>
                </div>
            </div>
            <button type="submit" class="btn-save" style="display:inline-block;">Save Manually</button>
            <p class="save-msg" id="save-msg"></p>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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

function startEdit() {
    ['aadhaar_number', 'name', 'dob'].forEach(f => {
        document.getElementById('view-' + f).style.display = 'none';
        document.getElementById('edit-' + f).style.display = 'block';
    });
    document.querySelector('.btn-edit').style.display  = 'none';
    document.querySelector('.btn-save').style.display  = 'inline-block';
    document.querySelector('.btn-cancel').style.display = 'inline-block';
}

function cancelEdit() {
    ['aadhaar_number', 'name', 'dob'].forEach(f => {
        const view = document.getElementById('view-' + f);
        const edit = document.getElementById('edit-' + f);
        edit.value = view.textContent.trim();
        view.style.display = 'block';
        edit.style.display  = 'none';
    });
    document.querySelector('.btn-edit').style.display   = 'inline-block';
    document.querySelector('.btn-save').style.display   = 'none';
    document.querySelector('.btn-cancel').style.display = 'none';
    document.getElementById('save-msg').style.display   = 'none';
}

async function saveEdit(aadhaar_data_id) {
    const btn = document.querySelector('.btn-save');
    const msg = document.getElementById('save-msg');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const body = new FormData();
    body.append('aadhaar_data_id', aadhaar_data_id);
    body.append('aadhaar_number',  document.getElementById('edit-aadhaar_number').value.trim());
    body.append('name',            document.getElementById('edit-name').value.trim());
    body.append('dob',             document.getElementById('edit-dob').value.trim());

    const res  = await fetch('update_aadhaar_data.php', { method: 'POST', body });
    const text = await res.text();

    btn.disabled = false;
    btn.textContent = 'Save';

    if (text.trim() === 'ok') {
        ['aadhaar_number', 'name', 'dob'].forEach(f => {
            const val = document.getElementById('edit-' + f).value.trim();
            document.getElementById('view-' + f).textContent = val;
        });
        cancelEdit();
        msg.className = 'save-msg success';
        msg.textContent = '✔ Data updated successfully.';
        msg.style.display = 'block';
        setTimeout(() => msg.style.display = 'none', 3000);
    } else {
        msg.className = 'save-msg error';
        msg.textContent = text.trim() || 'Failed to save. Please try again.';
        msg.style.display = 'block';
    }
}

async function saveResumeManual(e, document_id) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const msg = document.getElementById('resume-save-msg');
    btn.disabled = true; btn.textContent = 'Saving...';

    const body = new FormData();
    body.append('document_id',        document_id);
    ['name','email','phone','skills','education',
     'latest_company','latest_role','latest_start_date','latest_end_date',
     'address','linkedin','github'].forEach(f => {
        const el = document.getElementById('rm-' + f);
        if (el) body.append(f, el.value.trim());
    });

    const res  = await fetch('update_resume_data.php', { method: 'POST', body });
    const text = await res.text();
    btn.disabled = false; btn.textContent = 'Save Manually';

    if (text.trim() === 'ok') {
        location.reload();
    } else {
        msg.className = 'save-msg error';
        msg.textContent = text.trim() || 'Failed to save.';
        msg.style.display = 'block';
    }
}

async function saveManual(e, document_id) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const msg = document.getElementById('save-msg');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const body = new FormData();
    body.append('document_id',    document_id);
    body.append('aadhaar_number', document.getElementById('manual-aadhaar_number').value.trim());
    body.append('name',           document.getElementById('manual-name').value.trim());
    body.append('dob',            document.getElementById('manual-dob').value.trim());

    const res  = await fetch('update_aadhaar_data.php', { method: 'POST', body });
    const text = await res.text();

    btn.disabled = false;
    btn.textContent = 'Save Manually';

    if (text.trim() === 'ok') {
        location.reload();
    } else {
        msg.className = 'save-msg error';
        msg.textContent = text.trim() || 'Failed to save. Please try again.';
        msg.style.display = 'block';
    }
}
</script>

</body>
</html>
