<?php
/**
 * Save Aadhaar Upload
 * Validates the file, stores it, and inserts a record into the documents table.
 */

require_once '../config/db.php';

$token = $_POST['token'] ?? '';

// --- Validate token ---
if (!$token) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$result      = pg_query_params($conn, "SELECT * FROM applications WHERE token = $1", [$token]);
$application = pg_fetch_assoc($result);

if (!$application) {
    http_response_code(400);
    echo "Invalid or expired link.";
    exit;
}

// --- Validate file presence ---
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo "No file uploaded or upload error.";
    exit;
}

$file        = $_FILES['file'];
$max_size    = 5 * 1024 * 1024; // 5MB
$allowed_mime = ['image/jpeg', 'image/png', 'application/pdf'];
$allowed_ext  = ['jpg', 'jpeg', 'png', 'pdf'];

// --- Validate size ---
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo "File too large. Maximum size is 5MB.";
    exit;
}

// --- Validate MIME type (real check, not just extension) ---
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_mime)) {
    http_response_code(400);
    echo "Invalid file type. Allowed: JPG, PNG, PDF.";
    exit;
}

// --- Validate extension ---
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    http_response_code(400);
    echo "Invalid file extension. Allowed: jpg, jpeg, png, pdf.";
    exit;
}

// --- Store file ---
$filename   = 'aadhaar_' . uniqid() . '.' . $ext;
$upload_dir = '../uploads/';
$file_path  = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    http_response_code(500);
    echo "Failed to save file.";
    exit;
}

$file_url = 'uploads/' . $filename;

// --- Check for existing Aadhaar upload (handle re-upload) ---
$existing = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM documents WHERE application_id = $1 AND document_type = 'aadhaar'",
    [$application['id']]
));

if ($existing) {
    // Delete old file
    $old_file = '../' . $existing['file_url'];
    if (file_exists($old_file)) {
        unlink($old_file);
    }

    // Reset aadhaar_data linked to old document
    pg_query_params($conn,
        "DELETE FROM aadhaar_data WHERE document_id = $1",
        [$existing['id']]
    );

    // Update document record
    pg_query_params($conn,
        "UPDATE documents SET file_url = $1, blur_status = 'pending', processed_status = 'pending', uploaded_at = CURRENT_TIMESTAMP WHERE id = $2",
        [$file_url, $existing['id']]
    );
} else {
    // Insert new document record
    pg_query_params($conn,
        "INSERT INTO documents (application_id, document_type, file_url, blur_status, processed_status)
         VALUES ($1, 'aadhaar', $2, 'pending', 'pending')",
        [$application['id'], $file_url]
    );
}

echo 'ok';
