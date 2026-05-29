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

// Get membership status
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

// Default tab: non-members see 'about', approved members see 'community'
$default_tab = $is_approved ? 'community' : 'about';
$tab = $_GET['tab'] ?? $default_tab;
$valid_tabs = ['community', 'classroom', 'members', 'leaderboard', 'about'];
if (!in_array($tab, $valid_tabs)) $tab = $default_tab;

// Sidebar data
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

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex gap-6 flex-col lg:flex-row">

    <!-- ============ MAIN CONTENT ============ -->
    <div class="flex-1 min-w-0">

      <!-- Community Header Bar -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 mb-5 overflow-hidden shadow-sm">
        <div class="h-32 relative overflow-hidden bg-gradient-to-br from-primary-700 to-accent-500">
          <?php if ($community['banner']): ?>
            <img src="<?= e($community['banner']) ?>" alt="" class="w-full h-full object-cover">
          <?php endif; ?>
          <div class="absolute inset-0 bg-black/20"></div>
        </div>
        <div class="px-6 pb-4 -mt-8 flex items-end justify-between gap-4 flex-wrap">
          <div class="flex items-end gap-3">
            <?php if ($community['logo']): ?>
              <img src="<?= e($community['logo']) ?>" alt="" class="w-16 h-16 rounded-2xl border-3 border-white dark:border-gray-800 shadow-lg object-cover bg-white">
            <?php else: ?>
              <div class="w-16 h-16 rounded-2xl border-3 border-white dark:border-gray-800 shadow-lg bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-2xl">
                <?= strtoupper(substr($community['name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div class="pb-1">
              <h1 class="text-xl font-black text-gray-900 dark:text-white"><?= e($community['name']) ?></h1>
              <p class="text-sm text-gray-500 dark:text-gray-400">discover.com/<?= e($community['slug']) ?></p>
            </div>
          </div>
          <!-- Tab Navigation -->
          <div class="w-full mt-3">
            <nav class="flex gap-1 overflow-x-auto">
              <?php foreach (['community'=>'Community','classroom'=>'Classroom','members'=>'Members','leaderboard'=>'Leaderboard','about'=>'About'] as $t => $label): ?>
                <a href="?slug=<?= e($slug) ?>&tab=<?= $t ?>"
                  class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all whitespace-nowrap
                  <?= $tab === $t ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                  <?= $label ?>
                </a>
              <?php endforeach; ?>
            </nav>
          </div>
        </div>
      </div>

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

        // User liked posts
        $liked_posts = [];
        if ($current_user && $is_approved) {
            $likes = db_fetch_all('SELECT post_id FROM post_likes WHERE user_id = ?', [$current_user['id']]);
            $liked_posts = array_column($likes, 'post_id');
        }
        ?>

        <?php if (!$is_approved && $community['type'] === 'public'): ?>
          <!-- Join prompt for non-members -->
          <div class="bg-gradient-to-br from-primary-50 to-accent-50 dark:from-primary-900/20 dark:to-accent-900/20 rounded-2xl border border-primary-100 dark:border-primary-800 p-8 text-center mb-5">
            <div class="text-4xl mb-3">🔐</div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Join to see the community feed</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">Become a member to post, comment, and interact with the community.</p>
            <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
              <span class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-100 text-amber-600 rounded-xl text-sm font-semibold">
                ⏳ Request Pending Approval
              </span>
            <?php elseif ($current_user): ?>
              <button onclick="joinCommunity(<?= $community_id ?>)"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-lg transition-all hover:-translate-y-0.5">
                Join Community
              </button>
            <?php else: ?>
              <a href="/login.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold">Sign In to Join</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($is_approved): ?>
        <div class="flex gap-4">
          <!-- Topics sidebar -->
          <div class="w-40 flex-shrink-0">
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-3 sticky top-20">
              <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 px-2">Topics</h4>
              <a href="?slug=<?= e($slug) ?>&tab=community"
                class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm transition-all <?= !$topic_id ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                All Posts
              </a>
              <?php foreach ($topics as $t): ?>
                <a href="?slug=<?= e($slug) ?>&tab=community&topic=<?= $t['id'] ?>"
                  class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm transition-all <?= $topic_id === (int)$t['id'] ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
                  # <?= e($t['name']) ?>
                </a>
              <?php endforeach; ?>
              <?php if ($is_admin): ?>
                <button onclick="showAddTopic()" class="flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all mt-1 w-full">
                  + Add Topic
                </button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Posts Area -->
          <div class="flex-1 min-w-0">
            <!-- Create Post -->
            <?php if ($current_user): ?>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 mb-4 shadow-sm">
              <div class="flex items-start gap-3">
                <img src="<?= get_avatar_url($current_user['avatar'], $current_user['first_name'] . ' ' . $current_user['last_name']) ?>"
                  class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                <div class="flex-1">
                  <textarea id="post-content" placeholder="Write something to the community..." rows="2"
                    class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none placeholder-gray-400 dark:placeholder-gray-500 dark:text-gray-200"
                    onfocus="this.rows=4" onblur="if(!this.value)this.rows=2"></textarea>
                  <div id="post-options" class="mt-2 flex items-center justify-between">
                    <select id="post-topic" class="text-xs border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 focus:outline-none focus:ring-1 focus:ring-primary-500">
                      <option value="">No topic</option>
                      <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $topic_id === (int)$t['id'] ? 'selected' : '' ?>># <?= e($t['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button onclick="createPost(<?= $community_id ?>)"
                      class="px-4 py-1.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-xs font-semibold hover:shadow-md transition-all">
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
              <div class="bg-white dark:bg-gray-800 rounded-2xl border border-amber-200 dark:border-amber-800/50 p-5 mb-3 shadow-sm relative">
                <div class="absolute top-3 right-3 flex items-center gap-1">
                  <span class="text-xs bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded-full font-medium flex items-center gap-1">📌 Pinned</span>
                  <?php if ($is_owner): ?>
                    <button onclick="togglePostMenu(<?= $post['id'] ?>)" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all">⋮</button>
                    <div id="post-menu-<?= $post['id'] ?>" class="hidden absolute right-0 top-8 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-10 py-1 w-36">
                      <button onclick="pinPost(<?= $post['id'] ?>, false)" class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700">📌 Unpin</button>
                      <button onclick="deletePost(<?= $post['id'] ?>)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">🗑 Delete</button>
                    </div>
                  <?php endif; ?>
                </div>
                <?php include __DIR__ . '/includes/post_card.php'; ?>
              </div>
            <?php endforeach; ?>

            <!-- Regular Posts -->
            <?php if (empty($posts) && empty($pinned_posts)): ?>
              <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                <div class="text-5xl mb-3">💬</div>
                <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-2">No posts yet</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Be the first to post something!</p>
              </div>
            <?php else: ?>
              <?php foreach ($posts as $post): ?>
                <?php $liked = in_array($post['id'], $liked_posts); ?>
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5 mb-3 shadow-sm relative" id="post-<?= $post['id'] ?>">
                  <?php if ($is_owner): ?>
                    <div class="absolute top-3 right-3">
                      <button onclick="togglePostMenu(<?= $post['id'] ?>)" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all text-gray-400">⋮</button>
                      <div id="post-menu-<?= $post['id'] ?>" class="hidden absolute right-0 top-8 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg z-10 py-1 w-36">
                        <button onclick="pinPost(<?= $post['id'] ?>, true)" class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 dark:hover:bg-gray-700">📌 Pin Post</button>
                        <button onclick="deletePost(<?= $post['id'] ?>)" class="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">🗑 Delete</button>
                      </div>
                    </div>
                  <?php elseif ($current_user && $post['user_id'] == $current_user['id']): ?>
                    <div class="absolute top-3 right-3">
                      <button onclick="deletePost(<?= $post['id'] ?>)" class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all text-gray-400 hover:text-red-500 text-xs">🗑</button>
                    </div>
                  <?php endif; ?>
                  <?php include __DIR__ . '/includes/post_card.php'; ?>
                </div>
              <?php endforeach; ?>

              <!-- Pagination -->
              <?php if ($total_post_pages > 1): ?>
                <div class="flex justify-center gap-2 mt-4">
                  <?php for ($i = 1; $i <= $total_post_pages; $i++): ?>
                    <a href="?slug=<?= e($slug) ?>&tab=community&ppage=<?= $i ?><?= $topic_id ? '&topic='.$topic_id : '' ?>"
                      class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-medium <?= $i === $post_page ? 'bg-primary-600 text-white' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' ?>">
                      <?= $i ?>
                    </a>
                  <?php endfor; ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

      <!-- ====== TAB: CLASSROOM ====== -->
      <?php elseif ($tab === 'classroom'): ?>
        <?php
        $courses = db_fetch_all(
            'SELECT * FROM courses WHERE community_id = ? AND is_published = 1 ORDER BY sort_order, created_at',
            [$community_id]
        );
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Classroom</h2>
            <?php if ($is_admin): ?>
              <button onclick="alert('Course creation coming soon!')" class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Course
              </button>
            <?php endif; ?>
          </div>
          <?php if (empty($courses)): ?>
            <div class="text-center py-16">
              <div class="text-5xl mb-3">📚</div>
              <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-2">No courses yet</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400">The community hasn't added any courses yet.</p>
            </div>
          <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
              <?php foreach ($courses as $course): ?>
                <?php
                $progress = ['percent' => 0, 'completed' => 0, 'total' => 0];
                if ($current_user) $progress = get_course_progress($current_user['id'], $course['id']);
                $section_count = db_fetch('SELECT COUNT(*) as cnt FROM course_sections WHERE course_id = ?', [$course['id']]);
                $lesson_count = db_fetch('SELECT COUNT(*) as cnt FROM lessons l JOIN course_sections cs ON cs.id = l.section_id WHERE cs.course_id = ?', [$course['id']]);
                ?>
                <div class="group bg-gray-50 dark:bg-gray-700/50 rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-600 hover:shadow-lg hover:-translate-y-1 transition-all">
                  <div class="h-36 overflow-hidden relative">
                    <?php if ($course['thumbnail']): ?>
                      <img src="<?= e($course['thumbnail']) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                      <div class="w-full h-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-5xl">📘</div>
                    <?php endif; ?>
                    <?php if ($course['pricing'] === 'paid'): ?>
                      <div class="absolute top-2 right-2 bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= format_price($course['price']) ?></div>
                    <?php else: ?>
                      <div class="absolute top-2 right-2 bg-green-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">Free</div>
                    <?php endif; ?>
                  </div>
                  <div class="p-4">
                    <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-1 line-clamp-2"><?= e($course['title']) ?></h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3"><?= (int)($section_count['cnt'] ?? 0) ?> sections • <?= (int)($lesson_count['cnt'] ?? 0) ?> lessons</p>
                    <?php if ($current_user && $is_approved && $progress['total'] > 0): ?>
                      <div class="mb-3">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                          <span>Progress</span>
                          <span><?= $progress['percent'] ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5">
                          <div class="bg-gradient-to-r from-primary-500 to-accent-500 h-1.5 rounded-full transition-all" style="width:<?= $progress['percent'] ?>%"></div>
                        </div>
                      </div>
                    <?php endif; ?>
                    <a href="/course.php?id=<?= $course['id'] ?>"
                      class="block w-full text-center py-2 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-xs font-semibold hover:shadow-md transition-all hover:-translate-y-0.5">
                      <?= $progress['completed'] > 0 && $progress['percent'] < 100 ? 'Continue' : ($progress['percent'] === 100 ? '✓ Completed' : 'Start') ?>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

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
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
          <!-- Stats + Invite -->
          <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-4">
              <div class="text-center">
                <div class="text-xl font-black text-gray-900 dark:text-white"><?= count($all_members) ?></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Members</div>
              </div>
              <div class="w-px h-8 bg-gray-200 dark:bg-gray-600"></div>
              <div class="text-center">
                <div class="text-xl font-black text-gray-900 dark:text-white"><?= count(array_filter($all_members, fn($m) => in_array($m['role'], ['admin','owner']))) ?></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Admins</div>
              </div>
            </div>
            <?php if ($is_approved): ?>
              <button onclick="document.getElementById('invite-modal').classList.remove('hidden')"
                class="flex items-center gap-2 px-4 py-2 bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-xl text-sm font-semibold hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Invite Members
              </button>
            <?php endif; ?>
          </div>

          <!-- Pending requests (admin only) -->
          <?php if (!empty($pending_members)): ?>
            <div class="mb-6">
              <h3 class="font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                Pending Requests
                <span class="bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($pending_members) ?></span>
              </h3>
              <div class="space-y-2">
                <?php foreach ($pending_members as $mem): ?>
                  <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800/50">
                    <div class="flex items-center gap-3">
                      <img src="<?= get_avatar_url($mem['avatar'], $mem['first_name'] . ' ' . $mem['last_name']) ?>" class="w-9 h-9 rounded-full object-cover">
                      <div>
                        <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= e(trim($mem['first_name'] . ' ' . $mem['last_name'])) ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">@<?= e($mem['username']) ?></div>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <button onclick="approveMember(<?= $mem['membership_id'] ?>, 'approved')" class="px-3 py-1.5 bg-green-500 text-white rounded-lg text-xs font-semibold hover:bg-green-600 transition-all">Approve</button>
                      <button onclick="approveMember(<?= $mem['membership_id'] ?>, 'rejected')" class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-200 dark:hover:bg-red-900/50 transition-all">Reject</button>
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
                class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all border border-transparent hover:border-gray-100 dark:hover:border-gray-600">
                <div class="relative flex-shrink-0">
                  <img src="<?= get_avatar_url($mem['avatar'], $mem['first_name'] . ' ' . $mem['last_name']) ?>" class="w-10 h-10 rounded-full object-cover">
                  <?php if ($mem['role'] === 'owner'): ?>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-yellow-400 rounded-full flex items-center justify-center text-xs">👑</div>
                  <?php elseif ($mem['role'] === 'admin'): ?>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-primary-500 rounded-full flex items-center justify-center text-xs text-white font-bold text-xs leading-none">A</div>
                  <?php endif; ?>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-semibold text-sm text-gray-900 dark:text-white truncate"><?= e(trim($mem['first_name'] . ' ' . $mem['last_name']) ?: $mem['username']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 truncate">@<?= e($mem['username']) ?></div>
                  <?php if ($mem['bio']): ?>
                    <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 mt-0.5"><?= e($mem['bio']) ?></div>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Invite Modal -->
        <div id="invite-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold text-lg text-gray-900 dark:text-white">Invite to Community</h3>
              <button onclick="document.getElementById('invite-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Share this link to invite people:</p>
            <div class="flex gap-2">
              <input id="invite-link" type="text" readonly value="<?= e($referral_link) ?>"
                class="flex-1 px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
              <button onclick="copyInviteLink()" class="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Copy</button>
            </div>
          </div>
        </div>

      <!-- ====== TAB: LEADERBOARD ====== -->
      <?php elseif ($tab === 'leaderboard'): ?>
        <?php
        $leaderboard = get_community_leaderboard($community_id, 50);
        ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Leaderboard</h2>

          <!-- Top 3 Podium -->
          <?php if (count($leaderboard) >= 3): ?>
          <div class="flex items-end justify-center gap-3 mb-8 p-6 bg-gradient-to-br from-primary-50 to-accent-50 dark:from-primary-900/20 dark:to-accent-900/20 rounded-2xl">
            <!-- 2nd Place -->
            <div class="text-center flex-1">
              <div class="relative inline-block mb-2">
                <img src="<?= get_avatar_url($leaderboard[1]['avatar'] ?? null, ($leaderboard[1]['first_name'] ?? '') . ' ' . ($leaderboard[1]['last_name'] ?? '')) ?>"
                  class="w-14 h-14 rounded-full mx-auto border-2 border-gray-400 shadow-md">
                <div class="absolute -top-2 -right-1 w-6 h-6 bg-gray-400 rounded-full flex items-center justify-center text-white font-black text-xs">2</div>
              </div>
              <div class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate max-w-20 mx-auto"><?= e($leaderboard[1]['first_name'] ?? $leaderboard[1]['username']) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($leaderboard[1]['total_points']) ?> XP</div>
              <div class="h-16 bg-gray-300 dark:bg-gray-600 rounded-t-xl mt-2 w-full flex items-center justify-center text-2xl">🥈</div>
            </div>
            <!-- 1st Place -->
            <div class="text-center flex-1">
              <div class="text-2xl mb-1">👑</div>
              <div class="relative inline-block mb-2">
                <img src="<?= get_avatar_url($leaderboard[0]['avatar'] ?? null, ($leaderboard[0]['first_name'] ?? '') . ' ' . ($leaderboard[0]['last_name'] ?? '')) ?>"
                  class="w-18 h-18 rounded-full mx-auto border-2 border-yellow-400 shadow-xl" style="width:72px;height:72px">
                <div class="absolute -top-2 -right-1 w-7 h-7 bg-yellow-400 rounded-full flex items-center justify-center text-white font-black text-xs">1</div>
              </div>
              <div class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-24 mx-auto"><?= e($leaderboard[0]['first_name'] ?? $leaderboard[0]['username']) ?></div>
              <div class="text-xs text-primary-600 dark:text-primary-400 font-semibold"><?= number_format($leaderboard[0]['total_points']) ?> XP</div>
              <div class="h-24 bg-yellow-400 rounded-t-xl mt-2 w-full flex items-center justify-center text-3xl">🥇</div>
            </div>
            <!-- 3rd Place -->
            <div class="text-center flex-1">
              <div class="relative inline-block mb-2">
                <img src="<?= get_avatar_url($leaderboard[2]['avatar'] ?? null, ($leaderboard[2]['first_name'] ?? '') . ' ' . ($leaderboard[2]['last_name'] ?? '')) ?>"
                  class="w-14 h-14 rounded-full mx-auto border-2 border-amber-600 shadow-md">
                <div class="absolute -top-2 -right-1 w-6 h-6 bg-amber-600 rounded-full flex items-center justify-center text-white font-black text-xs">3</div>
              </div>
              <div class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate max-w-20 mx-auto"><?= e($leaderboard[2]['first_name'] ?? $leaderboard[2]['username']) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($leaderboard[2]['total_points']) ?> XP</div>
              <div class="h-12 bg-amber-600 rounded-t-xl mt-2 w-full flex items-center justify-center text-2xl">🥉</div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Full Leaderboard List -->
          <div class="space-y-2">
            <?php foreach ($leaderboard as $i => $leader): ?>
              <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all <?= $current_user && $leader['id'] === $current_user['id'] ? 'bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800/50' : '' ?>">
                <div class="w-8 text-center">
                  <?php if ($i === 0): ?>
                    <span class="text-xl">🥇</span>
                  <?php elseif ($i === 1): ?>
                    <span class="text-xl">🥈</span>
                  <?php elseif ($i === 2): ?>
                    <span class="text-xl">🥉</span>
                  <?php else: ?>
                    <span class="text-sm font-bold text-gray-500 dark:text-gray-400">#<?= $i + 1 ?></span>
                  <?php endif; ?>
                </div>
                <img src="<?= get_avatar_url($leader['avatar'], ($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? '')) ?>"
                  class="w-9 h-9 rounded-full object-cover">
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= e(trim(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? '')) ?: $leader['username']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">@<?= e($leader['username']) ?></div>
                </div>
                <div class="text-center">
                  <div class="text-sm font-black text-primary-600 dark:text-primary-400"><?= number_format($leader['total_points']) ?></div>
                  <div class="text-xs text-gray-400 dark:text-gray-500">XP</div>
                </div>
                <div class="text-center hidden sm:block">
                  <div class="text-sm font-bold text-gray-700 dark:text-gray-300"><?= $leader['badge_count'] ?></div>
                  <div class="text-xs text-gray-400 dark:text-gray-500">Badges</div>
                </div>
                <?php if ($is_owner && $current_user && $leader['id'] !== $current_user['id']): ?>
                  <button onclick="awardPoints(<?= $leader['id'] ?>, <?= $community_id ?>)"
                    class="flex-shrink-0 px-3 py-1.5 bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-lg text-xs font-semibold hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all">
                    +XP
                  </button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      <!-- ====== TAB: ABOUT ====== -->
      <?php elseif ($tab === 'about'): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
          <!-- Stats Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-6 border-b border-gray-100 dark:border-gray-700">
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
              <div class="text-2xl mb-1"><?= $community['type'] === 'private' ? '🔒' : '🌐' ?></div>
              <div class="font-bold text-sm text-gray-900 dark:text-white capitalize"><?= $community['type'] ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Access</div>
            </div>
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
              <div class="text-2xl mb-1">👥</div>
              <div class="font-bold text-sm text-gray-900 dark:text-white"><?= format_member_count($community['member_count']) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Members</div>
            </div>
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
              <div class="text-2xl mb-1"><?= $community['pricing'] === 'free' ? '🆓' : '💰' ?></div>
              <div class="font-bold text-sm text-gray-900 dark:text-white"><?= $community['pricing'] === 'free' ? 'Free' : format_price($community['price'], $community['price_interval']) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Pricing</div>
            </div>
            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
              <div class="text-2xl mb-1">👤</div>
              <div class="font-bold text-sm text-gray-900 dark:text-white"><?= e($community['owner_first']) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Owner</div>
            </div>
          </div>

          <!-- Description -->
          <div class="p-6">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">About This Community</h3>
            <div class="prose dark:prose-invert text-gray-700 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-line">
              <?= e($community['description'] ?: $community['short_bio'] ?: 'No description provided.') ?>
            </div>

            <?php if (!$is_approved): ?>
              <div class="mt-6">
                <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
                  <div class="inline-flex items-center gap-2 px-6 py-3 bg-amber-100 text-amber-600 rounded-xl font-semibold">
                    ⏳ Membership Request Pending
                  </div>
                <?php elseif ($current_user): ?>
                  <button onclick="joinCommunity(<?= $community_id ?>)"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all hover:-translate-y-0.5">
                    <?= $community['pricing'] === 'paid' ? '💰 Join for ' . format_price($community['price'], $community['price_interval']) : '🚀 Join Community — Free' ?>
                  </button>
                <?php else: ?>
                  <a href="/login.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold">Sign In to Join</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ============ STICKY SIDEBAR ============ -->
    <div class="lg:w-72 flex-shrink-0">
      <div class="sidebar-sticky space-y-4">
        <!-- Community Info Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm">
          <!-- Mini banner -->
          <div class="h-20 relative bg-gradient-to-br from-primary-600 to-accent-500 overflow-hidden">
            <?php if ($community['banner']): ?>
              <img src="<?= e($community['banner']) ?>" alt="" class="w-full h-full object-cover">
            <?php endif; ?>
          </div>
          <div class="px-4 pb-4">
            <div class="-mt-6 mb-3">
              <?php if ($community['logo']): ?>
                <img src="<?= e($community['logo']) ?>" alt="" class="w-12 h-12 rounded-xl border-2 border-white dark:border-gray-800 shadow-md object-cover">
              <?php else: ?>
                <div class="w-12 h-12 rounded-xl border-2 border-white dark:border-gray-800 shadow-md bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-xl">
                  <?= strtoupper(substr($community['name'], 0, 1)) ?>
                </div>
              <?php endif; ?>
            </div>
            <h2 class="font-bold text-gray-900 dark:text-white mb-1"><?= e($community['name']) ?></h2>
            <p class="text-xs text-primary-600 dark:text-primary-400 mb-2">discover.com/<?= e($community['slug']) ?></p>
            <?php if ($community['short_bio']): ?>
              <p class="text-xs text-gray-600 dark:text-gray-400 mb-3"><?= e($community['short_bio']) ?></p>
            <?php endif; ?>

            <!-- Links -->
            <?php if (!empty($community_links)): ?>
              <div class="space-y-1 mb-3">
                <?php foreach ($community_links as $link): ?>
                  <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener"
                    class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <?= e($link['name'] ?: $link['url']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="border-t border-gray-100 dark:border-gray-700 pt-3 mb-3">
              <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span><strong class="text-gray-700 dark:text-gray-300"><?= (int)($member_count_approved['cnt'] ?? 0) ?></strong> Members</span>
                <span><strong class="text-gray-700 dark:text-gray-300"><?= (int)($admin_count['cnt'] ?? 0) ?></strong> Admins</span>
              </div>
              <!-- Admin avatars -->
              <?php if (!empty($admin_members)): ?>
                <div class="flex items-center gap-1 mt-2">
                  <?php foreach (array_slice($admin_members, 0, 5) as $admin): ?>
                    <img src="<?= get_avatar_url($admin['avatar'], $admin['first_name'] . ' ' . $admin['last_name']) ?>"
                      title="<?= e($admin['first_name']) ?>"
                      class="w-7 h-7 rounded-full border-2 border-white dark:border-gray-800 -ml-1 first:ml-0 object-cover">
                  <?php endforeach; ?>
                  <?php if (count($admin_members) > 5): ?>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">+<?= count($admin_members) - 5 ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Join Button -->
            <?php if (!$is_approved): ?>
              <?php if ($my_membership && $my_membership['status'] === 'pending'): ?>
                <div class="w-full text-center py-2.5 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 text-sm font-semibold">⏳ Request Pending</div>
              <?php elseif ($current_user): ?>
                <button onclick="joinCommunity(<?= $community_id ?>)"
                  class="w-full py-2.5 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-sm font-semibold hover:shadow-lg hover:shadow-primary-500/25 transition-all hover:-translate-y-0.5">
                  <?= $community['pricing'] === 'paid' ? 'Join · ' . format_price($community['price']) : 'Join Community' ?>
                </button>
              <?php else: ?>
                <a href="/login.php" class="block w-full text-center py-2.5 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-sm font-semibold">Sign In to Join</a>
              <?php endif; ?>
            <?php else: ?>
              <div class="w-full text-center py-2 text-xs text-primary-600 dark:text-primary-400 font-medium">✓ Member <?= $is_admin ? '(' . ucfirst($my_role) . ')' : '' ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Your Points (if member) -->
        <?php if ($is_approved && $current_user): ?>
          <?php $my_points = get_user_points_in_community($current_user['id'], $community_id); ?>
          <?php $my_streak = db_fetch('SELECT * FROM user_streaks WHERE user_id = ? AND community_id = ?', [$current_user['id'], $community_id]); ?>
          <div class="bg-gradient-to-br from-primary-600 to-accent-500 rounded-2xl p-4 text-white">
            <h4 class="text-sm font-bold mb-3 opacity-90">Your Stats</h4>
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-white/10 rounded-xl p-3 text-center backdrop-blur-sm">
                <div class="text-xl font-black"><?= number_format($my_points) ?></div>
                <div class="text-xs opacity-80">XP Points</div>
              </div>
              <div class="bg-white/10 rounded-xl p-3 text-center backdrop-blur-sm">
                <div class="text-xl font-black"><?= $my_streak ? $my_streak['current_streak'] : 0 ?>🔥</div>
                <div class="text-xs opacity-80">Day Streak</div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<!-- Add Topic Modal -->
<div id="add-topic-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
  <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
    <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Add Topic</h3>
    <input type="text" id="new-topic-name" placeholder="Topic name..."
      class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 mb-4 dark:text-white">
    <div class="flex justify-end gap-3">
      <button onclick="document.getElementById('add-topic-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">Cancel</button>
      <button onclick="addTopic(<?= $community_id ?>)" class="px-5 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Add</button>
    </div>
  </div>
</div>

<!-- Award Points Modal -->
<div id="award-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
  <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
    <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Award Points</h3>
    <input type="hidden" id="award-user-id">
    <input type="hidden" id="award-community-id">
    <input type="number" id="award-points" placeholder="Points to award..." min="1" max="1000"
      class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 mb-2 dark:text-white">
    <input type="text" id="award-reason" placeholder="Reason (optional)..."
      class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 mb-4 dark:text-white">
    <div class="flex justify-end gap-3">
      <button onclick="document.getElementById('award-modal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">Cancel</button>
      <button onclick="submitAwardPoints()" class="px-5 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Award</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

function joinCommunity(communityId) {
  fetch('/api/join_community.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({community_id: communityId, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showToast(data.message || 'Request submitted!');
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(data.error || 'Error', 'error');
    }
  });
}

function createPost(communityId) {
  const content = document.getElementById('post-content').value.trim();
  const topicId = document.getElementById('post-topic')?.value || '';
  if (!content) { showToast('Please write something!', 'error'); return; }
  fetch('/api/post_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'create', community_id: communityId, content, topic_id: topicId, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showToast('Post created!');
      setTimeout(() => location.reload(), 600);
    } else {
      showToast(data.error || 'Error creating post', 'error');
    }
  });
}

