<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invalid Interview Link</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#0a0a14;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;display:flex;align-items:center;justify-content:center}
.wrap{max-width:480px;width:90%;text-align:center;padding:2rem}
.icon{font-size:4rem;margin-bottom:1.5rem}
.title{font-size:1.5rem;font-weight:800;color:#f1f5f9;margin-bottom:.75rem}
.sub{color:#94a3b8;font-size:.95rem;line-height:1.7;margin-bottom:2rem}
.alert{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:16px;color:#fca5a5;font-size:.875rem;margin-bottom:2rem;line-height:1.6}
.brand{position:fixed;top:20px;left:24px;font-size:1rem;font-weight:800;background:linear-gradient(135deg,#4f46e5,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.btn{display:inline-block;padding:11px 24px;border-radius:10px;font-weight:700;font-size:.875rem;text-decoration:none;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;transition:opacity .15s}
.btn:hover{opacity:.88}
</style>
</head>
<body>
<div class="brand">🤖 AI Recruit</div>
<div class="wrap">
    <div class="icon">🔗</div>
    <div class="title">Invalid or Expired Link</div>
    <div class="sub">This interview link is no longer valid.</div>

    <div class="alert">
        <?php if ($reason === 'expired'): ?>
        This interview link has expired. Interview links are valid for a limited time. Please contact the hiring company to request a new link.
        <?php elseif ($reason === 'already_completed'): ?>
        This interview has already been completed. Each interview link can only be used once.
        <?php else: ?>
        This interview link is invalid or does not exist. Please check the link in your email and try again.
        <?php endif; ?>
    </div>

    <a href="/login" class="btn">Sign In to My Account</a>
</div>
</body>
</html>
