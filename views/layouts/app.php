<?php
$user = $user ?? Auth::user();
$platformName = $_ENV['APP_NAME'] ?? 'HireAI';
$isSuper = $user && in_array('super_admin', $user['roles'] ?? []);
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (!function_exists('navItem')) { function navItem(string $href, string $label, string $icon, string $current): string {
    $active = str_starts_with($current, $href) && ($href !== '/dashboard' || $current === '/dashboard') && ($href !== '/super/dashboard' || $current === '/super/dashboard');
    $cls = $active
        ? 'flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-violet-700 text-white'
        : 'flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors';
    return "<a href='{$href}' class='{$cls}'>{$icon}<span>{$label}</span></a>";
} }
if (!function_exists('sideIcon')) { function sideIcon(string $path): string {
    $icons = [
        'home' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
        'briefcase' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
        'kanban' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>',
        'users' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'sparkles' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
        'calendar' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
        'file' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'bookmark' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>',
        'video' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>',
        'chart' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        'cog' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'shield' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
        'user-cog' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'building' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
        'terminal' => '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    ];
    return $icons[$path] ?? '';
} }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'HireAI') ?> — <?= htmlspecialchars($platformName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { font-family: 'Inter', sans-serif; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }
#sidebar { transition: transform 0.3s ease; }
@media (max-width:1023px) { #sidebar.closed { transform: translateX(-100%); } }
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.fade-in { animation: fadeIn 0.3s ease; }
@keyframes slideIn { from{transform:translateX(-10px);opacity:0} to{transform:translateX(0);opacity:1} }
.slide-in { animation: slideIn 0.2s ease; }
.toast { transition: all 0.3s ease; }
</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Sidebar Overlay (mobile) -->
<div id="overlay" class="fixed inset-0 bg-black/50 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white border-r border-gray-200 z-30 flex flex-col closed lg:translate-x-0">
  <!-- Logo -->
  <div class="h-16 flex items-center px-5 border-b border-gray-100 flex-shrink-0">
    <a href="<?= $isSuper ? '/super/dashboard' : '/dashboard' ?>" class="flex items-center gap-3">
      <div class="w-8 h-8 bg-violet-700 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
      </div>
      <span class="font-bold text-gray-900 text-base"><?= htmlspecialchars($platformName) ?></span>
    </a>
  </div>

  <!-- Nav -->
  <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
    <?php if ($isSuper): ?>
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">Management</div>
    <?= navItem('/super/dashboard', 'Dashboard', sideIcon('home'), $currentPath) ?>
    <?= navItem('/super/companies', 'Companies', sideIcon('building'), $currentPath) ?>
    <?= navItem('/super/users', 'All Users', sideIcon('users'), $currentPath) ?>
    <div class="border-t border-gray-100 my-2"></div>
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">Analytics & AI</div>
    <?= navItem('/super/ai-usage', 'AI Analytics', sideIcon('chart'), $currentPath) ?>
    <?= navItem('/super/api-keys', 'API Keys', sideIcon('shield'), $currentPath) ?>
    <div class="border-t border-gray-100 my-2"></div>
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">System</div>
    <?= navItem('/super/settings', 'Settings', sideIcon('cog'), $currentPath) ?>
    <?= navItem('/super/terminal', 'Terminal', sideIcon('terminal'), $currentPath) ?>
    <?php else: ?>
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">Recruitment</div>
    <?= navItem('/dashboard', 'Dashboard', sideIcon('home'), $currentPath) ?>
    <?= navItem('/jobs', 'Jobs', sideIcon('briefcase'), $currentPath) ?>
    <?= navItem('/pipeline', 'Pipeline', sideIcon('kanban'), $currentPath) ?>
    <?= navItem('/candidates', 'Candidates', sideIcon('users'), $currentPath) ?>
    <?= navItem('/ai-interviews', 'AI Interviews', sideIcon('sparkles'), $currentPath) ?>
    <div class="border-t border-gray-100 my-2"></div>
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">Hiring</div>
    <?= navItem('/human-interviews', 'Human Interviews', sideIcon('calendar'), $currentPath) ?>
    <?= navItem('/offers', 'Offers', sideIcon('file'), $currentPath) ?>
    <?= navItem('/talent-pool', 'Talent Pool', sideIcon('bookmark'), $currentPath) ?>
    <div class="border-t border-gray-100 my-2"></div>
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">Tools</div>
    <?= navItem('/avatars', 'Avatars', sideIcon('video'), $currentPath) ?>
    <?= navItem('/ai-analytics', 'AI Analytics', sideIcon('chart'), $currentPath) ?>
    <div class="border-t border-gray-100 my-2"></div>
    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 py-2">Manage</div>
    <?= navItem('/users', 'Users', sideIcon('user-cog'), $currentPath) ?>
    <?= navItem('/roles', 'Roles & Permissions', sideIcon('shield'), $currentPath) ?>
    <?= navItem('/settings', 'Settings', sideIcon('cog'), $currentPath) ?>
    <?php endif; ?>
  </nav>

  <!-- User Profile -->
  <div class="border-t border-gray-100 p-3 flex-shrink-0">
    <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer" onclick="toggleUserMenu()">
      <div class="w-9 h-9 rounded-xl bg-violet-700 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
        <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($user['full_name'] ?? '') ?></div>
        <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars(($user['roles'][0] ?? 'user')) ?></div>
      </div>
      <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
    </div>
    <div id="userMenu" class="hidden mt-1 bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden">
      <a href="/profile" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        My Profile
      </a>
      <a href="/logout" class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Sign Out
      </a>
    </div>
  </div>
</aside>

<!-- MAIN CONTENT -->
<div class="lg:pl-64 flex flex-col min-h-screen">
  <!-- Top Bar -->
  <header class="sticky top-0 z-10 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
    <div class="flex items-center gap-4">
      <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl hover:bg-gray-100 transition-colors text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
    </div>
    <div class="flex items-center gap-2">
      <!-- Notification Bell -->
      <div class="relative">
        <button id="notifBtn" class="relative p-2 rounded-xl hover:bg-gray-100 transition-colors text-gray-600" onclick="toggleNotifications()">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <span id="notifDot" class="absolute top-1.5 right-1.5 w-2 h-2 bg-violet-600 rounded-full hidden"></span>
        </button>
        <div id="notifPanel" class="hidden absolute right-0 top-12 w-80 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-50">
            <span class="font-semibold text-gray-900 text-sm">Notifications</span>
            <button onclick="markAllRead()" class="text-xs text-violet-600 hover:text-violet-800 font-medium">Mark all read</button>
          </div>
          <div id="notifList" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <div class="py-8 text-center text-sm text-gray-400">Loading…</div>
          </div>
        </div>
      </div>
      <!-- Avatar -->
      <div class="w-9 h-9 rounded-xl bg-violet-700 flex items-center justify-center text-white font-bold text-sm cursor-pointer" onclick="toggleUserMenu()">
        <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
      </div>
    </div>
  </header>

  <!-- Page Content -->
  <main class="flex-1 p-6 fade-in">
    <?= $content ?>
  </main>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed top-5 right-5 z-50 space-y-2 max-w-sm"></div>

<!-- AI Copilot Button -->
<button onclick="toggleCopilot()" class="fixed bottom-6 right-6 w-14 h-14 bg-violet-700 hover:bg-violet-800 text-white rounded-2xl shadow-lg hover:shadow-violet-200 hover:shadow-xl transition-all duration-200 flex items-center justify-center z-40" title="AI Copilot">
  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
</button>

<!-- AI Copilot Panel -->
<div id="copilotPanel" class="fixed bottom-24 right-6 w-96 bg-white rounded-2xl shadow-2xl border border-gray-100 z-40 hidden flex-col" style="height:500px">
  <div class="flex items-center justify-between p-4 border-b border-gray-100">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 bg-violet-700 rounded-xl flex items-center justify-center">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
      </div>
      <span class="font-semibold text-gray-900 text-sm">AI Recruitment Copilot</span>
    </div>
    <button onclick="toggleCopilot()" class="text-gray-400 hover:text-gray-600 transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div id="copilotMessages" class="flex-1 overflow-y-auto p-4 space-y-3">
    <div class="text-center text-xs text-gray-400 py-4">Ask me anything about your candidates,<br>jobs, or recruitment pipeline.</div>
    <div class="bg-violet-50 rounded-2xl rounded-tl-sm p-3 text-sm text-gray-700">
      <strong class="text-violet-700">Hello!</strong> I'm your AI recruitment assistant. Try asking:<br>
      <em class="text-gray-500">"Who is the strongest candidate for the senior developer role?"</em>
    </div>
  </div>
  <div class="p-3 border-t border-gray-100">
    <div class="flex gap-2">
      <input type="text" id="copilotInput" placeholder="Ask about candidates, jobs..." 
        class="flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none"
        onkeydown="if(event.key==='Enter')sendCopilot()">
      <button onclick="sendCopilot()" class="bg-violet-700 hover:bg-violet-800 text-white rounded-xl px-4 py-2.5 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
      </button>
    </div>
  </div>
</div>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  const isClosed = sidebar.classList.contains('closed');
  sidebar.classList.toggle('closed', !isClosed);
  overlay.classList.toggle('hidden', isClosed);
}

function toggleUserMenu() {
  document.getElementById('userMenu').classList.toggle('hidden');
}

let notifLoaded = false;
async function toggleNotifications() {
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('hidden');
  if (!panel.classList.contains('hidden') && !notifLoaded) {
    notifLoaded = true;
    try {
      const r = await fetch('/api/v1/notifications');
      const d = await r.json();
      const list = document.getElementById('notifList');
      const items = d.data || [];
      if (!items.length) { list.innerHTML = '<div class="py-8 text-center text-sm text-gray-400">No notifications</div>'; return; }
      list.innerHTML = items.map(n => `<div class="px-4 py-3 ${n.read_at ? '' : 'bg-violet-50'} hover:bg-gray-50 transition-colors">
        <div class="text-sm font-medium text-gray-900">${n.title || ''}</div>
        <div class="text-xs text-gray-500 mt-0.5">${n.body || ''}</div>
      </div>`).join('');
      const unread = items.filter(n => !n.read_at).length;
      document.getElementById('notifDot').classList.toggle('hidden', unread === 0);
    } catch(e) { document.getElementById('notifList').innerHTML = '<div class="py-8 text-center text-sm text-gray-400">Failed to load</div>'; }
  }
}
async function markAllRead() {
  await fetch('/api/v1/notifications', {method:'POST'});
  notifLoaded = false;
  document.getElementById('notifDot').classList.add('hidden');
  document.getElementById('notifPanel').classList.add('hidden');
}
// Load unread count on page load
(async () => { try { const r = await fetch('/api/v1/notifications'); const d = await r.json(); const unread = (d.data||[]).filter(n=>!n.read_at).length; if (unread > 0) document.getElementById('notifDot').classList.remove('hidden'); } catch(e){} })();

function toggleCopilot() {
  const panel = document.getElementById('copilotPanel');
  panel.classList.toggle('hidden');
  panel.classList.toggle('flex');
  if (!panel.classList.contains('hidden')) document.getElementById('copilotInput').focus();
}

async function sendCopilot() {
  const input = document.getElementById('copilotInput');
  const msg = input.value.trim();
  if (!msg) return;
  const messages = document.getElementById('copilotMessages');
  // User message
  messages.innerHTML += `<div class="bg-gray-100 rounded-2xl rounded-tr-sm p-3 text-sm text-gray-900 self-end ml-8">${escHtml(msg)}</div>`;
  input.value = '';
  // Thinking
  const thinkId = 'think-' + Date.now();
  messages.innerHTML += `<div id="${thinkId}" class="bg-violet-50 rounded-2xl rounded-tl-sm p-3 text-sm text-violet-600">
    <span class="inline-flex gap-1">
      <span class="w-1.5 h-1.5 bg-violet-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
      <span class="w-1.5 h-1.5 bg-violet-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
      <span class="w-1.5 h-1.5 bg-violet-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
    </span>
  </div>`;
  messages.scrollTop = messages.scrollHeight;

  try {
    const r = await fetch('/api/v1/ai?action=copilot', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify({message: msg})
    });
    const d = await r.json();
    document.getElementById(thinkId)?.remove();
    messages.innerHTML += `<div class="bg-violet-50 rounded-2xl rounded-tl-sm p-3 text-sm text-gray-700">${escHtml(d.data?.answer || d.message || 'Sorry, I could not process that.')}</div>`;
  } catch(e) {
    document.getElementById(thinkId)?.remove();
    messages.innerHTML += `<div class="bg-red-50 rounded-2xl p-3 text-sm text-red-600">Connection error. Please try again.</div>`;
  }
  messages.scrollTop = messages.scrollHeight;
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(message, type = 'success') {
  const colors = {success:'bg-emerald-600', error:'bg-red-600', info:'bg-gray-800', warning:'bg-amber-500'};
  const id = 'toast-' + Date.now();
  const toast = document.createElement('div');
  toast.id = id;
  toast.className = `toast ${colors[type]||colors.info} text-white rounded-xl shadow-lg px-5 py-3 text-sm font-medium flex items-center gap-2 min-w-64`;
  toast.textContent = message;
  document.getElementById('toastContainer').appendChild(toast);
  setTimeout(() => { toast.style.opacity='0'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function confirm2(msg, callback) {
  if (window.confirm(msg)) callback();
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('#userMenu') && !e.target.closest('[onclick*="toggleUserMenu"]')) {
    document.getElementById('userMenu')?.classList.add('hidden');
  }
  if (!e.target.closest('#notifPanel') && !e.target.closest('#notifBtn')) {
    document.getElementById('notifPanel')?.classList.add('hidden');
  }
});
</script>
</body>
</html>
