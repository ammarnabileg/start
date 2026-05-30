<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$username = $_GET['username'] ?? '';
if (!$username) { header('Location: /index.php'); exit; }

$profile_user = db_fetch('SELECT * FROM users WHERE username = ?', [$username]);
if (!$profile_user) { http_response_code(404); die('<!DOCTYPE html><html><head><title>Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:50px"><h1>User Not Found</h1><p><a href="/index.php">Go Home</a></p></body></html>'); }

$current_user = get_auth_user();
$is_own_profile = $current_user && $current_user['id'] === $profile_user['id'];

$is_following = false;
if ($current_user && !$is_own_profile) {
    $follow = db_fetch('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?', [$current_user['id'], $profile_user['id']]);
    $is_following = (bool)$follow;
}

$follower_count  = db_fetch('SELECT COUNT(*) as cnt FROM follows WHERE following_id = ?', [$profile_user['id']]);
$following_count = db_fetch('SELECT COUNT(*) as cnt FROM follows WHERE follower_id = ?', [$profile_user['id']]);
$post_count      = db_fetch('SELECT COUNT(*) as cnt FROM posts WHERE user_id = ?', [$profile_user['id']]);
$owned_communities = db_fetch_all('SELECT * FROM communities WHERE owner_id = ? AND is_active = 1 ORDER BY created_at DESC', [$profile_user['id']]);
$memberships = db_fetch_all(
    'SELECT c.*, m.role, m.joined_at FROM memberships m JOIN communities c ON c.id = m.community_id WHERE m.user_id = ? AND m.status = "approved" ORDER BY m.joined_at DESC',
    [$profile_user['id']]
);
$user_links  = db_fetch_all('SELECT * FROM user_links WHERE user_id = ? ORDER BY sort_order', [$profile_user['id']]);
$user_badges = db_fetch_all(
    'SELECT b.*, ub.awarded_at, co.name as community_name FROM user_badges ub JOIN badges b ON b.id = ub.badge_id LEFT JOIN communities co ON co.id = ub.community_id WHERE ub.user_id = ? ORDER BY ub.awarded_at DESC',
    [$profile_user['id']]
);

