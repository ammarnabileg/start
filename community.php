<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) { header('Location: /index.php'); exit; }

$community = db_fetch('SELECT c.*, u.username as owner_username, u.first_name as owner_first, u.last_name as owner_last, u.avatar as owner_avatar FROM communities c JOIN users u ON u.id = c.owner_id WHERE c.slug = ? AND c.is_active = 1', [$slug]);
if (!$community) { http_response_code(404); die('<!DOCTYPE html><html><head><title>Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:50px"><h1>Community Not Found</h1><p><a href="/index.php">Browse Communities</a></p></body></html>'); }

$current_user = get_auth_user();
$community_id = $community['id'];

$my_membership = null;
$my_role = null;
$is_approved = false;
$is_admin = false;
$is_owner = false;
if ($current_user) {
    $my_membership = db_fetch('SELECT * FROM memberships WHERE user_id = ? AND community_id = ?', [$current_user['id'], $community_id]);
    if ($my_membership && $my_membership['status'] === 'approved') {
        $is_approved = true;
        $my_role = $my_membership['role'];
        $is_admin = in_array($my_role, ['admin', 'owner']);
        $is_owner = $my_role === 'owner';
    }
}

$default_tab = $is_approved ? 'community' : 'about';
$tab = $_GET['tab'] ?? $default_tab;
$valid_tabs = ['community', 'classroom', 'members', 'leaderboard', 'about', 'admin'];
if (!in_array($tab, $valid_tabs)) $tab = $default_tab;
if ($tab === 'admin' && !$is_admin) $tab = $default_tab;

$is_admin_or_owner = $is_admin;
if ($is_approved) {
    $tab_order = ['community' => 'Community', 'classroom' => 'Classroom', 'members' => 'Members', 'leaderboard' => 'Leaderboard', 'about' => 'About'];
} else {
    $tab_order = ['about' => 'About', 'community' => 'Community', 'classroom' => 'Classroom', 'members' => 'Members', 'leaderboard' => 'Leaderboard'];
}
if ($is_admin) {
    $tab_order['admin'] = 'Admin';
}

$admin_members = db_fetch_all(
    'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, m.role FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.community_id = ? AND m.status = "approved" AND m.role IN ("admin","owner") ORDER BY FIELD(m.role,"owner","admin")',
    [$community_id]
);
$member_count_approved = db_fetch('SELECT COUNT(*) as cnt FROM memberships WHERE community_id = ? AND status = "approved"', [$community_id]);
$admin_count = db_fetch('SELECT COUNT(*) as cnt FROM memberships WHERE community_id = ? AND status = "approved" AND role IN ("admin","owner")', [$community_id]);
$community_links = db_fetch_all('SELECT * FROM community_links WHERE community_id = ? ORDER BY sort_order', [$community_id]);

