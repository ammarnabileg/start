<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$current_user = get_auth_user();
if (!$current_user || (int)$current_user['id'] !== 1) {
    header('Location: /');
    exit;
}

$tab = $_GET['tab'] ?? 'settings';
$valid_tabs = ['settings', 'seo', 'pricing', 'users', 'communities'];
if (!in_array($tab, $valid_tabs)) $tab = 'settings';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $keys = ['platform_name', 'platform_tagline', 'footer_text'];
        foreach ($keys as $k) {
            $val = trim($_POST[$k] ?? '');
            db_execute('INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?', [$k, $val, $val]);
        }
        // Handle logo upload
        if (!empty($_FILES['platform_logo']['tmp_name'])) {
            $dir = __DIR__ . '/../uploads/site/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['platform_logo']['tmp_name'], $dir . 'logo.png');
            db_execute('INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?', ['platform_logo', '/uploads/site/logo.png', '/uploads/site/logo.png']);
        }
        if (!empty($_FILES['favicon']['tmp_name'])) {
            $dir = __DIR__ . '/../uploads/site/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['favicon']['tmp_name'], $dir . 'favicon.ico');
        }
        $success = 'Site settings saved!';
    }

    elseif ($action === 'save_seo') {
        $keys = ['seo_title', 'seo_description', 'seo_keywords', 'ga_id', 'seo_index'];
        foreach ($keys as $k) {
            $val = trim($_POST[$k] ?? '');
            db_execute('INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?', [$k, $val, $val]);
        }
        if (!empty($_FILES['seo_og_image']['tmp_name'])) {
            $dir = __DIR__ . '/../uploads/site/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['seo_og_image']['tmp_name'], $dir . 'og_image.jpg');
            db_execute('INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?', ['seo_og_image', '/uploads/site/og_image.jpg', '/uploads/site/og_image.jpg']);
        }
        $success = 'SEO settings saved!';
    }

    elseif ($action === 'save_pricing') {
        $keys = ['community_creation_price', 'affiliate_commission_rate'];
        foreach ($keys as $k) {
            $val = trim($_POST[$k] ?? '0');
            db_execute('INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?', [$k, $val, $val]);
        }
        $success = 'Pricing settings saved!';
    }

    elseif ($action === 'toggle_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $ban = (int)($_POST['ban'] ?? 0);
        if ($uid && $uid !== 1) {
            db_execute('UPDATE users SET is_banned = ? WHERE id = ?', [$ban, $uid]);
            $success = $ban ? 'User banned.' : 'User unbanned.';
        }
    }

    elseif ($action === 'toggle_community') {
        $cid = (int)($_POST['community_id'] ?? 0);
        $active = (int)($_POST['active'] ?? 1);
        if ($cid) {
            db_execute('UPDATE communities SET is_active = ? WHERE id = ?', [$active, $cid]);
            $success = $active ? 'Community activated.' : 'Community deactivated.';
        }
    }
}

// Load settings
$settings = [];
$rows = db_fetch_all('SELECT setting_key, setting_value FROM platform_settings');
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

