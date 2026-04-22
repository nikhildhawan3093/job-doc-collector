<?php
require_once '../config/db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid link.");
}

// Fetch application by token
$result = pg_query_params($conn, "
    SELECT * FROM applications WHERE token = $1
", [$token]);

$application = pg_fetch_assoc($result);

if (!$application) {
    die("Invalid or expired link.");
}

// Fetch already uploaded documents
$doc_result = pg_query_params($conn, "
    SELECT document_type FROM documents WHERE application_id = $1
", [$application['id']]);

$uploaded_types = [];
while ($row = pg_fetch_assoc($doc_result)) {
    $uploaded_types[] = $row['document_type'];
}

$doc_types = ['resume', 'cover_letter', 'id_proof', 'aadhaar'];
$doc_labels = [
    'resume'       => 'Resume',
    'cover_letter' => 'Cover Letter',
    'id_proof'     => 'ID Proof',
    'aadhaar'      => 'Aadhaar Card',
];
$mandatory_types = ['aadhaar'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents – Job Doc Collector</title>
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
        }

        header h1 { font-size: 1.2rem; }

        .container {
            max-width: 560px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .intro {
            background: #fff;
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .intro h2 { font-size: 1.1rem; color: #333; margin-bottom: 0.3rem; }
        .intro p  { font-size: 0.9rem; color: #666; }

        .doc-card {
            background: #fff;
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .doc-info h3 { font-size: 1rem; color: #333; }
        .doc-info p  { font-size: 0.85rem; color: #888; margin-top: 0.2rem; }

        .badge-done {
            background: #e6f9ef;
            color: #27ae60;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .upload-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-form input[type="file"] {
            font-size: 0.85rem;
            max-width: 160px;
        }

        .upload-form button {
            padding: 0.4rem 0.8rem;
            background: #4a90e2;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            cursor: pointer;
            white-space: nowrap;
        }

        .upload-form button:hover { background: #357abd; }

        .all-done {
            text-align: center;
            background: #e6f9ef;
            color: #27ae60;
            padding: 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            margin-top: 1rem;
        }

        .upload-form button:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 4px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .error-msg {
            font-size: 0.82rem;
            color: #c0392b;
            margin-top: 0.4rem;
        }
    </style>
</head>
<body>

<header>
    <h1>Job Doc Collector</h1>
</header>

<div class="container">

    <div class="intro">
        <h2>Hello, <?= htmlspecialchars($application['candidate_name']) ?>!</h2>
        <p>Please upload the required documents for the <strong><?= htmlspecialchars($application['role']) ?></strong> position.</p>
    </div>

    <?php foreach ($doc_types as $type): ?>
        <?php $is_mandatory = in_array($type, $mandatory_types); ?>
        <div class="doc-card">
            <div class="doc-info">
                <h3>
                    <?= $doc_labels[$type] ?>
                    <?php if ($is_mandatory): ?>
                        <span style="background:#fff3e0;color:#e67e22;font-size:0.72rem;padding:0.15rem 0.5rem;border-radius:20px;margin-left:0.4rem;font-weight:bold;">Required</span>
                    <?php endif; ?>
                </h3>
                <p><?= $type === 'aadhaar' ? 'JPG, PNG, or PDF — must be clear (not blurry)' : 'PDF, JPG, or PNG' ?></p>
            </div>

            <?php if (in_array($type, $uploaded_types)): ?>
                <span class="badge-done" id="badge-<?= $type ?>">✔ Uploaded</span>
            <?php else: ?>
                <div id="slot-<?= $type ?>">
                    <form class="upload-form" onsubmit="handleUpload(event, '<?= $type ?>')" enctype="multipart/form-data">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="document_type" value="<?= $type ?>">
                        <input type="hidden" name="ajax" value="1">
                        <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png">
                        <button type="submit" id="btn-<?= $type ?>">Upload</button>
                    </form>
                    <div class="error-msg" id="err-<?= $type ?>"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (count($uploaded_types) === count($doc_types)): ?>
        <div class="all-done">All documents uploaded. Thank you!</div>
    <?php endif; ?>

</div>

<script>
let uploadedCount = <?= count($uploaded_types) ?>;
const totalDocs   = <?= count($doc_types) ?>;

async function handleUpload(e, type) {
    e.preventDefault();

    const form   = e.target;
    const btn    = document.getElementById('btn-' + type);
    const errDiv = document.getElementById('err-' + type);

    errDiv.textContent = '';
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span>Uploading...';

    try {
        const res  = await fetch('save_document.php', { method: 'POST', body: new FormData(form) });
        const text = await res.text();

        const response = text.trim();

        if (res.ok && response.startsWith('validation_failed:')) {
            const msg = response.replace('validation_failed:', '').trim();
            errDiv.textContent = '⚠ Data invalid — ' + msg + ' Please re-upload a clearer image.';
            btn.disabled  = false;
            btn.innerHTML = 'Re-upload';

        } else if (res.ok && response === 'blurry') {
            const slot = document.getElementById('slot-' + type);
            const token = document.querySelector('input[name="token"]').value;
            slot.innerHTML =
                '<span style="background:#fdecea;color:#c0392b;padding:0.3rem 0.7rem;border-radius:20px;font-size:0.82rem;">⚠ Blurry — please re-upload a clearer image</span>' +
                '<div style="margin-top:0.5rem">' +
                '<form class="upload-form" onsubmit="handleUpload(event,\'' + type + '\')" enctype="multipart/form-data">' +
                '<input type="hidden" name="token" value="' + token + '">' +
                '<input type="hidden" name="document_type" value="' + type + '">' +
                '<input type="hidden" name="ajax" value="1">' +
                '<input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png">' +
                '<button type="submit" id="btn-' + type + '">Re-upload</button>' +
                '</form>' +
                '<div class="error-msg" id="err-' + type + '"></div></div>';

        } else if (res.ok && response === 'ok') {
            const slot = document.getElementById('slot-' + type);
            slot.innerHTML = '<span class="badge-done">✔ Uploaded</span>';
            uploadedCount++;
            if (uploadedCount === totalDocs) {
                const allDone = document.createElement('div');
                allDone.className = 'all-done';
                allDone.textContent = 'All documents uploaded. Thank you!';
                document.querySelector('.container').appendChild(allDone);
            }

        } else {
            errDiv.textContent = response || 'Upload failed. Please try again.';
            btn.disabled  = false;
            btn.innerHTML = 'Upload';
        }
    } catch (err) {
        errDiv.textContent = 'Network error. Please try again.';
        btn.disabled  = false;
        btn.innerHTML = 'Upload';
    }
}
</script>
</body>
</html>
