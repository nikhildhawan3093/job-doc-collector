<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$candidate_name  = trim($_POST['candidate_name']);
$candidate_email = trim($_POST['candidate_email']);
$role            = trim($_POST['role']);
$user_id         = $_SESSION['user_id'];

// Generate unique magic token
$token = bin2hex(random_bytes(16));

pg_query_params($conn, "
    INSERT INTO applications (candidate_name, candidate_email, role, token, created_by)
    VALUES ($1, $2, $3, $4, $5)
", [$candidate_name, $candidate_email, $role, $token, $user_id]);

header('Location: application_link.php?token=' . $token);
exit;
