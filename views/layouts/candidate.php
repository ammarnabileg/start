<?php
// Candidate portal layout
// Variables: $pageTitle, $user, $content
$platformName = $_ENV['APP_NAME'] ?? 'HireAI';
$pageTitle    = $pageTitle ?? 'Candidate Portal';
$userName     = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['full_name'] ?? $user['name'] ?? 'Candidate'));
$userEmail    = htmlspecialchars($user['email'] ?? '');
$userAvatar   = $user['avatar'] ?? null;
$initials     = strtoupper(substr($user['first_name'] ?? $user['full_name'] ?? $user['name'] ?? 'C', 0, 1));

$currentPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function candNavItem(string $href, string $label, string $currentPath): string {
    $active = str_starts_with($currentPath, $href);
    if ($href === '/c/dashboard' && $currentPath === '/c/dashboard') $active = true;
    if ($href === '/c/dashboard' && $currentPath !== '/c/dashboard') $active = false;
    $cls = $active
        ? 'text-violet-700 font-semibold border-b-2 border-violet-600 pb-0.5'
        : 'text-gray-600 hover:text-gray-900 font-medium transition-colors';
    return "<a href='{$href}' class='text-sm {$cls}'>{$label}</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($platformName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { font-family: 'Inter', sans-serif; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }
@keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.fade-in { animation: fadeIn 0.3s ease; }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.slide-down { animation: slideDown 0.2s ease; }
.dropdown-menu { display: none; }
.dropdown-menu.open { display: block; }
/* Mobile menu */
#mobile-nav { display: none; }
#mobile-nav.open { display: block; }
</style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<!-- ═══════════════════════════════ TOP NAVBAR ═══════════════════════════════ -->
<nav class="fixed top-0 left-0 right-0 z-40 bg-white border-b border-gray-100 h-16">
  <div class="max-w-7xl mx-auto h-full px-4 sm:px-6 flex items-center gap-6">

    <!-- Logo -->
    <a href="/c/dashboard" class="flex items-center gap-2.5 flex-shrink-0 mr-2">
      <div class="w-8 h-8 bg-violet-700 rounded-xl flex items-center justify-center">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
      </div>
      <span class="font-bold text-gray-900 text-base hidden sm:block"><?= htmlspecialchars($platformName) ?></span>
    </a>

    <!-- Desktop Nav Links -->
    <div class="hidden md:flex items-center gap-6 flex-1">
      <?= candNavItem('/c/dashboard', 'Dashboard', $currentPath) ?>
      <?= candNavItem('/c/jobs', 'Find Jobs', $currentPath) ?>
      <?= candNavItem('/c/applications', 'My Applications', $currentPath) ?>
      <?= candNavItem('/c/profile', 'My Profile', $currentPath) ?>
      <?= candNavItem('/c/offers', 'Offers', $currentPath) ?>
    </div>

    <div class="flex items-center gap-3 ml-auto">
      <!-- Notification bell -->
      <div class="relative">
        <button id="notif-btn" onclick="toggleDropdown('notif-dropdown')"
          class="relative w-9 h-9 bg-gray-100 hover:bg-gray-200 rounded-full flex items-center justify-center transition-colors">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <!-- Unread dot -->
          <span id="notif-dot" class="absolute top-1.5 right-1.5 w-2 h-2 bg-amber-500 rounded-full hidden"></span>
        </button>

        <!-- Notification dropdown -->
        <div id="notif-dropdown" class="dropdown-menu absolute right-0 top-12 w-80 bg-white border border-gray-100 rounded-2xl shadow-xl z-50">
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-900">Notifications</span>
            <button onclick="markAllRead()" class="text-xs text-violet-600 hover:text-violet-800 font-medium">Mark all read</button>
          </div>
          <div id="notif-list" class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
            <div class="px-4 py-8 text-center text-sm text-gray-400">No new notifications</div>
          </div>
          <div class="px-4 py-2.5 border-t border-gray-100">
            <a href="/c/notifications" class="text-xs text-violet-600 hover:text-violet-800 font-medium">View all notifications →</a>
          </div>
        </div>
      </div>

      <!-- User avatar dropdown -->
      <div class="relative">
        <button onclick="toggleDropdown('user-dropdown')"
          class="flex items-center gap-2 rounded-full hover:bg-gray-50 pl-1 pr-2 py-1 transition-colors">
          <?php if ($userAvatar): ?>
          <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover">
          <?php else: ?>
          <div class="w-8 h-8 bg-violet-600 rounded-full flex items-center justify-center text-sm font-bold text-white">
            <?= $initials ?>
          </div>
          <?php endif; ?>
          <span class="text-sm font-medium text-gray-700 hidden sm:block max-w-28 truncate"><?= $userName ?></span>
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>

        <!-- User dropdown -->
        <div id="user-dropdown" class="dropdown-menu absolute right-0 top-12 w-56 bg-white border border-gray-100 rounded-2xl shadow-xl z-50 py-2">
          <div class="px-4 py-2 border-b border-gray-100 mb-1">
            <div class="text-sm font-semibold text-gray-900 truncate"><?= $userName ?></div>
            <div class="text-xs text-gray-400 truncate"><?= $userEmail ?></div>
          </div>
          <a href="/c/profile" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            My Profile
          </a>
          <a href="/c/notifications" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Notifications
          </a>
          <div class="border-t border-gray-100 mt-1 pt-1">
            <a href="/logout" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              Sign Out
            </a>
          </div>
        </div>
      </div>

      <!-- Mobile hamburger -->
      <button onclick="toggleMobileNav()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
        <svg id="hamburger-icon" class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </div>

  <!-- Mobile nav panel -->
  <div id="mobile-nav" class="md:hidden bg-white border-t border-gray-100 px-4 py-3 space-y-1">
    <a href="/c/dashboard" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Dashboard</a>
    <a href="/c/jobs" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Find Jobs</a>
    <a href="/c/applications" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">My Applications</a>
    <a href="/c/profile" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">My Profile</a>
    <a href="/c/offers" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Offers</a>
    <a href="/c/notifications" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Notifications</a>
    <div class="border-t border-gray-100 pt-2 mt-2">
      <a href="/logout" class="block px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50">Sign Out</a>
    </div>
  </div>
</nav>

<!-- ═══════════════════════════════ MAIN CONTENT ═══════════════════════════════ -->
<main class="flex-1 pt-16">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 fade-in">
    <?= $content ?? '' ?>
  </div>
</main>

<!-- ═══════════════════════════════ FOOTER ═══════════════════════════════ -->
<footer class="bg-white border-t border-gray-100 mt-auto">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-5 flex flex-col sm:flex-row items-center justify-between gap-2">
    <div class="flex items-center gap-2">
      <div class="w-5 h-5 bg-violet-700 rounded-md flex items-center justify-center">
        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18"/></svg>
      </div>
      <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($platformName) ?></span>
    </div>
    <p class="text-xs text-gray-400">&copy; <?= date('Y') ?> <?= htmlspecialchars($platformName) ?>. All rights reserved.</p>
    <div class="flex items-center gap-4">
      <a href="/privacy" class="text-xs text-gray-400 hover:text-gray-700 transition-colors">Privacy</a>
      <a href="/terms" class="text-xs text-gray-400 hover:text-gray-700 transition-colors">Terms</a>
      <a href="/help" class="text-xs text-gray-400 hover:text-gray-700 transition-colors">Help</a>
    </div>
  </div>
</footer>

<!-- ═══════════════════════════════ AI HELP BUTTON ═══════════════════════════════ -->
<div class="fixed bottom-6 right-6 z-30">
  <div id="help-tooltip" class="hidden absolute bottom-14 right-0 bg-gray-900 text-white text-xs rounded-xl px-3 py-2 whitespace-nowrap shadow-lg">
    Need help? Ask AI
  </div>
  <button onclick="toggleHelpChat()"
    onmouseenter="document.getElementById('help-tooltip').classList.remove('hidden')"
    onmouseleave="document.getElementById('help-tooltip').classList.add('hidden')"
    class="w-12 h-12 bg-violet-600 hover:bg-violet-700 rounded-full flex items-center justify-center shadow-lg shadow-violet-900/30 transition-all hover:scale-105">
    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  </button>

  <!-- Help chat panel -->
  <div id="help-chat-panel" class="hidden absolute bottom-16 right-0 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
    <div class="bg-violet-700 px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
          <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18"/></svg>
        </div>
        <span class="text-white text-sm font-semibold">AI Assistant</span>
      </div>
      <button onclick="toggleHelpChat()" class="text-white/70 hover:text-white">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div id="help-messages" class="h-52 overflow-y-auto p-4 space-y-3">
      <div class="bg-gray-100 rounded-xl px-3 py-2 text-sm text-gray-700 max-w-[85%]">
        Hi! I'm here to help. Ask me about your applications, interviews, or anything else.
      </div>
    </div>
    <div class="border-t border-gray-100 p-3 flex gap-2">
      <input id="help-input" type="text" placeholder="Ask something..."
        class="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-violet-400"
        onkeydown="if(event.key==='Enter') sendHelpMessage()">
      <button onclick="sendHelpMessage()"
        class="w-9 h-9 bg-violet-600 hover:bg-violet-700 rounded-full flex items-center justify-center transition-colors">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
      </button>
    </div>
  </div>
</div>

<script>
// Dropdown toggle
function toggleDropdown(id) {
  document.querySelectorAll('.dropdown-menu').forEach(el => {
    if (el.id !== id) el.classList.remove('open');
  });
  document.getElementById(id).classList.toggle('open');
}
document.addEventListener('click', (e) => {
  if (!e.target.closest('[onclick*="toggleDropdown"]') && !e.target.closest('.dropdown-menu')) {
    document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('open'));
  }
});