$page_title = 'Admin Dashboard';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="mb-8">
    <h1 class="text-3xl font-black text-gray-900 dark:text-white">Admin Dashboard</h1>
    <p class="text-gray-500 dark:text-gray-400 mt-1">Platform management and settings</p>
  </div>

  <?php if ($success): ?>
    <div class="mb-5 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm">✅ <?= e($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="mb-5 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-400 text-sm">❌ <?= e($error) ?></div>
  <?php endif; ?>

  <div class="flex gap-6 flex-col md:flex-row">
    <!-- Sidebar -->
    <div class="md:w-52 flex-shrink-0">
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-3 shadow-sm sticky top-20">
        <h2 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-3 mb-2">Admin</h2>
        <?php
        $nav = [
            'settings'    => ['icon' => '⚙️',  'label' => 'Site Settings'],
            'seo'         => ['icon' => '🔍',  'label' => 'SEO Settings'],
            'pricing'     => ['icon' => '💰',  'label' => 'Pricing'],
            'users'       => ['icon' => '👥',  'label' => 'Users'],
            'communities' => ['icon' => '🏘️', 'label' => 'Communities'],
        ];
        foreach ($nav as $t => $info):
        ?>
          <a href="?tab=<?= $t ?>"
            class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition-all <?= $tab === $t ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
            <span><?= $info['icon'] ?></span>
            <span><?= $info['label'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 min-w-0">

      <?php if ($tab === 'settings'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Site Settings</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-5">
          <input type="hidden" name="action" value="save_settings">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Site Name</label>
            <input type="text" name="platform_name" value="<?= e($settings['platform_name'] ?? 'Discover') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Site Tagline</label>
            <input type="text" name="platform_tagline" value="<?= e($settings['platform_tagline'] ?? '') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Site Logo</label>
            <?php if (!empty($settings['platform_logo'])): ?>
              <img src="<?= e($settings['platform_logo']) ?>" class="h-12 mb-2 rounded-lg border border-gray-200">
            <?php endif; ?>
            <input type="file" name="platform_logo" accept="image/*"
              class="block text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100">
            <p class="text-xs text-gray-400 mt-1">Will be saved as /uploads/site/logo.png</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Favicon</label>
            <input type="file" name="favicon" accept="image/*,.ico"
              class="block text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100">
            <p class="text-xs text-gray-400 mt-1">Will be saved as /uploads/site/favicon.ico</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Footer Text</label>
            <input type="text" name="footer_text" value="<?= e($settings['footer_text'] ?? '') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200"
              placeholder="© 2025 Discover. All rights reserved.">
          </div>
          <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Save Settings</button>
        </form>
      </div>

      <?php elseif ($tab === 'seo'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">SEO Settings</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-5">
          <input type="hidden" name="action" value="save_seo">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Meta Title</label>
            <input type="text" name="seo_title" value="<?= e($settings['seo_title'] ?? '') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Meta Description</label>
            <textarea name="seo_description" rows="3"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none dark:text-gray-200"><?= e($settings['seo_description'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Meta Keywords</label>
            <input type="text" name="seo_keywords" value="<?= e($settings['seo_keywords'] ?? '') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200"
              placeholder="community, learning, networking">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">OG Image</label>
            <?php if (!empty($settings['seo_og_image'])): ?>
              <img src="<?= e($settings['seo_og_image']) ?>" class="h-16 mb-2 rounded-lg border border-gray-200">
            <?php endif; ?>
            <input type="file" name="seo_og_image" accept="image/*"
              class="block text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Google Analytics ID</label>
            <input type="text" name="ga_id" value="<?= e($settings['ga_id'] ?? '') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200"
              placeholder="G-XXXXXXXXXX">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Allow Search Indexing</label>
            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="seo_index" value="yes" <?= ($settings['seo_index'] ?? 'yes') === 'yes' ? 'checked' : '' ?> class="text-primary-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">Yes (allow indexing)</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="seo_index" value="no" <?= ($settings['seo_index'] ?? 'yes') === 'no' ? 'checked' : '' ?> class="text-primary-600">
                <span class="text-sm text-gray-700 dark:text-gray-300">No (noindex)</span>
              </label>
            </div>
          </div>
          <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Save SEO Settings</button>
        </form>
      </div>

      <?php elseif ($tab === 'pricing'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Community Pricing</h2>
        <form method="POST" class="space-y-5">
          <input type="hidden" name="action" value="save_pricing">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Community Creation Price ($)</label>
            <input type="number" name="community_creation_price" step="0.01" min="0"
              value="<?= e($settings['community_creation_price'] ?? '0') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            <p class="text-xs text-gray-400 mt-1">Set to 0 for free community creation</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Affiliate Commission Rate (%)</label>
            <input type="number" name="affiliate_commission_rate" step="0.1" min="0" max="100"
              value="<?= e($settings['affiliate_commission_rate'] ?? '7') ?>"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
          </div>
          <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Save Pricing</button>
        </form>
      </div>

      <?php elseif ($tab === 'users'): ?>
      <?php
      $users = db_fetch_all(
          'SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.created_at,
           COALESCE(u.is_banned, 0) as is_banned,
           (SELECT COUNT(*) FROM memberships m WHERE m.user_id = u.id AND m.status = "approved" AND m.role = "owner") as community_count
           FROM users u ORDER BY u.id ASC'
      );
      ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Users Management <span class="text-sm font-normal text-gray-500">(<?= count($users) ?> total)</span></h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 dark:border-gray-700">
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">ID</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">User</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Email</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Joined</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Communities</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr class="border-b border-gray-50 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                  <td class="py-2.5 px-3 text-gray-400 text-xs"><?= $u['id'] ?></td>
                  <td class="py-2.5 px-3">
                    <div class="font-medium text-gray-900 dark:text-white text-xs"><?= e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?: e($u['username']) ?></div>
                    <div class="text-gray-400 text-xs">@<?= e($u['username']) ?></div>
                  </td>
                  <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400 text-xs"><?= e($u['email']) ?></td>
                  <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400 text-xs"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                  <td class="py-2.5 px-3 text-center text-gray-700 dark:text-gray-300 text-xs font-semibold"><?= (int)$u['community_count'] ?></td>
                  <td class="py-2.5 px-3">
                    <?php if ((int)$u['id'] === 1): ?>
                      <span class="text-xs text-primary-600 font-semibold">Super Admin</span>
                    <?php else: ?>
                      <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle_user">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="ban" value="<?= $u['is_banned'] ? '0' : '1' ?>">
                        <button type="submit"
                          class="text-xs px-3 py-1 rounded-lg font-medium transition-all <?= $u['is_banned'] ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400' ?>"
                          onclick="return confirm('<?= $u['is_banned'] ? 'Unban' : 'Ban' ?> this user?')">
                          <?= $u['is_banned'] ? 'Unban' : 'Ban' ?>
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($tab === 'communities'): ?>
      <?php
      $communities = db_fetch_all(
          'SELECT c.id, c.name, c.slug, c.pricing, c.price, c.price_interval, c.is_active,
           u.username as owner_username,
           (SELECT COUNT(*) FROM memberships m WHERE m.community_id = c.id AND m.status = "approved") as member_count
           FROM communities c JOIN users u ON u.id = c.owner_id ORDER BY c.id ASC'
      );
      ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Communities Management <span class="text-sm font-normal text-gray-500">(<?= count($communities) ?> total)</span></h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 dark:border-gray-700">
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Name</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Owner</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Members</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Pricing</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Status</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($communities as $c): ?>
                <tr class="border-b border-gray-50 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                  <td class="py-2.5 px-3">
                    <a href="/community.php?slug=<?= e($c['slug']) ?>" class="font-medium text-primary-600 dark:text-primary-400 hover:underline text-xs"><?= e($c['name']) ?></a>
                  </td>
                  <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400 text-xs">@<?= e($c['owner_username']) ?></td>
                  <td class="py-2.5 px-3 text-gray-700 dark:text-gray-300 text-xs font-semibold"><?= (int)$c['member_count'] ?></td>
                  <td class="py-2.5 px-3 text-xs">
                    <?php if ($c['pricing'] === 'paid'): ?>
                      <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-full font-medium">$<?= number_format($c['price'], 2) ?>/<?= $c['price_interval'] ?></span>
                    <?php elseif ($c['pricing'] === 'free_trial'): ?>
                      <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full font-medium">Free Trial</span>
                    <?php else: ?>
                      <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded-full font-medium">Free</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $c['is_active'] ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' ?>">
                      <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td class="py-2.5 px-3">
                    <form method="POST" class="inline">
                      <input type="hidden" name="action" value="toggle_community">
                      <input type="hidden" name="community_id" value="<?= $c['id'] ?>">
                      <input type="hidden" name="active" value="<?= $c['is_active'] ? '0' : '1' ?>">
                      <button type="submit"
                        class="text-xs px-3 py-1 rounded-lg font-medium transition-all <?= $c['is_active'] ? 'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400' ?>"
                        onclick="return confirm('<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?> this community?')">
                        <?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