$page_title = $community['name'];
// Load topics for sidebar
$sidebar_topics = db_fetch_all('SELECT * FROM topics WHERE community_id = ? ORDER BY sort_order', [$community_id]);
// Build tab labels map
$tab_labels = ['community' => 'Home', 'classroom' => 'Courses', 'members' => 'Members', 'leaderboard' => 'Leaderboard', 'about' => 'About', 'admin' => 'Admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($community['name']) ?></title>
  <meta name="csrf-token" content="<?= generate_csrf_token() ?>">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { DEFAULT: '#3B5BDB', hover: '#3451C7', light: '#EEF2FF', mid: '#C5D0FA' }
          },
          boxShadow: {
            card: '0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04)',
            'card-hover': '0 4px 12px rgba(0,0,0,.10)',
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; }
    .sidebar-link { display:flex; align-items:center; gap:8px; padding:6px 10px; border-radius:8px; font-size:13px; font-weight:500; color:#52525b; transition:background .15s,color .15s; cursor:pointer; }
    .sidebar-link:hover { background:#f0f0f1; color:#18181b; }
    .sidebar-link.active { background:#EEF2FF; color:#3B5BDB; font-weight:600; }
    .post-card { background:#fff; border:1px solid #e4e4e7; border-radius:12px; padding:16px; margin-bottom:8px; transition:box-shadow .15s; }
    .post-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .btn-brand { background:#3B5BDB; color:#fff; padding:6px 16px; border-radius:8px; font-size:13px; font-weight:600; border:none; cursor:pointer; transition:background .15s; }
    .btn-brand:hover { background:#3451C7; }
    .scrollbar-hide::-webkit-scrollbar { display:none; }
    .scrollbar-hide { -ms-overflow-style:none; scrollbar-width:none; }
  </style>
</head>
<body>

<!-- ═══════════════ COMMUNITY TOP BAR ═══════════════ -->
<header class="sticky top-0 z-50 bg-white border-b border-gray-200" style="box-shadow:0 1px 0 #e4e4e7">
  <div class="max-w-[1280px] mx-auto px-4 flex items-center h-14 gap-4">

    <!-- Left: Community identity -->
    <div class="flex items-center gap-2 flex-shrink-0 mr-2">
      <?php if ($community['logo']): ?>
        <img src="<?= e($community['logo']) ?>" alt="" class="w-8 h-8 rounded-lg object-cover flex-shrink-0">
      <?php else: ?>
        <div class="w-8 h-8 rounded-lg bg-brand flex items-center justify-center text-white font-black text-sm flex-shrink-0">
          <?= strtoupper(substr($community['name'], 0, 1)) ?>
        </div>
      <?php endif; ?>
      <span class="font-bold text-gray-900 text-sm hidden sm:block max-w-[140px] truncate"><?= e($community['name']) ?></span>
      <?php if ($is_owner): ?>
        <a href="/edit-community.php?id=<?= $community_id ?>" class="text-gray-500 hover:text-gray-600 transition-colors ml-1" title="Edit community">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
        </a>
      <?php endif; ?>
    </div>

    <!-- Center: Tabs -->
    <nav class="flex items-center gap-0.5 flex-1 justify-center overflow-x-auto scrollbar-hide">
      <?php foreach ($tab_order as $t => $label): ?>
        <?php $active = $tab === $t; $lbl = $tab_labels[$t] ?? $label; ?>
        <a href="?slug=<?= e($slug) ?>&tab=<?= $t ?>"
           class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors
                  <?= $active ? 'bg-brand/10 text-brand font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>
                  <?= $t === 'admin' ? ($active ? '' : 'text-amber-600 hover:bg-amber-50') : '' ?>">
          <?= $lbl ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Right: Actions -->
    <div class="flex items-center gap-2 flex-shrink-0">
      <!-- Search -->
      <button class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Search">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </button>
      <?php if ($current_user): ?>
        <!-- Notifications -->
        <a href="/notifications.php" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Notifications">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        </a>
        <!-- Avatar -->
        <a href="/profile.php?username=<?= e($current_user['username']) ?>" class="ml-1">
          <img src="<?= get_avatar_url($current_user['avatar'] ?? null, ($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')) ?>"
               class="w-8 h-8 rounded-full object-cover border-2 border-gray-200 hover:border-brand transition-colors">
        </a>
      <?php else: ?>
        <a href="/login.php" class="btn-brand">Sign In</a>
      <?php endif; ?>
      <!-- Join / member status -->
      <?php if (!$is_approved): ?>
        <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
          <span class="text-xs bg-amber-100 text-amber-700 font-semibold px-3 py-1.5 rounded-full">Pending</span>
        <?php elseif ($current_user): ?>
          <button onclick="joinCommunity(<?= $community_id ?>)" class="btn-brand ml-1">
            <?= $community['pricing'] === 'paid' ? 'Join &middot; ' . format_price($community['price'], $community['price_interval'] ?? '') : 'Join' ?>
          </button>
        <?php endif; ?>
      <?php else: ?>
        <span class="text-xs text-brand font-semibold px-3 py-1.5 rounded-full bg-brand/10 ml-1">Member</span>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ═══════════════ MAIN LAYOUT ═══════════════ -->
<div class="max-w-[1280px] mx-auto px-4 py-5 flex gap-5">

  <!-- LEFT SIDEBAR -->
  <aside class="w-52 flex-shrink-0 hidden lg:block">
    <div class="sticky top-20 space-y-1">
      <!-- Feed -->
      <a href="?slug=<?= e($slug) ?>&tab=community" class="sidebar-link <?= $tab === 'community' && !isset($_GET['topic']) ? 'active' : '' ?>">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
        Feed
      </a>

      <?php if (!empty($sidebar_topics)): ?>
        <div class="pt-3 pb-1">
          <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider px-2 mb-1">Topics</p>
          <?php foreach ($sidebar_topics as $st):
            $topic_active = $tab === 'community' && isset($_GET['topic']) && (int)$_GET['topic'] === (int)$st['id'];
          ?>
            <a href="?slug=<?= e($slug) ?>&tab=community&topic=<?= $st['id'] ?>"
               class="sidebar-link <?= $topic_active ? 'active' : '' ?>">
              <span class="text-gray-500 font-bold">#</span>
              <span class="truncate"><?= e($st['name']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Quick links -->
      <div class="pt-3 border-t border-gray-200 space-y-1">
        <a href="?slug=<?= e($slug) ?>&tab=classroom" class="sidebar-link <?= $tab === 'classroom' ? 'active' : '' ?>">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
          Courses
        </a>
        <a href="?slug=<?= e($slug) ?>&tab=members" class="sidebar-link <?= $tab === 'members' ? 'active' : '' ?>">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          Members
        </a>
        <a href="?slug=<?= e($slug) ?>&tab=leaderboard" class="sidebar-link <?= $tab === 'leaderboard' ? 'active' : '' ?>">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          Leaderboard
        </a>
        <?php if ($is_approved && $current_user): ?>
          <?php
          $my_points_sidebar = get_user_points_in_community($current_user['id'], $community_id);
          $my_streak_sidebar = db_fetch('SELECT current_streak FROM user_streaks WHERE user_id = ? AND community_id = ?', [$current_user['id'], $community_id]);
          ?>
          <div class="mt-4 p-3 bg-brand rounded-xl text-white text-xs">
            <p class="font-bold mb-2 opacity-80">Your Stats</p>
            <div class="flex justify-between">
              <span><?= number_format($my_points_sidebar) ?> XP</span>
              <span><?= $my_streak_sidebar ? $my_streak_sidebar['current_streak'] : 0 ?> day streak</span>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($is_owner): ?>
          <button onclick="alert('Go live feature coming soon!')" class="sidebar-link w-full mt-2 border border-gray-200 justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Go live
          </button>
        <?php endif; ?>
      </div>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <div class="flex-1 min-w-0">
    <!-- Mobile: topic pills -->
    <?php if ($tab === 'community' && !empty($sidebar_topics) && $is_approved): ?>
      <div class="lg:hidden flex gap-2 overflow-x-auto scrollbar-hide mb-4 pb-1">
        <a href="?slug=<?= e($slug) ?>&tab=community"
           class="flex-shrink-0 px-3 py-1 rounded-full text-xs font-semibold border <?= !isset($_GET['topic']) ? 'bg-brand text-white border-brand' : 'bg-white text-gray-600 border-gray-200' ?>">All</a>
        <?php foreach ($sidebar_topics as $st): ?>
          <a href="?slug=<?= e($slug) ?>&tab=community&topic=<?= $st['id'] ?>"
             class="flex-shrink-0 px-3 py-1 rounded-full text-xs font-semibold border <?= isset($_GET['topic']) && (int)$_GET['topic'] === (int)$st['id'] ? 'bg-brand text-white border-brand' : 'bg-white text-gray-600 border-gray-200' ?>">
            # <?= e($st['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="space-y-0">

    <!-- ============ MAIN CONTENT ============ -->
    <div class="flex-1 min-w-0">

      <!-- ====== TAB: COMMUNITY ====== -->
      <?php if ($tab === 'community'): ?>
        <?php
        $topic_id = isset($_GET['topic']) ? (int)$_GET['topic'] : 0;
        $topics = db_fetch_all('SELECT * FROM topics WHERE community_id = ? ORDER BY sort_order', [$community_id]);
        $post_page = max(1, (int)($_GET['ppage'] ?? 1));
        $post_per = 10;
        $post_offset = ($post_page - 1) * $post_per;

        $pwhere = 'WHERE p.community_id = ?';
        $pparams = [$community_id];
        if ($topic_id) { $pwhere .= ' AND p.topic_id = ?'; $pparams[] = $topic_id; }

        $pinned_posts = $is_approved ? db_fetch_all(
            "SELECT p.*, u.username, u.first_name, u.last_name, u.avatar, t.name as topic_name FROM posts p JOIN users u ON u.id = p.user_id LEFT JOIN topics t ON t.id = p.topic_id $pwhere AND p.is_pinned = 1 ORDER BY p.pin_order ASC LIMIT 3",
            $pparams
        ) : [];
        $post_count = db_fetch("SELECT COUNT(*) as cnt FROM posts p $pwhere AND p.is_pinned = 0", $pparams);
        $posts = $is_approved ? db_fetch_all(
            "SELECT p.*, u.username, u.first_name, u.last_name, u.avatar, t.name as topic_name FROM posts p JOIN users u ON u.id = p.user_id LEFT JOIN topics t ON t.id = p.topic_id $pwhere AND p.is_pinned = 0 ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
            array_merge($pparams, [$post_per, $post_offset])
        ) : [];
        $total_posts = (int)($post_count['cnt'] ?? 0);
        $total_post_pages = ceil($total_posts / $post_per);

        $liked_posts = [];
        if ($current_user && $is_approved) {
            $likes = db_fetch_all('SELECT post_id FROM post_likes WHERE user_id = ?', [$current_user['id']]);
            $liked_posts = array_column($likes, 'post_id');
        }
        ?>

        <!-- Join prompt for non-members -->
        <?php if (!$is_approved): ?>
          <div class="bg-white rounded-xl border border-gray-200 p-8 text-center mb-4">
            <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Join to see the community feed</h3>
            <p class="text-gray-500 text-sm mb-5">Become a member to post, comment, and interact with the community.</p>
            <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
              <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-100 text-amber-600 rounded-xl text-sm font-semibold">Request Pending Approval</span>
            <?php elseif ($current_user): ?>
              <button onclick="joinCommunity(<?= $community_id ?>)"
                      class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl text-sm font-semibold hover:shadow-lg transition-all">
                Join Community
              </button>
            <?php else: ?>
              <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl text-sm font-semibold">Sign In to Join</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($is_approved): ?>
          <!-- Topic Pills -->
          <div class="flex gap-2 flex-wrap mb-5 overflow-x-auto scrollbar-hide">
            <a href="?slug=<?= e($slug) ?>&tab=community"
               class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap
                      <?= !$topic_id ? 'bg-brand text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
              All Posts
            </a>
            <?php foreach ($topics as $t): ?>
              <a href="?slug=<?= e($slug) ?>&tab=community&topic=<?= $t['id'] ?>"
                 class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap
                        <?= $topic_id === (int)$t['id'] ? 'bg-brand text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
                # <?= e($t['name']) ?>
              </a>
            <?php endforeach; ?>
            <?php if ($is_admin): ?>
              <button onclick="showAddTopic()"
                      class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium border-2 border-dashed border-gray-300 text-gray-500 hover:border-brand hover:text-brand transition-colors whitespace-nowrap">
                + Add Topic
              </button>
            <?php endif; ?>
          </div>

          <!-- Create Post -->
          <?php if ($current_user): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-4 mb-3">
              <div class="flex items-start gap-3">
                <img src="<?= get_avatar_url($current_user['avatar'] ?? null, ($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')) ?>"
                     class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                <div class="flex-1">
                  <textarea id="post-content" placeholder="Write something to the community..." rows="2"
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50 resize-none placeholder-gray-400"
                            onfocus="this.rows=4" onblur="if(!this.value)this.rows=2"></textarea>
                  <div class="mt-2 flex items-center justify-between">
                    <select id="post-topic"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-gray-50 text-gray-600 focus:outline-none focus:ring-1 focus:ring-brand">
                      <option value="">No topic</option>
                      <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $topic_id === (int)$t['id'] ? 'selected' : '' ?>># <?= e($t['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button onclick="createPost(<?= $community_id ?>)"
                            class="px-4 py-1.5 bg-brand text-white rounded-full text-xs font-semibold hover:shadow-md transition-all">
                      Post
                    </button>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Pinned Posts -->
          <?php foreach ($pinned_posts as $post): ?>
            <?php $liked = in_array($post['id'], $liked_posts); ?>
            <div class="post-card relative border-amber-300/60" id="post-<?= $post['id'] ?>">
              <div class="absolute top-3 right-3 flex items-center gap-1">
                <span class="text-xs bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full font-medium flex items-center gap-1">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/></svg>
                  Pinned
                </span>
                <?php if ($is_owner): ?>
                  <button onclick="togglePostMenu(<?= $post['id'] ?>)" class="p-1 hover:bg-gray-100 rounded-lg text-gray-500">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                  </button>
                  <div id="post-menu-<?= $post['id'] ?>" class="hidden absolute right-0 top-8 bg-white border border-gray-200 rounded-xl shadow-xl z-10 py-1 w-36">
                    <button onclick="pinPost(<?= $post['id'] ?>, false)" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-gray-50">Unpin</button>
                    <button onclick="deletePost(<?= $post['id'] ?>)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50">Delete</button>
                  </div>
                <?php endif; ?>
              </div>
              <?php include __DIR__ . '/includes/post_card.php'; ?>
            </div>
          <?php endforeach; ?>

          <!-- Regular Posts -->
          <?php if (empty($posts) && empty($pinned_posts)): ?>
            <div class="text-center py-16 bg-white rounded-2xl border border-gray-200">
              <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              </div>
              <h3 class="font-bold text-gray-700 mb-2">No posts yet</h3>
              <p class="text-sm text-gray-500">Be the first to post something!</p>
            </div>
          <?php else: ?>
            <?php foreach ($posts as $post): ?>
              <?php $liked = in_array($post['id'], $liked_posts); ?>
              <div class="post-card relative" id="post-<?= $post['id'] ?>">
                <?php if ($is_owner): ?>
                  <div class="absolute top-3 right-3">
                    <button onclick="togglePostMenu(<?= $post['id'] ?>)" class="p-1.5 hover:bg-gray-100 rounded-lg text-gray-500 transition-colors">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                    </button>
                    <div id="post-menu-<?= $post['id'] ?>" class="hidden absolute right-0 top-8 bg-white border border-gray-200 rounded-xl shadow-xl z-10 py-1 w-36">
                      <button onclick="pinPost(<?= $post['id'] ?>, true)" class="w-full text-left px-4 py-2 text-xs text-gray-700 hover:bg-gray-50">Pin Post</button>
                      <button onclick="deletePost(<?= $post['id'] ?>)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50">Delete</button>
                    </div>
                  </div>
                <?php elseif ($current_user && $post['user_id'] == $current_user['id']): ?>
                  <div class="absolute top-3 right-3">
                    <button onclick="deletePost(<?= $post['id'] ?>)" class="p-1.5 hover:bg-red-50 rounded-lg text-gray-500 hover:text-red-500 transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                <?php endif; ?>
                <?php include __DIR__ . '/includes/post_card.php'; ?>
              </div>
            <?php endforeach; ?>

            <!-- Post pagination -->
            <?php if ($total_post_pages > 1): ?>
              <div class="flex justify-center gap-2 mt-4">
                <?php for ($i = 1; $i <= $total_post_pages; $i++): ?>
                  <a href="?slug=<?= e($slug) ?>&tab=community&ppage=<?= $i ?><?= $topic_id ? '&topic='.$topic_id : '' ?>"
                     class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-medium
                            <?= $i === $post_page ? 'bg-brand text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; // end $is_approved ?>

      <!-- ====== TAB: CLASSROOM ====== -->
      <?php elseif ($tab === 'classroom'): ?>
        <?php if (!$is_approved): ?>
          <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Members Only</h3>
            <p class="text-gray-500 mb-6 text-sm">Join this community to access all courses and learning materials.</p>
            <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
              <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-100 text-amber-600 rounded-xl font-semibold">Request Pending</span>
            <?php elseif ($current_user): ?>
              <button onclick="joinCommunity(<?= $community_id ?>)" class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl font-semibold hover:shadow-lg transition-all">Join Community</button>
            <?php else: ?>
              <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl font-semibold">Sign In to Join</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <?php
          $courses = db_fetch_all('SELECT * FROM courses WHERE community_id = ? AND is_published = 1 ORDER BY sort_order, created_at', [$community_id]);
          ?>
          <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-xl font-bold text-gray-900">Classroom</h2>
              <?php if ($is_admin): ?>
                <a href="/manage-course.php?community_id=<?= $community_id ?>"
                   class="flex items-center gap-2 px-4 py-2 bg-brand text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  Add Course
                </a>
              <?php endif; ?>
            </div>
            <?php if (empty($courses)): ?>
              <div class="text-center py-16">
                <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                  <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 class="font-bold text-gray-700 mb-2">No courses yet</h3>
                <p class="text-sm text-gray-500">The community hasn't added any courses yet.</p>
              </div>
            <?php else: ?>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($courses as $course): ?>
                  <?php
                  $progress = ['percent' => 0, 'completed' => 0, 'total' => 0];
                  if ($current_user) $progress = get_course_progress($current_user['id'], $course['id']);
                  $section_count = db_fetch('SELECT COUNT(*) as cnt FROM course_sections WHERE course_id = ?', [$course['id']]);
                  $lesson_count = db_fetch('SELECT COUNT(*) as cnt FROM lessons l JOIN course_sections cs ON cs.id = l.section_id WHERE cs.course_id = ?', [$course['id']]);
                  ?>
                  <div class="group bg-gray-50 rounded-2xl overflow-hidden border border-gray-200 hover:border-gray-300 hover:shadow-card transition-all">
                    <div class="aspect-video overflow-hidden relative">
                      <?php if ($course['thumbnail']): ?>
                        <img src="<?= e($course['thumbnail']) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                      <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-brand to-blue-400 flex items-center justify-center">
                          <svg class="w-12 h-12 text-gray-900/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                      <?php endif; ?>
                      <div class="absolute top-2 right-2">
                        <?php if ($course['pricing'] === 'paid'): ?>
                          <span class="bg-white/80 glass text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full"><?= format_price($course['price']) ?></span>
                        <?php else: ?>
                          <span class="bg-green-500/90 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">Free</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($is_admin): ?>
                        <div class="absolute top-2 left-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
                          <a href="/manage-course.php?community_id=<?= $community_id ?>&course_id=<?= $course['id'] ?>"
                             class="p-1.5 bg-white/90 rounded-xl shadow text-xs text-brand hover:bg-white" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                          </a>
                          <button onclick="deleteCourse(<?= $course['id'] ?>, <?= $community_id ?>)"
                                  class="p-1.5 bg-white/90 rounded-xl shadow text-red-600 hover:bg-red-50" title="Delete">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                          </button>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="p-4">
                      <h3 class="font-bold text-sm text-gray-900 mb-1 line-clamp-2"><?= e($course['title']) ?></h3>
                      <p class="text-xs text-gray-500 mb-3"><?= (int)($section_count['cnt'] ?? 0) ?> sections &bull; <?= (int)($lesson_count['cnt'] ?? 0) ?> lessons</p>
                      <?php if ($current_user && $is_approved && $progress['total'] > 0): ?>
                        <div class="mb-3">
                          <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Progress</span>
                            <span><?= $progress['percent'] ?>%</span>
                          </div>
                          <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-brand h-1.5 rounded-full" style="width:<?= $progress['percent'] ?>%"></div>
                          </div>
                        </div>
                      <?php endif; ?>
                      <a href="/course.php?id=<?= $course['id'] ?>"
                         class="block w-full text-center py-2 rounded-xl bg-brand text-white text-xs font-semibold hover:shadow-md transition-all">
                        <?= $progress['completed'] > 0 && $progress['percent'] < 100 ? 'Continue' : ($progress['percent'] === 100 ? 'Completed' : 'Start') ?>
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <!-- ====== TAB: MEMBERS ====== -->
      <?php elseif ($tab === 'members'): ?>
        <?php
        $all_members = db_fetch_all(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio, m.role, m.joined_at FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.community_id = ? AND m.status = "approved" ORDER BY FIELD(m.role,"owner","admin","member"), m.joined_at',
            [$community_id]
        );
        $pending_members = $is_admin ? db_fetch_all(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, m.id as membership_id FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.community_id = ? AND m.status = "pending" ORDER BY m.joined_at',
            [$community_id]
        ) : [];
        $referral_link = BASE_URL . '/community.php?slug=' . urlencode($community['slug']);
        ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-6">
              <div class="text-center">
                <div class="text-2xl font-black text-gray-900"><?= count($all_members) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">Members</div>
              </div>
              <div class="w-px h-8 bg-gray-200"></div>
              <div class="text-center">
                <div class="text-2xl font-black text-gray-900"><?= count(array_filter($all_members, fn($m) => in_array($m['role'], ['admin','owner']))) ?></div>
                <div class="text-xs text-gray-500 mt-0.5">Admins</div>
              </div>
            </div>
            <?php if ($is_approved): ?>
              <button onclick="document.getElementById('invite-modal').classList.remove('hidden')"
                      class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Invite Members
              </button>
            <?php endif; ?>
          </div>

          <!-- Pending requests -->
          <?php if (!empty($pending_members)): ?>
            <div class="mb-6">
              <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                Pending Requests
                <span class="bg-amber-100 text-amber-600 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($pending_members) ?></span>
              </h3>
              <div class="space-y-2">
                <?php foreach ($pending_members as $mem): ?>
                  <div class="flex items-center justify-between p-3 bg-amber-50 rounded-xl border border-amber-200">
                    <div class="flex items-center gap-3">
                      <img src="<?= get_avatar_url($mem['avatar'] ?? null, ($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? '')) ?>" class="w-9 h-9 rounded-full object-cover">
                      <div>
                        <div class="font-semibold text-sm text-gray-900"><?= e(trim(($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? ''))) ?></div>
                        <div class="text-xs text-gray-500">@<?= e($mem['username']) ?></div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <button onclick="approveMember(<?= $mem['membership_id'] ?>, 'approved')" class="px-3 py-1.5 bg-green-500 text-white rounded-lg text-xs font-semibold hover:bg-green-600 transition-colors">Approve</button>
                      <button onclick="approveMember(<?= $mem['membership_id'] ?>, 'rejected')" class="px-3 py-1.5 bg-red-100 text-red-600 rounded-lg text-xs font-semibold hover:bg-red-200 transition-colors">Reject</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Members Grid -->
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($all_members as $mem): ?>
              <a href="/profile.php?username=<?= e($mem['username']) ?>"
                 class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-200">
                <div class="relative flex-shrink-0">
                  <img src="<?= get_avatar_url($mem['avatar'] ?? null, ($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? '')) ?>" class="w-10 h-10 rounded-full object-cover">
                  <?php if ($mem['role'] === 'owner'): ?>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-amber-400 rounded-full flex items-center justify-center text-xs">
                      <svg class="w-2.5 h-2.5 text-gray-900" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                  <?php elseif ($mem['role'] === 'admin'): ?>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-brand rounded-full flex items-center justify-center text-white text-xs font-bold leading-none">A</div>
                  <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-semibold text-sm text-gray-900 truncate"><?= e(trim(($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? '')) ?: $mem['username']) ?></div>
                  <div class="text-xs text-gray-500 truncate">@<?= e($mem['username']) ?></div>
                  <?php if ($mem['bio']): ?>
                    <div class="text-xs text-gray-500 line-clamp-1 mt-0.5"><?= e($mem['bio']) ?></div>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Invite Modal -->
        <div id="invite-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold text-lg text-gray-900">Invite to Community</h3>
              <button onclick="document.getElementById('invite-modal').classList.add('hidden')" class="text-gray-500 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <p class="text-sm text-gray-600 mb-3">Share this link to invite people:</p>
            <div class="flex gap-2">
              <input id="invite-link" type="text" readonly value="<?= e($referral_link) ?>"
                     class="flex-1 bg-gray-100 border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-600 focus:outline-none">
              <button onclick="copyInviteLink()" class="px-4 py-2.5 bg-brand text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Copy</button>
            </div>
          </div>
        </div>

      <!-- ====== TAB: LEADERBOARD ====== -->
      <?php elseif ($tab === 'leaderboard'): ?>
        <?php if (!$is_approved): ?>
          <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Members Only</h3>
            <p class="text-gray-500 mb-6 text-sm">Join this community to see the leaderboard.</p>
            <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
              <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-100 text-amber-600 rounded-xl font-semibold">Request Pending</span>
            <?php elseif ($current_user): ?>
              <button onclick="joinCommunity(<?= $community_id ?>)" class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl font-semibold hover:shadow-lg transition-all">Join Community</button>
            <?php else: ?>
              <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl font-semibold">Sign In to Join</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <?php $leaderboard = get_community_leaderboard($community_id, 50); ?>
          <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Leaderboard</h2>

            <!-- Top 3 Podium -->
            <?php if (count($leaderboard) >= 3): ?>
              <div class="flex items-end justify-center gap-3 mb-8 p-6 bg-gray-50 rounded-2xl">
                <!-- 2nd -->
                <div class="text-center flex-1">
                  <div class="relative inline-block mb-2">
                    <img src="<?= get_avatar_url($leaderboard[1]['avatar'] ?? null, ($leaderboard[1]['first_name'] ?? '') . ' ' . ($leaderboard[1]['last_name'] ?? '')) ?>"
                         class="w-14 h-14 rounded-full mx-auto border-2 border-gray-400 shadow-md">
                    <div class="absolute -top-2 -right-1 w-6 h-6 bg-gray-400 rounded-full flex items-center justify-center text-gray-900 font-black text-xs">2</div>
                  </div>
                  <div class="text-xs font-bold text-gray-800 truncate max-w-20 mx-auto"><?= e($leaderboard[1]['first_name'] ?? $leaderboard[1]['username']) ?></div>
                  <div class="text-xs text-gray-500"><?= number_format($leaderboard[1]['total_points']) ?> XP</div>
                  <div class="h-16 bg-gray-300 rounded-t-xl mt-2 w-full"></div>
                </div>
                <!-- 1st -->
                <div class="text-center flex-1">
                  <div class="relative inline-block mb-2">
                    <img src="<?= get_avatar_url($leaderboard[0]['avatar'] ?? null, ($leaderboard[0]['first_name'] ?? '') . ' ' . ($leaderboard[0]['last_name'] ?? '')) ?>"
                         class="w-20 h-20 rounded-full mx-auto border-2 border-amber-400 shadow-xl">
                    <div class="absolute -top-2 -right-1 w-7 h-7 bg-amber-400 rounded-full flex items-center justify-center text-gray-900 font-black text-xs">1</div>
                  </div>
                  <div class="text-sm font-bold text-gray-900 truncate max-w-24 mx-auto"><?= e($leaderboard[0]['first_name'] ?? $leaderboard[0]['username']) ?></div>
                  <div class="text-xs text-brand font-semibold"><?= number_format($leaderboard[0]['total_points']) ?> XP</div>
                  <div class="h-24 bg-amber-400 rounded-t-xl mt-2 w-full"></div>
                </div>
                <!-- 3rd -->
                <div class="text-center flex-1">
                  <div class="relative inline-block mb-2">
                    <img src="<?= get_avatar_url($leaderboard[2]['avatar'] ?? null, ($leaderboard[2]['first_name'] ?? '') . ' ' . ($leaderboard[2]['last_name'] ?? '')) ?>"
                         class="w-14 h-14 rounded-full mx-auto border-2 border-amber-700 shadow-md">
                    <div class="absolute -top-2 -right-1 w-6 h-6 bg-amber-700 rounded-full flex items-center justify-center text-gray-900 font-black text-xs">3</div>
                  </div>
                  <div class="text-xs font-bold text-gray-800 truncate max-w-20 mx-auto"><?= e($leaderboard[2]['first_name'] ?? $leaderboard[2]['username']) ?></div>
                  <div class="text-xs text-gray-500"><?= number_format($leaderboard[2]['total_points']) ?> XP</div>
                  <div class="h-12 bg-amber-700 rounded-t-xl mt-2 w-full"></div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Full list -->
            <div class="space-y-1">
              <?php foreach ($leaderboard as $i => $leader): ?>
                <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 transition-colors <?= $current_user && $leader['id'] === $current_user['id'] ? 'bg-brand border border-brand' : '' ?>">
                  <div class="w-7 text-center text-sm font-bold text-gray-500">
                    <?= $i < 3 ? ['#1','#2','#3'][$i] : '#'.($i+1) ?>
                  </div>
                  <img src="<?= get_avatar_url($leader['avatar'] ?? null, ($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? '')) ?>"
                       class="w-9 h-9 rounded-full object-cover">
                  <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-gray-900"><?= e(trim(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? '')) ?: $leader['username']) ?></div>
                    <div class="text-xs text-gray-500">@<?= e($leader['username']) ?></div>
                  </div>
                  <div class="text-right">
                    <div class="text-sm font-black text-brand"><?= number_format($leader['total_points']) ?></div>
                    <div class="text-xs text-gray-500">XP</div>
                  </div>
                  <div class="text-right hidden sm:block">
                    <div class="text-sm font-bold text-gray-700"><?= $leader['badge_count'] ?></div>
                    <div class="text-xs text-gray-500">Badges</div>
                  </div>
                  <?php if ($is_owner && $current_user && $leader['id'] !== $current_user['id']): ?>
                    <button onclick="awardPoints(<?= $leader['id'] ?>, <?= $community_id ?>)"
                            class="flex-shrink-0 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-semibold hover:bg-gray-200 transition-colors">
                      +XP
                    </button>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      <!-- ====== TAB: ABOUT ====== -->
      <?php elseif ($tab === 'about'): ?>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <!-- Stats Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-6 border-b border-gray-100">
            <div class="text-center p-4 bg-gray-50 rounded-xl">
              <div class="font-bold text-lg text-gray-900 capitalize"><?= $community['type'] ?></div>
              <div class="text-xs text-gray-500 mt-0.5">Access</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-xl">
              <div class="font-bold text-lg text-gray-900"><?= format_member_count((int)($member_count_approved['cnt'] ?? 0)) ?></div>
              <div class="text-xs text-gray-500 mt-0.5">Members</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-xl">
              <div class="font-bold text-lg text-gray-900"><?= $community['pricing'] === 'free' ? 'Free' : format_price($community['price'], $community['price_interval'] ?? '') ?></div>
              <div class="text-xs text-gray-500 mt-0.5">Pricing</div>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-xl">
              <div class="font-bold text-lg text-gray-900 truncate"><?= e($community['owner_first'] ?: $community['owner_username']) ?></div>
              <div class="text-xs text-gray-500 mt-0.5">Owner</div>
            </div>
          </div>

          <!-- Description -->
          <div class="p-6">
            <h3 class="font-bold text-lg text-gray-900 mb-4">About This Community</h3>
            <div class="text-gray-700 text-sm leading-relaxed whitespace-pre-line">
              <?= e($community['description'] ?: $community['short_bio'] ?: 'No description provided.') ?>
            </div>

            <?php if (!$is_approved): ?>
              <div class="mt-6">
                <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
                  <div class="inline-flex items-center gap-2 px-6 py-3 bg-amber-100 text-amber-600 rounded-xl font-semibold">
                    Membership Request Pending
                  </div>
                <?php elseif ($current_user): ?>
                  <button onclick="joinCommunity(<?= $community_id ?>)"
                          class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                    <?= $community['pricing'] === 'paid' ? 'Join for ' . format_price($community['price'], $community['price_interval'] ?? '') : 'Join Community — Free' ?>
                  </button>
                <?php else: ?>
                  <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-brand text-white rounded-xl font-semibold">Sign In to Join</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <!-- ====== TAB: ADMIN ====== -->
      <?php elseif ($tab === 'admin' && $is_admin): ?>
<?php
  // Load all data needed for admin tab
  $adm_members = db_fetch_all(
    'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar
     FROM memberships m JOIN users u ON u.id = m.user_id
     WHERE m.community_id = ? AND m.status = "approved"
     ORDER BY u.first_name',
    [$community_id]
  );
  $adm_badges = db_fetch_all('SELECT * FROM badges WHERE community_id = ? ORDER BY id DESC', [$community_id]);
  $adm_pending = db_fetch_all(
    'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, m.id as membership_id
     FROM memberships m JOIN users u ON u.id = m.user_id
     WHERE m.community_id = ? AND m.status = "pending" ORDER BY m.joined_at',
    [$community_id]
  );
  $adm_topics = db_fetch_all('SELECT * FROM topics WHERE community_id = ? ORDER BY sort_order', [$community_id]);
  $adm_admins = db_fetch_all(
    'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, m.role
     FROM memberships m JOIN users u ON u.id = m.user_id
     WHERE m.community_id = ? AND m.role IN ("admin","owner") AND m.status = "approved"
     ORDER BY FIELD(m.role,"owner","admin"), u.first_name',
    [$community_id]
  );
?>
<div class="space-y-6">

  <!-- AWARD POINTS -->
  <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Award Points</h3>
    <div class="space-y-3">
      <select id="adm-user" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-gray-900 text-sm focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
        <?php foreach ($adm_members as $m): ?>
          <option value="<?= $m['id'] ?>"><?= e(trim(($m['first_name']??'').' '.($m['last_name']??'')) ?: $m['username']) ?> (@<?= e($m['username']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="flex gap-3">
        <input id="adm-pts" type="number" min="1" max="9999" placeholder="Points" class="flex-1 bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-gray-900 text-sm placeholder-gray-600 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
        <input id="adm-reason" type="text" placeholder="Reason (optional)" class="flex-1 bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-gray-900 text-sm placeholder-gray-600 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
      </div>
      <button onclick="doAwardPoints(<?= $community_id ?>)" class="px-5 py-2.5 bg-brand text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity">Award Points</button>
    </div>
  </div>

  <!-- PENDING REQUESTS -->
  <?php if (!empty($adm_pending)): ?>
  <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Pending Requests <span class="ml-2 bg-amber-500/20 text-amber-400 text-sm px-2 py-0.5 rounded-full"><?= count($adm_pending) ?></span></h3>
    <div class="space-y-2">
      <?php foreach ($adm_pending as $pm): ?>
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
          <div class="flex items-center gap-3">
            <img src="<?= get_avatar_url($pm) ?>" class="w-9 h-9 rounded-full object-cover bg-gray-700">
            <div>
              <p class="text-sm font-semibold text-gray-900"><?= e(trim(($pm['first_name']??'').' '.($pm['last_name']??'')) ?: $pm['username']) ?></p>
              <p class="text-xs text-gray-500">@<?= e($pm['username']) ?></p>
            </div>
          </div>
          <div class="flex gap-2">
            <button onclick="approveMember(<?= $pm['membership_id'] ?>, 'approved', this)" class="px-3 py-1.5 bg-brand text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors">Approve</button>
            <button onclick="approveMember(<?= $pm['membership_id'] ?>, 'rejected', this)" class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-semibold hover:bg-white/20 transition-colors">Reject</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- MANAGE BADGES -->
  <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Badges</h3>
    <?php if (!empty($adm_badges)): ?>
    <div class="space-y-2 mb-5">
      <?php foreach ($adm_badges as $b): ?>
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
          <div class="flex items-center gap-3">
            <span class="text-xl"><?= e($b['icon'] ?? '🏅') ?></span>
            <div>
              <p class="text-sm font-semibold text-gray-900"><?= e($b['name']) ?></p>
              <p class="text-xs text-gray-500"><?= e($b['description'] ?? '') ?></p>
            </div>
          </div>
          <div class="flex gap-2">
            <button onclick="doAwardBadge(<?= $b['id'] ?>, '<?= e($b['name']) ?>', <?= $community_id ?>)" class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-semibold hover:bg-white/20">Award</button>
            <button onclick="doDeleteBadge(<?= $b['id'] ?>, <?= $community_id ?>)" class="px-3 py-1.5 bg-red-900/30 text-red-400 rounded-lg text-xs font-semibold hover:bg-red-900/50">Del</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="space-y-3 pt-4 border-t border-gray-200">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Create Badge</p>
      <div class="grid grid-cols-2 gap-3">
        <input id="b-name" type="text" placeholder="Badge name" class="bg-gray-100 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm placeholder-gray-600 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
        <input id="b-icon" type="text" placeholder="Icon emoji" value="🏅" class="bg-gray-100 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm placeholder-gray-600 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
      </div>
      <input id="b-desc" type="text" placeholder="Description" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm placeholder-gray-600 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
      <button onclick="doCreateBadge(<?= $community_id ?>)" class="px-5 py-2.5 bg-brand text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity">Create Badge</button>
    </div>
  </div>

  <!-- TOPICS -->
  <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Topics</h3>
    <div class="flex flex-wrap gap-2 mb-4">
      <?php foreach ($adm_topics as $t): ?>
        <span class="flex items-center gap-2 bg-gray-100 text-gray-600 px-3 py-1.5 rounded-full text-sm">
          # <?= e($t['name']) ?>
          <button onclick="doDeleteTopic(<?= $t['id'] ?>, <?= $community_id ?>)" class="text-gray-500 hover:text-red-400 transition-colors text-xs">&#x2715;</button>
        </span>
      <?php endforeach; ?>
    </div>
    <div class="flex gap-2">
      <input id="new-topic-name" type="text" placeholder="New topic..." class="flex-1 bg-gray-100 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm placeholder-gray-600 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
      <button onclick="doAddTopic(<?= $community_id ?>)" class="px-5 py-2.5 bg-white text-gray-900 rounded-xl text-sm font-semibold hover:bg-gray-100 transition-colors">Add</button>
    </div>
  </div>

  <!-- MANAGE ADMINS (owner only) -->
  <?php if ($is_owner): ?>
  <div class="bg-white rounded-2xl border border-gray-200 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Manage Admins</h3>
    <div class="space-y-2 mb-5">
      <?php foreach ($adm_admins as $a): ?>
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
          <div class="flex items-center gap-3">
            <img src="<?= get_avatar_url($a) ?>" class="w-9 h-9 rounded-full object-cover bg-gray-700">
            <div>
              <p class="text-sm font-semibold text-gray-900"><?= e(trim(($a['first_name']??'').' '.($a['last_name']??'')) ?: $a['username']) ?></p>
              <p class="text-xs text-gray-500">@<?= e($a['username']) ?></p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs px-2 py-1 rounded-full <?= $a['role']==='owner' ? 'bg-amber-900/30 text-amber-400' : 'bg-brand/10 text-brand' ?> font-semibold"><?= ucfirst($a['role']) ?></span>
            <?php if ($a['role'] === 'admin'): ?>
              <button onclick="doDemoteAdmin(<?= $a['id'] ?>, <?= $community_id ?>)" class="px-3 py-1.5 bg-red-900/30 text-red-400 rounded-lg text-xs font-semibold hover:bg-red-900/50">Remove</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="space-y-2 pt-4 border-t border-gray-200">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Promote Member to Admin</p>
      <div class="flex gap-2">
        <select id="promote-sel" class="flex-1 bg-gray-100 border border-gray-200 rounded-xl px-4 py-2.5 text-gray-900 text-sm focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50">
          <option value="">&#8212; Select member &#8212;</option>
          <?php
          $admin_ids = array_column($adm_admins, 'id');
          foreach ($adm_members as $m):
            if (in_array($m['id'], $admin_ids)) continue;
          ?>
            <option value="<?= $m['id'] ?>"><?= e(trim(($m['first_name']??'').' '.($m['last_name']??'')) ?: $m['username']) ?></option>
          <?php endforeach; ?>
        </select>
        <button onclick="doPromoteAdmin(<?= $community_id ?>)" class="px-5 py-2.5 bg-brand text-white rounded-xl text-sm font-semibold hover:opacity-90">Promote</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- end admin tab -->

<!-- Award Badge Modal -->
<div id="ab-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 border border-gray-200">
    <h3 class="font-bold text-lg text-gray-900 mb-1">Award Badge</h3>
    <p class="text-sm text-gray-500 mb-4">Awarding: <strong id="ab-badge-name" class="text-gray-900"></strong></p>
    <input type="hidden" id="ab-badge-id">
    <input type="hidden" id="ab-comm-id">
    <select id="ab-user" class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-gray-900 text-sm focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50 mb-4">
      <?php foreach ($adm_members as $m): ?>
        <option value="<?= $m['id'] ?>"><?= e(trim(($m['first_name']??'').' '.($m['last_name']??'')) ?: $m['username']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="flex justify-end gap-3">
      <button onclick="document.getElementById('ab-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-900">Cancel</button>
      <button onclick="submitAwardBadge()" class="px-5 py-2.5 bg-brand text-white rounded-xl text-sm font-semibold hover:opacity-90">Award</button>
    </div>
  </div>
</div>

      <?php endif; // end tab ?>
    </div><!-- end space-y-0 -->
  </div><!-- end main content -->
</div><!-- end flex layout -->

<!-- Add Topic Modal -->
<div id="add-topic-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 border border-gray-200">
    <h3 class="font-bold text-lg text-gray-900 mb-4">Add Topic</h3>
    <input type="text" id="new-topic-name" placeholder="Topic name..."
           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50 mb-4">
    <div class="flex justify-end gap-3">
      <button onclick="document.getElementById('add-topic-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">Cancel</button>
      <button onclick="addTopic(<?= $community_id ?>)" class="px-5 py-2 bg-brand text-white rounded-xl text-sm font-semibold">Add</button>
    </div>
  </div>
</div>

<!-- Award Points Modal -->
<div id="award-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 border border-gray-200">
    <h3 class="font-bold text-lg text-gray-900 mb-4">Award Points</h3>
    <input type="hidden" id="award-user-id">
    <input type="hidden" id="award-community-id">
    <input type="number" id="award-points" placeholder="Points to award..." min="1" max="1000"
           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50 mb-2">
    <input type="text" id="award-reason" placeholder="Reason (optional)..."
           class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:outline-none focus:ring-2 focus:ring-brand/50 mb-4">
    <div class="flex justify-end gap-3">
      <button onclick="document.getElementById('award-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">Cancel</button>
      <button onclick="submitAwardPoints()" class="px-5 py-2 bg-brand text-white rounded-xl text-sm font-semibold">Award</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '<?= csrf_token() ?>';

function showToast(msg, type = 'success') {
  const el = document.getElementById('toast');
  const inner = document.getElementById('toast-inner');
  if (!el || !inner) return;
  inner.textContent = msg;
  inner.className = 'px-5 py-3 rounded-xl shadow-xl text-sm font-semibold text-gray-900 flex items-center gap-2 ' +
    (type === 'error' ? 'bg-red-600' : type === 'warning' ? 'bg-amber-500' : 'bg-[#111827]');
  el.classList.remove('hidden');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.add('hidden'), 3000);
}

function toggleLike(btn) {
  if (!btn) return;
  const postId = btn.dataset.postId;
  const liked = btn.dataset.liked === '1';
  const countEl = btn.querySelector('.like-count');
  const svgPath = btn.querySelector('svg path');
  const currentCount = parseInt(countEl.textContent) || 0;
  btn.dataset.liked = liked ? '0' : '1';
  countEl.textContent = liked ? currentCount - 1 : currentCount + 1;
  if (!liked) {
    btn.classList.add('text-red-500', 'bg-red-50'); btn.classList.remove('text-gray-500');
    if (svgPath) svgPath.setAttribute('fill', 'currentColor');
  } else {
    btn.classList.remove('text-red-500', 'bg-red-50'); btn.classList.add('text-gray-500');
    if (svgPath) svgPath.setAttribute('fill', 'none');
  }
  fetch('/api/post_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'like', post_id: postId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.like_count !== undefined) countEl.textContent = data.like_count;
  }).catch(() => { btn.dataset.liked = liked ? '1' : '0'; countEl.textContent = currentCount; });
}

function joinCommunity(communityId) {
  fetch('/api/join_community.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({community_id: communityId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast(data.message || 'Request submitted!'); setTimeout(() => location.reload(), 800); }
    else showToast(data.error || 'Error', 'error');
  });
}

function createPost(communityId) {
  const content = document.getElementById('post-content').value.trim();
  const topicId = document.getElementById('post-topic')?.value || '';
  if (!content) { showToast('Please write something!', 'error'); return; }
  fetch('/api/post_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'create', community_id: communityId, content, topic_id: topicId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Post created!'); setTimeout(() => location.reload(), 600); }
    else showToast(data.error || 'Error creating post', 'error');
  });
}

function toggleComments(postId) {
  const el = document.getElementById('comments-' + postId);
  if (el) el.classList.toggle('hidden');
}

function submitComment(postId, communityId) {
  const input = document.getElementById('comment-input-' + postId);
  const content = input?.value.trim();
  if (!content) return;
  const listEl = document.getElementById('comments-list-' + postId);
  const tempDiv = document.createElement('div');
  tempDiv.className = 'flex items-start gap-2';
  tempDiv.innerHTML = `<div class="w-7 h-7 rounded-full bg-gradient-to-br from-brand to-blue-400 flex-shrink-0"></div>
    <div class="flex-1 bg-gray-50 rounded-xl px-3 py-2">
      <div class="flex items-baseline gap-2"><span class="text-xs font-semibold text-gray-900">You</span><span class="text-xs text-gray-500">just now</span></div>
      <p class="text-xs text-gray-700 mt-0.5">${content.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>
    </div>`;
  if (listEl) listEl.appendChild(tempDiv);
  input.value = '';
  const commentCountEl = document.querySelector('#post-' + postId + ' .comment-count');
  if (commentCountEl) commentCountEl.textContent = parseInt(commentCountEl.textContent||0) + 1;
  fetch('/api/post_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'comment', post_id: postId, community_id: communityId, content, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => { if (data.success) showToast('Comment added!'); });
}

function togglePostMenu(postId) {
  const menu = document.getElementById('post-menu-' + postId);
  if (menu) menu.classList.toggle('hidden');
}

function pinPost(postId, pin) {
  fetch('/api/post_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'pin', post_id: postId, pin: pin, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast(pin ? 'Post pinned!' : 'Post unpinned!'); setTimeout(() => location.reload(), 600); }
    else showToast(data.error || 'Error', 'error');
  });
}

function deletePost(postId) {
  if (!confirm('Delete this post?')) return;
  fetch('/api/post_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'delete', post_id: postId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { const el = document.getElementById('post-' + postId); if (el) el.remove(); showToast('Post deleted!'); }
  });
}

function awardPoints(userId, communityId) {
  document.getElementById('award-user-id').value = userId;
  document.getElementById('award-community-id').value = communityId;
  document.getElementById('award-modal').classList.remove('hidden');
}

function submitAwardPoints() {
  const userId = document.getElementById('award-user-id').value;
  const communityId = document.getElementById('award-community-id').value;
  const points = document.getElementById('award-points').value;
  const reason = document.getElementById('award-reason').value;
  if (!points || points < 1) { showToast('Enter valid points', 'error'); return; }
  fetch('/api/award_points.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({user_id: userId, community_id: communityId, points, reason, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Points awarded!'); document.getElementById('award-modal').classList.add('hidden'); setTimeout(() => location.reload(), 600); }
    else showToast(data.error || 'Error', 'error');
  });
}

