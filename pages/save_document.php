<?php
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/env.php';

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

$file        = $_FILES['file'];
$max_size    = 5 * 1024 * 1024; // 5MB
$allowed     = ['application/pdf', 'image/jpeg', 'image/png'];
$allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];

// Validate size
if ($file['size'] > $max_size) {
    die("File too large. Maximum size is 5MB.");
}

// Validate MIME type (real check via finfo)
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

// Save file to uploads/
$filename   = uniqid($document_type . '_') . '.' . $ext;
$upload_dir = '../uploads/';
$file_path  = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    die("Failed to save file.");
}

$file_url = 'uploads/' . $filename;

// Run blur detection for Aadhaar
$blur_status      = 'pending';
$processed_status = 'pending';

if ($document_type === 'aadhaar') {
    $blur_status = detect_blur($file_path);
}

// Check for existing document (re-upload)
$existing = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM documents WHERE application_id = $1 AND document_type = $2",
    [$application['id'], $document_type]
));

if ($existing) {
    // Delete old file
    $old_file = '../' . $existing['file_url'];
    if (file_exists($old_file)) unlink($old_file);

    // Clear old aadhaar_data on re-upload
    if ($document_type === 'aadhaar') {
        pg_query_params($conn, "DELETE FROM aadhaar_data WHERE document_id = $1", [$existing['id']]);
    }

    pg_query_params($conn,
        "UPDATE documents SET file_url = $1, blur_status = $2, processed_status = $3, uploaded_at = CURRENT_TIMESTAMP WHERE id = $4",
        [$file_url, $blur_status, $processed_status, $existing['id']]
    );
    $document_id = $existing['id'];
} else {
    pg_query_params($conn,
        "INSERT INTO documents (application_id, document_type, file_url, blur_status, processed_status) VALUES ($1, $2, $3, $4, $5)",
        [$application['id'], $document_type, $file_url, $blur_status, $processed_status]
    );
    $document_id = pg_fetch_result(pg_query($conn, "SELECT lastval()"), 0, 0);
}

// If Aadhaar is clear — run OCR extraction via OpenAI
if ($document_type === 'aadhaar' && $blur_status === 'clear') {
    $extracted = extract_aadhaar_data($file_path);

    if (!empty($extracted['aadhaar_number']) || !empty($extracted['name']) || !empty($extracted['dob'])) {
        pg_query_params($conn,
            "INSERT INTO aadhaar_data (document_id, aadhaar_number, name, dob) VALUES ($1, $2, $3, $4)",
            [$document_id, $extracted['aadhaar_number'], $extracted['name'], $extracted['dob']]
        );
        pg_query_params($conn,
            "UPDATE documents SET processed_status = 'done' WHERE id = $1",
            [$document_id]
        );
    } else {
        pg_query_params($conn,
            "UPDATE documents SET processed_status = 'failed' WHERE id = $1",
            [$document_id]
        );
    }
}

// Return response
if (!empty($_POST['ajax'])) {
    echo ($document_type === 'aadhaar' && $blur_status === 'blurry') ? 'blurry' : 'ok';
} else {
    header('Location: upload.php?token=' . urlencode($token));
}
exit;
