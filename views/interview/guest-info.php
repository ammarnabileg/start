<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Interview — <?= htmlspecialchars($jobTitle ?? 'Position') ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#0a0a14;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;display:flex;align-items:center;justify-content:center}
.wrap{max-width:480px;width:90%;padding:2rem}
.brand{text-align:center;font-size:1.1rem;font-weight:800;background:linear-gradient(135deg,#4f46e5,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:2rem}
.card{background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:16px;padding:32px}
.card-icon{font-size:2.5rem;text-align:center;margin-bottom:1rem}
.card-title{font-size:1.25rem;font-weight:800;color:#f1f5f9;text-align:center;margin-bottom:.5rem}
.card-sub{color:#64748b;font-size:.875rem;text-align:center;margin-bottom:2rem;line-height:1.6}
.job-badge{background:rgba(79,70,229,.12);border:1px solid rgba(79,70,229,.2);border-radius:8px;padding:10px 16px;text-align:center;margin-bottom:1.5rem}
.job-label{font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.job-title{font-size:.95rem;color:#e2e8f0;font-weight:700;margin-top:2px}
.form-group{margin-bottom:1rem}
.form-label{display:block;font-size:.82rem;color:#94a3b8;font-weight:600;margin-bottom:6px}
.form-input{width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:10px 14px;color:#e2e8f0;font-size:.9rem;outline:none;font-family:inherit;transition:border .15s}
.form-input:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.12)}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:10px;color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity .15s;margin-top:.5rem}
.btn:hover{opacity:.9}
.terms{text-align:center;font-size:.75rem;color:#475569;margin-top:1rem;line-height:1.5}
.alert-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:12px 16px;color:#fca5a5;font-size:.85rem;margin-bottom:1rem}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">🤖 AI Recruit</div>

    <div class="card">
        <div class="card-icon">🎤</div>
        <div class="card-title">Welcome to Your AI Interview</div>
        <div class="card-sub">Please enter your details before we begin. This helps us match your interview to your application.</div>

        <div class="job-badge">
            <div class="job-label">You are interviewing for</div>
            <div class="job-title"><?= htmlspecialchars($jobTitle ?? 'Open Position') ?></div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e): ?><?= htmlspecialchars($e) ?><br><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/interview/<?= htmlspecialchars($token ?? '') ?>/guest">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input class="form-input" type="text" name="name" placeholder="Your full name" value="<?= htmlspecialchars($old['name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input class="form-input" type="email" name="email" placeholder="your@email.com" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number (optional)</label>
                <input class="form-input" type="tel" name="phone" placeholder="+1 555 000 0000" value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
            </div>
            <button type="submit" class="btn">Start Interview →</button>
        </form>

        <div class="terms">
            By continuing, you agree that this interview may be recorded and evaluated by AI. Your data is processed in accordance with applicable privacy policies.
        </div>
    </div>
</div>
</body>
</html>
