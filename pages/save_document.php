<?php
require_once '../config/db.php';

$token         = $_POST['token'] ?? '';
$document_type = $_POST['document_type'] ?? '';

if (!$token || !$document_type) {
    die("Invalid request.");
}

// Validate token
$result      = pg_query_params($conn, "SELECT * FROM applications WHERE token = $1", [$token]);
$application = pg_fetch_assoc($result);

if (!$application) {
    die("Invalid or expired link.");
}

// Validate file presence
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    die("No file uploaded or upload error.");
}

$file      = $_FILES['file'];
$max_size  = 5 * 1024 * 1024; // 5MB
$allowed   = ['application/pdf', 'image/jpeg', 'image/png'];
$allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];

// Validate size
if ($file['size'] > $max_size) {
    die("File too large. Maximum size is 5MB.");
}

// Validate MIME type
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed)) {
    die("Invalid file type. Allowed: PDF, JPG, PNG.");
}

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    die("Invalid file extension. Allowed: pdf, jpg, jpeg, png.");
}

// Save file
$filename  = uniqid($document_type . '_') . '.' . $ext;
$upload_dir = '../uploads/';
$file_path  = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    die("Failed to save file.");
}

$file_url = 'uploads/' . $filename;

// Check if document already exists
$existing = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM documents WHERE application_id = $1 AND document_type = $2",
    [$application['id'], $document_type]
));

if ($existing) {
    // Delete old file
    $old_file = '../' . $existing['file_url'];
    if (file_exists($old_file)) {
        unlink($old_file);
    }
    // Update DB record
    pg_query_params($conn,
        "UPDATE documents SET file_url = $1, uploaded_at = CURRENT_TIMESTAMP WHERE id = $2",
        [$file_url, $existing['id']]
    );
} else {
    // Insert new record
    pg_query_params($conn,
        "INSERT INTO documents (application_id, document_type, file_url) VALUES ($1, $2, $3)",
        [$application['id'], $document_type, $file_url]
    );
}

if (!empty($_POST['ajax'])) {
    echo 'ok';
} else {
    header('Location: upload.php?token=' . urlencode($token));
}
exit;