function toggleLike(postId, btn) {
  fetch('/api/post_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'like', post_id: postId, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const countEl = btn.querySelector('.like-count');
      if (countEl) countEl.textContent = data.like_count;
      btn.classList.toggle('text-red-500', data.liked);
      btn.classList.toggle('text-gray-500', !data.liked);
    }
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
  fetch('/api/post_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'comment', post_id: postId, community_id: communityId, content, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      input.value = '';
      showToast('Comment added!');
      setTimeout(() => location.reload(), 500);
    }
  });
}

function togglePostMenu(postId) {
  const menu = document.getElementById('post-menu-' + postId);
  if (menu) menu.classList.toggle('hidden');
}

function pinPost(postId, pin) {
  fetch('/api/post_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'pin', post_id: postId, pin: pin, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) { showToast(pin ? 'Post pinned!' : 'Post unpinned!'); setTimeout(() => location.reload(), 600); }
    else showToast(data.error || 'Error', 'error');
  });
}

function deletePost(postId) {
  if (!confirm('Delete this post?')) return;
  fetch('/api/post_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'delete', post_id: postId, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const el = document.getElementById('post-' + postId);
      if (el) el.remove();
      showToast('Post deleted!');
    }
  });
}

function approveMember(membershipId, status) {
  fetch('/api/approve_member.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({membership_id: membershipId, status, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
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
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({user_id: userId, community_id: communityId, points, reason, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) { showToast('Points awarded!'); document.getElementById('award-modal').classList.add('hidden'); setTimeout(() => location.reload(), 600); }
    else showToast(data.error || 'Error', 'error');
  });
}

function showAddTopic() {
  document.getElementById('add-topic-modal').classList.remove('hidden');
}

function addTopic(communityId) {
  const name = document.getElementById('new-topic-name').value.trim();
  if (!name) return;
  fetch('/api/post_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'add_topic', community_id: communityId, name, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) { showToast('Topic added!'); setTimeout(() => location.reload(), 500); }
    else showToast(data.error || 'Error', 'error');
  });
}

function copyInviteLink() {
  const link = document.getElementById('invite-link');
  link.select();
  navigator.clipboard.writeText(link.value).then(() => showToast('Link copied!'));
}
</script>
