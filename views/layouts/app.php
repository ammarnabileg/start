<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — AI Recruit</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            background: #0f0f1a;
            color: #e2e8f0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        a { text-decoration: none; color: inherit; }

        /* ---- Layout ---- */
        .layout { display: flex; min-height: 100vh; }

        /* ---- Sidebar ---- */
        .sidebar {
            width: 240px;
            min-width: 240px;
            background: #1a1a2e;
            border-right: 1px solid rgba(79, 70, 229, 0.15);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(79, 70, 229, 0.1);
        }
        .sidebar-brand .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-brand .logo-icon { font-size: 1.5rem; }
        .sidebar-brand .logo-text {
            font-size: 1.1rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .sidebar-tenant {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding-left: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sidebar-nav { flex: 1; padding: 16px 12px; }
        .nav-section-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #475569;
            padding: 12px 8px 6px;
            font-weight: 600;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #94a3b8;
            margin-bottom: 2px;
            transition: background 0.15s, color 0.15s;
            cursor: pointer;
        }
        .nav-item:hover {
            background: rgba(79, 70, 229, 0.1);
            color: #e2e8f0;
        }
        .nav-item.active {
            background: rgba(79, 70, 229, 0.2);
            color: #a5b4fc;
            font-weight: 600;
        }
        .nav-item.active .nav-icon { color: #4f46e5; }
        .nav-icon { font-size: 1rem; width: 20px; text-align: center; }
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid rgba(79, 70, 229, 0.1);
        }
        .sidebar-footer .nav-item { color: #ef4444; }
        .sidebar-footer .nav-item:hover { background: rgba(239, 68, 68, 0.1); color: #fca5a5; }

        /* ---- Main area ---- */
        .main-area {
            margin-left: 240px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ---- Topbar ---- */
        .topbar {
            height: 60px;
            background: #1a1a2e;
            border-bottom: 1px solid rgba(79, 70, 229, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        .topbar-actions { display: flex; align-items: center; gap: 16px; }
        .topbar-btn {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            font-size: 1.1rem;
            transition: background 0.15s, color 0.15s;
            position: relative;
        }
        .topbar-btn:hover { background: rgba(79, 70, 229, 0.1); color: #e2e8f0; }
        .notif-badge {
            position: absolute;
            top: 2px; right: 2px;
            width: 8px; height: 8px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid #1a1a2e;
        }
        .user-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background 0.15s;
            position: relative;
        }
        .user-dropdown-toggle:hover { background: rgba(79, 70, 229, 0.1); }
        .user-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        .user-name { font-size: 0.875rem; font-weight: 600; color: #e2e8f0; }
        .user-role { font-size: 0.7rem; color: #64748b; }
        .dropdown-caret { color: #64748b; font-size: 0.65rem; }
        .user-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #1a1a2e;
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 10px;
            min-width: 180px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            overflow: hidden;
            z-index: 200;
        }
        .user-dropdown-menu.open { display: block; }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            font-size: 0.875rem;
            color: #94a3b8;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .dropdown-item:hover { background: rgba(79, 70, 229, 0.1); color: #e2e8f0; }
        .dropdown-item.danger { color: #ef4444; }
        .dropdown-item.danger:hover { background: rgba(239, 68, 68, 0.1); color: #fca5a5; }
        .dropdown-divider { border: none; border-top: 1px solid rgba(79, 70, 229, 0.1); margin: 4px 0; }

        /* ---- Page content ---- */
        .page-content { flex: 1; padding: 28px; }

        /* ---- Scrollbar ---- */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #0f0f1a; }
        ::-webkit-scrollbar-thumb { background: rgba(79, 70, 229, 0.3); border-radius: 4px; }

        /* ---- Toast ---- */
        #toast-container {
            position: fixed;
            top: 20px; right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
    </style>
</head>
<body>
<?php
$user = Auth::user();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
function isActive(string $path, string $currentPath): string {
    return (str_starts_with($currentPath, $path)) ? 'active' : '';
}
function initials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) return strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
    return strtoupper(substr($name, 0, 2));
}
$tenantId = $user['tenant_id'] ?? null;
$tenantRow = $tenantId ? Database::getInstance()->fetch("SELECT name FROM tenants WHERE id = ?", [$tenantId]) : null;
$tenantName = $tenantRow['name'] ?? 'Your Company';
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'User';
$userRole = $user['type'] === 'super_admin' ? 'Super Admin' : ($user['roles'] ?? 'HR Staff');
?>
<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo">
                <span class="logo-icon">🤖</span>
                <span class="logo-text">AI Recruit</span>
            </div>
            <div class="sidebar-tenant" title="<?= htmlspecialchars($tenantName) ?>"><?= htmlspecialchars($tenantName) ?></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="/dashboard" class="nav-item <?= isActive('/dashboard', $currentPath) ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="/jobs" class="nav-item <?= isActive('/jobs', $currentPath) ?>">
                <span class="nav-icon">💼</span> Jobs
            </a>
            <a href="/candidates" class="nav-item <?= isActive('/candidates', $currentPath) ?>">
                <span class="nav-icon">👥</span> Candidates
            </a>
            <a href="/pipeline" class="nav-item <?= isActive('/pipeline', $currentPath) ?>">
                <span class="nav-icon">🔄</span> Pipeline
            </a>

            <div class="nav-section-label">Interviews</div>
            <a href="/ai-interviews" class="nav-item <?= isActive('/ai-interviews', $currentPath) ?>">
                <span class="nav-icon">🤖</span> AI Interviews
            </a>
            <a href="/human-interviews" class="nav-item <?= isActive('/human-interviews', $currentPath) ?>">
                <span class="nav-icon">🎙️</span> Human Interviews
            </a>

            <div class="nav-section-label">Hiring</div>
            <a href="/offers" class="nav-item <?= isActive('/offers', $currentPath) ?>">
                <span class="nav-icon">📄</span> Offers
            </a>
            <a href="/talent-pool" class="nav-item <?= isActive('/talent-pool', $currentPath) ?>">
                <span class="nav-icon">⭐</span> Talent Pool
            </a>
            <a href="/avatars" class="nav-item <?= isActive('/avatars', $currentPath) ?>">
                <span class="nav-icon">🪪</span> Avatars
            </a>

            <div class="nav-section-label">Admin</div>
            <a href="/users" class="nav-item <?= isActive('/users', $currentPath) ?>">
                <span class="nav-icon">👤</span> Users
            </a>
            <a href="/roles" class="nav-item <?= isActive('/roles', $currentPath) ?>">
                <span class="nav-icon">🛡️</span> Roles
            </a>
            <a href="/settings" class="nav-item <?= isActive('/settings', $currentPath) ?>">
                <span class="nav-icon">⚙️</span> Settings
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="/logout" class="nav-item" onclick="return confirm('Sign out?')">
                <span class="nav-icon">🚪</span> Sign Out
            </a>
        </div>
    </aside>

    <!-- Main area -->
    <div class="main-area">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
            <div class="topbar-actions">
                <!-- Notification bell -->
                <button class="topbar-btn" title="Notifications" onclick="window.location='/notifications'">
                    🔔
                    <span class="notif-badge"></span>
                </button>
                <!-- User dropdown -->
                <div class="user-dropdown-toggle" id="userDropdownToggle">
                    <div class="user-avatar"><?= initials($userName) ?></div>
                    <div>
                        <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="user-role"><?= htmlspecialchars($userRole) ?></div>
                    </div>
                    <span class="dropdown-caret">▼</span>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <a href="/profile" class="dropdown-item">👤 My Profile</a>
                        <a href="/settings" class="dropdown-item">⚙️ Settings</a>
                        <hr class="dropdown-divider">
                        <a href="/logout" class="dropdown-item danger" onclick="return confirm('Sign out?')">🚪 Sign Out</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page content -->
        <main class="page-content">
            <?= $content ?? '' ?>
        </main>
    </div>
</div>

<!-- Toast container -->
<div id="toast-container"></div>

<?php require_once __DIR__ . '/../partials/view_scripts.php'; ?>

<script>
// User dropdown toggle
document.getElementById('userDropdownToggle').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('userDropdownMenu').classList.toggle('open');
});
document.addEventListener('click', function() {
    document.getElementById('userDropdownMenu').classList.remove('open');
});
</script>
</body>
</html>