// Mobile nav
function toggleMobileNav() {
  document.getElementById('mobile-nav').classList.toggle('open');
}

// Help chat
function toggleHelpChat() {
  const panel = document.getElementById('help-chat-panel');
  panel.classList.toggle('hidden');
  if (!panel.classList.contains('hidden')) {
    setTimeout(() => document.getElementById('help-input')?.focus(), 100);
  }
}

async function sendHelpMessage() {
  const input = document.getElementById('help-input');
  const msgs  = document.getElementById('help-messages');
  const text  = input?.value.trim();
  if (!text || !msgs) return;
  input.value = '';

  // User message
  const userDiv = document.createElement('div');
  userDiv.className = 'flex justify-end';
  userDiv.innerHTML = `<div class="bg-violet-600 text-white rounded-xl px-3 py-2 text-sm max-w-[85%]">${text}</div>`;
  msgs.appendChild(userDiv);
  msgs.scrollTop = msgs.scrollHeight;

  // AI reply
  try {
    const res = await fetch('/api/v1/ai?action=help', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: text, context: 'candidate_portal' })
    });
    const data = await res.json();
    const aiDiv = document.createElement('div');
    aiDiv.className = 'bg-gray-100 rounded-xl px-3 py-2 text-sm text-gray-700 max-w-[85%]';
    aiDiv.textContent = data.reply || 'I\'ll look into that for you!';
    msgs.appendChild(aiDiv);
    msgs.scrollTop = msgs.scrollHeight;
  } catch(e) {
    const errDiv = document.createElement('div');
    errDiv.className = 'bg-gray-100 rounded-xl px-3 py-2 text-sm text-gray-500 max-w-[85%]';
    errDiv.textContent = 'Sorry, I\'m having trouble right now. Please try again.';
    msgs.appendChild(errDiv);
    msgs.scrollTop = msgs.scrollHeight;
  }
}

function markAllRead() {
  document.getElementById('notif-dot')?.classList.add('hidden');
  fetch('/api/v1/notifications', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
}

// Load notifications on page load
async function loadNotifications() {
  try {
    const res = await fetch('/api/v1/notifications?limit=5', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    const items = data.data ?? data.notifications ?? [];
    if (items.length) {
      document.getElementById('notif-dot')?.classList.remove('hidden');
      const list = document.getElementById('notif-list');
      if (list) {
        list.innerHTML = items.map(n => `
          <div class="px-4 py-3 hover:bg-gray-50 transition-colors">
            <div class="text-sm font-medium text-gray-800">${n.title ?? ''}</div>
            <div class="text-xs text-gray-400 mt-0.5">${n.message ?? n.body ?? ''}</div>
          </div>
        `).join('');
      }
    }
  } catch(e) { /* silent */ }
}
loadNotifications();
</script>
</body>
</html>
