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
include __DIR__ . '/includes/header.php';
?>

<!-- Community banner header -->
<div class="relative h-56 sm:h-72 overflow-hidden">
  <?php if ($community['banner']): ?>
    <img src="<?= e($community['banner']) ?>" alt="" class="w-full h-full object-cover">
  <?php else: ?>
    <div class="w-full h-full bg-gradient-to-br from-primary-700 via-primary-600 to-accent-500"></div>
  <?php endif; ?>
  <!-- Gradient overlay -->
  <div class="absolute inset-0 bg-gradient-to-t from-[#121212] via-[#121212]/50 to-transparent dark:from-[#121212] dark:via-[#121212]/50"></div>
  <div class="absolute inset-0 bg-gradient-to-t from-white via-white/30 to-transparent dark:from-[#121212] dark:via-[#121212]/30"></div>

  <!-- Community info overlaid at bottom -->
  <div class="absolute bottom-0 left-0 right-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-5 flex items-end gap-4">
    <!-- Logo -->
    <?php if ($community['logo']): ?>
      <img src="<?= e($community['logo']) ?>" alt=""
           class="w-16 h-16 rounded-2xl border-2 border-white/30 shadow-xl flex-shrink-0 object-cover">
    <?php else: ?>
      <div class="w-16 h-16 rounded-2xl border-2 border-white/20 shadow-xl flex-shrink-0 bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-2xl">
        <?= strtoupper(substr($community['name'], 0, 1)) ?>
      </div>
    <?php endif; ?>
    <div class="flex-1 min-w-0">
      <h1 class="text-2xl sm:text-3xl font-bold text-white drop-shadow-lg line-clamp-1"><?= e($community['name']) ?></h1>
      <p class="text-sm text-white/70 drop-shadow mt-0.5">
        <?= format_member_count((int)($member_count_approved['cnt'] ?? 0)) ?> members
        &bull;
        <?= $community['pricing'] === 'free' ? 'Free' : format_price($community['price'], $community['price_interval'] ?? '') ?>
        <?php if ($community['type'] === 'private'): ?>
          &bull; Private
        <?php endif; ?>
      </p>
    </div>
    <?php if ($is_owner): ?>
      <a href="/edit-community.php?id=<?= $community_id ?>"
         class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white/10 glass border border-white/20 text-sm font-medium text-white hover:bg-white/20 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Edit
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Sticky tab bar -->
<div class="sticky top-16 z-30 bg-white/95 dark:bg-[#121212]/95 glass border-b border-gray-200/60 dark:border-white/10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide">
      <?php foreach ($tab_order as $t => $label): ?>
        <a href="?slug=<?= e($slug) ?>&tab=<?= $t ?>"
           class="flex-shrink-0 px-4 py-4 text-sm font-medium whitespace-nowrap transition-colors border-b-2
                  <?= $tab === $t ? 'text-gray-900 dark:text-white border-gray-900 dark:border-white' : 'text-gray-500 dark:text-gray-500 border-transparent hover:text-gray-700 dark:hover:text-gray-300' ?>
                  <?= $t === 'admin' ? 'text-amber-600 dark:text-amber-400' : '' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex gap-8 flex-col lg:flex-row">

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
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-8 text-center mb-5 shadow-airbnb">
            <div class="w-14 h-14 bg-gray-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Join to see the community feed</h3>
            <p class="text-gray-500 dark:text-gray-500 text-sm mb-5">Become a member to post, comment, and interact with the community.</p>
            <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
              <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-xl text-sm font-semibold">Request Pending Approval</span>
            <?php elseif ($current_user): ?>
              <button onclick="joinCommunity(<?= $community_id ?>)"
                      class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-lg transition-all">
                Join Community
              </button>
            <?php else: ?>
              <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold">Sign In to Join</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($is_approved): ?>
          <!-- Topic Pills -->
          <div class="flex gap-2 flex-wrap mb-5 overflow-x-auto scrollbar-hide">
            <a href="?slug=<?= e($slug) ?>&tab=community"
               class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap
                      <?= !$topic_id ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/15' ?>">
              All Posts
            </a>
            <?php foreach ($topics as $t): ?>
              <a href="?slug=<?= e($slug) ?>&tab=community&topic=<?= $t['id'] ?>"
                 class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap
                        <?= $topic_id === (int)$t['id'] ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/15' ?>">
                # <?= e($t['name']) ?>
              </a>
            <?php endforeach; ?>
            <?php if ($is_admin): ?>
              <button onclick="showAddTopic()"
                      class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium border-2 border-dashed border-gray-300 dark:border-white/20 text-gray-500 dark:text-gray-400 hover:border-primary-400 hover:text-primary-500 transition-colors whitespace-nowrap">
                + Add Topic
              </button>
            <?php endif; ?>
          </div>

          <!-- Create Post -->
          <?php if ($current_user): ?>
            <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-4 mb-4 shadow-airbnb">
              <div class="flex items-start gap-3">
                <img src="<?= get_avatar_url($current_user['avatar'] ?? null, ($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')) ?>"
                     class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                <div class="flex-1">
                  <textarea id="post-content" placeholder="Write something to the community..." rows="2"
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none placeholder-gray-400 dark:placeholder-gray-600"
                            onfocus="this.rows=4" onblur="if(!this.value)this.rows=2"></textarea>
                  <div class="mt-2 flex items-center justify-between">
                    <select id="post-topic"
                            class="text-xs border border-gray-200 dark:border-white/10 rounded-lg px-2 py-1.5 bg-gray-50 dark:bg-[#2a2a2a] text-gray-600 dark:text-gray-300 focus:outline-none focus:ring-1 focus:ring-primary-500">
                      <option value="">No topic</option>
                      <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $topic_id === (int)$t['id'] ? 'selected' : '' ?>># <?= e($t['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button onclick="createPost(<?= $community_id ?>)"
                            class="px-4 py-1.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-full text-xs font-semibold hover:shadow-md transition-all">
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
            <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-amber-300/50 dark:border-amber-700/30 p-5 mb-3 shadow-airbnb relative" id="post-<?= $post['id'] ?>">
              <div class="absolute top-3 right-3 flex items-center gap-1">
                <span class="text-xs bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded-full font-medium flex items-center gap-1">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/></svg>
                  Pinned
                </span>
                <?php if ($is_owner): ?>
                  <button onclick="togglePostMenu(<?= $post['id'] ?>)" class="p-1 hover:bg-gray-100 dark:hover:bg-white/10 rounded-lg text-gray-400">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                  </button>
                  <div id="post-menu-<?= $post['id'] ?>" class="hidden absolute right-0 top-8 bg-white dark:bg-[#1a1a1a] border border-gray-200 dark:border-white/10 rounded-xl shadow-airbnb-lg z-10 py-1 w-36">
                    <button onclick="pinPost(<?= $post['id'] ?>, false)" class="w-full text-left px-4 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">Unpin</button>
                    <button onclick="deletePost(<?= $post['id'] ?>)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Delete</button>
                  </div>
                <?php endif; ?>
              </div>
              <?php include __DIR__ . '/includes/post_card.php'; ?>
            </div>
          <?php endforeach; ?>

          <!-- Regular Posts -->
          <?php if (empty($posts) && empty($pinned_posts)): ?>
            <div class="text-center py-16 bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10">
              <div class="w-12 h-12 bg-gray-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              </div>
              <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-2">No posts yet</h3>
              <p class="text-sm text-gray-500 dark:text-gray-500">Be the first to post something!</p>
            </div>
          <?php else: ?>
            <?php foreach ($posts as $post): ?>
              <?php $liked = in_array($post['id'], $liked_posts); ?>
              <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-5 mb-3 shadow-airbnb relative hover:border-gray-300 dark:hover:border-white/20 transition-colors" id="post-<?= $post['id'] ?>">
                <?php if ($is_owner): ?>
                  <div class="absolute top-3 right-3">
                    <button onclick="togglePostMenu(<?= $post['id'] ?>)" class="p-1.5 hover:bg-gray-100 dark:hover:bg-white/10 rounded-lg text-gray-400 transition-colors">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                    </button>
                    <div id="post-menu-<?= $post['id'] ?>" class="hidden absolute right-0 top-8 bg-white dark:bg-[#1a1a1a] border border-gray-200 dark:border-white/10 rounded-xl shadow-airbnb-lg z-10 py-1 w-36">
                      <button onclick="pinPost(<?= $post['id'] ?>, true)" class="w-full text-left px-4 py-2 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">Pin Post</button>
                      <button onclick="deletePost(<?= $post['id'] ?>)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Delete</button>
                    </div>
                  </div>
                <?php elseif ($current_user && $post['user_id'] == $current_user['id']): ?>
                  <div class="absolute top-3 right-3">
                    <button onclick="deletePost(<?= $post['id'] ?>)" class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-gray-400 hover:text-red-500 transition-colors">
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
                            <?= $i === $post_page ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900' : 'border border-gray-300 dark:border-white/20 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' ?>">
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
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-12 text-center shadow-airbnb">
            <div class="w-16 h-16 bg-gray-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Members Only</h3>
            <p class="text-gray-500 dark:text-gray-500 mb-6 text-sm">Join this community to access all courses and learning materials.</p>
            <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
              <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-xl font-semibold">Request Pending</span>
            <?php elseif ($current_user): ?>
              <button onclick="joinCommunity(<?= $community_id ?>)" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all">Join Community</button>
            <?php else: ?>
              <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold">Sign In to Join</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <?php
          $courses = db_fetch_all('SELECT * FROM courses WHERE community_id = ? AND is_published = 1 ORDER BY sort_order, created_at', [$community_id]);
          ?>
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">Classroom</h2>
              <?php if ($is_admin): ?>
                <a href="/manage-course.php?community_id=<?= $community_id ?>"
                   class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  Add Course
                </a>
              <?php endif; ?>
            </div>
            <?php if (empty($courses)): ?>
              <div class="text-center py-16">
                <div class="w-12 h-12 bg-gray-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-3">
                  <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-2">No courses yet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-500">The community hasn't added any courses yet.</p>
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
                  <div class="group bg-gray-50 dark:bg-[#2a2a2a] rounded-2xl overflow-hidden border border-gray-200 dark:border-white/10 hover:border-gray-300 dark:hover:border-white/20 hover:shadow-airbnb transition-all">
                    <div class="aspect-video overflow-hidden relative">
                      <?php if ($course['thumbnail']): ?>
                        <img src="<?= e($course['thumbnail']) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                      <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center">
                          <svg class="w-12 h-12 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                      <?php endif; ?>
                      <div class="absolute top-2 right-2">
                        <?php if ($course['pricing'] === 'paid'): ?>
                          <span class="bg-[#1a1a1a]/80 glass text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= format_price($course['price']) ?></span>
                        <?php else: ?>
                          <span class="bg-green-500/90 text-white text-xs font-bold px-2 py-0.5 rounded-full">Free</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($is_admin): ?>
                        <div class="absolute top-2 left-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
                          <a href="/manage-course.php?community_id=<?= $community_id ?>&course_id=<?= $course['id'] ?>"
                             class="p-1.5 bg-white/90 rounded-xl shadow text-xs text-primary-600 hover:bg-white" title="Edit">
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
                      <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-1 line-clamp-2"><?= e($course['title']) ?></h3>
                      <p class="text-xs text-gray-500 dark:text-gray-500 mb-3"><?= (int)($section_count['cnt'] ?? 0) ?> sections &bull; <?= (int)($lesson_count['cnt'] ?? 0) ?> lessons</p>
                      <?php if ($current_user && $is_approved && $progress['total'] > 0): ?>
                        <div class="mb-3">
                          <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                            <span>Progress</span>
                            <span><?= $progress['percent'] ?>%</span>
                          </div>
                          <div class="w-full bg-gray-200 dark:bg-white/10 rounded-full h-1.5">
                            <div class="bg-gradient-to-r from-primary-500 to-accent-500 h-1.5 rounded-full" style="width:<?= $progress['percent'] ?>%"></div>
                          </div>
                        </div>
                      <?php endif; ?>
                      <a href="/course.php?id=<?= $course['id'] ?>"
                         class="block w-full text-center py-2 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-xs font-semibold hover:shadow-md transition-all">
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
        <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
          <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-6">
              <div class="text-center">
                <div class="text-2xl font-black text-gray-900 dark:text-white"><?= count($all_members) ?></div>
                <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Members</div>
              </div>
              <div class="w-px h-8 bg-gray-200 dark:bg-white/10"></div>
              <div class="text-center">
                <div class="text-2xl font-black text-gray-900 dark:text-white"><?= count(array_filter($all_members, fn($m) => in_array($m['role'], ['admin','owner']))) ?></div>
                <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Admins</div>
              </div>
            </div>
            <?php if ($is_approved): ?>
              <button onclick="document.getElementById('invite-modal').classList.remove('hidden')"
                      class="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 dark:hover:bg-white/15 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Invite Members
              </button>
            <?php endif; ?>
          </div>

          <!-- Pending requests -->
          <?php if (!empty($pending_members)): ?>
            <div class="mb-6">
              <h3 class="font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                Pending Requests
                <span class="bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($pending_members) ?></span>
              </h3>
              <div class="space-y-2">
                <?php foreach ($pending_members as $mem): ?>
                  <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-200 dark:border-amber-800/30">
                    <div class="flex items-center gap-3">
                      <img src="<?= get_avatar_url($mem['avatar'] ?? null, ($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? '')) ?>" class="w-9 h-9 rounded-full object-cover">
                      <div>
                        <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= e(trim(($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? ''))) ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-500">@<?= e($mem['username']) ?></div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <button onclick="approveMember(<?= $mem['membership_id'] ?>, 'approved')" class="px-3 py-1.5 bg-green-500 text-white rounded-lg text-xs font-semibold hover:bg-green-600 transition-colors">Approve</button>
                      <button onclick="approveMember(<?= $mem['membership_id'] ?>, 'rejected')" class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">Reject</button>
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
                 class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-white/5 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-white/10">
                <div class="relative flex-shrink-0">
                  <img src="<?= get_avatar_url($mem['avatar'] ?? null, ($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? '')) ?>" class="w-10 h-10 rounded-full object-cover">
                  <?php if ($mem['role'] === 'owner'): ?>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-amber-400 rounded-full flex items-center justify-center text-xs">
                      <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                  <?php elseif ($mem['role'] === 'admin'): ?>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-primary-500 rounded-full flex items-center justify-center text-white text-xs font-bold leading-none">A</div>
                  <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-semibold text-sm text-gray-900 dark:text-white truncate"><?= e(trim(($mem['first_name'] ?? '') . ' ' . ($mem['last_name'] ?? '')) ?: $mem['username']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-500 truncate">@<?= e($mem['username']) ?></div>
                  <?php if ($mem['bio']): ?>
                    <div class="text-xs text-gray-500 dark:text-gray-500 line-clamp-1 mt-0.5"><?= e($mem['bio']) ?></div>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Invite Modal -->
        <div id="invite-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl shadow-airbnb-lg w-full max-w-md p-6 border border-gray-200 dark:border-white/10">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold text-lg text-gray-900 dark:text-white">Invite to Community</h3>
              <button onclick="document.getElementById('invite-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Share this link to invite people:</p>
            <div class="flex gap-2">
              <input id="invite-link" type="text" readonly value="<?= e($referral_link) ?>"
                     class="flex-1 bg-[#2a2a2a] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-gray-300 focus:outline-none">
              <button onclick="copyInviteLink()" class="px-4 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Copy</button>
            </div>
          </div>
        </div>

      <!-- ====== TAB: LEADERBOARD ====== -->
      <?php elseif ($tab === 'leaderboard'): ?>
        <?php if (!$is_approved): ?>
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-12 text-center shadow-airbnb">
            <div class="w-16 h-16 bg-gray-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Members Only</h3>
            <p class="text-gray-500 dark:text-gray-500 mb-6 text-sm">Join this community to see the leaderboard.</p>
            <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
              <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-xl font-semibold">Request Pending</span>
            <?php elseif ($current_user): ?>
              <button onclick="joinCommunity(<?= $community_id ?>)" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all">Join Community</button>
            <?php else: ?>
              <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold">Sign In to Join</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <?php $leaderboard = get_community_leaderboard($community_id, 50); ?>
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Leaderboard</h2>

            <!-- Top 3 Podium -->
            <?php if (count($leaderboard) >= 3): ?>
              <div class="flex items-end justify-center gap-3 mb-8 p-6 bg-gray-50 dark:bg-white/5 rounded-2xl">
                <!-- 2nd -->
                <div class="text-center flex-1">
                  <div class="relative inline-block mb-2">
                    <img src="<?= get_avatar_url($leaderboard[1]['avatar'] ?? null, ($leaderboard[1]['first_name'] ?? '') . ' ' . ($leaderboard[1]['last_name'] ?? '')) ?>"
                         class="w-14 h-14 rounded-full mx-auto border-2 border-gray-400 shadow-md">
                    <div class="absolute -top-2 -right-1 w-6 h-6 bg-gray-400 rounded-full flex items-center justify-center text-white font-black text-xs">2</div>
                  </div>
                  <div class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate max-w-20 mx-auto"><?= e($leaderboard[1]['first_name'] ?? $leaderboard[1]['username']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($leaderboard[1]['total_points']) ?> XP</div>
                  <div class="h-16 bg-gray-300 dark:bg-gray-600 rounded-t-xl mt-2 w-full"></div>
                </div>
                <!-- 1st -->
                <div class="text-center flex-1">
                  <div class="relative inline-block mb-2">
                    <img src="<?= get_avatar_url($leaderboard[0]['avatar'] ?? null, ($leaderboard[0]['first_name'] ?? '') . ' ' . ($leaderboard[0]['last_name'] ?? '')) ?>"
                         class="w-20 h-20 rounded-full mx-auto border-2 border-amber-400 shadow-xl">
                    <div class="absolute -top-2 -right-1 w-7 h-7 bg-amber-400 rounded-full flex items-center justify-center text-white font-black text-xs">1</div>
                  </div>
                  <div class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-24 mx-auto"><?= e($leaderboard[0]['first_name'] ?? $leaderboard[0]['username']) ?></div>
                  <div class="text-xs text-primary-600 dark:text-primary-400 font-semibold"><?= number_format($leaderboard[0]['total_points']) ?> XP</div>
                  <div class="h-24 bg-amber-400 rounded-t-xl mt-2 w-full"></div>
                </div>
                <!-- 3rd -->
                <div class="text-center flex-1">
                  <div class="relative inline-block mb-2">
                    <img src="<?= get_avatar_url($leaderboard[2]['avatar'] ?? null, ($leaderboard[2]['first_name'] ?? '') . ' ' . ($leaderboard[2]['last_name'] ?? '')) ?>"
                         class="w-14 h-14 rounded-full mx-auto border-2 border-amber-700 shadow-md">
                    <div class="absolute -top-2 -right-1 w-6 h-6 bg-amber-700 rounded-full flex items-center justify-center text-white font-black text-xs">3</div>
                  </div>
                  <div class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate max-w-20 mx-auto"><?= e($leaderboard[2]['first_name'] ?? $leaderboard[2]['username']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($leaderboard[2]['total_points']) ?> XP</div>
                  <div class="h-12 bg-amber-700 rounded-t-xl mt-2 w-full"></div>
                </div>
              </div>
            <?php endif; ?>

            <!-- Full list -->
            <div class="space-y-1">
              <?php foreach ($leaderboard as $i => $leader): ?>
                <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-white/5 transition-colors <?= $current_user && $leader['id'] === $current_user['id'] ? 'bg-primary-50 dark:bg-primary-900/10 border border-primary-200 dark:border-primary-800/30' : '' ?>">
                  <div class="w-7 text-center text-sm font-bold text-gray-500 dark:text-gray-400">
                    <?= $i < 3 ? ['#1','#2','#3'][$i] : '#'.($i+1) ?>
                  </div>
                  <img src="<?= get_avatar_url($leader['avatar'] ?? null, ($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? '')) ?>"
                       class="w-9 h-9 rounded-full object-cover">
                  <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= e(trim(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? '')) ?: $leader['username']) ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-500">@<?= e($leader['username']) ?></div>
                  </div>
                  <div class="text-right">
                    <div class="text-sm font-black text-primary-600 dark:text-primary-400"><?= number_format($leader['total_points']) ?></div>
                    <div class="text-xs text-gray-400 dark:text-gray-600">XP</div>
                  </div>
                  <div class="text-right hidden sm:block">
                    <div class="text-sm font-bold text-gray-700 dark:text-gray-300"><?= $leader['badge_count'] ?></div>
                    <div class="text-xs text-gray-400 dark:text-gray-600">Badges</div>
                  </div>
                  <?php if ($is_owner && $current_user && $leader['id'] !== $current_user['id']): ?>
                    <button onclick="awardPoints(<?= $leader['id'] ?>, <?= $community_id ?>)"
                            class="flex-shrink-0 px-3 py-1.5 bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-200 dark:hover:bg-white/15 transition-colors">
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
        <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 shadow-airbnb overflow-hidden">
          <!-- Stats Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-6 border-b border-gray-100 dark:border-white/10">
            <div class="text-center p-4 bg-gray-50 dark:bg-white/5 rounded-xl">
              <div class="font-bold text-lg text-gray-900 dark:text-white capitalize"><?= $community['type'] ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Access</div>
            </div>
            <div class="text-center p-4 bg-gray-50 dark:bg-white/5 rounded-xl">
              <div class="font-bold text-lg text-gray-900 dark:text-white"><?= format_member_count((int)($member_count_approved['cnt'] ?? 0)) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Members</div>
            </div>
            <div class="text-center p-4 bg-gray-50 dark:bg-white/5 rounded-xl">
              <div class="font-bold text-lg text-gray-900 dark:text-white"><?= $community['pricing'] === 'free' ? 'Free' : format_price($community['price'], $community['price_interval'] ?? '') ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Pricing</div>
            </div>
            <div class="text-center p-4 bg-gray-50 dark:bg-white/5 rounded-xl">
              <div class="font-bold text-lg text-gray-900 dark:text-white truncate"><?= e($community['owner_first'] ?: $community['owner_username']) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Owner</div>
            </div>
          </div>

          <!-- Description -->
          <div class="p-6">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">About This Community</h3>
            <div class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-line">
              <?= e($community['description'] ?: $community['short_bio'] ?: 'No description provided.') ?>
            </div>

            <?php if (!$is_approved): ?>
              <div class="mt-6">
                <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
                  <div class="inline-flex items-center gap-2 px-6 py-3 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-xl font-semibold">
                    Membership Request Pending
                  </div>
                <?php elseif ($current_user): ?>
                  <button onclick="joinCommunity(<?= $community_id ?>)"
                          class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all">
                    <?= $community['pricing'] === 'paid' ? 'Join for ' . format_price($community['price'], $community['price_interval'] ?? '') : 'Join Community — Free' ?>
                  </button>
                <?php else: ?>
                  <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold">Sign In to Join</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <!-- ====== TAB: ADMIN ====== -->
      <?php elseif ($tab === 'admin' && $is_admin): ?>
        <?php
        $approved_members = db_fetch_all(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.community_id = ? AND m.status = "approved" ORDER BY u.first_name',
            [$community_id]
        );
        $community_badges = db_fetch_all('SELECT * FROM badges WHERE community_id = ? ORDER BY created_at DESC', [$community_id]);
        $pending_members_admin = db_fetch_all(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, m.id as membership_id FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.community_id = ? AND m.status = "pending" ORDER BY m.joined_at',
            [$community_id]
        );
        $all_topics = db_fetch_all('SELECT * FROM topics WHERE community_id = ? ORDER BY sort_order', [$community_id]);
        ?>
        <div class="space-y-5">
          <!-- Award Points -->
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Award Points to Member</h3>
            <form onsubmit="adminAwardPoints(event, <?= $community_id ?>)" class="space-y-3">
              <select name="user_id" id="admin-award-user"
                      class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                <?php foreach ($approved_members as $m): ?>
                  <option value="<?= $m['id'] ?>"><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: $m['username']) ?> (@<?= e($m['username']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <div class="flex gap-3">
                <input type="number" id="admin-award-points-val" placeholder="Points (e.g. 50)" min="1" max="10000"
                       class="flex-1 bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <input type="text" id="admin-award-reason" placeholder="Reason..."
                       class="flex-1 bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
              </div>
              <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Award Points</button>
            </form>
          </div>

          <!-- Manage Badges -->
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Manage Badges</h3>
            <?php if (!empty($community_badges)): ?>
              <div class="space-y-2 mb-5">
                <?php foreach ($community_badges as $badge): ?>
                  <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                    <div class="flex items-center gap-3">
                      <span class="text-2xl"><?= e($badge['icon']) ?></span>
                      <div>
                        <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= e($badge['name']) ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-500"><?= e($badge['description']) ?></div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <button onclick="openAwardBadgeModal(<?= $badge['id'] ?>, '<?= e($badge['name']) ?>')"
                              class="px-3 py-1.5 bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-200 dark:hover:bg-white/15 transition-colors">Award</button>
                      <button onclick="deleteBadge(<?= $badge['id'] ?>, <?= $community_id ?>)"
                              class="px-3 py-1.5 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-100 transition-colors">Delete</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <h4 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-3">Create New Badge</h4>
            <form onsubmit="createBadge(event, <?= $community_id ?>)" class="space-y-3">
              <div class="grid grid-cols-2 gap-3">
                <input type="text" id="badge-name" placeholder="Badge name" required
                       class="bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <input type="text" id="badge-icon" placeholder="Icon (emoji)" value="🏅"
                       class="bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
              </div>
              <input type="text" id="badge-description" placeholder="Description..."
                     class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
              <div class="grid grid-cols-2 gap-3">
                <input type="number" id="badge-points" placeholder="Points required (0=manual)" min="0"
                       class="bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <select id="badge-type"
                        class="bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                  <option value="achievement">Achievement</option>
                  <option value="participation">Participation</option>
                  <option value="completion">Completion</option>
                </select>
              </div>
              <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Create Badge</button>
            </form>
          </div>

          <!-- Pending Members -->
          <?php if (!empty($pending_members_admin)): ?>
            <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
              <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                Pending Membership Requests
                <span class="bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 text-sm font-bold px-2 py-0.5 rounded-full"><?= count($pending_members_admin) ?></span>
              </h3>
              <div class="space-y-2">
                <?php foreach ($pending_members_admin as $pm): ?>
                  <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/10 rounded-xl border border-amber-200 dark:border-amber-800/30">
                    <div class="flex items-center gap-3">
                      <img src="<?= get_avatar_url($pm['avatar'] ?? null, ($pm['first_name'] ?? '') . ' ' . ($pm['last_name'] ?? '')) ?>" class="w-9 h-9 rounded-full object-cover">
                      <div>
                        <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= e(trim(($pm['first_name'] ?? '') . ' ' . ($pm['last_name'] ?? ''))) ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-500">@<?= e($pm['username']) ?></div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <button onclick="approveMember(<?= $pm['membership_id'] ?>, 'approved')" class="px-3 py-1.5 bg-green-500 text-white rounded-lg text-xs font-semibold hover:bg-green-600 transition-colors">Approve</button>
                      <button onclick="approveMember(<?= $pm['membership_id'] ?>, 'rejected')" class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-200 transition-colors">Reject</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Topics Management -->
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Manage Topics</h3>
            <div class="space-y-2 mb-4">
              <?php foreach ($all_topics as $topic): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                  <span class="text-sm font-medium text-gray-900 dark:text-white"># <?= e($topic['name']) ?></span>
                  <button onclick="deleteTopic(<?= $topic['id'] ?>, <?= $community_id ?>)"
                          class="text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">Delete</button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
              <input type="text" id="admin-new-topic" placeholder="New topic name..."
                     class="flex-1 bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
              <button onclick="addTopic(<?= $community_id ?>)" class="px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-semibold hover:bg-gray-700 dark:hover:bg-gray-100 transition-colors">Add</button>
            </div>
          </div>
        </div>

        <!-- Award Badge Modal -->
        <div id="award-badge-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl shadow-airbnb-lg w-full max-w-sm p-6 border border-gray-200 dark:border-white/10">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Award Badge: <span id="award-badge-name"></span></h3>
            <input type="hidden" id="award-badge-id">
            <select id="award-badge-user"
                    class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 mb-4">
              <?php foreach ($approved_members as $m): ?>
                <option value="<?= $m['id'] ?>"><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: $m['username']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="flex justify-end gap-3">
              <button onclick="document.getElementById('award-badge-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">Cancel</button>
              <button onclick="submitAwardBadge(<?= $community_id ?>)" class="px-5 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Award</button>
            </div>
          </div>
        </div>

      <?php endif; // end tab ?>
    </div>

    <!-- ============ STICKY RIGHT PANEL ============ -->
    <div class="lg:w-72 flex-shrink-0">
      <div class="sidebar-sticky space-y-4">
        <!-- Community info card — Airbnb listing style -->
        <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden shadow-airbnb">
          <!-- Mini banner -->
          <div class="h-20 relative overflow-hidden bg-gradient-to-br from-primary-600 to-accent-500">
            <?php if ($community['banner']): ?>
              <img src="<?= e($community['banner']) ?>" alt="" class="w-full h-full object-cover">
            <?php endif; ?>
          </div>
          <div class="px-4 pb-5">
            <div class="-mt-5 mb-3">
              <?php if ($community['logo']): ?>
                <img src="<?= e($community['logo']) ?>" alt="" class="w-10 h-10 rounded-xl border-2 border-white dark:border-[#1a1a1a] shadow-md object-cover">
              <?php else: ?>
                <div class="w-10 h-10 rounded-xl border-2 border-white dark:border-[#1a1a1a] shadow-md bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black">
                  <?= strtoupper(substr($community['name'], 0, 1)) ?>
                </div>
              <?php endif; ?>
            </div>
            <h2 class="font-bold text-gray-900 dark:text-white mb-0.5 text-sm"><?= e($community['name']) ?></h2>
            <p class="text-xs text-primary-600 dark:text-primary-400 mb-2">discover.com/<?= e($community['slug']) ?></p>
            <?php if ($community['short_bio']): ?>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-relaxed"><?= e($community['short_bio']) ?></p>
            <?php endif; ?>

            <!-- Links -->
            <?php if (!empty($community_links)): ?>
              <div class="space-y-1 mb-3">
                <?php foreach ($community_links as $link): ?>
                  <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener"
                     class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <?= e($link['name'] ?: $link['url']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="border-t border-gray-100 dark:border-white/10 pt-3 mb-3">
              <div class="flex justify-between text-xs text-gray-500 dark:text-gray-500">
                <span><strong class="text-gray-700 dark:text-gray-300"><?= (int)($member_count_approved['cnt'] ?? 0) ?></strong> Members</span>
                <span><strong class="text-gray-700 dark:text-gray-300"><?= (int)($admin_count['cnt'] ?? 0) ?></strong> Admins</span>
              </div>
              <?php if (!empty($admin_members)): ?>
                <div class="flex items-center mt-2">
                  <?php foreach (array_slice($admin_members, 0, 5) as $admin): ?>
                    <img src="<?= get_avatar_url($admin['avatar'] ?? null, ($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?>"
                         title="<?= e($admin['first_name'] ?? '') ?>"
                         class="w-6 h-6 rounded-full border-2 border-white dark:border-[#1a1a1a] -ml-1 first:ml-0 object-cover">
                  <?php endforeach; ?>
                  <?php if (count($admin_members) > 5): ?>
                    <span class="text-xs text-gray-500 dark:text-gray-500 ml-2">+<?= count($admin_members) - 5 ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Join/Member button -->
            <?php if (!$is_approved): ?>
              <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
                <div class="w-full text-center py-2.5 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 text-sm font-semibold border border-amber-200 dark:border-amber-800/30">Request Pending</div>
              <?php elseif ($current_user): ?>
                <button onclick="joinCommunity(<?= $community_id ?>)"
                        class="w-full py-2.5 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-sm font-semibold hover:shadow-lg hover:shadow-primary-900/30 transition-all">
                  <?= $community['pricing'] === 'paid' ? 'Join &middot; ' . format_price($community['price'], $community['price_interval'] ?? '') : 'Join Community' ?>
                </button>
              <?php else: ?>
                <a href="/login.php" class="block w-full text-center py-2.5 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-sm font-semibold">Sign In to Join</a>
              <?php endif; ?>
            <?php else: ?>
              <div class="w-full text-center py-2 text-xs text-primary-600 dark:text-primary-400 font-medium">
                Member<?= $is_admin ? ' &middot; ' . ucfirst((string)$my_role) : '' ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Your Stats (if member) -->
        <?php if ($is_approved && $current_user): ?>
          <?php
          $my_points = get_user_points_in_community($current_user['id'], $community_id);
          $my_streak = db_fetch('SELECT * FROM user_streaks WHERE user_id = ? AND community_id = ?', [$current_user['id'], $community_id]);
          ?>
          <div class="bg-gradient-to-br from-primary-600 to-accent-500 rounded-2xl p-4 text-white shadow-airbnb">
            <h4 class="text-sm font-bold mb-3 opacity-90">Your Stats</h4>
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-white/10 rounded-xl p-3 text-center">
                <div class="text-xl font-black"><?= number_format($my_points) ?></div>
                <div class="text-xs opacity-70 mt-0.5">XP Points</div>
              </div>
              <div class="bg-white/10 rounded-xl p-3 text-center">
                <div class="text-xl font-black"><?= $my_streak ? $my_streak['current_streak'] : 0 ?></div>
                <div class="text-xs opacity-70 mt-0.5">Day Streak</div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<!-- Add Topic Modal -->
<div id="add-topic-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
  <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl shadow-airbnb-lg w-full max-w-sm p-6 border border-gray-200 dark:border-white/10">
    <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Add Topic</h3>
    <input type="text" id="new-topic-name" placeholder="Topic name..."
           class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 mb-4">
    <div class="flex justify-end gap-3">
      <button onclick="document.getElementById('add-topic-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 transition-colors">Cancel</button>
      <button onclick="addTopic(<?= $community_id ?>)" class="px-5 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold">Add</button>
    </div>
  </div>
</div>

<!-- Award Points Modal -->
<div id="award-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
  <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl shadow-airbnb-lg w-full max-w-sm p-6 border border-gray-200 dark:border-white/10">
    <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Award Points</h3>
    <input type="hidden" id="award-user-id">
    <input type="hidden" id="award-community-id">
    <input type="number" id="award-points" placeholder="Points to award..." min="1" max="1000"
           class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 mb-2">
    <input type="text" id="award-reason" placeholder="Reason (optional)..."
           class="w-full bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 mb-4">
    <div class="flex justify-end gap-3">
      <button onclick="document.getElementById('award-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 transition-colors">Cancel</button>
      <button onclick="submitAwardPoints()" class="px-5 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold">Award</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '<?= csrf_token() ?>';

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
  tempDiv.innerHTML = `<div class="w-7 h-7 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex-shrink-0"></div>
    <div class="flex-1 bg-gray-50 dark:bg-[#2a2a2a] rounded-xl px-3 py-2">
      <div class="flex items-baseline gap-2"><span class="text-xs font-semibold text-gray-900 dark:text-white">You</span><span class="text-xs text-gray-400">just now</span></div>
      <p class="text-xs text-gray-700 dark:text-gray-300 mt-0.5">${content.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>
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

function approveMember(membershipId, status) {
  fetch('/api/approve_member.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({membership_id: membershipId, status, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast(status === 'approved' ? 'Member approved!' : 'Request rejected!'); setTimeout(() => location.reload(), 600); }
    else showToast(data.error || 'Error', 'error');
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
  const adminInput = document.getElementById('admin-new-topic');
  const modalInput = document.getElementById('new-topic-name');
  const name = (adminInput ? adminInput.value : (modalInput ? modalInput.value : '')).trim();
  if (!name) return;
  fetch('/api/post_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'add_topic', community_id: communityId, name, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Topic added!'); setTimeout(() => location.reload(), 500); }
    else showToast(data.error || 'Error', 'error');
  });
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

function adminAwardPoints(event, communityId) {
  event.preventDefault();
  const userId = document.getElementById('admin-award-user').value;
  const points = document.getElementById('admin-award-points-val').value;
  const reason = document.getElementById('admin-award-reason').value;
  if (!points || points < 1) { showToast('Enter valid points', 'error'); return; }
  fetch('/api/award_points.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({user_id: userId, community_id: communityId, points, reason, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Points awarded!'); document.getElementById('admin-award-points-val').value = ''; document.getElementById('admin-award-reason').value = ''; }
    else showToast(data.error || 'Error', 'error');
  });
}

function createBadge(event, communityId) {
  event.preventDefault();
  const name = document.getElementById('badge-name').value.trim();
  const icon = document.getElementById('badge-icon').value.trim() || '🏅';
  const description = document.getElementById('badge-description').value.trim();
  const points_required = parseInt(document.getElementById('badge-points').value) || 0;
  const badge_type = document.getElementById('badge-type').value;
  if (!name) { showToast('Badge name required', 'error'); return; }
  fetch('/api/manage_badges.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'create_badge', community_id: communityId, name, icon, description, points_required, badge_type, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Badge created!'); setTimeout(() => location.reload(), 500); }
    else showToast(data.error || 'Error', 'error');
  });
}

function openAwardBadgeModal(badgeId, badgeName) {
  document.getElementById('award-badge-id').value = badgeId;
  document.getElementById('award-badge-name').textContent = badgeName;
  document.getElementById('award-badge-modal').classList.remove('hidden');
}

function submitAwardBadge(communityId) {
  const badgeId = document.getElementById('award-badge-id').value;
  const userId = document.getElementById('award-badge-user').value;
  fetch('/api/manage_badges.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'award_badge', community_id: communityId, badge_id: badgeId, user_id: userId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Badge awarded!'); document.getElementById('award-badge-modal').classList.add('hidden'); }
    else showToast(data.error || 'Error', 'error');
  });
}

function deleteBadge(badgeId, communityId) {
  if (!confirm('Delete this badge?')) return;
  fetch('/api/manage_badges.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'delete_badge', community_id: communityId, badge_id: badgeId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Badge deleted!'); setTimeout(() => location.reload(), 500); }
    else showToast(data.error || 'Error', 'error');
  });
}

function deleteTopic(topicId, communityId) {
  if (!confirm('Delete this topic?')) return;
  fetch('/api/post_action.php', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'delete_topic', topic_id: topicId, community_id: communityId, csrf_token: CSRF_TOKEN})
  }).then(r => r.json()).then(data => {
    if (data.success) { showToast('Topic deleted!'); setTimeout(() => location.reload(), 500); }
    else showToast(data.error || 'Error', 'error');
  });
}
</script>