function showAddTopic() { document.getElementById('add-topic-modal').classList.remove('hidden'); }

function addTopic(communityId) {
  const modalInput = document.getElementById('new-topic-name');
  const name = (modalInput ? modalInput.value : '').trim();
  if (!name) return;
  doAddTopic(communityId);
}

function copyInviteLink() {
  const link = document.getElementById('invite-link');
  link.select();
  navigator.clipboard.writeText(link.value).then(() => showToast('Link copied!'));
}

function deleteCourse(courseId, communityId) {
  if (!confirm('Delete this course? This cannot be undone.')) return;
  fetch('/api/manage_course.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'delete_course', course_id: courseId, community_id: communityId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Course deleted!'); setTimeout(() => location.reload(), 500); }
    else showToast(data.error || 'Error', 'error');
  });
}

// ── Admin Tab Functions ──────────────────────────────────────────────
async function doAwardPoints(cid) {
  const uid = document.getElementById('adm-user')?.value;
  const pts = parseInt(document.getElementById('adm-pts')?.value);
  const reason = document.getElementById('adm-reason')?.value || 'Bonus points';
  if (!uid || !pts || pts < 1) { alert('Select member and enter valid points'); return; }
  const fd = new FormData();
  fd.append('user_id', uid); fd.append('community_id', cid);
  fd.append('points', pts); fd.append('reason', reason);
  fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/award_points.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { showToast('Points awarded!'); document.getElementById('adm-pts').value=''; document.getElementById('adm-reason').value=''; }
  else alert(d.error || 'Failed');
}

