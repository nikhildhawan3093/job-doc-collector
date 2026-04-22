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

$file     = $_FILES['file'];
$max_size = 5 * 1024 * 1024; // 5MB

// Resume accepts PDF only; all others accept PDF + images
if ($document_type === 'resume') {
    $allowed     = ['application/pdf'];
    $allowed_ext = ['pdf'];
} elseif ($document_type === 'aadhaar') {
    $allowed     = ['image/jpeg', 'image/png', 'application/pdf'];
    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
} else {
    $allowed     = ['application/pdf', 'image/jpeg', 'image/png'];
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
}

// Validate size
if ($file['size'] > $max_size) {
    die("File too large. Maximum size is 5MB.");
}

// Validate MIME type (real check via finfo)
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed)) {
    die($document_type === 'resume'
        ? "Resume must be a PDF file."
        : "Invalid file type. Allowed: PDF, JPG, PNG.");
}

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    die($document_type === 'resume'
        ? "Resume must be a PDF file."
        : "Invalid file extension. Allowed: pdf, jpg, jpeg, png.");
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

    // Clear old extracted data on re-upload
    if ($document_type === 'aadhaar') {
        pg_query_params($conn, "DELETE FROM aadhaar_data WHERE document_id = $1", [$existing['id']]);
    }
    if ($document_type === 'resume') {
        pg_query_params($conn, "DELETE FROM resume_data WHERE document_id = $1", [$existing['id']]);
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

// Run OCR if Aadhaar is clear image or a PDF (blur skipped for PDFs)
if ($document_type === 'aadhaar' && ($blur_status === 'clear' || $blur_status === 'skipped')) {
    $extracted   = extract_aadhaar_data($file_path);
    $validation  = validate_aadhaar_data($extracted);

    if ($validation['valid']) {
        // Clean aadhaar number (remove spaces before storing)
        $extracted['aadhaar_number'] = preg_replace('/\s+/', '', $extracted['aadhaar_number']);

        pg_query_params($conn,
            "INSERT INTO aadhaar_data (document_id, aadhaar_number, name, dob) VALUES ($1, $2, $3, $4)",
            [$document_id, $extracted['aadhaar_number'], $extracted['name'], $extracted['dob']]
        );
        pg_query_params($conn,
            "UPDATE documents SET processed_status = 'done' WHERE id = $1",
            [$document_id]
        );
    } else {
        // Validation failed — mark document and return validation errors to client
        pg_query_params($conn,
            "UPDATE documents SET processed_status = 'failed' WHERE id = $1",
            [$document_id]
        );

        if (!empty($_POST['ajax'])) {
            echo 'validation_failed:' . implode(' ', $validation['errors']);
            exit;
        }
    }
}

// Extract and store structured data from resume
if ($document_type === 'resume') {
    $raw_text = extract_resume_text($file_path);

    if ($raw_text !== '') {
        $parsed     = parse_resume_data($raw_text);
        $validation = validate_resume_data($parsed);

        if ($validation['valid']) {
            pg_query_params($conn,
                "INSERT INTO resume_data
                    (document_id, name, email, phone, skills, education,
                     latest_company, latest_role, latest_start_date, latest_end_date)
                 VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)",
                [
                    $document_id,
                    $parsed['name'],
                    $parsed['email'],
                    $parsed['phone'],
                    $parsed['skills'],
                    $parsed['education'],
                    $parsed['latest_company'],
                    $parsed['latest_role'],
                    $parsed['latest_start_date'],
                    $parsed['latest_end_date'],
                ]
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
            if (!empty($_POST['ajax'])) {
                echo 'validation_failed:' . implode(' ', $validation['errors']);
                exit;
            }
        }
    } else {
        pg_query_params($conn,
            "UPDATE documents SET processed_status = 'failed' WHERE id = $1",
            [$document_id]
        );
        if (!empty($_POST['ajax'])) {
            echo 'validation_failed:Could not extract text from the PDF. Please upload a valid resume.';
            exit;
        }
    }
}

// Return response
if (!empty($_POST['ajax'])) {
    echo ($document_type === 'aadhaar' && $blur_status === 'blurry') ? 'blurry' : 'ok';

} else {
    header('Location: upload.php?token=' . urlencode($token));
}
exit;
