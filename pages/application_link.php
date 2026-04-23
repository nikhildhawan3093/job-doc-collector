<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$token      = $_GET['token'] ?? '';
$magic_link = 'http://localhost/php/job-doc-collector/pages/upload.php?token=' . urlencode($token);
$user_email  = $_SESSION['user_email'] ?? '';
$user_initial = strtoupper(substr($user_email, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Created – Job Doc Collector</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            <a href="dashboard.php" class="btn-nav"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
        </div>
    </div>
</header>

<div class="page-sm">

    <!-- Success card -->
    <div class="card fade-up" style="text-align:center;padding:2.5rem 2rem;">
        <div style="width:72px;height:72px;background:var(--success-light);border-radius:20px;
                    display:inline-flex;align-items:center;justify-content:center;
                    font-size:1.75rem;color:var(--success);margin-bottom:1.25rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2 style="font-size:1.5rem;font-weight:800;letter-spacing:-.03em;color:var(--text);margin-bottom:.5rem;">
            Application Created!
        </h2>
        <p style="color:var(--text-2);font-size:.9rem;line-height:1.6;max-width:360px;margin:0 auto 2rem;">
            Share the magic link below with the candidate. They can upload all required documents without creating an account.
        </p>

        <div style="text-align:left;margin-bottom:.5rem;">
            <label class="form-label" style="font-size:.8rem;">
                <i class="fa-solid fa-link" style="margin-right:.3rem;color:var(--primary);"></i>
                Candidate Upload Link
            </label>
        </div>
        <div class="link-box" style="margin-bottom:.5rem;">
            <input type="text" id="magic-link" value="<?= htmlspecialchars($magic_link) ?>" readonly>
            <button onclick="copyLink()" id="copy-btn">
                <i class="fa-regular fa-copy"></i> Copy
            </button>
        </div>
        <p id="copied-msg" style="font-size:.8rem;color:var(--success);height:1.2rem;text-align:left;
                                   display:flex;align-items:center;gap:.3rem;">
        </p>

        <div class="divider"></div>

        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fa-solid fa-grid-2"></i> Go to Dashboard
            </a>
            <a href="create_application.php" class="btn btn-ghost">
                <i class="fa-solid fa-plus"></i> New Application
            </a>
        </div>
    </div>

    <!-- Info steps -->
    <div class="card fade-up" style="animation-delay:.1s;margin-top:1.25rem;">
        <div class="card-head">
            <div class="card-title">
                <div class="card-icon"><i class="fa-solid fa-list-check"></i></div>
                Next Steps
            </div>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:.9rem;">
                <?php foreach ([
                    ['fa-share-nodes', 'primary', 'Share the link', 'Copy the magic link and send it to the candidate via email or WhatsApp.'],
                    ['fa-file-arrow-up', 'warning', 'Candidate uploads', 'The candidate uploads their Resume, Aadhaar Card, Cover Letter, and ID Proof.'],
                    ['fa-brain', 'success', 'Auto-processing', 'We automatically detect blur, extract Aadhaar data, and parse the resume with AI.'],
                    ['fa-file-pdf', 'neutral', 'Generate report', 'Once processed, generate a PDF report from the application detail page.'],
                ] as [$icon, $color, $title, $desc]): ?>
                <div style="display:flex;align-items:flex-start;gap:.85rem;">
                    <div style="width:32px;height:32px;border-radius:9px;
                                background:var(--<?= $color === 'neutral' ? 'bg' : $color . '-light' ?>);
                                color:var(--<?= $color === 'neutral' ? 'text-2' : $color ?>);
                                display:flex;align-items:center;justify-content:center;
                                font-size:.8rem;flex-shrink:0;">
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div>
                        <div style="font-size:.875rem;font-weight:600;color:var(--text);"><?= $title ?></div>
                        <div style="font-size:.8rem;color:var(--text-2);margin-top:.15rem;"><?= $desc ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
function copyLink() {
    const input = document.getElementById('magic-link');
    input.select();
    document.execCommand('copy');
    const msg = document.getElementById('copied-msg');
    const btn = document.getElementById('copy-btn');
    msg.innerHTML = '<i class="fa-solid fa-circle-check"></i> Link copied to clipboard!';
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
    btn.style.background = 'var(--success)';
    setTimeout(() => {
        msg.innerHTML = '';
        btn.innerHTML = '<i class="fa-regular fa-copy"></i> Copy';
        btn.style.background = '';
    }, 2500);
}
</script>
</body>
</html>
