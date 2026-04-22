<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Unauthorized.");
}

$fields = ['name', 'email', 'phone', 'skills', 'education',
           'latest_company', 'latest_role', 'latest_start_date', 'latest_end_date'];

$data = [];
foreach ($fields as $f) {
    $data[$f] = trim($_POST[$f] ?? '');
}

$validation = validate_resume_data($data);
if (!$validation['valid']) {
    die(implode(' ', $validation['errors']));
}

$document_id = (int)($_POST['document_id'] ?? 0);
if (!$document_id) die("Invalid request.");

// Verify ownership
$check = pg_fetch_assoc(pg_query_params($conn, "
    SELECT d.id FROM documents d
    JOIN applications a ON a.id = d.application_id
    WHERE d.id = $1 AND a.created_by = $2
", [$document_id, $_SESSION['user_id']]));

if (!$check) die("Not found.");

// Upsert resume_data
pg_query_params($conn, "DELETE FROM resume_data WHERE document_id = $1", [$document_id]);
pg_query_params($conn,
    "INSERT INTO resume_data
        (document_id, name, email, phone, skills, education,
         latest_company, latest_role, latest_start_date, latest_end_date)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)",
    [
        $document_id,
        $data['name'], $data['email'], $data['phone'],
        $data['skills'], $data['education'],
        $data['latest_company'], $data['latest_role'],
        $data['latest_start_date'], $data['latest_end_date'],
    ]
);
pg_query_params($conn,
    "UPDATE documents SET processed_status = 'done' WHERE id = $1",
    [$document_id]
);

echo 'ok';
