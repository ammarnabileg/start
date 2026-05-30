<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Discover Communities';
$current_user = get_auth_user();

// Filters
$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? 'all';
$price    = $_GET['price'] ?? '';
$type     = $_GET['type'] ?? '';
$sort     = $_GET['sort'] ?? 'trending';
$lang     = $_GET['lang'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$categories = ['all', 'trending', 'hobbies', 'music', 'money', 'celebrity', 'tech', 'health', 'sports', 'self_improvement', 'relationships'];
$cat_labels = ['all'=>'All', 'trending'=>'Trending', 'hobbies'=>'Hobbies', 'music'=>'Music', 'money'=>'Money', 'celebrity'=>'Celebrity', 'tech'=>'Tech', 'health'=>'Health', 'sports'=>'Sports', 'self_improvement'=>'Self Improvement', 'relationships'=>'Relationships'];
$cat_icons = [
    'all' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
    'trending' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'hobbies' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>',
    'music' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>',
    'money' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'celebrity' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>',
    'tech' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>',
    'health' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>',
    'sports' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>',
    'self_improvement' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
    'relationships' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
];

// Build query
$where = ['c.is_active = 1'];
$params = [];

if ($q) {
    $where[] = '(c.name LIKE ? OR c.description LIKE ? OR c.short_bio LIKE ?)';
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
if ($category && $category !== 'all' && $category !== 'trending') {
    $where[] = 'c.category = ?';
    $params[] = $category;
}
if ($price === 'free') {
    $where[] = 'c.pricing = "free"';
} elseif ($price === 'paid') {
    $where[] = 'c.pricing = "paid"';
} elseif ($price === 'free_trial') {
    $where[] = 'c.pricing = "free_trial"';
}
if ($type === 'public') {
    $where[] = 'c.type = "public"';
} elseif ($type === 'private') {
    $where[] = 'c.type = "private"';
}
if ($lang) {
    $where[] = 'c.language = ?';
    $params[] = $lang;
}

$where_str = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order = $sort === 'top' ? 'c.member_count DESC' : 'c.member_count DESC, c.created_at DESC';

$count_row = db_fetch("SELECT COUNT(*) as cnt FROM communities c $where_str", $params);
$total = (int)($count_row['cnt'] ?? 0);
$total_pages = ceil($total / $per_page);

$communities = db_fetch_all(
    "SELECT c.*, u.username as owner_username, u.first_name as owner_first, u.last_name as owner_last,
     (SELECT COUNT(*) FROM memberships m WHERE m.community_id = c.id AND m.status = 'approved') as real_member_count
     FROM communities c
     JOIN users u ON u.id = c.owner_id
     $where_str
     ORDER BY $order
     LIMIT ? OFFSET ?",
    array_merge($params, [$per_page, $offset])
);

include __DIR__ . '/includes/header.php';
?>

<!-- Category chips row — sticky below nav, Airbnb-style -->
<div class="sticky top-16 z-40 bg-white/95 dark:bg-[#121212]/95 glass border-b border-gray-200/60 dark:border-white/10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center gap-1">
      <!-- Chips scroll area -->
      <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide py-3 flex-1">
        <?php foreach ($categories as $cat): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['category' => $cat, 'page' => 1])) ?>"
             class="flex flex-col items-center gap-1 px-4 py-1 flex-shrink-0 text-xs font-medium transition-colors whitespace-nowrap cursor-pointer
                    <?= ($category === $cat) ? 'text-gray-900 dark:text-white chip-active' : 'text-gray-500 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' ?>">
            <?= $cat_icons[$cat] ?? '' ?>
            <?= $cat_labels[$cat] ?>
          </a>
        <?php endforeach; ?>
      </div>
      <!-- Filter button -->
      <div class="flex-shrink-0 pl-4 border-l border-gray-200 dark:border-white/10 ml-2">
        <button onclick="toggleFilters()"
                class="flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-300 dark:border-white/20 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
          Filters
          <?php if ($price || $type || ($sort && $sort !== 'trending') || $lang): ?>
            <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
          <?php endif; ?>
        </button>
      </div>
    </div>
  </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <!-- Filter Panel -->
  <div id="filter-panel" class="<?= ($price || $type || ($sort && $sort !== 'trending') || $lang) ? '' : 'hidden' ?> bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 mb-6 shadow-airbnb">
    <form method="GET" id="filter-form">
      <input type="hidden" name="q" value="<?= e($q) ?>">
      <input type="hidden" name="category" value="<?= e($category) ?>">
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Price</h4>
          <?php foreach ([''=>'All Prices', 'free'=>'Free', 'paid'=>'Paid', 'free_trial'=>'Free Trial'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="price" value="<?= $val ?>" <?= $price === $val ? 'checked' : '' ?>
                     class="text-primary-600 focus:ring-primary-500 bg-gray-100 dark:bg-[#2a2a2a] border-gray-300 dark:border-white/20">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Type</h4>
          <?php foreach ([''=>'All Types', 'public'=>'Public', 'private'=>'Private'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="type" value="<?= $val ?>" <?= $type === $val ? 'checked' : '' ?>
                     class="text-primary-600 focus:ring-primary-500 bg-gray-100 dark:bg-[#2a2a2a] border-gray-300 dark:border-white/20">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Sort By</h4>
          <?php foreach (['trending'=>'Trending', 'top'=>'Top Members'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="sort" value="<?= $val ?>" <?= $sort === $val ? 'checked' : '' ?>
                     class="text-primary-600 focus:ring-primary-500 bg-gray-100 dark:bg-[#2a2a2a] border-gray-300 dark:border-white/20">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Language</h4>
          <?php foreach ([''=>'All Languages', 'en'=>'English', 'ar'=>'Arabic', 'fr'=>'French'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="lang" value="<?= $val ?>" <?= $lang === $val ? 'checked' : '' ?>
                     class="text-primary-600 focus:ring-primary-500 bg-gray-100 dark:bg-[#2a2a2a] border-gray-300 dark:border-white/20">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-white/10">
        <a href="/index.php" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 font-medium transition-colors">Reset</a>
        <button type="submit" class="px-5 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-semibold hover:bg-gray-700 dark:hover:bg-gray-100 transition-colors">Apply Filters</button>
      </div>
    </form>
  </div>

  <!-- Results header -->
  <div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500 dark:text-gray-400">
      <?php if ($q): ?>
        <span class="font-semibold text-gray-900 dark:text-white"><?= $total ?></span> results for "<span class="font-semibold text-primary-600 dark:text-primary-400"><?= e($q) ?></span>"
      <?php else: ?>
        <span class="font-semibold text-gray-900 dark:text-white"><?= $total ?></span> communities
      <?php endif; ?>
    </p>
    <?php if ($current_user): ?>
      <a href="/create-community.php" class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Create Community
      </a>
    <?php endif; ?>
  </div>

  <!-- Community Cards Grid — Airbnb property card style -->
  <?php if (empty($communities)): ?>
    <div class="text-center py-24">
      <div class="w-16 h-16 bg-gray-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </div>
      <h3 class="text-lg font-bold text-gray-700 dark:text-gray-300 mb-2">No communities found</h3>
      <p class="text-gray-500 dark:text-gray-500 mb-6 text-sm">Try different search terms or filters</p>
      <a href="/index.php" class="px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-full text-sm font-semibold hover:bg-gray-700 dark:hover:bg-gray-100 transition-colors">Browse All</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
      <?php foreach ($communities as $c): ?>
        <?php
        $is_member = false;
        $membership_status = null;
        if ($current_user) {
            $mem = db_fetch('SELECT status FROM memberships WHERE user_id = ? AND community_id = ?', [$current_user['id'], $c['id']]);
            if ($mem) { $is_member = $mem['status'] === 'approved'; $membership_status = $mem['status']; }
        }
        ?>
        <!-- Airbnb-style property card -->
        <div class="community-card group cursor-pointer" onclick="window.location='/community.php?slug=<?= e($c['slug']) ?>'">
          <!-- Image area — 4:3 ratio -->
          <div class="relative aspect-[4/3] rounded-2xl overflow-hidden mb-3 bg-gray-100 dark:bg-[#2a2a2a]">
            <?php if ($c['banner']): ?>
              <img src="<?= e($c['banner']) ?>" alt="<?= e($c['name']) ?>"
                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            <?php else: ?>
              <div class="w-full h-full bg-gradient-to-br from-primary-700 to-accent-500 flex items-center justify-center">
                <span class="text-5xl font-black text-white/30"><?= strtoupper(substr($c['name'], 0, 1)) ?></span>
              </div>
            <?php endif; ?>

            <!-- Category badge top-left -->
            <div class="absolute top-3 left-3">
              <span class="bg-black/50 glass text-white text-xs font-semibold px-2.5 py-1 rounded-full capitalize">
                <?= str_replace('_', ' ', $cat_labels[$c['category']] ?? $c['category']) ?>
              </span>
            </div>

            <!-- Pricing badge top-right -->
            <div class="absolute top-3 right-3">
              <?php if ($c['pricing'] === 'free'): ?>
                <span class="bg-green-500/90 text-white text-xs font-bold px-2.5 py-1 rounded-full">Free</span>
              <?php elseif ($c['pricing'] === 'paid'): ?>
                <span class="bg-[#1a1a1a]/80 glass text-white text-xs font-bold px-2.5 py-1 rounded-full"><?= format_price($c['price'], $c['price_interval'] ?? '') ?>/mo</span>
              <?php else: ?>
                <span class="bg-accent-500/90 text-white text-xs font-bold px-2.5 py-1 rounded-full">Free Trial</span>
              <?php endif; ?>
            </div>

            <!-- Logo bottom-left -->
            <?php if ($c['logo']): ?>
              <div class="absolute bottom-3 left-3">
                <img src="<?= e($c['logo']) ?>" class="w-9 h-9 rounded-xl object-cover border-2 border-white/30 shadow-lg" alt="">
              </div>
            <?php endif; ?>

            <!-- Member status indicator -->
            <?php if ($is_member): ?>
              <div class="absolute bottom-3 right-3">
                <span class="bg-primary-500/90 text-white text-xs font-bold px-2 py-0.5 rounded-full">Joined</span>
              </div>
            <?php elseif ($membership_status === 'pending'): ?>
              <div class="absolute bottom-3 right-3">
                <span class="bg-amber-500/90 text-white text-xs font-bold px-2 py-0.5 rounded-full">Pending</span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Card content — Airbnb minimal style -->
          <div class="px-0.5">
            <div class="flex items-start justify-between gap-2 mb-1">
              <h3 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight line-clamp-1"><?= e($c['name']) ?></h3>
              <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0 font-medium"><?= format_member_count((int)$c['real_member_count']) ?> members</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-500 line-clamp-2 leading-relaxed mb-1.5"><?= e($c['short_bio'] ?: $c['description']) ?></p>
            <div class="flex items-center gap-1.5">
              <img src="<?= get_avatar_url(null, ($c['owner_first'] ?: $c['owner_username'])) ?>"
                   class="w-4 h-4 rounded-full object-cover bg-gray-200 dark:bg-gray-700" alt="">
              <span class="text-xs text-gray-500 dark:text-gray-500">by <?= e($c['owner_first'] ?: $c['owner_username']) ?></span>
              <?php if ($c['type'] === 'private'): ?>
                <span class="ml-auto text-xs text-gray-400 dark:text-gray-600 flex items-center gap-0.5">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                  Private
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="flex items-center justify-center gap-2 mt-12">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
             class="w-10 h-10 rounded-full border border-gray-300 dark:border-white/20 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </a>
        <?php endif; ?>
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
             class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-colors
                    <?= $i === $page ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900' : 'border border-gray-300 dark:border-white/20 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
             class="w-10 h-10 rounded-full border border-gray-300 dark:border-white/20 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
function toggleFilters() {
  document.getElementById('filter-panel').classList.toggle('hidden');
}
</script>