async function doCreateBadge(cid) {
  const name = document.getElementById('b-name')?.value?.trim();
  const icon = document.getElementById('b-icon')?.value?.trim() || '🏅';
  const desc = document.getElementById('b-desc')?.value?.trim() || '';
  if (!name) { alert('Badge name required'); return; }
  const fd = new FormData();
  fd.append('action','create'); fd.append('community_id', cid);
  fd.append('name', name); fd.append('icon', icon); fd.append('description', desc);
  fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/manage_badges.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { showToast('Badge created!'); setTimeout(()=>location.reload(), 800); }
  else alert(d.error || 'Failed');
}

function doAwardBadge(badgeId, badgeName, cid) {
  document.getElementById('ab-badge-id').value = badgeId;
  document.getElementById('ab-badge-name').textContent = badgeName;
  document.getElementById('ab-comm-id').value = cid;
  document.getElementById('ab-modal').classList.remove('hidden');
}

async function submitAwardBadge() {
  const badgeId = document.getElementById('ab-badge-id').value;
  const userId  = document.getElementById('ab-user').value;
  const cid     = document.getElementById('ab-comm-id').value;
  const fd = new FormData();
  fd.append('action','award'); fd.append('badge_id', badgeId);
  fd.append('user_id', userId); fd.append('community_id', cid);
  fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/manage_badges.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { document.getElementById('ab-modal').classList.add('hidden'); showToast('Badge awarded!'); }
  else alert(d.error || 'Failed');
}

