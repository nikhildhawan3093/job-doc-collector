<?php
require_once '../config/db.php';

$token = $_GET['token'] ?? '';
if (!$token) die("Invalid link.");

$result      = pg_query_params($conn, "SELECT * FROM applications WHERE token = \$1", [$token]);
$application = pg_fetch_assoc($result);
if (!$application) die("Invalid or expired link.");

$doc_result = pg_query_params($conn,
    "SELECT document_type FROM documents WHERE application_id = \$1", [$application['id']]);
$uploaded_types = [];
while ($row = pg_fetch_assoc($doc_result)) $uploaded_types[] = $row['document_type'];

$doc_types = ['resume', 'cover_letter', 'id_proof', 'aadhaar'];
$doc_config = [
    'resume'       => ['Resume',       'fa-file-lines',         'PDF only — max 5 MB',                    '.pdf',                 false, true ],
    'cover_letter' => ['Cover Letter', 'fa-envelope-open-text', 'PDF, JPG or PNG — max 5 MB',             '.pdf,.jpg,.jpeg,.png', false, false],
    'id_proof'     => ['ID Proof',     'fa-id-card',            'PDF, JPG or PNG — max 5 MB',             '.pdf,.jpg,.jpeg,.png', false, false],
    'aadhaar'      => ['Aadhaar Card', 'fa-fingerprint',        'PDF, JPG or PNG — images must be clear', '.pdf,.jpg,.jpeg,.png', true,  false],
];
// [label, icon, hint, accept, required, pdf_only]

