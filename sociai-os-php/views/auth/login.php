<?php
// SociAI OS - Login Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &mdash; <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0F0F1A; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; }
        .auth-card { background: #1A1A2E; border: 1px solid rgba(255,255,255,.08); border-radius: 20px; padding: 2.5rem; max-width: 420px; width: 100%; }
        .form-control { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: #fff; border-radius: 10px; padding: .75rem 1rem; }
        .form-control:focus { background: rgba(255,255,255,.08); border-color: #6C63FF; box-shadow: 0 0 0 3px rgba(108,99,255,.2); color: #fff; }
        .form-label { font-size: .875rem; font-weight: 500; color: #ccc; margin-bottom: .4rem; }
        .btn-primary { background: linear-gradient(135deg, #6C63FF, #8B5CF6); border: none; border-radius: 10px; padding: .75rem; font-weight: 600; width: 100%; }
        .btn-primary:hover { opacity: .9; }
        .auth-title { font-size: 1.75rem; font-weight: 800; margin-bottom: .25rem; }
        .auth-subtitle { color: #8b8b9e; font-size: .9rem; margin-bottom: 2rem; }
        .logo { font-size: 1.4rem; font-weight: 900; text-decoration: none;
                background: linear-gradient(135deg, #6C63FF, #FF6584); -webkit-background-clip: text;
                -webkit-text-fill-color: transparent; background-clip: text; }
        .form-check-input { background-color: transparent; border-color: rgba(255,255,255,.2); }
        ::placeholder { color: #555 !important; }
        a { color: #a89cff; }
        a:hover { color: #6C63FF; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-center">
        <div class="auth-card">
            <div class="text-center mb-4">
                <a href="/" class="logo">⚡ <?= APP_NAME ?></a>
            </div>

            <?php $flash = \SociAI\Core\Response::getFlash(); ?>
            <?php foreach ($flash['error'] ?? [] as $msg): ?>
            <div class="alert alert-danger alert-sm py-2 mb-3" style="background:rgba(220,53,69,.15);border:1px solid rgba(220,53,69,.3);color:#f8d7da;border-radius:10px;font-size:.875rem;">
                <?= htmlspecialchars($msg) ?>
            </div>
            <?php endforeach; ?>
            <?php foreach ($flash['success'] ?? [] as $msg): ?>
            <div class="alert alert-success alert-sm py-2 mb-3" style="background:rgba(25,135,84,.15);border-radius:10px;font-size:.875rem;">
                <?= htmlspecialchars($msg) ?>
            </div>
            <?php endforeach; ?>

            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-subtitle">Sign in to your <?= APP_NAME ?> account</p>

            <form method="POST" action="/auth/login" id="loginForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl ?? '/dashboard') ?>">

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="you@company.com" required autocomplete="email">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        Password
                        <a href="/auth/forgot-password" class="float-end" style="font-size:.8rem;">Forgot password?</a>
                    </label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="••••••••" required autocomplete="current-password">
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                    <label class="form-check-label" for="remember" style="font-size:.875rem;color:#aaa;">
                        Keep me signed in for 30 days
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-loading d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>Signing in...
                    </span>
                </button>
            </form>

            <p class="text-center mt-4" style="color:#8b8b9e;font-size:.875rem;">
                Don't have an account? <a href="/auth/register">Create one free</a>
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('loginForm').addEventListener('submit', function() {
    document.querySelector('.btn-text').classList.add('d-none');
    document.querySelector('.btn-loading').classList.remove('d-none');
});
</script>
</body>
</html>