async function doDeleteBadge(badgeId, cid) {
  if (!confirm('Delete this badge?')) return;
  const fd = new FormData();
  fd.append('action','delete'); fd.append('badge_id', badgeId); fd.append('community_id', cid); fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/manage_badges.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { showToast('Deleted'); setTimeout(()=>location.reload(), 800); }
  else alert(d.error || 'Failed');
}

async function doAddTopic(cid) {
  const name = document.getElementById('new-topic-name')?.value?.trim();
  if (!name) { alert('Enter topic name'); return; }
  const fd = new FormData();
  fd.append('action','add_topic'); fd.append('community_id', cid); fd.append('name', name); fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/post_action.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { showToast('Topic added'); setTimeout(()=>location.reload(), 800); }
  else alert(d.error || 'Failed');
}

async function doDeleteTopic(topicId, cid) {
  if (!confirm('Delete topic?')) return;
  const fd = new FormData();
  fd.append('action','delete_topic'); fd.append('topic_id', topicId); fd.append('community_id', cid); fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/post_action.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { showToast('Deleted'); setTimeout(()=>location.reload(), 800); }
  else alert(d.error || 'Failed');
}

async function doPromoteAdmin(cid) {
  const uid = document.getElementById('promote-sel')?.value;
  if (!uid) { alert('Select a member'); return; }
  const fd = new FormData();
  fd.append('action','promote_admin'); fd.append('user_id', uid); fd.append('community_id', cid); fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/approve_member.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { showToast('Promoted to admin!'); setTimeout(()=>location.reload(), 800); }
  else alert(d.error || 'Failed');
}

