<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$current_user = get_auth_user();
$platform_name = 'Discover';
try { $platform_name = get_platform_setting('platform_name', 'Discover'); } catch(Exception $e) {}
$unread_count = 0;
$recent_notifications = [];
if ($current_user) {
    try {
        $unread_count = get_unread_notification_count($current_user['id']);
        $recent_notifications = db_fetch_all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8',
            [$current_user['id']]
        );
    } catch(Exception $e) {}
}

// Apply theme
$theme_class = ($current_user && $current_user['theme'] === 'dark') ? 'dark' : '';
?>
<!DOCTYPE html>
<html lang="en" class="<?= $theme_class ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?><?= e($platform_name) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#f0fdfa', 100: '#ccfbf1', 200: '#99f6e4',
              300: '#5eead4', 400: '#2dd4bf', 500: '#14b8a6',
              600: '#0d9488', 700: '#0f766e', 800: '#115e59', 900: '#134e4a',
            },
            accent: {
              400: '#22d3ee', 500: '#06b6d4', 600: '#0891b2',
            }
          },
          fontFamily: { sans: ['Inter', 'sans-serif'] }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .gradient-text { background: linear-gradient(135deg, #0d9488, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .glass-card { backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
    .sidebar-sticky { position: sticky; top: 80px; max-height: calc(100vh - 100px); overflow-y: auto; }
    .transition-smooth { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .hover-lift:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); }
    .notification-dot { animation: pulse 2s infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #0d9488; border-radius: 10px; }
    .dark ::-webkit-scrollbar-thumb { background: #0f766e; }
    .dropdown-menu { display: none; }
    .dropdown-menu.active { display: block; }
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .prose { max-width: none; }
    .prose p { margin-bottom: 1rem; line-height: 1.75; }
    .prose h2 { font-size: 1.5rem; font-weight: 700; margin: 1.5rem 0 0.75rem; }
    .prose h3 { font-size: 1.25rem; font-weight: 600; margin: 1.25rem 0 0.5rem; }
    .prose ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
    .prose ol { list-style: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
    .prose li { margin-bottom: 0.25rem; }
    .prose strong { font-weight: 700; }
    .prose pre { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin: 1rem 0; }
    .prose code { background: #f1f5f9; color: #0f172a; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
    .dark .prose code { background: #334155; color: #e2e8f0; }
  </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen">

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/90 dark:bg-gray-900/90 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-700/50 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">
      <!-- Logo -->
      <a href="/index.php" class="flex items-center gap-2 group flex-shrink-0">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-600 to-accent-500 flex items-center justify-center shadow-md group-hover:shadow-primary-500/30 transition-smooth">
          <span class="text-white font-bold text-lg">D</span>
        </div>
        <span class="font-black text-xl hidden sm:block gradient-text"><?= e($platform_name) ?></span>
      </a>

      <!-- Search Bar -->
      <form action="/index.php" method="GET" class="flex-1 max-w-md mx-4 hidden sm:block">
        <div class="relative">
          <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>"
            placeholder="Search communities..."
            class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-smooth placeholder-gray-400">
          <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
      </form>

      <!-- Right side -->
      <div class="flex items-center gap-2 sm:gap-3">
        <?php if ($current_user): ?>
          <!-- Create Community -->
          <a href="/create-community.php"
            class="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-sm font-semibold hover:shadow-lg hover:shadow-primary-500/25 transition-smooth hover:-translate-y-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Create</span>
          </a>

          <!-- Notifications -->
          <div class="relative" id="notif-dropdown-wrap">
            <button onclick="toggleDropdown('notif-menu')"
              class="relative p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-smooth">
              <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
              </svg>
              <?php if ($unread_count > 0): ?>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full notification-dot"></span>
              <?php endif; ?>
            </button>
            <div id="notif-menu" class="dropdown-menu absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden z-50">
              <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold text-sm">Notifications</h3>
                <?php if ($unread_count > 0): ?>
                  <span class="text-xs bg-primary-100 dark:bg-primary-900/50 text-primary-600 dark:text-primary-400 px-2 py-0.5 rounded-full font-medium"><?= $unread_count ?> new</span>
                <?php endif; ?>
              </div>
              <div class="max-h-96 overflow-y-auto">
                <?php if (empty($recent_notifications)): ?>
                  <div class="px-4 py-8 text-center">
                    <div class="text-3xl mb-2">🔔</div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No notifications yet</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($recent_notifications as $notif): ?>
                    <a href="<?= e($notif['link'] ?: '#') ?>"
                      class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-smooth <?= $notif['is_read'] ? '' : 'bg-primary-50/50 dark:bg-primary-900/20' ?>">
                      <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">
                        <?php
                        $icons = ['new_follower'=>'👤','post_like'=>'❤️','membership_approved'=>'✅','points_awarded'=>'⭐','badge_awarded'=>'🏅'];
                        echo $icons[$notif['type']] ?? '🔔';
                        ?>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-900 dark:text-gray-100"><?= e($notif['title']) ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= e($notif['message']) ?></p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5"><?= time_ago($notif['created_at']) ?></p>
                      </div>
                      <?php if (!$notif['is_read']): ?>
                        <div class="w-2 h-2 bg-primary-500 rounded-full mt-1.5 flex-shrink-0"></div>
                      <?php endif; ?>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="border-t border-gray-100 dark:border-gray-700 px-4 py-2">
                <button onclick="markAllRead()" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">Mark all as read</button>
              </div>
            </div>
          </div>

          <!-- User Avatar Dropdown -->
          <div class="relative" id="user-dropdown-wrap">
            <button onclick="toggleDropdown('user-menu')" class="flex items-center gap-2 p-1 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-smooth">
              <img src="<?= get_avatar_url($current_user['avatar'], $current_user['first_name'] . ' ' . $current_user['last_name']) ?>"
                alt="<?= e($current_user['username']) ?>"
                class="w-8 h-8 rounded-full object-cover ring-2 ring-primary-500/30">
              <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-300 max-w-24 truncate"><?= e($current_user['first_name'] ?: $current_user['username']) ?></span>
              <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div id="user-menu" class="dropdown-menu absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden z-50">
              <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <p class="font-semibold text-sm"><?= e(trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''))) ?: e($current_user['username']) ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">@<?= e($current_user['username']) ?></p>
              </div>
              <div class="py-1">
                <a href="/profile.php?username=<?= e($current_user['username']) ?>" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-smooth">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  My Profile
                </a>
                <a href="/settings.php" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-smooth">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  Settings
                </a>
                <a href="/create-community.php" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-smooth sm:hidden">
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  Create Community
                </a>
              </div>
              <div class="border-t border-gray-100 dark:border-gray-700 py-1">
                <a href="/logout.php" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-smooth">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                  Sign Out
                </a>
              </div>
            </div>
          </div>

        <?php else: ?>
          <a href="/login.php" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-smooth">Sign In</a>
          <a href="/register.php" class="px-4 py-2 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-sm font-semibold hover:shadow-lg hover:shadow-primary-500/25 transition-smooth hover:-translate-y-0.5">Get Started</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- Spacer for fixed nav -->
<div class="h-16"></div>

<script>
function toggleDropdown(id) {
  const menu = document.getElementById(id);
  const isActive = menu.classList.contains('active');
  // Close all dropdowns
  document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('active'));
  if (!isActive) menu.classList.add('active');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
  if (!e.target.closest('#notif-dropdown-wrap') && !e.target.closest('#user-dropdown-wrap')) {
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('active'));
  }
});

function markAllRead() {
  fetch('/api/notifications.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'mark_all_read'})
  }).then(() => {
    document.querySelectorAll('.notification-dot').forEach(el => el.remove());
    document.querySelectorAll('.bg-primary-50\\/50, .bg-primary-50').forEach(el => {
      el.classList.remove('bg-primary-50/50', 'bg-primary-50', 'bg-primary-900/20', 'dark:bg-primary-900/20');
    });
  });
}

function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  const colors = type === 'success' ? 'bg-primary-600' : 'bg-red-600';
  toast.className = `fixed bottom-6 right-6 z-[9999] ${colors} text-white px-6 py-3 rounded-2xl shadow-2xl font-medium text-sm transform translate-y-20 opacity-0 transition-all duration-300`;
  toast.textContent = message;
  document.body.appendChild(toast);
  requestAnimationFrame(() => {
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
  });
  setTimeout(() => {
    toast.style.transform = 'translateY(20px)';
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
</script>
