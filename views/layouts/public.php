<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Careers') ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:#f8fafc;color:#1e293b;line-height:1.6}
a{color:#4f46e5;text-decoration:none}a:hover{text-decoration:underline}
.pub-nav{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:60px}
.pub-nav-brand{font-size:1.2rem;font-weight:800;background:linear-gradient(135deg,#4f46e5,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.pub-content{max-width:1100px;margin:0 auto;padding:2rem 1rem}
.pub-footer{text-align:center;padding:2rem;color:#94a3b8;font-size:.85rem;border-top:1px solid #e2e8f0;margin-top:4rem}
</style>
</head>
<body>
<nav class="pub-nav">
    <span class="pub-nav-brand">🤖 AI Recruit</span>
    <a href="/login" style="font-size:.9rem;font-weight:600;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;padding:8px 18px;border-radius:8px;text-decoration:none;">Sign In</a>
</nav>
<div class="pub-content">
<?= $content ?? '' ?>
</div>
<footer class="pub-footer">
    &copy; <?= date('Y') ?> AI Recruit &mdash; Powered by AI
</footer>
</body>
</html>
