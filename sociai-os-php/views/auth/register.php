<?php // SociAI OS - Registration Page ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account &mdash; <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0F0F1A; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; padding: 2rem 0; }
        .auth-card { background: #1A1A2E; border: 1px solid rgba(255,255,255,.08); border-radius: 20px; padding: 2.5rem; max-width: 460px; width: 100%; }
        .form-control, .form-select { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: #fff; border-radius: 10px; padding: .75rem 1rem; }
        .form-control:focus, .form-select:focus { background: rgba(255,255,255,.08); border-color: #6C63FF; box-shadow: 0 0 0 3px rgba(108,99,255,.2); color: #fff; }
        .form-select option { background: #1A1A2E; }
        .form-label { font-size: .875rem; font-weight: 500; color: #ccc; }
        .btn-primary { background: linear-gradient(135deg, #6C63FF, #8B5CF6); border: none; border-radius: 10px; padding: .75rem; font-weight: 600; width: 100%; }
        .logo { font-size: 1.4rem; font-weight: 900; text-decoration: none; background: linear-gradient(135deg,#6C63FF,#FF6584); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        a { color: #a89cff; } a:hover { color: #6C63FF; }
        ::placeholder { color: #555 !important; }
        .pw-strength-bar { height: 4px; border-radius: 4px; transition: width .3s, background .3s; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-center">
        <div class="auth-card">
            <div class="text-center mb-4">
                <a href="/" class="logo">⚡ <?= APP_NAME ?></a>
            </div>
            <h1 style="font-size:1.75rem;font-weight:800;margin-bottom:.25rem;">Create your account</h1>
            <p style="color:#8b8b9e;font-size:.9rem;margin-bottom:2rem;">Start managing social media with AI</p>

            <?php $flash = \SociAI\Core\Response::getFlash(); ?>
            <?php foreach ($flash['error'] ?? [] as $msg): ?>
            <div class="alert py-2 mb-3" style="background:rgba(220,53,69,.15);border:1px solid rgba(220,53,69,.3);color:#f8d7da;border-radius:10px;font-size:.875rem;"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>

            <form method="POST" action="/auth/register" id="registerForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" placeholder="Jane Smith" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" placeholder="janesmith" pattern="[a-zA-Z0-9_.\-]{3,64}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Language</label>
                        <select class="form-select" name="language">
                            <option value="en">English</option>
                            <option value="ar">Arabic</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" placeholder="you@company.com" required>
                </div>
                <div class="mb-1">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" id="password" placeholder="Min 8 chars, mixed case + numbers" required minlength="8">
                </div>
                <div class="mb-4" style="background:rgba(255,255,255,.05);border-radius:4px;overflow:hidden;">
                    <div class="pw-strength-bar" id="pwStrengthBar" style="width:0;background:#dc3545;"></div>
                </div>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
            <p class="text-center mt-3" style="color:#8b8b9e;font-size:.875rem;">
                Already have an account? <a href="/auth/login">Sign in</a>
            </p>
        </div>
    </div>
</div>
<script>
const pw = document.getElementById('password');
const bar = document.getElementById('pwStrengthBar');
pw.addEventListener('input', function() {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)               score++;
    if (/[A-Z]/.test(v))             score++;
    if (/[a-z]/.test(v))             score++;
    if (/[0-9]/.test(v))             score++;
    if (/[^A-Za-z0-9]/.test(v))      score++;
    const w = [0,20,40,60,80,100][score];
    const c = ['#dc3545','#fd7e14','#ffc107','#20c997','#198754'][score-1] || '#dc3545';
    bar.style.width = w + '%';
    bar.style.background = c;
});
</script>
</body>
</html>
