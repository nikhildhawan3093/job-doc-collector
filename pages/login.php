<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $result = pg_query_params($conn, "SELECT * FROM users WHERE email = $1", [$email]);
    $user   = pg_fetch_assoc($result);

    if ($user && $password === $user['password']) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In – Job Doc Collector</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            background: var(--bg);
        }

        /* Left panel */
        .login-left {
            flex: 1;
            background: linear-gradient(145deg, #4338CA 0%, #4F46E5 40%, #6366F1 75%, #818CF8 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            padding: 3rem 4rem;
            position: relative;
            overflow: hidden;
        }
        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .login-left::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
            bottom: -100px; right: -100px;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: var(--r-full);
            padding: .4rem 1rem;
            color: rgba(255,255,255,.9);
            font-size: .82rem;
            font-weight: 600;
            margin-bottom: 2rem;
            backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
        }
        .login-left h2 {
            font-size: 2.4rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -.04em;
            line-height: 1.15;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        .login-left p {
            font-size: 1rem;
            color: rgba(255,255,255,.75);
            max-width: 340px;
            line-height: 1.7;
            position: relative;
            z-index: 1;
        }
        .features-list {
            margin-top: 2.5rem;
            display: flex;
            flex-direction: column;
            gap: .8rem;
            position: relative;
            z-index: 1;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            color: rgba(255,255,255,.85);
            font-size: .88rem;
            font-weight: 500;
        }
        .feature-item i {
            width: 28px; height: 28px;
            background: rgba(255,255,255,.18);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            flex-shrink: 0;
        }

        /* Right panel */
        .login-right {
            width: 460px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 3.5rem;
            background: #fff;
            box-shadow: -4px 0 32px rgba(0,0,0,.08);
        }
        .login-right h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -.03em;
            margin-bottom: .4rem;
        }
        .login-right > p {
            font-size: .875rem;
            color: var(--text-2);
            margin-bottom: 2rem;
        }
        .input-wrap {
            position: relative;
            margin-bottom: 1.1rem;
        }
        .input-wrap i {
            position: absolute;
            left: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: .85rem;
        }
        .input-wrap .form-control {
            padding-left: 2.4rem;
        }
        .login-footer {
            margin-top: 1.5rem;
            font-size: .8rem;
            color: var(--text-3);
            text-align: center;
        }

        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { width: 100%; padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Left branding panel -->
<div class="login-left">
    <div class="brand-badge">
        <i class="fa-solid fa-file-shield"></i>
        Job Doc Collector
    </div>
    <h2>Streamline your hiring document collection</h2>
    <p>Send magic links to candidates and collect verified documents — automatically.</p>
    <div class="features-list">
        <div class="feature-item">
            <i class="fa-solid fa-link"></i>
            Magic links for candidates — no signup needed
        </div>
        <div class="feature-item">
            <i class="fa-solid fa-eye"></i>
            Aadhaar blur detection &amp; OCR extraction
        </div>
        <div class="feature-item">
            <i class="fa-solid fa-file-lines"></i>
            AI-powered resume parsing
        </div>
        <div class="feature-item">
            <i class="fa-solid fa-file-pdf"></i>
            Auto-generated candidate PDF reports
        </div>
    </div>
</div>

<!-- Right form panel -->
<div class="login-right fade-up">
    <h1>Welcome back</h1>
    <p>Sign in to your hiring manager account</p>

    <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom:1.25rem;">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="login-form">
        <div class="form-group">
            <label class="form-label" for="email">Email address</label>
            <div class="input-wrap">
                <i class="fa-regular fa-envelope"></i>
                <input class="form-control" type="email" id="email" name="email"
                       placeholder="you@company.com" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input class="form-control" type="password" id="password" name="password"
                       placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="login-btn" style="margin-top:.5rem;">
            <i class="fa-solid fa-right-to-bracket"></i>
            Sign In
        </button>
    </form>

    <div class="login-footer">
        Secured hiring document management &nbsp;·&nbsp; Job Doc Collector
    </div>
</div>

<script>
document.getElementById('login-form').addEventListener('submit', function() {
    const btn = document.getElementById('login-btn');
    btn.innerHTML = '<span class="spinner"></span> Signing in…';
    btn.disabled = true;
});
</script>
</body>
</html>
