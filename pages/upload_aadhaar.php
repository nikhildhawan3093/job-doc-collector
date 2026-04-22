<?php
/**
 * Aadhaar Upload Page
 * Candidate-facing page to upload their Aadhaar document via magic token.
 */

require_once '../config/db.php';

$token = $_GET['token'] ?? '';

// Validate token
if (!$token) {
    die("Invalid link.");
}

$result      = pg_query_params($conn, "SELECT * FROM applications WHERE token = $1", [$token]);
$application = pg_fetch_assoc($result);

if (!$application) {
    die("Invalid or expired link.");
}

// Check if Aadhaar already uploaded
$existing = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM documents WHERE application_id = $1 AND document_type = 'aadhaar'",
    [$application['id']]
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Aadhaar – Job Doc Collector</title>
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

        /* Candidate info card */
        .intro {
            background: #fff;
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .intro h2 { font-size: 1.1rem; color: #333; margin-bottom: 0.3rem; }
        .intro p  { font-size: 0.9rem; color: #666; }

        /* Upload card */
        .upload-card {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .upload-card h3 {
            font-size: 1rem;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .upload-card p {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 1.2rem;
        }

        /* Drag-and-drop zone */
        .drop-zone {
            border: 2px dashed #c0d3f0;
            border-radius: 8px;
            padding: 2rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            margin-bottom: 1rem;
        }

        .drop-zone.dragover {
            border-color: #4a90e2;
            background: #eef3fb;
        }

        .drop-zone .icon   { font-size: 2rem; margin-bottom: 0.5rem; }
        .drop-zone .label  { font-size: 0.9rem; color: #555; }
        .drop-zone .hint   { font-size: 0.8rem; color: #aaa; margin-top: 0.3rem; }

        /* Hidden file input */
        #aadhaar-file { display: none; }

        /* Selected file preview */
        .file-preview {
            display: none;
            align-items: center;
            gap: 0.6rem;
            background: #f7f9fc;
            border: 1px solid #dce6f5;
            border-radius: 6px;
            padding: 0.6rem 0.9rem;
            margin-bottom: 1rem;
            font-size: 0.88rem;
            color: #333;
        }

        .file-preview .remove-btn {
            margin-left: auto;
            color: #c0392b;
            cursor: pointer;
            font-size: 1rem;
            border: none;
            background: none;
        }

        /* Submit button */
        .btn-upload {
            width: 100%;
            padding: 0.75rem;
            background: #4a90e2;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
        }

        .btn-upload:hover    { background: #357abd; }
        .btn-upload:disabled { background: #aaa; cursor: not-allowed; }

        /* Spinner inside button */
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Error / success messages */
        .msg {
            margin-top: 0.8rem;
            padding: 0.6rem 0.9rem;
            border-radius: 5px;
            font-size: 0.88rem;
            display: none;
        }

        .msg.error   { background: #fdecea; color: #c0392b; }
        .msg.success { background: #e6f9ef; color: #27ae60; }

        /* Already uploaded state */
        .already-uploaded {
            text-align: center;
            padding: 1.5rem;
            background: #e6f9ef;
            border-radius: 8px;
            color: #27ae60;
            font-size: 1rem;
        }
    </style>
</head>
<body>

<header>
    <h1>Job Doc Collector</h1>
</header>

<div class="container">

    <!-- Candidate greeting -->
    <div class="intro">
        <h2>Hello, <?= htmlspecialchars($application['candidate_name']) ?>!</h2>
        <p>Please upload your <strong>Aadhaar card</strong> for the <strong><?= htmlspecialchars($application['role']) ?></strong> position.</p>
    </div>

    <?php if ($existing): ?>
        <!-- Already uploaded -->
        <div class="already-uploaded">
            ✔ Aadhaar already uploaded. Thank you!
        </div>

    <?php else: ?>
        <!-- Upload form -->
        <div class="upload-card">
            <h3>Aadhaar Card</h3>
            <p>Accepted formats: JPG, PNG, PDF &nbsp;|&nbsp; Max size: 5MB</p>

            <form id="aadhaar-form" enctype="multipart/form-data">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="ajax" value="1">
                <input type="file" id="aadhaar-file" name="file" accept=".jpg,.jpeg,.png,.pdf">

                <!-- Drag-and-drop zone -->
                <div class="drop-zone" id="drop-zone" onclick="document.getElementById('aadhaar-file').click()">
                    <div class="icon">📄</div>
                    <div class="label">Click or drag & drop your Aadhaar here</div>
                    <div class="hint">JPG, PNG, PDF up to 5MB</div>
                </div>

                <!-- Selected file preview -->
                <div class="file-preview" id="file-preview">
                    <span id="file-name"></span>
                    <button type="button" class="remove-btn" onclick="removeFile()">✕</button>
                </div>

                <button type="submit" class="btn-upload" id="submit-btn" disabled>Upload Aadhaar</button>
            </form>

            <div class="msg" id="msg-box"></div>
        </div>

    <?php endif; ?>

</div>

<script>
const fileInput  = document.getElementById('aadhaar-file');
const dropZone   = document.getElementById('drop-zone');
const filePreview = document.getElementById('file-preview');
const fileNameEl = document.getElementById('file-name');
const submitBtn  = document.getElementById('submit-btn');
const msgBox     = document.getElementById('msg-box');

// --- File selection ---
fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) showPreview(fileInput.files[0]);
});

function showPreview(file) {
    fileNameEl.textContent = file.name;
    filePreview.style.display = 'flex';
    dropZone.style.display = 'none';
    submitBtn.disabled = false;
}

function removeFile() {
    fileInput.value = '';
    filePreview.style.display = 'none';
    dropZone.style.display = 'block';
    submitBtn.disabled = true;
    hideMsg();
}

// --- Drag and drop ---
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});

dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
        // Transfer to file input
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        showPreview(file);
    }
});

// --- AJAX form submit ---
document.getElementById('aadhaar-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    hideMsg();

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span>Uploading...';

    try {
        const res  = await fetch('save_aadhaar.php', { method: 'POST', body: new FormData(e.target) });
        const text = await res.text();

        if (res.ok && text.trim() === 'ok') {
            showMsg('Aadhaar uploaded successfully!', 'success');
            submitBtn.textContent = '✔ Done';
            filePreview.style.display = 'none';
        } else {
            showMsg(text.trim() || 'Upload failed. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Upload Aadhaar';
        }
    } catch (err) {
        showMsg('Network error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Upload Aadhaar';
    }
});

function showMsg(text, type) {
    msgBox.textContent = text;
    msgBox.className = 'msg ' + type;
    msgBox.style.display = 'block';
}

function hideMsg() {
    msgBox.style.display = 'none';
}
</script>

</body>
</html>
