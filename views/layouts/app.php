<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'RecruitAI') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-transition { transition: width 0.25s ease, transform 0.25s ease; }
        .nav-link { transition: background-color 0.15s, color 0.15s; }
        .nav-link.active { background-color: rgba(99,102,241,0.15); color: #a5b4fc; }
        .nav-link:not(.active):hover { background-color: rgba(255,255,255,0.07); }
        #sidebar-overlay { display: none; }
        @media (max-width: 1023px) {
            #sidebar { transform: translateX(-100%); position: fixed; z-index: 40; height: 100vh; }
            #sidebar.open { transform: translateX(0); }
            #sidebar-overlay.visible { display: block; }
        }
    </style>
</head>
<body class="h-full bg-gray-100 flex">

<?php
$user = Auth::user();
$isSuperAdmin = ($user['type'] ?? '') === 'super_admin';
$currentPath = strtok($_SERVER['REQUEST_URI'], '?');
$notifCount = $_SESSION['notification_count'] ?? 0;

$hrNav = [
    ['href' => '/dashboard',        'label' => 'Dashboard',         'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['href' => '/jobs',             'label' => 'Jobs',              'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
    ['href' => '/pipeline',         'label' => 'Pipeline',          'icon' => 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2'],
    ['href' => '/candidates',       'label' => 'Candidates',        'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
    ['href' => '/interviews',       'label' => 'Interviews',        'icon' => 'M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
    ['href' => '/human-interviews', 'label' => 'Human Interviews',  'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
    ['href' => '/offers',           'label' => 'Offers',            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['href' => '/talent-pool',      'label' => 'Talent Pool',       'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
    ['href' => '/avatars',          'label' => 'Avatars',           'icon' => 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['href' => '/ai-analytics',     'label' => 'AI Analytics',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ['href' => '/users',            'label' => 'Team',              'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    ['href' => '/roles',            'label' => 'Roles',             'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
    ['href' => '/settings',         'label' => 'Settings',          'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
];

$superNav = [
    ['href' => '/super/dashboard', 'label' => 'Dashboard',  'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['href' => '/super/companies', 'label' => 'Companies',  'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
    ['href' => '/super/users',     'label' => 'Users',      'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    ['href' => '/super/ai-usage',  'label' => 'AI Usage',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ['href' => '/super/settings',  'label' => 'Settings',   'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
];

$navItems = $isSuperAdmin ? $superNav : $hrNav;
$userInitials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
?>

<!-- Sidebar overlay (mobile) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-30" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar-transition flex flex-col w-64 bg-gray-900 text-white min-h-screen flex-shrink-0">
    <!-- Logo -->
    <div class="flex items-center px-4 py-5 border-b border-gray-700/50">
        <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <span class="ml-3 text-lg font-bold text-white whitespace-nowrap">RecruitAI</span>
        <?php if ($isSuperAdmin): ?>
            <span class="ml-auto text-xs bg-purple-600 text-purple-100 px-2 py-0.5 rounded-full font-medium">Admin</span>
        <?php endif; ?>
    </div>

    <!-- Nav -->
    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <?php foreach ($navItems as $item): ?>
            <?php $active = ($currentPath === $item['href'] || strpos($currentPath, $item['href']) === 0 && strlen($item['href']) > 1); ?>
            <a href="<?= $item['href'] ?>" class="nav-link <?= $active ? 'active' : 'text-gray-400' ?> flex items-center px-3 py-2.5 rounded-lg text-sm font-medium group">
                <svg class="w-5 h-5 flex-shrink-0 <?= $active ? 'text-indigo-400' : 'text-gray-500 group-hover:text-gray-300' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="<?= $item['icon'] ?>"/>
                </svg>
                <span class="ml-3 truncate"><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- User info bottom -->
    <div class="border-t border-gray-700/50 p-4">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                <?= htmlspecialchars($userInitials) ?>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($userName) ?></p>
                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($user['email'] ?? '') ?></p>
            </div>
            <a href="/logout" class="text-gray-400 hover:text-red-400 transition-colors" title="Logout">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </a>
        </div>
    </div>
</aside>

<!-- Main content area -->
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">

    <!-- Top navbar -->
    <header class="bg-white border-b border-gray-200 flex-shrink-0 z-20">
        <div class="flex items-center justify-between h-16 px-4 sm:px-6">
            <!-- Left: hamburger + page title -->
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1 class="text-lg font-semibold text-gray-900 hidden sm:block"><?= htmlspecialchars($pageTitle ?? $title ?? 'Dashboard') ?></h1>
            </div>

            <!-- Right: notifications + avatar dropdown -->
            <div class="flex items-center space-x-3">
                <!-- Notifications -->
                <div class="relative">
                    <button id="notif-btn" onclick="toggleDropdown('notif-dropdown')" class="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <?php if ($notifCount > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                                <?= $notifCount > 99 ? '99+' : $notifCount ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900">Notifications</p>
                        </div>
                        <div class="px-4 py-6 text-center text-sm text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                            </svg>
                            No new notifications
                        </div>
                    </div>
                </div>

                <!-- Avatar dropdown -->
                <div class="relative">
                    <button id="avatar-btn" onclick="toggleDropdown('avatar-dropdown')" class="flex items-center space-x-2 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-xs font-bold text-white">
                            <?= htmlspecialchars($userInitials) ?>
                        </div>
                        <span class="text-sm font-medium text-gray-700 hidden sm:block max-w-32 truncate"><?= htmlspecialchars($userName) ?></span>
                        <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="avatar-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($userName) ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                        </div>
                        <a href="/profile" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Profile
                        </a>
                        <a href="/logout" class="flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4 mr-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Flash messages -->
    <?php if (!empty($_SESSION['flash'])): ?>
        <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div id="flash-messages" class="px-4 sm:px-6 pt-4 space-y-2">
            <?php foreach ($flash as $type => $message): ?>
                <?php
                $styles = [
                    'success' => 'bg-green-50 border-green-400 text-green-800',
                    'error'   => 'bg-red-50 border-red-400 text-red-800',
                    'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
                    'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
                ];
                $cls = $styles[$type] ?? $styles['info'];
                ?>
                <div class="border-l-4 p-3 rounded text-sm font-medium <?= $cls ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('flash-messages');
                if (el) { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(function(){ el.remove(); }, 500); }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- Page content -->
    <main class="flex-1 overflow-y-auto p-4 sm:p-6">
        <?= $content ?>
    </main>
</div>

<script>
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('visible');
}
function closeSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
}
function toggleDropdown(id) {
    var el = document.getElementById(id);
    var allDropdowns = ['notif-dropdown', 'avatar-dropdown'];
    allDropdowns.forEach(function(d) {
        if (d !== id) {
            document.getElementById(d).classList.add('hidden');
        }
    });
    el.classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    var dropdowns = ['notif-dropdown', 'avatar-dropdown'];
    var triggers = ['notif-btn', 'avatar-btn'];
    var clickedTrigger = triggers.some(function(t) {
        var el = document.getElementById(t);
        return el && el.contains(e.target);
    });
    if (!clickedTrigger) {
        dropdowns.forEach(function(d) {
            var el = document.getElementById(d);
            if (el) el.classList.add('hidden');
        });
    }
});
</script>
</body>
</html>
