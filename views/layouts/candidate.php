<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Candidate Portal') ?> — AI Recruit</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100vh;
            background: #0f0f1a;
            color: #e2e8f0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        a { text-decoration: none; color: inherit; }

        /* ---- Topnav ---- */
        .topnav {
            background: #1a1a2e;
            border-bottom: 1px solid rgba(79, 70, 229, 0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topnav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-icon { font-size: 1.4rem; }
        .brand-text {
            font-size: 1.1rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Desktop nav links */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .nav-link {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #94a3b8;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .nav-link:hover { background: rgba(79, 70, 229, 0.1); color: #e2e8f0; }
        .nav-link.active { background: rgba(79, 70, 229, 0.18); color: #a5b4fc; font-weight: 600; }

        .topnav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .notif-btn {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 6px;
            border-radius: 8px;
            position: relative;
            transition: background 0.15s, color 0.15s;
        }
        .notif-btn:hover { background: rgba(79, 70, 229, 0.1); color: #e2e8f0; }
        .notif-dot {
            position: absolute;
            top: 4px; right: 4px;
            width: 8px; height: 8px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid #1a1a2e;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px 5px 6px;
            border-radius: 24px;
            background: rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(79, 70, 229, 0.2);
            cursor: pointer;
            transition: background 0.15s;
            position: relative;
        }
        .user-chip:hover { background: rgba(79, 70, 229, 0.18); }
        .user-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        .user-chip-name { font-size: 0.8125rem; font-weight: 600; color: #e2e8f0; }

        .chip-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #1a1a2e;
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 10px;
            min-width: 160px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            overflow: hidden;
            z-index: 200;
        }
        .chip-dropdown.open { display: block; }
        .chip-dd-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            font-size: 0.875rem;
            color: #94a3b8;
            transition: background 0.15s, color 0.15s;
        }
        .chip-dd-item:hover { background: rgba(79, 70, 229, 0.1); color: #e2e8f0; }
        .chip-dd-item.danger { color: #ef4444; }
        .chip-dd-item.danger:hover { background: rgba(239,68,68,0.1); color: #fca5a5; }
        .chip-dd-divider { border: none; border-top: 1px solid rgba(79,70,229,0.1); margin: 4px 0; }

        /* Mobile hamburger */
        .hamburger {
            display: none;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.4rem;
            cursor: pointer;
            padding: 4px;
        }
        .mobile-menu {
            display: none;
            flex-direction: column;
            padding: 12px 16px;
            background: #1a1a2e;
            border-top: 1px solid rgba(79,70,229,0.1);
        }
        .mobile-menu.open { display: flex; }
        .mobile-nav-link {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #94a3b8;
            display: block;
            transition: background 0.15s, color 0.15s;
        }
        .mobile-nav-link:hover, .mobile-nav-link.active {
            background: rgba(79,70,229,0.1);
            color: #a5b4fc;
        }

        /* ---- Page content ---- */
        .page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* ---- Toast ---- */
        #toast-container {
            position: fixed;
            top: 80px; right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* ---- Footer ---- */
        .site-footer {
            border-top: 1px solid rgba(79,70,229,0.1);
            padding: 24px;
            text-align: center;
            font-size: 0.8rem;
            color: #475569;
            margin-top: 48px;
        }
        .site-footer a { color: #4f46e5; }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hamburger { display: block; }
            .user-chip-name { display: none; }
            .page-wrapper { padding: 20px 16px; }
        }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #0f0f1a; }
        ::-webkit-scrollbar-thumb { background: rgba(79,70,229,0.3); border-radius: 4px; }
    </style>
</head>
<body>
<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
function candActive(string $path, string $currentPath): string {
    return (str_starts_with($currentPath, $path)) ? 'active' : '';
}
function candInitials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) return strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
    return strtoupper(substr($name, 0, 2));
}
$candUser = Auth::user();
$candName = $candUser->name ?? 'Candidate';
?>
<header class="topnav">
    <div class="topnav-inner">
        <a href="/portal" class="brand">
            <span class="brand-icon">🤖</span>
            <span class="brand-text">AI Recruit</span>
        </a>

        <!-- Desktop nav -->
        <nav class="nav-links">
            <a href="/portal/jobs" class="nav-link <?= candActive('/portal/jobs', $currentPath) ?>">Browse Jobs</a>
            <a href="/portal/applications" class="nav-link <?= candActive('/portal/applications', $currentPath) ?>">My Applications</a>
            <a href="/portal/profile" class="nav-link <?= candActive('/portal/profile', $currentPath) ?>">Profile</a>
            <a href="/portal/offers" class="nav-link <?= candActive('/portal/offers', $currentPath) ?>">Offers</a>
        </nav>

        <div class="topnav-right">
            <!-- Notifications -->
            <a href="/portal/notifications" class="notif-btn" title="Notifications">
                🔔
                <span class="notif-dot"></span>
            </a>

            <!-- User chip with dropdown -->
            <div class="user-chip" id="candUserChip">
                <div class="user-avatar"><?= candInitials($candName) ?></div>
                <span class="user-chip-name"><?= htmlspecialchars($candName) ?></span>
                <div class="chip-dropdown" id="candChipDropdown">
                    <a href="/portal/profile" class="chip-dd-item">👤 My Profile</a>
                    <a href="/portal/settings" class="chip-dd-item">⚙️ Settings</a>
                    <hr class="chip-dd-divider">
                    <a href="/logout" class="chip-dd-item danger" onclick="return confirm('Sign out?')">🚪 Sign Out</a>
                </div>
            </div>

            <!-- Mobile hamburger -->
            <button class="hamburger" id="hamburgerBtn" aria-label="Menu">☰</button>
        </div>
    </div>

    <!-- Mobile menu -->
    <nav class="mobile-menu" id="mobileMenu">
        <a href="/portal/jobs" class="mobile-nav-link <?= candActive('/portal/jobs', $currentPath) ?>">Browse Jobs</a>
        <a href="/portal/applications" class="mobile-nav-link <?= candActive('/portal/applications', $currentPath) ?>">My Applications</a>
        <a href="/portal/profile" class="mobile-nav-link <?= candActive('/portal/profile', $currentPath) ?>">Profile</a>
        <a href="/portal/offers" class="mobile-nav-link <?= candActive('/portal/offers', $currentPath) ?>">Offers</a>
        <a href="/portal/notifications" class="mobile-nav-link">Notifications</a>
        <a href="/logout" class="mobile-nav-link" style="color:#ef4444;" onclick="return confirm('Sign out?')">Sign Out</a>
    </nav>
</header>

<div class="page-wrapper">
    <?= $content ?? '' ?>
</div>

<footer class="site-footer">
    &copy; <?= date('Y') ?> AI Recruit &mdash; <a href="#">Privacy Policy</a> &middot; <a href="#">Terms of Service</a>
</footer>

<div id="toast-container"></div>

<?php require_once __DIR__ . '/../partials/view_scripts.php'; ?>

<script>
// Mobile menu
document.getElementById('hamburgerBtn').addEventListener('click', function() {
    document.getElementById('mobileMenu').classList.toggle('open');
});
// User chip dropdown
document.getElementById('candUserChip').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('candChipDropdown').classList.toggle('open');
});
document.addEventListener('click', function() {
    document.getElementById('candChipDropdown').classList.remove('open');
});
</script>
</body>
</html>
