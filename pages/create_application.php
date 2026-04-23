<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_email  = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr($user_email, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Application – Job Doc Collector</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .input-icon { position: relative; }
        .input-icon i {
            position: absolute;
            left: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: .85rem;
            pointer-events: none;
        }
        .input-icon .form-control { padding-left: 2.4rem; }
    </style>
</head>
<body>

<header class="app-header">
    <div class="header-inner">
        <a class="brand" href="dashboard.php">
            <div class="brand-icon"><i class="fa-solid fa-file-shield"></i></div>
            <div>
                <span class="brand-text">Job Doc Collector</span>
                <span class="brand-sub">Hiring Manager Portal</span>
            </div>
        </a>
        <div class="header-right">
            <div class="header-user">
                <div class="avatar"><?= $user_initial ?></div>
            </div>
            <a href="dashboard.php" class="btn-nav"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>
</header>

<div class="page-sm">

    <!-- Breadcrumb -->
    <div style="display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:var(--text-2);margin-bottom:1.5rem;">
        <a href="dashboard.php" style="color:var(--text-2);text-decoration:none;">Dashboard</a>
        <i class="fa-solid fa-chevron-right" style="font-size:.65rem;color:var(--text-3);"></i>
        <span style="color:var(--text);">New Application</span>
    </div>

    <div class="page-head" style="margin-bottom:1.5rem;">
        <div>
            <h1 class="page-title">New Application</h1>
            <p class="page-subtitle">Create a candidate application and generate a magic upload link</p>
        </div>
    </div>

    <div class="card fade-up">
        <div class="card-head">
            <div class="card-title">
                <div class="card-icon"><i class="fa-solid fa-user-plus"></i></div>
                Candidate Details
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="save_application.php" id="app-form">

                <div class="form-group">
                    <label class="form-label" for="candidate_name">
                        Candidate Name <span style="color:var(--danger);">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fa-solid fa-user"></i>
                        <input class="form-control" type="text" id="candidate_name" name="candidate_name"
                               placeholder="e.g. Priya Sharma" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="candidate_email">
                        Email Address <span style="color:var(--danger);">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fa-regular fa-envelope"></i>
                        <input class="form-control" type="email" id="candidate_email" name="candidate_email"
                               placeholder="candidate@example.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">
                        Role Applied For <span style="color:var(--danger);">*</span>
                    </label>
                    <div class="input-icon">
                        <i class="fa-solid fa-briefcase"></i>
                        <input class="form-control" type="text" id="role" name="role"
                               placeholder="e.g. Senior Software Engineer" required>
                    </div>
                    <p class="form-hint"><i class="fa-solid fa-circle-info"></i> A magic upload link will be generated for the candidate after creation.</p>
                </div>

                <div class="divider"></div>

                <div style="display:flex;gap:.75rem;">
                    <a href="dashboard.php" class="btn btn-ghost btn-full">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-full" id="submit-btn">
                        <i class="fa-solid fa-paper-plane"></i> Create Application
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Info box -->
    <div class="alert alert-info fade-up" style="margin-top:1.25rem;animation-delay:.1s;">
        <i class="fa-solid fa-circle-info" style="flex-shrink:0;"></i>
        <div>
            <strong>How it works:</strong> After creating the application, you'll get a unique magic link.
            Share it with the candidate — they can upload all required documents without creating an account.
        </div>
    </div>

</div>

<script>
document.getElementById('app-form').addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    btn.innerHTML = '<span class="spinner"></span> Creating…';
    btn.disabled = true;
});
</script>
</body>
</html>
