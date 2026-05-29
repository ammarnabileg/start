<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$username = $_GET['username'] ?? '';
if (!$username) { header('Location: /index.php'); exit; }

$profile_user = db_fetch('SELECT * FROM users WHERE username = ?', [$username]);
if (!$profile_user) { http_response_code(404); die('<h1>User not found</h1>'); }

$current_user = get_auth_user();
$is_own_profile = $current_user && $current_user['id'] === $profile_user['id'];

// Follow status
$is_following = false;
if ($current_user && !$is_own_profile) {
    $follow = db_fetch('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?', [$current_user['id'], $profile_user['id']]);
    $is_following = (bool)$follow;
}

// Stats
$follower_count = db_fetch('SELECT COUNT(*) as cnt FROM follows WHERE following_id = ?', [$profile_user['id']]);
$following_count = db_fetch('SELECT COUNT(*) as cnt FROM follows WHERE follower_id = ?', [$profile_user['id']]);
$post_count = db_fetch('SELECT COUNT(*) as cnt FROM posts WHERE user_id = ?', [$profile_user['id']]);
$owned_communities = db_fetch_all('SELECT * FROM communities WHERE owner_id = ? AND is_active = 1 ORDER BY created_at DESC', [$profile_user['id']]);
$memberships = db_fetch_all(
    'SELECT c.*, m.role, m.joined_at FROM memberships m JOIN communities c ON c.id = m.community_id WHERE m.user_id = ? AND m.status = "approved" ORDER BY m.joined_at DESC',
    [$profile_user['id']]
);
$user_links = db_fetch_all('SELECT * FROM user_links WHERE user_id = ? ORDER BY sort_order', [$profile_user['id']]);
$user_badges = db_fetch_all(
    'SELECT b.*, ub.awarded_at, co.name as community_name FROM user_badges ub JOIN badges b ON b.id = ub.badge_id LEFT JOIN communities co ON co.id = ub.community_id WHERE ub.user_id = ? ORDER BY ub.awarded_at DESC',
    [$profile_user['id']]
);

