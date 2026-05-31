<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$current_user = get_auth_user();
$platform_name = 'Discover';
$platform_logo = '';
$platform_tagline = '';
$seo_title = '';
$seo_desc = '';
$seo_keywords = '';
$seo_og_image = '';
$ga_id = '';
$seo_index = 'yes';
try {
 $platform_name = get_platform_setting('platform_name', 'Discover');
 $platform_logo = get_platform_setting('platform_logo', '');
 $platform_tagline = get_platform_setting('platform_tagline', '');
 $seo_title = get_platform_setting('seo_title', $platform_name . ' - Discover Communities');
 $seo_desc = get_platform_setting('seo_description', 'Join thousands of communities for learning, networking, and growth.');
 $seo_keywords = get_platform_setting('seo_keywords', 'community, learning, courses, networking');
 $seo_og_image = get_platform_setting('seo_og_image', '');
 $ga_id = get_platform_setting('ga_id', '');
 $seo_index = get_platform_setting('seo_index', 'yes');
} catch(Exception $e) {}
$page_title_full = isset($page_title) ? $page_title . ' | ' . $platform_name : $seo_title;
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

// Dark mode is DEFAULT — always dark unless user explicitly chose light
$theme_class = 'dark';
if ($current_user && isset($current_user['theme']) && $current_user['theme'] === 'light') {
 $theme_class = '';
}
?>
<!DOCTYPE html>
<html lang="en" class="<?= $theme_class ?>">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title><?= e($page_title_full) ?></title>
 <meta name="description" content="<?= e($seo_desc) ?>">
 <meta name="keywords" content="<?= e($seo_keywords) ?>">
 <?php if ($seo_index !== 'yes'): ?>
 <meta name="robots" content="noindex,nofollow">
 <?php endif; ?>
 <?php if ($seo_og_image): ?>
 <meta property="og:image" content="<?= e($seo_og_image) ?>">
 <?php endif; ?>
 <meta property="og:title" content="<?= e($page_title_full) ?>">
 <meta property="og:description" content="<?= e($seo_desc) ?>">
 <meta property="og:type" content="website">
 <meta name="csrf-token" content="<?= csrf_token() ?>">
 <?php if ($ga_id): ?>
 <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga_id) ?>"></script>
 <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga_id) ?>');</script>
 <?php endif; ?>
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
 },
 surface: {
 DEFAULT: '#1a1a1a',
 100: '#121212',
 200: '#1a1a1a',
 300: '#222222',
 400: '#2a2a2a',
 500: '#333333',
 }
 },
 borderRadius: { '4xl': '2rem' },
 fontFamily: { sans: ['Inter', 'sans-serif'] },
 boxShadow: {
 'airbnb': '0 6px 20px rgba(0,0,0,0.2)',
 'airbnb-lg': '0 16px 48px rgba(0,0,0,0.3)',
 }
 }
 }
 }
 </script>
 <style>
 body { font-family: 'Inter', sans-serif; }

 /* Scrollbar */
 ::-webkit-scrollbar { width: 6px; height: 6px; }
 ::-webkit-scrollbar-track { background: transparent; }
 ::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
 .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
 .scrollbar-hide::-webkit-scrollbar { display: none; }

 /* Card hover lift */
 .community-card { transition: transform 0.2s ease, box- 0.2s ease; }
 .community-card:hover { transform: translateY(-2px); }

 /* Glass effect */
 .glass { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }

 /* Gradient text */
 .gradient-text { background: linear-gradient(135deg, #0d9488, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

 /* Chip active underline */
 .chip-active { border-bottom: 2px solid currentColor; padding-bottom: calc(1rem - 2px); }

 /* Prose */
 .prose { max-width: none; }
 .prose p { margin-bottom: 1rem; line-height: 1.75; }
 .prose h2 { font-size: 1.5rem; font-weight: 700; margin: 1.5rem 0 0.75rem; }
 .prose h3 { font-size: 1.25rem; font-weight: 600; margin: 1.25rem 0 0.5rem; }
 .prose ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
 .prose ol { list-style: decimal; padding-left: 1.5rem; margin-bottom: 1rem; }
 .prose li { margin-bottom: 0.25rem; }
 .prose strong { font-weight: 700; }
 .prose pre { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin: 1rem 0; }
 .prose code { background: #2a2a2a; color: #e2e8f0; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }

 /* Line clamps */
 .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
 .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
 .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

 /* Notification pulse */
 @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
 .notification-dot { animation: pulse 2s infinite; }

 /* Sidebar sticky */
 .sidebar-sticky { position: sticky; top: 88px; max-height: calc(100vh - 100px); overflow-y: auto; }

 /* Dropdown */
 .dropdown-menu { display: none; }
 .dropdown-menu.active { display: block; }

 /* Dark mode defaults applied via Tailwind dark: prefix */
 .dark body { background: #121212; }
 </style>
</head>
<body class="bg-gray-50 dark:bg-[#121212] text-gray-900 dark:text-gray-100 min-h-screen">

<!-- Community dock — collapsed by default, hover to expand -->
<?php if ($current_user): ?>
<?php
$my_communities = [];
try {
 $my_communities = db_fetch_all('SELECT c.id, c.name, c.slug, c.logo FROM memberships m JOIN communities c ON c.id = m.community_id WHERE m.user_id = ? AND m.status = "approved" ORDER BY m.joined_at DESC LIMIT 10', [$current_user['id']]);
} catch (Exception $e) {}
?>
<?php if (!empty($my_communities)): ?>
<div id="community-dock" class="fixed left-0 top-1/2 -translate-y-1/2 z-40 group hidden md:block">
 <div class="flex flex-col gap-2 bg-white dark:bg-[#1a1a1a] border border-gray-200 dark:border-white/10 rounded-r-2xl py-3 px-2 transition-all duration-300 w-12 group-hover:w-52 overflow-hidden">
 <?php foreach ($my_communities as $mc): ?>
 <a href="/community.php?slug=<?= e($mc['slug']) ?>" class="flex items-center gap-3 min-w-max">
 <!-- Logo always visible -->
 <?php if ($mc['logo']): ?>
 <img src="<?= e($mc['logo']) ?>" class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
 <?php else: ?>
 <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-sm flex-shrink-0"><?= strtoupper(substr($mc['name'],0,1)) ?></div>
 <?php endif; ?>
 <!-- Name only visible on hover -->
 <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate max-w-[120px] opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap"><?= e($mc['name']) ?></span>
 </a>
 <?php endforeach; ?>
 </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- NAVIGATION — Airbnb sticky pill-search nav -->
<nav class="sticky top-0 z-50 glass bg-white/95 dark:bg-[#121212]/95 border-b border-gray-200/60 dark:border-white/10">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 md:pl-16">
 <div class="flex items-center justify-between h-16 gap-4">

 <!-- Logo / Community branding -->
 <?php if (!empty($community_header_mode) && !empty($community_for_header)): ?>
 <div class="flex items-center gap-2 flex-shrink-0">
 <a href="/index.php" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 mr-1" title="All communities">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
 </a>
 <?php if ($community_for_header['logo']): ?>
 <img src="<?= e($community_for_header['logo']) ?>" alt="" class="h-8 w-8 rounded-xl object-cover">
 <?php else: ?>
 <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-sm">
 <?= strtoupper(substr($community_for_header['name'], 0, 1)) ?>
 </div>
 <?php endif; ?>
 <span class="font-black text-base gradient-text hidden sm:block truncate max-w-[180px]"><?= e($community_for_header['name']) ?></span>
 </div>
 <?php else: ?>
 <a href="/index.php" class="flex items-center gap-2 flex-shrink-0 group">
 <?php if ($platform_logo): ?>
 <img src="<?= e($platform_logo) ?>" alt="<?= e($platform_name) ?>" class="h-8 w-auto object-contain">
 <?php else: ?>
 <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-sm ">
 <?= strtoupper(substr($platform_name, 0, 1)) ?>
 </div>
 <?php endif; ?>
 <span class="font-black text-lg gradient-text hidden sm:block"><?= e($platform_name) ?></span>
 </a>
 <?php endif; ?>

 <!-- Center search pill (desktop) -->
 <div class="hidden md:flex flex-1 max-w-sm justify-center">
 <div class="flex items-center bg-gray-100 dark:bg-[#2a2a2a] hover:bg-gray-200 dark:hover:bg-[#333] rounded-full px-4 py-2 gap-3 cursor-pointer border border-gray-200 dark:border-white/10 transition-colors w-full max-w-xs"
 onclick="document.getElementById('nav-search-input').focus()">
 <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
 </svg>
 <?php
 $in_community = !empty($page_community_slug);
 $search_placeholder = $in_community ? 'Search in ' . ($page_community_name ?? 'community') . '...' : 'Search communities...';
 $search_val = e($_GET['q'] ?? '');
 $search_action = $in_community
     ? "window.location='/community.php?slug=" . e($page_community_slug) . "&tab=community&q='+encodeURIComponent(this.value)"
     : "window.location='/index.php?q='+encodeURIComponent(this.value)";
 $btn_action = $in_community
     ? "window.location='/community.php?slug=" . e($page_community_slug) . "&tab=community&q='+encodeURIComponent(document.getElementById('nav-search-input').value)"
     : "window.location='/index.php?q='+encodeURIComponent(document.getElementById('nav-search-input').value)";
 ?>
 <input id="nav-search-input" type="text" placeholder="<?= $search_placeholder ?>"
 class="bg-transparent text-sm text-gray-700 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none w-full"
 value="<?= $search_val ?>"
 onkeydown="if(event.key==='Enter'){<?= $search_action ?>}">
 <button onclick="<?= $btn_action ?>"
 class="w-7 h-7 bg-gradient-to-r from-primary-600 to-accent-500 rounded-full flex items-center justify-center flex-shrink-0 hover: transition-all">
 <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
 </svg>
 </button>
 </div>
 </div>

 <!-- Right actions -->
 <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
 <?php if ($current_user): ?>
 <!-- Create -->
 <a href="/create-community.php"
 class="hidden sm:flex items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-white border border-gray-300 dark:border-white/20 rounded-full px-4 py-2 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors whitespace-nowrap">
 <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
 Create
 </a>

 <!-- Notifications -->
 <div class="relative" id="notif-dropdown-wrap">
 <button onclick="toggleDropdown('notif-menu')"
 class="relative p-2 rounded-full hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">
 <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
 </svg>
 <?php if ($unread_count > 0): ?>
 <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full notification-dot"></span>
 <?php endif; ?>
 </button>
 <!-- Notifications dropdown -->
 <div id="notif-menu" class="dropdown-menu absolute right-0 mt-2 w-80 bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden z-50" style="max-width:calc(100vw - 2rem);right:max(-100vw + 5rem, 0px)">
 <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-white/10">
 <h3 class="font-semibold text-sm text-gray-900 dark:text-white">Notifications</h3>
 <?php if ($unread_count > 0): ?>
 <span class="text-xs bg-primary-100 dark:bg-primary-900/50 text-primary-600 dark:text-primary-400 px-2 py-0.5 rounded-full font-medium"><?= $unread_count ?> new</span>
 <?php endif; ?>
 </div>
 <div class="max-h-80 overflow-y-auto">
 <?php if (empty($recent_notifications)): ?>
 <div class="px-4 py-8 text-center">
 <div class="w-10 h-10 bg-gray-100 dark:bg-white/10 rounded-full flex items-center justify-center mx-auto mb-2">
 <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
 </div>
 <p class="text-sm text-gray-500 dark:text-gray-400">No notifications yet</p>
 </div>
 <?php else: ?>
 <?php foreach ($recent_notifications as $notif): ?>
 <a href="<?= e($notif['link'] ? str_replace('/platform/', '/', $notif['link']) : '#') ?>"
 class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors <?= !$notif['is_read'] ? 'bg-primary-50/50 dark:bg-primary-900/10' : '' ?>">
 <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">
 <?php $icons = ['new_follower'=>'👤','post_like'=>'♥','membership_approved'=>'✓','points_awarded'=>'★','badge_awarded'=>'🏅']; echo $icons[$notif['type']] ?? '●'; ?>
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
 <div class="border-t border-gray-100 dark:border-white/10 px-4 py-2">
 <button onclick="markAllRead()" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">Mark all as read</button>
 </div>
 </div>
 </div>

 <!-- User menu pill -->
 <?php
 $u_name = e(trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')) ?: $current_user['username']);
 $u_at = e($current_user['username']);
 $u_avatar = get_avatar_url($current_user['avatar'] ?? null, ($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''));
 ?>
 <div class="relative" id="user-dropdown-wrap">
 <button onclick="toggleUserMenu()"
 class="flex items-center gap-2 pl-3 pr-1 py-1 rounded-full border border-gray-300 dark:border-white/20 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">
 <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
 </svg>
 <img src="<?= $u_avatar ?>" alt="<?= $u_at ?>" class="w-8 h-8 rounded-full object-cover">
 </button>
 <!-- Desktop dropdown -->
 <div id="user-menu-desktop" class="dropdown-menu absolute right-0 mt-2 w-56 bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden z-50 py-1">
 <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
 <p class="font-semibold text-sm text-gray-900 dark:text-white"><?= $u_name ?></p>
 <p class="text-xs text-gray-500 dark:text-gray-400">@<?= $u_at ?></p>
 </div>
 <div class="py-1">
 <a href="/profile.php?username=<?= $u_at ?>" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
 <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
 My Profile
 </a>
 <a href="/settings.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
 <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
 Settings
 </a>
 </div>
 <div class="border-t border-gray-100 dark:border-white/10 py-1">
 <a href="/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
 Sign Out
 </a>
 </div>
 </div>
 </div>

 <?php else: ?>
 <a href="/login.php" class="text-sm font-semibold text-gray-700 dark:text-white hover:underline px-3 py-2 transition-colors">Log in</a>
 <a href="/register.php" class="text-sm font-semibold bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-full px-4 py-2 hover:bg-gray-700 dark:hover:bg-gray-100 transition-colors ">Sign up</a>
 <?php endif; ?>

 </div>
 </div>
 </div>
</nav>

<!-- Mobile bottom sheet for user menu -->
<?php if ($current_user): ?>
<div id="mobile-sheet-backdrop" onclick="closeMobileSheet()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:998"></div>
<div id="user-menu-mobile" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:999;background:var(--sheet-bg,#fff);border-radius:1.5rem 1.5rem 0 0;overflow:hidden;padding-bottom:env(safe-area-inset-bottom,0px)" class="dark:[--sheet-bg:#1a1a1a]">
 <div class="w-10 h-1 bg-gray-300 dark:bg-gray-600 rounded-full mx-auto mt-3 mb-1"></div>
 <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/10">
 <img src="<?= $u_avatar ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
 <div>
 <p class="font-semibold text-sm text-gray-900 dark:text-white"><?= $u_name ?></p>
 <p class="text-xs text-gray-500 dark:text-gray-400">@<?= $u_at ?></p>
 </div>
 </div>
 <div class="py-2">
 <a href="/profile.php?username=<?= $u_at ?>" class="flex items-center gap-3 px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">
 <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
 My Profile
 </a>
 <a href="/settings.php" class="flex items-center gap-3 px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">
 <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
 Settings
 </a>
 <a href="/create-community.php" class="flex items-center gap-3 px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">
 <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
 Create Community
 </a>
 </div>
 <div class="border-t border-gray-100 dark:border-white/10 py-2">
 <a href="/logout.php" class="flex items-center gap-3 px-5 py-3.5 text-sm text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
 Sign Out
 </a>
 </div>
</div>
<?php endif; ?>

<script>
function toggleDropdown(id) {
 const menu = document.getElementById(id);
 const isActive = menu.classList.contains('active');
 document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('active'));
 if (!isActive) menu.classList.add('active');
}
function toggleUserMenu() {
 if (window.innerWidth < 640) {
 const sheet = document.getElementById('user-menu-mobile');
 const backdrop = document.getElementById('mobile-sheet-backdrop');
 const open = sheet.style.display !== 'none';
 sheet.style.display = open ? 'none' : 'block';
 backdrop.style.display = open ? 'none' : 'block';
 } else {
 toggleDropdown('user-menu-desktop');
 }
}
function closeMobileSheet() {
 document.getElementById('user-menu-mobile').style.display = 'none';
 document.getElementById('mobile-sheet-backdrop').style.display = 'none';
}
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
 });
}

function showToast(message, type = 'success') {
 const toast = document.createElement('div');
 const bg = type === 'success' ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900' : 'bg-red-600 text-white';
 toast.className = `fixed bottom-6 right-6 z-[9999] ${bg} px-5 py-3 rounded-2xl font-medium text-sm transform translate-y-8 opacity-0 transition-all duration-300`;
 toast.textContent = message;
 document.body.appendChild(toast);
 requestAnimationFrame(() => {
 toast.style.transform = 'translateY(0)';
 toast.style.opacity = '1';
 });
 setTimeout(() => {
 toast.style.transform = 'translateY(8px)';
 toast.style.opacity = '0';
 setTimeout(() => toast.remove(), 300);
 }, 3000);
}
</script>
