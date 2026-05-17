<?php
$pageTitle = $pageTitle ?? 'Sign In';
$appVersion = '1.0.0';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">
  <title><?= htmlspecialchars($pageTitle) ?> — SociAI OS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css">
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .auth-bg-grid {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image:
        linear-gradient(rgba(59,130,246,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(59,130,246,0.04) 1px, transparent 1px);
      background-size: 50px 50px;
    }
    .auth-glow-1 {
      position: fixed; width: 500px; height: 500px; border-radius: 50%;
      background: radial-gradient(circle, rgba(59,130,246,0.12), transparent 70%);
      top: -100px; left: -100px; z-index: 0; pointer-events: none;
      animation: float 10s ease-in-out infinite;
    }
    .auth-glow-2 {
      position: fixed; width: 400px; height: 400px; border-radius: 50%;
      background: radial-gradient(circle, rgba(139,92,246,0.1), transparent 70%);
      bottom: -80px; right: -80px; z-index: 0; pointer-events: none;
      animation: float 8s ease-in-out infinite reverse;
    }
    .auth-glow-3 {
      position: fixed; width: 250px; height: 250px; border-radius: 50%;
      background: radial-gradient(circle, rgba(16,185,129,0.08), transparent 70%);
      top: 50%; left: 50%; transform: translate(-50%,-50%); z-index: 0; pointer-events: none;
      animation: float 12s ease-in-out infinite 3s;
    }
    .particle { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; animation: float 8s ease-in-out infinite; }
    .floating-badge {
      position: fixed; z-index: 0; pointer-events: none;
      background: var(--glass-bg); border: 1px solid var(--glass-border);
      backdrop-filter: blur(8px); border-radius: var(--radius-md);
      padding: 0.5rem 0.9rem; font-size: 0.8rem; font-weight: 500;
      color: var(--text-secondary); animation: float 7s ease-in-out infinite;
    }
  </style>
</head>
<body>
<!-- Background effects -->
<div class="auth-bg-grid"></div>
<div class="auth-glow-1"></div>
<div class="auth-glow-2"></div>
<div class="auth-glow-3"></div>
<div class="particle" style="width:200px;height:200px;background:rgba(59,130,246,0.06);top:15%;right:10%;animation-delay:1s"></div>
<div class="particle" style="width:150px;height:150px;background:rgba(139,92,246,0.06);bottom:25%;left:8%;animation-delay:3s"></div>
<div class="floating-badge" style="top:20%;left:5%;animation-delay:0s">🤖 8 AI Agents</div>
<div class="floating-badge" style="top:40%;right:5%;animation-delay:2s">📈 +234% Growth</div>
<div class="floating-badge" style="bottom:30%;left:3%;animation-delay:4s">⚡ 11 Platforms</div>
<div class="floating-badge" style="bottom:15%;right:8%;animation-delay:1.5s">✨ Auto-Pilot Mode</div>

<div class="auth-layout">
  <?= $content ?? '' ?>
</div>

<footer style="position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);font-size:0.72rem;color:var(--text-muted);z-index:1;white-space:nowrap">
  SociAI OS v<?= $appVersion ?> · Enterprise Edition · © <?= date('Y') ?>
</footer>

<script src="/assets/js/app.js"></script>
</body>
</html>