$tab = $_GET['tab'] ?? 'communities';
$page_title = ($profile_user['first_name'] ?: $profile_user['username']) . "'s Profile";
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">

  <!-- Profile header — full-width banner -->
  <div class="relative -mx-4 sm:-mx-6 lg:-mx-8 mb-0">
    <!-- Banner -->
    <div class="h-40 sm:h-56 bg-gradient-to-br from-primary-700 via-primary-600 to-accent-500 overflow-hidden">
      <div class="w-full h-full opacity-30 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.4),transparent)]"></div>
    </div>
  </div>

  <!-- Avatar + info card -->
  <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 shadow-airbnb -mt-8 mx-0 mb-6 overflow-hidden">
    <div class="px-6 pt-0 pb-5">
      <!-- Avatar row -->
      <div class="flex items-end justify-between gap-4 flex-wrap -mt-10 mb-4">
        <img src="<?= get_avatar_url($profile_user['avatar'] ?? null, ($profile_user['first_name'] ?? '') . ' ' . ($profile_user['last_name'] ?? ''), 96) ?>"
             class="w-20 h-20 rounded-2xl border-4 border-white dark:border-[#1a1a1a] shadow-xl object-cover">
        <div class="flex items-center gap-2 mt-10">
          <?php if ($is_own_profile): ?>
            <a href="/settings.php" class="px-4 py-2 border border-gray-300 dark:border-white/20 text-gray-700 dark:text-gray-300 rounded-full text-sm font-semibold hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">Edit Profile</a>
          <?php elseif ($current_user): ?>
            <button onclick="toggleFollow(<?= $profile_user['id'] ?>)" id="follow-btn"
                    class="px-5 py-2 rounded-full text-sm font-semibold transition-all
                           <?= $is_following ? 'border border-gray-300 dark:border-white/20 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/10' : 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:bg-gray-700 dark:hover:bg-gray-100' ?>">
              <?= $is_following ? 'Following' : 'Follow' ?>
            </button>
          <?php else: ?>
            <a href="/login.php" class="px-5 py-2 rounded-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-semibold hover:bg-gray-700 dark:hover:bg-gray-100 transition-colors">Follow</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Name -->
      <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= e(trim(($profile_user['first_name'] ?? '') . ' ' . ($profile_user['last_name'] ?? '')) ?: $profile_user['username']) ?></h1>
      <p class="text-gray-500 dark:text-gray-500 text-sm mb-2">@<?= e($profile_user['username']) ?></p>

      <?php if ($profile_user['bio']): ?>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed mb-3"><?= nl2br(e($profile_user['bio'])) ?></p>
      <?php endif; ?>

      <!-- Meta -->
      <div class="flex items-center gap-4 flex-wrap text-xs text-gray-500 dark:text-gray-500 mb-4">
        <?php if ($profile_user['location']): ?>
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?= e($profile_user['location']) ?>
          </span>
        <?php endif; ?>
        <span class="flex items-center gap-1">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Joined <?= date('F Y', strtotime($profile_user['created_at'])) ?>
        </span>
      </div>

      <!-- Stats row -->
      <div class="flex items-center gap-6 pt-4 border-t border-gray-100 dark:border-white/10">
        <div class="text-center">
          <div class="font-black text-gray-900 dark:text-white"><?= (int)($post_count['cnt'] ?? 0) ?></div>
          <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Posts</div>
        </div>
        <div class="text-center">
          <div class="font-black text-gray-900 dark:text-white"><?= (int)($follower_count['cnt'] ?? 0) ?></div>
          <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Followers</div>
        </div>
        <div class="text-center">
          <div class="font-black text-gray-900 dark:text-white"><?= (int)($following_count['cnt'] ?? 0) ?></div>
          <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Following</div>
        </div>
        <div class="text-center">
          <div class="font-black text-gray-900 dark:text-white"><?= count($user_badges) ?></div>
          <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Badges</div>
        </div>
      </div>
    </div>

    <!-- Tab nav -->
    <div class="border-t border-gray-100 dark:border-white/10 flex overflow-x-auto scrollbar-hide">
      <?php foreach (['communities' => 'Communities', 'memberships' => 'Memberships', 'about' => 'About'] as $t => $label): ?>
        <a href="?username=<?= e($username) ?>&tab=<?= $t ?>"
           class="flex-shrink-0 px-5 py-3.5 text-sm font-medium whitespace-nowrap transition-colors border-b-2
                  <?= $tab === $t ? 'text-gray-900 dark:text-white border-gray-900 dark:border-white' : 'text-gray-500 dark:text-gray-500 border-transparent hover:text-gray-700 dark:hover:text-gray-300' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Tab content + sidebar -->
  <div class="flex gap-6 flex-col lg:flex-row">
    <div class="flex-1 min-w-0">
      <?php if ($tab === 'communities'): ?>
        <?php if (empty($owned_communities)): ?>
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-12 text-center shadow-airbnb">
            <div class="w-12 h-12 bg-gray-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-3">
              <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h3 class="font-bold text-gray-700 dark:text-gray-300 mb-1">No communities yet</h3>
            <p class="text-sm text-gray-500 dark:text-gray-500"><?= $is_own_profile ? "You haven't created any communities yet." : "This user hasn't created any communities yet." ?></p>
            <?php if ($is_own_profile): ?>
              <a href="/create-community.php" class="mt-4 inline-block px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl text-sm font-semibold hover:bg-gray-700 dark:hover:bg-gray-100 transition-colors">Create Community</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($owned_communities as $c): ?>
              <a href="/community.php?slug=<?= e($c['slug']) ?>"
                 class="group bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden hover:shadow-airbnb hover:border-gray-300 dark:hover:border-white/20 transition-all">
                <div class="h-24 relative bg-gradient-to-br from-primary-600 to-accent-500 overflow-hidden">
                  <?php if ($c['banner']): ?><img src="<?= e($c['banner']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"><?php endif; ?>
                </div>
                <div class="p-4 -mt-5">
                  <?php if ($c['logo']): ?>
                    <img src="<?= e($c['logo']) ?>" class="w-10 h-10 rounded-xl border-2 border-white dark:border-[#1a1a1a] shadow-md object-cover mb-2">
                  <?php else: ?>
                    <div class="w-10 h-10 rounded-xl border-2 border-white dark:border-[#1a1a1a] shadow-md bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black mb-2"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                  <?php endif; ?>
                  <h3 class="font-bold text-sm text-gray-900 dark:text-white line-clamp-1"><?= e($c['name']) ?></h3>
                  <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5"><?= format_member_count($c['member_count']) ?> members &bull; <?= ucfirst($c['pricing']) ?></p>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php elseif ($tab === 'memberships'): ?>
        <?php if (empty($memberships)): ?>
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-12 text-center shadow-airbnb">
            <h3 class="font-bold text-gray-700 dark:text-gray-300">No memberships yet</h3>
          </div>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($memberships as $c): ?>
              <a href="/community.php?slug=<?= e($c['slug']) ?>"
                 class="group bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-4 hover:shadow-airbnb hover:border-gray-300 dark:hover:border-white/20 transition-all flex items-center gap-3">
                <?php if ($c['logo']): ?>
                  <img src="<?= e($c['logo']) ?>" class="w-12 h-12 rounded-xl object-cover flex-shrink-0">
                <?php else: ?>
                  <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-lg flex-shrink-0"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                <?php endif; ?>
                <div class="min-w-0 flex-1">
                  <div class="font-bold text-sm text-gray-900 dark:text-white line-clamp-1"><?= e($c['name']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
                    <span class="capitalize"><?= $c['role'] ?></span>
                    <span>&bull;</span>
                    <span>Joined <?= date('M Y', strtotime($c['joined_at'])) ?></span>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php elseif ($tab === 'about'): ?>
        <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-6 shadow-airbnb">
          <h3 class="font-bold text-lg mb-4 text-gray-900 dark:text-white">About</h3>
          <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed"><?= nl2br(e($profile_user['bio'] ?: 'No bio provided.')) ?></p>
          <?php if (!empty($user_badges)): ?>
            <h4 class="font-bold mt-6 mb-3 text-gray-900 dark:text-white text-sm">Badges</h4>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($user_badges as $b): ?>
                <div class="flex items-center gap-2 bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 px-3 py-1.5 rounded-full text-sm">
                  <?= $b['icon'] ?? '🏅' ?> <?= e($b['name']) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="lg:w-64 flex-shrink-0">
      <div class="sidebar-sticky space-y-4">
        <!-- Quick Stats -->
        <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-5 shadow-airbnb">
          <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Stats</h4>
          <div class="space-y-2.5">
            <?php foreach ([
              'Communities' => count($owned_communities),
              'Memberships' => count($memberships),
              'Posts' => (int)($post_count['cnt'] ?? 0),
              'Badges' => count($user_badges),
            ] as $label => $val): ?>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-500"><?= $label ?></span>
                <span class="font-semibold text-gray-900 dark:text-white"><?= $val ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Social Links -->
        <?php if (!empty($user_links)): ?>
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-5 shadow-airbnb">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Links</h4>
            <div class="space-y-2">
              <?php foreach ($user_links as $link): ?>
                <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener"
                   class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                  <?= e($link['name'] ?: $link['url']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Badges Preview -->
        <?php if (!empty($user_badges)): ?>
          <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 p-5 shadow-airbnb">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Recent Badges</h4>
            <div class="flex flex-wrap gap-2">
              <?php foreach (array_slice($user_badges, 0, 8) as $b): ?>
                <div title="<?= e($b['name']) . ': ' . e($b['description'] ?? '') ?>"
                     class="w-9 h-9 rounded-xl bg-gray-100 dark:bg-white/10 flex items-center justify-center text-lg cursor-default">
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
  }).then(r => r.json()).then(data => {
    if (data.success) {
      const btn = document.getElementById('follow-btn');
      if (data.following) {
        btn.textContent = 'Following';
        btn.className = 'px-5 py-2 rounded-full text-sm font-semibold transition-all border border-gray-300 dark:border-white/20 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/10';
      } else {
        btn.textContent = 'Follow';
        btn.className = 'px-5 py-2 rounded-full text-sm font-semibold transition-all bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:bg-gray-700 dark:hover:bg-gray-100';
      }
      showToast(data.message);
    }
  });
}
</script>