$tab = $_GET['tab'] ?? 'communities';
$page_title = ($profile_user['first_name'] ?: $profile_user['username']) . "'s Profile";
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex gap-6 flex-col lg:flex-row">

    <!-- Main Content -->
    <div class="flex-1 min-w-0">
      <!-- Profile Header Card -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm mb-5">
        <!-- Banner -->
        <div class="h-36 relative overflow-hidden bg-gradient-to-br from-primary-700 via-primary-600 to-accent-500">
          <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.4),transparent)]"></div>
        </div>
        <!-- Avatar + Info -->
        <div class="px-6 pb-5 -mt-10">
          <div class="flex items-end justify-between gap-4 flex-wrap">
            <div class="flex items-end gap-4">
              <img src="<?= get_avatar_url($profile_user['avatar'], ($profile_user['first_name'] ?? '') . ' ' . ($profile_user['last_name'] ?? ''), 96) ?>"
                class="w-20 h-20 rounded-2xl border-3 border-white dark:border-gray-800 shadow-xl object-cover">
              <div class="pb-1">
                <h1 class="text-xl font-black text-gray-900 dark:text-white"><?= e(trim(($profile_user['first_name'] ?? '') . ' ' . ($profile_user['last_name'] ?? '')) ?: $profile_user['username']) ?></h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm">@<?= e($profile_user['username']) ?></p>
              </div>
            </div>
            <div class="flex items-center gap-2 pb-1">
              <?php if ($is_own_profile): ?>
                <a href="/settings.php" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">Edit Profile</a>
              <?php elseif ($current_user): ?>
                <button onclick="toggleFollow(<?= $profile_user['id'] ?>)" id="follow-btn"
                  class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?= $is_following ? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400' : 'bg-gradient-to-r from-primary-600 to-accent-500 text-white hover:shadow-md hover:-translate-y-0.5' ?>">
                  <?= $is_following ? 'Following' : 'Follow' ?>
                </button>
              <?php else: ?>
                <a href="/login.php" class="px-4 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold">Follow</a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Bio -->
          <?php if ($profile_user['bio']): ?>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-3 leading-relaxed"><?= nl2br(e($profile_user['bio'])) ?></p>
          <?php endif; ?>

          <!-- Meta Info -->
          <div class="flex items-center gap-4 mt-3 flex-wrap">
            <?php if ($profile_user['location']): ?>
              <span class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?= e($profile_user['location']) ?>
              </span>
            <?php endif; ?>
            <span class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              Joined <?= date('F Y', strtotime($profile_user['created_at'])) ?>
            </span>
          </div>

          <!-- Stats Row -->
          <div class="flex items-center gap-6 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
            <div class="text-center cursor-pointer hover:text-primary-600 dark:hover:text-primary-400">
              <div class="font-black text-gray-900 dark:text-white"><?= (int)($post_count['cnt'] ?? 0) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Posts</div>
            </div>
            <div class="text-center cursor-pointer hover:text-primary-600 dark:hover:text-primary-400">
              <div class="font-black text-gray-900 dark:text-white"><?= (int)($follower_count['cnt'] ?? 0) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Followers</div>
            </div>
            <div class="text-center cursor-pointer hover:text-primary-600 dark:hover:text-primary-400">
              <div class="font-black text-gray-900 dark:text-white"><?= (int)($following_count['cnt'] ?? 0) ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Following</div>
            </div>
            <?php if (!empty($user_badges)): ?>
              <div class="text-center cursor-pointer hover:text-primary-600 dark:hover:text-primary-400">
                <div class="font-black text-gray-900 dark:text-white"><?= count($user_badges) ?></div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Badges</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tabs -->
        <div class="px-6 border-t border-gray-100 dark:border-gray-700">
          <nav class="flex gap-1 py-2 overflow-x-auto">
            <?php foreach (['communities' => 'Communities', 'memberships' => 'Memberships', 'about' => 'About'] as $t => $label): ?>
              <a href="?username=<?= e($username) ?>&tab=<?= $t ?>"
                class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all whitespace-nowrap <?= $tab === $t ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
                <?= $label ?>
              </a>
            <?php endforeach; ?>
          </nav>
        </div>
      </div>

      <!-- Tab Content -->
      <?php if ($tab === 'communities'): ?>
        <?php if (empty($owned_communities)): ?>
          <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-12 text-center shadow-sm">
            <div class="text-4xl mb-3">🏘️</div>
            <h3 class="font-bold text-gray-700 dark:text-gray-300">No communities yet</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?= $is_own_profile ? "You haven't created any communities yet." : "This user hasn't created any communities yet." ?></p>
            <?php if ($is_own_profile): ?>
              <a href="/create-community.php" class="mt-4 inline-block px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Create Community</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($owned_communities as $c): ?>
              <a href="/community.php?slug=<?= e($c['slug']) ?>"
                class="group bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all">
                <div class="h-24 relative bg-gradient-to-br from-primary-600 to-accent-500 overflow-hidden">
                  <?php if ($c['banner']): ?><img src="<?= e($c['banner']) ?>" class="w-full h-full object-cover"><?php endif; ?>
                </div>
                <div class="p-4 -mt-5">
                  <?php if ($c['logo']): ?>
                    <img src="<?= e($c['logo']) ?>" class="w-10 h-10 rounded-xl border-2 border-white dark:border-gray-800 shadow-md object-cover mb-2">
                  <?php else: ?>
                    <div class="w-10 h-10 rounded-xl border-2 border-white dark:border-gray-800 shadow-md bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black mb-2"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                  <?php endif; ?>
                  <h3 class="font-bold text-sm text-gray-900 dark:text-white line-clamp-1"><?= e($c['name']) ?></h3>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= format_member_count($c['member_count']) ?> members • <?= ucfirst($c['pricing']) ?></p>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php elseif ($tab === 'memberships'): ?>
        <?php if (empty($memberships)): ?>
          <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-12 text-center shadow-sm">
            <div class="text-4xl mb-3">🤝</div>
            <h3 class="font-bold text-gray-700 dark:text-gray-300">No memberships yet</h3>
          </div>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($memberships as $c): ?>
              <a href="/community.php?slug=<?= e($c['slug']) ?>"
                class="group bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 hover:shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-3">
                <?php if ($c['logo']): ?>
                  <img src="<?= e($c['logo']) ?>" class="w-12 h-12 rounded-xl object-cover flex-shrink-0">
                <?php else: ?>
                  <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-lg flex-shrink-0"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                <?php endif; ?>
                <div class="min-w-0 flex-1">
                  <div class="font-bold text-sm text-gray-900 dark:text-white line-clamp-1"><?= e($c['name']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                    <span class="capitalize"><?= $c['role'] ?></span>
                    <span>•</span>
                    <span>Joined <?= date('M Y', strtotime($c['joined_at'])) ?></span>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php elseif ($tab === 'about'): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
          <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white">About</h3>
          <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed"><?= nl2br(e($profile_user['bio'] ?: 'No bio provided.')) ?></p>
          <?php if (!empty($user_badges)): ?>
            <h4 class="font-bold mt-6 mb-3 text-gray-900 dark:text-white">Badges</h4>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($user_badges as $b): ?>
                <div class="flex items-center gap-2 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 px-3 py-1.5 rounded-xl text-sm" title="<?= e($b['description'] ?? '') ?>">
                  <?= $b['icon'] ?? '🏅' ?> <?= e($b['name']) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right Sidebar -->
    <div class="lg:w-64 flex-shrink-0">
      <div class="sidebar-sticky space-y-4">
        <!-- Quick Stats -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
          <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Quick Stats</h4>
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Communities</span>
              <span class="font-semibold text-gray-900 dark:text-white"><?= count($owned_communities) ?></span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Memberships</span>
              <span class="font-semibold text-gray-900 dark:text-white"><?= count($memberships) ?></span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Posts</span>
              <span class="font-semibold text-gray-900 dark:text-white"><?= (int)($post_count['cnt'] ?? 0) ?></span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Badges</span>
              <span class="font-semibold text-gray-900 dark:text-white"><?= count($user_badges) ?></span>
            </div>
          </div>
        </div>

        <!-- Social Links -->
        <?php if (!empty($user_links)): ?>
          <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Links</h4>
            <div class="space-y-2">
              <?php foreach ($user_links as $link): ?>
                <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener"
                  class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                  <?= e($link['name'] ?: $link['url']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Badges Preview -->
        <?php if (!empty($user_badges)): ?>
          <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 shadow-sm">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Recent Badges</h4>
            <div class="flex flex-wrap gap-1.5">
              <?php foreach (array_slice($user_badges, 0, 8) as $b): ?>
                <div title="<?= e($b['name']) . ': ' . e($b['description'] ?? '') ?>"
                  class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-50 to-accent-50 dark:from-primary-900/30 dark:to-accent-900/30 border border-primary-100 dark:border-primary-800/50 flex items-center justify-center text-lg cursor-default">
                  <?= $b['icon'] ?? '🏅' ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

function toggleFollow(userId) {
  fetch('/api/settings_save.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'toggle_follow', user_id: userId, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const btn = document.getElementById('follow-btn');
      if (data.following) {
        btn.textContent = 'Following';
        btn.className = btn.className.replace('from-primary-600 to-accent-500 text-white hover:shadow-md hover:-translate-y-0.5', 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400');
      } else {
        btn.textContent = 'Follow';
        btn.className = btn.className.replace('bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400', 'from-primary-600 to-accent-500 text-white hover:shadow-md hover:-translate-y-0.5');
      }
      showToast(data.message);
    }
  });
}
</script>