async function doDemoteAdmin(uid, cid) {
  if (!confirm('Remove admin privileges?')) return;
  const fd = new FormData();
  fd.append('action','demote_admin'); fd.append('user_id', uid); fd.append('community_id', cid); fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/approve_member.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) { showToast('Admin removed'); setTimeout(()=>location.reload(), 800); }
  else alert(d.error || 'Failed');
}

async function approveMember(membershipId, status, btn) {
  if (btn) { btn.disabled = true; btn.textContent = '...'; }
  const fd = new FormData();
  fd.append('membership_id', membershipId); fd.append('status', status); fd.append('csrf_token', CSRF_TOKEN);
  const r = await fetch('/api/approve_member.php', {method:'POST', body:fd});
  const d = await r.json();
  if (d.success) {
    showToast(status === 'approved' ? 'Approved!' : 'Rejected');
    if (btn) { const row = btn.closest('.flex.items-center.justify-between'); if (row) row.remove(); }
    else setTimeout(() => location.reload(), 600);
  } else { alert(d.error || 'Failed'); if (btn) { btn.disabled = false; btn.textContent = status === 'approved' ? 'Approve' : 'Reject'; } }
}
</script>

<!-- Toast -->
<div id="toast" class="fixed bottom-6 right-6 z-[100] hidden transition-all">
  <div id="toast-inner" class="px-5 py-3 rounded-xl shadow-xl text-sm font-semibold text-gray-900 bg-gray-900 flex items-center gap-2"></div>
</div>
</body>
</html>