$uploaded_count = count($uploaded_types);
$total          = count($doc_types);
$pct            = round(($uploaded_count / $total) * 100);
$first_name     = explode(' ', $application['candidate_name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents – Job Doc Collector</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .upload-hero {
            background: linear-gradient(135deg, #4338CA 0%, #6366F1 100%);
            padding: 2rem 1.5rem 2.5rem;
            position: relative;
            overflow: hidden;
        }
        .upload-hero::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
            top: -120px; right: -80px;
        }
        .upload-hero::after {
            content: '';
            position: absolute;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,.04);
            bottom: -60px; left: -40px;
        }
        .hero-inner {
            max-width: 560px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .hero-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            color: rgba(255,255,255,.8);
            font-size: .82rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }
        .hero-brand-dot {
            width: 28px; height: 28px;
            background: rgba(255,255,255,.18);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem;
        }
        .hero-greeting {
            font-size: 1.55rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -.03em;
            line-height: 1.2;
            margin-bottom: .5rem;
        }
        .hero-role {
            font-size: .875rem;
            color: rgba(255,255,255,.75);
            margin-bottom: 1.25rem;
        }
        .hero-strip {
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: var(--r);
            padding: .9rem 1.1rem;
            backdrop-filter: blur(6px);
        }
        .hero-strip-label {
            font-size: .75rem;
            color: rgba(255,255,255,.75);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            margin-bottom: .5rem;
        }
        .hero-progress {
            height: 7px;
            background: rgba(255,255,255,.2);
            border-radius: 99px;
            overflow: hidden;
        }
        .hero-progress-bar {
            height: 100%;
            border-radius: 99px;
            background: rgba(255,255,255,.85);
            transition: width .5s ease;
        }

        .upload-content {
            max-width: 560px;
            margin: 0 auto;
            padding: 1.5rem 1rem 3rem;
        }

        .doc-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 1.1rem 1.3rem;
            margin-bottom: .85rem;
            box-shadow: var(--shadow-xs);
            transition: border-color .2s, box-shadow .2s;
        }
        .doc-card:hover { border-color: #CBD5E1; box-shadow: var(--shadow-sm); }
        .doc-card-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .doc-card-left { display: flex; align-items: center; gap: .85rem; }
        .doc-icon {
            width: 42px; height: 42px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .doc-name {
            font-size: .92rem;
            font-weight: 700;
            color: var(--text);
            display: flex; align-items: center; gap: .4rem;
            flex-wrap: wrap;
        }
        .req-badge {
            background: #FEF3C7;
            color: #92400E;
            font-size: .65rem;
            font-weight: 700;
            padding: .1rem .45rem;
            border-radius: var(--r-full);
        }
        .doc-hint { font-size: .75rem; color: var(--text-2); margin-top: .1rem; }

        .file-wrap { display: flex; align-items: center; gap: .5rem; }
        .file-input-btn { position: relative; overflow: hidden; }
        .file-input-btn input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            font-size: 0;
        }
        .file-label {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .38rem .85rem;
            background: #F8FAFC;
            border: 1.5px solid var(--border);
            border-radius: var(--r-sm);
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-2);
            cursor: pointer;
            transition: var(--ease);
            white-space: nowrap;
        }
        .file-label:hover { background: var(--border); color: var(--text); }

        .all-done {
            background: var(--success-light);
            border: 1px solid #A7F3D0;
            border-radius: var(--r);
            padding: 2rem;
            text-align: center;
            margin-top: 1rem;
        }
        .all-done .done-icon {
            font-size: 2.5rem;
            color: var(--success);
            display: block;
            margin-bottom: .75rem;
        }
        .all-done h3 { font-size: 1.1rem; font-weight: 800; color: #065F46; margin-bottom: .3rem; }
        .all-done p  { font-size: .875rem; color: #047857; }
    </style>
</head>
<body>

<!-- Hero -->
<div class="upload-hero">
    <div class="hero-inner">
        <div class="hero-brand">
            <div class="hero-brand-dot"><i class="fa-solid fa-file-shield"></i></div>
            Job Doc Collector
        </div>
        <div class="hero-greeting">Hello, <?= htmlspecialchars($first_name) ?>! 👋</div>
        <div class="hero-role">
            Submitting documents for
            <strong style="color:#fff;"><?= htmlspecialchars($application['role']) ?></strong>
        </div>
        <div class="hero-strip">
            <div class="hero-strip-label">
                <span><i class="fa-solid fa-paperclip" style="margin-right:.3rem;"></i>Upload progress</span>
                <span id="hero-count"><?= $uploaded_count ?>/<?= $total ?></span>
            </div>
            <div class="hero-progress">
                <div class="hero-progress-bar" id="hero-bar" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- Document cards -->
<div class="upload-content">

    <?php
    $icon_styles = [
        'resume'       => ['#EFF6FF', '#2563EB'],
        'cover_letter' => ['#F0FDF4', '#059669'],
        'id_proof'     => ['#FEF3C7', '#D97706'],
        'aadhaar'      => ['#F5F3FF', '#7C3AED'],
    ];
    foreach ($doc_types as $type):
        [$label, $icon, $hint, $accept, $required, $pdf_only] = $doc_config[$type];
        $done = in_array($type, $uploaded_types);
        [$ic_bg, $ic_col] = $icon_styles[$type];
    ?>
    <div class="doc-card fade-up" id="card-<?= $type ?>">
        <div class="doc-card-row">

            <div class="doc-card-left">
                <div class="doc-icon" style="background:<?= $ic_bg ?>;color:<?= $ic_col ?>;">
                    <i class="fa-solid <?= $icon ?>"></i>
                </div>
                <div>
                    <div class="doc-name">
                        <?= $label ?>
                        <?php if ($required): ?>
                            <span class="req-badge">Required</span>
                        <?php endif; ?>
                    </div>
                    <div class="doc-hint"><?= $hint ?></div>
                </div>
            </div>

            <div id="slot-<?= $type ?>">
                <?php if ($done): ?>
                    <span class="badge badge-success">
                        <i class="fa-solid fa-circle-check"></i> Uploaded
                    </span>
                <?php else: ?>
                    <form class="file-wrap" onsubmit="handleUpload(event,'<?= $type ?>')" enctype="multipart/form-data">
                        <input type="hidden" name="token"         value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="document_type" value="<?= $type ?>">
                        <input type="hidden" name="ajax"          value="1">

                        <div class="file-input-btn">
                            <input type="file" name="file" required accept="<?= $accept ?>"
                                   id="file-<?= $type ?>" onchange="showFileName('<?= $type ?>')">
                            <label class="file-label" for="file-<?= $type ?>">
                                <i class="fa-solid fa-folder-open"></i>
                                <span id="file-label-<?= $type ?>">Choose file</span>
                            </label>
                        </div>

                        <button type="submit" class="upload-btn" id="btn-<?= $type ?>">
                            <i class="fa-solid fa-arrow-up-from-bracket"></i> Upload
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>
        <div class="err-msg" id="err-<?= $type ?>"></div>
    </div>
    <?php endforeach; ?>

    <?php if ($uploaded_count === $total): ?>
    <div class="all-done" id="all-done-banner">
        <i class="fa-solid fa-circle-check done-icon"></i>
        <h3>All documents submitted!</h3>
        <p>Thank you, <?= htmlspecialchars($first_name) ?>. The hiring team will review your documents shortly.</p>
    </div>
    <?php endif; ?>

</div>

<script>
let uploadedCount = <?= $uploaded_count ?>;
const totalDocs   = <?= $total ?>;

function showFileName(type) {
    const input = document.getElementById('file-' + type);
    const label = document.getElementById('file-label-' + type);
    if (input.files && input.files[0]) {
        const name = input.files[0].name;
        label.textContent = name.length > 20 ? name.substring(0, 18) + '…' : name;
    }
}

function updateHeroProgress() {
    const pct = Math.round((uploadedCount / totalDocs) * 100);
    document.getElementById('hero-bar').style.width    = pct + '%';
    document.getElementById('hero-count').textContent  = uploadedCount + '/' + totalDocs;
}

async function handleUpload(e, type) {
    e.preventDefault();
    const btn    = document.getElementById('btn-' + type);
    const errDiv = document.getElementById('err-' + type);
    const form   = e.target;

    errDiv.textContent = '';
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> Uploading…';

    try {
        const res  = await fetch('save_document.php', { method: 'POST', body: new FormData(form) });
        const resp = (await res.text()).trim();

        if (res.ok && resp.startsWith('validation_failed:')) {
            const msg = resp.replace('validation_failed:', '').trim();
            errDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + msg + ' Please re-upload.';
            btn.disabled  = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Re-upload';

        } else if (res.ok && resp === 'blurry') {
            const token = form.querySelector('input[name="token"]').value;
            document.getElementById('slot-' + type).innerHTML =
                '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;">' +
                '<span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Blurry image — re-upload</span>' +
                '<form class="file-wrap" onsubmit="handleUpload(event,\'' + type + '\')" enctype="multipart/form-data">' +
                '<input type="hidden" name="token" value="' + token + '">' +
                '<input type="hidden" name="document_type" value="' + type + '">' +
                '<input type="hidden" name="ajax" value="1">' +
                '<div class="file-input-btn">' +
                '<input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" id="file-' + type + '" onchange="showFileName(\'' + type + '\')">' +
                '<label class="file-label" for="file-' + type + '"><i class="fa-solid fa-folder-open"></i> <span id="file-label-' + type + '">Choose file</span></label>' +
                '</div>' +
                '<button type="submit" class="upload-btn" id="btn-' + type + '"><i class="fa-solid fa-arrow-up-from-bracket"></i> Upload</button>' +
                '</form></div>';

        } else if (res.ok && resp === 'ok') {
            document.getElementById('slot-' + type).innerHTML =
                '<span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Uploaded</span>';
            errDiv.textContent = '';
            uploadedCount++;
            updateHeroProgress();

            if (uploadedCount === totalDocs && !document.getElementById('all-done-banner')) {
                const banner = document.createElement('div');
                banner.className = 'all-done fade-up';
                banner.id = 'all-done-banner';
                banner.innerHTML =
                    '<i class="fa-solid fa-circle-check done-icon"></i>' +
                    '<h3>All documents submitted!</h3>' +
                    '<p>Thank you! The hiring team will review your documents shortly.</p>';
                document.querySelector('.upload-content').appendChild(banner);
            }

        } else {
            errDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                               (resp || 'Upload failed. Please try again.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="fa-solid fa-arrow-up-from-bracket"></i> Upload';
        }

    } catch (err) {
        errDiv.innerHTML = '<i class="fa-solid fa-wifi"></i> Network error. Please try again.';
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-arrow-up-from-bracket"></i> Upload';
    }
}
</script>
</body>
</html>
