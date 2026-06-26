<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Interview Completed</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#0a0a14;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;display:flex;align-items:center;justify-content:center}
.wrap{max-width:520px;width:90%;text-align:center;padding:2rem}
.icon{font-size:4rem;margin-bottom:1.5rem}
.title{font-size:1.75rem;font-weight:800;color:#4ade80;margin-bottom:.75rem}
.sub{color:#94a3b8;font-size:1rem;line-height:1.7;margin-bottom:2rem}
.card{background:#1a1a2e;border:1px solid rgba(34,197,94,.2);border-radius:16px;padding:24px;margin-bottom:2rem;text-align:left}
.card-title{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:600;margin-bottom:12px}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(79,70,229,.1)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:.85rem;color:#64748b}
.detail-value{font-size:.85rem;color:#e2e8f0;font-weight:600}
.btn{display:inline-block;padding:12px 28px;border-radius:10px;font-weight:700;font-size:.9rem;text-decoration:none;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;transition:opacity .15s}
.btn:hover{opacity:.88}
.brand{position:fixed;top:20px;left:24px;font-size:1rem;font-weight:800;background:linear-gradient(135deg,#4f46e5,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
</style>
</head>
<body>
<div class="brand">🤖 AI Recruit</div>
<div class="wrap">
    <div class="icon">✅</div>
    <div class="title">Interview Completed!</div>
    <div class="sub">
        Thank you for completing your AI interview. Your responses have been recorded and will be carefully reviewed by the hiring team.
    </div>

    <div class="card">
        <div class="card-title">Interview Summary</div>
        <div class="detail-row">
            <span class="detail-label">Position</span>
            <span class="detail-value"><?= htmlspecialchars($jobTitle ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Company</span>
            <span class="detail-value"><?= htmlspecialchars($companyName ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Questions Answered</span>
            <span class="detail-value"><?= (int)($totalAnswered ?? 0) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Completed At</span>
            <span class="detail-value"><?= $completedAt ? date('M j, Y g:i A', strtotime($completedAt)) : 'Just now' ?></span>
        </div>
    </div>

    <div style="color:#64748b;font-size:.85rem;line-height:1.6;margin-bottom:2rem;">
        You'll receive an email notification once the team has reviewed your interview. This typically takes 2–5 business days.
    </div>

    <?php if ($candidateLoggedIn ?? false): ?>
    <a href="/c/dashboard" class="btn">Go to My Dashboard</a>
    <?php else: ?>
    <a href="/login" class="btn">Sign In to Track Your Application</a>
    <?php endif; ?>
</div>
</body>
</html>
