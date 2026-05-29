<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$current_user = get_current_user();
$tab = $_GET['tab'] ?? 'profile';
$valid_tabs = ['profile', 'account', 'notifications', 'payment', 'history', 'affiliates', 'payouts', 'theme'];
if (!in_array($tab, $valid_tabs)) $tab = 'profile';

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_profile') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name  = trim($_POST['last_name'] ?? '');
            $bio        = trim($_POST['bio'] ?? '');
            $location   = trim($_POST['location'] ?? '');
            $avatar     = trim($_POST['avatar'] ?? '');
            $new_username = strtolower(trim($_POST['username'] ?? ''));

            if ($new_username && !preg_match('/^[a-z0-9_]{3,30}$/', $new_username)) {
                $error = 'Invalid username format.';
            } else {
                if ($new_username !== $current_user['username']) {
                    $exists = db_fetch('SELECT id FROM users WHERE username = ? AND id != ?', [$new_username, $current_user['id']]);
                    if ($exists) { $error = 'Username is taken.'; }
                }
                if (!$error) {
                    db_execute('UPDATE users SET first_name=?, last_name=?, bio=?, location=?, avatar=?, username=? WHERE id=?',
                        [$first_name, $last_name, $bio, $location, $avatar ?: $current_user['avatar'], $new_username ?: $current_user['username'], $current_user['id']]);

                    // Save links
                    db_execute('DELETE FROM user_links WHERE user_id = ?', [$current_user['id']]);
                    $link_names = $_POST['link_name'] ?? [];
                    $link_urls  = $_POST['link_url'] ?? [];
                    foreach ($link_names as $i => $ln) {
                        $lu = $link_urls[$i] ?? '';
                        if (trim($ln) && trim($lu)) {
                            db_insert('INSERT INTO user_links (user_id, name, url, sort_order) VALUES (?,?,?,?)', [$current_user['id'], trim($ln), trim($lu), $i]);
                        }
                    }
                    $success = 'Profile updated successfully!';
                    $current_user = get_current_user();
                }
            }
        }

        elseif ($action === 'change_password') {
            $old = $_POST['old_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (!password_verify($old, $current_user['password_hash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                db_execute('UPDATE users SET password_hash=? WHERE id=?', [$hash, $current_user['id']]);
                $success = 'Password updated!';
            }
        }

        elseif ($action === 'save_notifications') {
            $new_follower = isset($_POST['new_follower']) ? 1 : 0;
            $post_likes = isset($_POST['post_likes']) ? 1 : 0;
            $affiliate_referral = isset($_POST['affiliate_referral']) ? 1 : 0;
            $existing = db_fetch('SELECT user_id FROM notification_settings WHERE user_id = ?', [$current_user['id']]);
            if ($existing) {
                db_execute('UPDATE notification_settings SET new_follower=?, post_likes=?, affiliate_referral=? WHERE user_id=?',
                    [$new_follower, $post_likes, $affiliate_referral, $current_user['id']]);
            } else {
                db_insert('INSERT INTO notification_settings (user_id, new_follower, post_likes, affiliate_referral) VALUES (?,?,?,?)',
                    [$current_user['id'], $new_follower, $post_likes, $affiliate_referral]);
            }
            // Community notification settings
            $comm_ids = $_POST['community_id'] ?? [];
            foreach ($comm_ids as $cid) {
                $admin_posts = isset($_POST['admin_posts_' . $cid]) ? 1 : 0;
                $new_events = isset($_POST['new_events_' . $cid]) ? 1 : 0;
                $existing = db_fetch('SELECT * FROM community_notification_settings WHERE user_id=? AND community_id=?', [$current_user['id'], $cid]);
                if ($existing) {
                    db_execute('UPDATE community_notification_settings SET admin_posts=?, new_events=? WHERE user_id=? AND community_id=?',
                        [$admin_posts, $new_events, $current_user['id'], $cid]);
                } else {
                    db_insert('INSERT INTO community_notification_settings (user_id, community_id, admin_posts, new_events) VALUES (?,?,?,?)',
                        [$current_user['id'], $cid, $admin_posts, $new_events]);
                }
            }
            $success = 'Notification settings saved!';
        }

        elseif ($action === 'save_theme') {
            $theme = in_array($_POST['theme'] ?? '', ['light', 'dark']) ? $_POST['theme'] : 'light';
            db_execute('UPDATE users SET theme=? WHERE id=?', [$theme, $current_user['id']]);
            $success = 'Theme updated!';
            $current_user = get_current_user();
            header('Location: /platform/settings.php?tab=theme&saved=1');
            exit;
        }

        elseif ($action === 'add_payment') {
            $last4 = trim($_POST['card_last4'] ?? '');
            $brand = trim($_POST['card_brand'] ?? '');
            $exp_m = (int)($_POST['exp_month'] ?? 0);
            $exp_y = (int)($_POST['exp_year'] ?? 0);
            if (strlen($last4) === 4 && $brand && $exp_m && $exp_y) {
                db_insert('INSERT INTO payment_methods (user_id, card_last4, card_brand, exp_month, exp_year) VALUES (?,?,?,?,?)',
                    [$current_user['id'], $last4, $brand, $exp_m, $exp_y]);
                $success = 'Payment method added!';
            } else {
                $error = 'Invalid card information.';
            }
        }

        elseif ($action === 'revoke_session') {
            $session_id = (int)($_POST['session_id'] ?? 0);
            db_execute('DELETE FROM user_sessions WHERE id=? AND user_id=?', [$session_id, $current_user['id']]);
            $success = 'Session revoked.';
        }
    }
}

// Load data for current tab
$user_links = db_fetch_all('SELECT * FROM user_links WHERE user_id = ? ORDER BY sort_order', [$current_user['id']]);
$user_sessions = db_fetch_all('SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_active DESC', [$current_user['id']]);
$notif_settings = db_fetch('SELECT * FROM notification_settings WHERE user_id = ?', [$current_user['id']]);
$user_communities = db_fetch_all(
    'SELECT c.id, c.name FROM memberships m JOIN communities c ON c.id = m.community_id WHERE m.user_id = ? AND m.status = "approved"',
    [$current_user['id']]
);
$payment_methods = db_fetch_all('SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC', [$current_user['id']]);
$payment_history = db_fetch_all('SELECT p.*, c.name as community_name, co.title as course_title FROM payments p LEFT JOIN communities c ON c.id = p.community_id LEFT JOIN courses co ON co.id = p.course_id WHERE p.user_id = ? ORDER BY p.created_at DESC', [$current_user['id']]);

// Affiliate data
$referred_users = db_fetch('SELECT COUNT(*) as cnt FROM users WHERE referred_by = ?', [$current_user['id']]);
$affiliate_earnings = db_fetch('SELECT COALESCE(SUM(affiliate_commission), 0) as total FROM payments WHERE affiliate_user_id = ? AND status = "completed"', [$current_user['id']]);

$timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
$base_url = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$page_title = 'Settings';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex gap-6 flex-col md:flex-row">

    <!-- Sidebar Nav -->
    <div class="md:w-52 flex-shrink-0">
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-3 shadow-sm sticky top-20">
        <h2 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-3 mb-2">Settings</h2>
        <?php
        $nav_tabs = [
            'profile'       => ['icon' => '👤', 'label' => 'Profile'],
            'account'       => ['icon' => '🔐', 'label' => 'Account'],
            'notifications' => ['icon' => '🔔', 'label' => 'Notifications'],
            'payment'       => ['icon' => '💳', 'label' => 'Payment Methods'],
            'history'       => ['icon' => '📜', 'label' => 'Payment History'],
            'affiliates'    => ['icon' => '🔗', 'label' => 'Affiliates'],
            'payouts'       => ['icon' => '💰', 'label' => 'Payouts'],
            'theme'         => ['icon' => '🎨', 'label' => 'Theme'],
        ];
        foreach ($nav_tabs as $t => $info):
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
      <!-- Alert messages -->
      <?php if ($success): ?>
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-600 dark:text-green-400 text-sm flex items-center gap-2">
          ✅ <?= e($success) ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl text-red-600 dark:text-red-400 text-sm">❌ <?= e($error) ?></div>
      <?php endif; ?>
      <?php if (isset($_GET['saved'])): ?>
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-600 dark:text-green-400 text-sm">✅ Settings saved!</div>
      <?php endif; ?>

      <?php if ($tab === 'profile'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Profile Settings</h2>
        <form method="POST" class="space-y-5">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="save_profile">

          <!-- Avatar URL -->
          <div class="flex items-center gap-4">
            <img id="avatar-preview" src="<?= get_avatar_url($current_user['avatar'], ($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''), 80) ?>"
              class="w-16 h-16 rounded-2xl object-cover border-2 border-gray-200 dark:border-gray-600">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Avatar URL</label>
              <input type="url" name="avatar" value="<?= e($current_user['avatar'] ?? '') ?>"
                oninput="document.getElementById('avatar-preview').src = this.value || '<?= get_avatar_url(null, ($current_user['first_name'] ?? 'U')) ?>'"
                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200"
                placeholder="https://example.com/avatar.jpg">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">First Name</label>
              <input type="text" name="first_name" value="<?= e($current_user['first_name'] ?? '') ?>"
                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Last Name</label>
              <input type="text" name="last_name" value="<?= e($current_user['last_name'] ?? '') ?>"
                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Username</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">@</span>
              <input type="text" name="username" value="<?= e($current_user['username']) ?>"
                class="w-full pl-8 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Bio</label>
            <textarea name="bio" rows="3"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none dark:text-gray-200 placeholder-gray-400"
              placeholder="Tell your community about yourself..."><?= e($current_user['bio'] ?? '') ?></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Location</label>
            <input type="text" name="location" value="<?= e($current_user['location'] ?? '') ?>"
              placeholder="Riyadh, Saudi Arabia"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
          </div>

          <!-- Links Manager -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Custom Links</label>
            <div id="links-container" class="space-y-2">
              <?php foreach ($user_links as $link): ?>
                <div class="flex items-center gap-2 link-row">
                  <input type="text" name="link_name[]" value="<?= e($link['name']) ?>" placeholder="Label (e.g. Twitter)"
                    class="w-32 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
                  <input type="url" name="link_url[]" value="<?= e($link['url']) ?>" placeholder="https://..."
                    class="flex-1 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
                  <button type="button" onclick="this.closest('.link-row').remove()" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" onclick="addLinkRow()"
              class="mt-2 flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline font-medium">
              + Add Link
            </button>
          </div>

          <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all hover:-translate-y-0.5">Save Profile</button>
        </form>
      </div>

      <?php elseif ($tab === 'account'): ?>
      <div class="space-y-5">
        <!-- Change Password -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
          <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Security</h2>
          <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="change_password">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Current Password</label>
              <input type="password" name="old_password" required
                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">New Password</label>
              <input type="password" name="new_password" required minlength="8"
                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirm New Password</label>
              <input type="password" name="confirm_password" required
                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Update Password</button>
          </form>
        </div>

        <!-- Active Sessions -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Active Sessions</h2>
            <a href="/platform/api/settings_save.php?action=logout_all" class="text-sm text-red-600 dark:text-red-400 hover:underline font-medium">Logout All Devices</a>
          </div>
          <div class="space-y-3">
            <?php foreach ($user_sessions as $sess): ?>
              <div class="flex items-start justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                <div>
                  <div class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-xs"><?= e(substr($sess['device_info'] ?? 'Unknown device', 0, 80)) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">IP: <?= e($sess['ip_address'] ?? '-') ?> • Last active: <?= time_ago($sess['last_active']) ?></div>
                </div>
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="action" value="revoke_session">
                  <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                  <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium px-2 py-1 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all">Revoke</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php elseif ($tab === 'notifications'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Notification Preferences</h2>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="save_notifications">
          <?php foreach ($user_communities as $c): ?><input type="hidden" name="community_id[]" value="<?= $c['id'] ?>"><?php endforeach; ?>

          <div class="space-y-4 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Platform Notifications</h3>
            <?php
            $notif_opts = [
                'new_follower'     => ['label' => 'New Follower', 'desc' => 'When someone follows you'],
                'post_likes'       => ['label' => 'Post Likes', 'desc' => 'When someone likes your post'],
                'affiliate_referral' => ['label' => 'Affiliate Referral', 'desc' => 'When someone signs up via your link'],
            ];
            foreach ($notif_opts as $key => $opt):
                $checked = $notif_settings ? (bool)$notif_settings[$key] : true;
            ?>
              <label class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">
                <div>
                  <div class="text-sm font-medium text-gray-900 dark:text-white"><?= $opt['label'] ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= $opt['desc'] ?></div>
                </div>
                <div class="relative ml-4">
                  <input type="checkbox" name="<?= $key ?>" <?= $checked ? 'checked' : '' ?> class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>

          <?php if (!empty($user_communities)): ?>
            <div class="border-t border-gray-100 dark:border-gray-700 pt-4 space-y-4">
              <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Community Notifications</h3>
              <?php foreach ($user_communities as $c): ?>
                <?php $cs = db_fetch('SELECT * FROM community_notification_settings WHERE user_id=? AND community_id=?', [$current_user['id'], $c['id']]); ?>
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                  <div class="font-semibold text-sm text-gray-900 dark:text-white mb-3"><?= e($c['name']) ?></div>
                  <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" name="admin_posts_<?= $c['id'] ?>" <?= (!$cs || $cs['admin_posts']) ? 'checked' : '' ?> class="w-4 h-4 text-primary-600 rounded">
                      <span class="text-xs text-gray-600 dark:text-gray-400">Admin posts</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" name="new_events_<?= $c['id'] ?>" <?= (!$cs || $cs['new_events']) ? 'checked' : '' ?> class="w-4 h-4 text-primary-600 rounded">
                      <span class="text-xs text-gray-600 dark:text-gray-400">New events</span>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <button type="submit" class="mt-5 px-6 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Save Preferences</button>
        </form>
      </div>

      <?php elseif ($tab === 'payment'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Payment Methods</h2>

        <!-- Existing cards -->
        <?php if (!empty($payment_methods)): ?>
          <div class="space-y-3 mb-6">
            <?php foreach ($payment_methods as $pm): ?>
              <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-7 rounded bg-gradient-to-br from-gray-700 to-gray-900 flex items-center justify-center text-white text-xs font-bold"><?= substr(e($pm['card_brand'] ?? 'CARD'), 0, 4) ?></div>
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white"><?= e($pm['card_brand'] ?? 'Card') ?> •••• <?= e($pm['card_last4'] ?? '????') ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Expires <?= $pm['exp_month'] ?>/<?= $pm['exp_year'] ?></div>
                  </div>
                  <?php if ($pm['is_default']): ?>
                    <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 px-2 py-0.5 rounded-full font-medium">Default</span>
                  <?php endif; ?>
                </div>
                <a href="/platform/api/settings_save.php?action=remove_payment&id=<?= $pm['id'] ?>&csrf_token=<?= csrf_token() ?>"
                  class="text-xs text-red-500 hover:text-red-700 font-medium" onclick="return confirm('Remove this card?')">Remove</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">No payment methods saved yet.</p>
        <?php endif; ?>

        <!-- Add new card -->
        <details class="bg-gray-50 dark:bg-gray-700/50 rounded-xl">
          <summary class="px-4 py-3 text-sm font-semibold text-primary-600 dark:text-primary-400 cursor-pointer hover:text-primary-700 dark:hover:text-primary-300 list-none flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Payment Method
          </summary>
          <form method="POST" class="px-4 pb-4 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_payment">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">Card Brand</label>
                <select name="card_brand" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
                  <option value="Visa">Visa</option>
                  <option value="Mastercard">Mastercard</option>
                  <option value="Mada">Mada</option>
                  <option value="Amex">Amex</option>
                </select>
              </div>
              <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">Last 4 Digits</label>
                <input type="text" name="card_last4" maxlength="4" pattern="[0-9]{4}" placeholder="1234"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
              </div>
              <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">Exp Month</label>
                <input type="number" name="exp_month" min="1" max="12" placeholder="MM"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
              </div>
              <div>
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 block">Exp Year</label>
                <input type="number" name="exp_year" min="<?= date('Y') ?>" max="<?= date('Y') + 10 ?>" placeholder="YYYY"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
              </div>
            </div>
            <button type="submit" class="px-5 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Add Card</button>
          </form>
        </details>
      </div>

      <?php elseif ($tab === 'history'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Payment History</h2>
        <?php if (empty($payment_history)): ?>
          <div class="text-center py-12">
            <div class="text-4xl mb-3">📭</div>
            <p class="text-gray-500 dark:text-gray-400 text-sm">No transactions yet.</p>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100 dark:border-gray-700">
                  <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Date</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Type</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">For</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Amount</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payment_history as $p): ?>
                  <tr class="border-b border-gray-50 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30">
                    <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400 text-xs"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                    <td class="py-2.5 px-3 capitalize text-gray-700 dark:text-gray-300 text-xs"><?= e($p['type']) ?></td>
                    <td class="py-2.5 px-3 text-gray-700 dark:text-gray-300 text-xs"><?= e($p['community_name'] ?? $p['course_title'] ?? '-') ?></td>
                    <td class="py-2.5 px-3 font-semibold text-gray-900 dark:text-white text-xs">$<?= number_format($p['amount'], 2) ?></td>
                    <td class="py-2.5 px-3">
                      <?php $sc = ['completed'=>'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', 'pending'=>'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'failed'=>'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'refunded'=>'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400']; ?>
                      <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $sc[$p['status']] ?? '' ?> capitalize"><?= $p['status'] ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php elseif ($tab === 'affiliates'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Affiliate Program</h2>

        <!-- Referral Link -->
        <div class="bg-gradient-to-br from-primary-50 to-accent-50 dark:from-primary-900/20 dark:to-accent-900/20 rounded-2xl p-5 mb-5 border border-primary-100 dark:border-primary-800/50">
          <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Your Referral Link</h3>
          <div class="flex gap-2">
            <input type="text" readonly id="ref-link"
              value="<?= e($base_url) ?>/platform/register.php?ref=<?= e($current_user['affiliate_code'] ?? '') ?>"
              class="flex-1 px-4 py-2.5 rounded-xl border border-primary-200 dark:border-primary-700 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 font-mono">
            <button onclick="copyRefLink()" class="px-4 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Copy</button>
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Earn 7% commission on all payments from your referred users</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-5">
          <div class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
            <div class="text-2xl font-black text-primary-600 dark:text-primary-400"><?= (int)($referred_users['cnt'] ?? 0) ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Referred Users</div>
          </div>
          <div class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
            <div class="text-2xl font-black text-green-600 dark:text-green-400">$<?= number_format($affiliate_earnings['total'] ?? 0, 2) ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Earnings</div>
          </div>
          <div class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
            <div class="text-2xl font-black text-accent-500 dark:text-accent-400">7%</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Commission Rate</div>
          </div>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">Share your unique link and earn 7% commission on all payments made by users who sign up through your link. Payouts are processed monthly.</p>
      </div>

      <?php elseif ($tab === 'payouts'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Payouts</h2>
        <div class="bg-gradient-to-br from-primary-600 to-accent-500 rounded-2xl p-6 text-white mb-5">
          <div class="text-sm opacity-80 mb-1">Available Balance</div>
          <div class="text-4xl font-black"><?= '$' . number_format($current_user['affiliate_balance'] ?? 0, 2) ?></div>
          <button class="mt-4 px-5 py-2.5 bg-white/20 hover:bg-white/30 rounded-xl text-sm font-semibold transition-all backdrop-blur-sm border border-white/30">
            Request Payout
          </button>
        </div>
        <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
          <div class="text-3xl mb-2">📬</div>
          No payout history yet. Earnings are paid out monthly when balance exceeds $50.
        </div>
      </div>

      <?php elseif ($tab === 'theme'): ?>
      <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Appearance</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Choose how Discover looks for you.</p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="save_theme">
          <div class="grid sm:grid-cols-2 gap-4 mb-6">
            <label class="cursor-pointer">
              <input type="radio" name="theme" value="light" <?= ($current_user['theme'] ?? 'light') === 'light' ? 'checked' : '' ?> class="sr-only">
              <div class="theme-card border-2 rounded-2xl overflow-hidden transition-all hover:shadow-md <?= ($current_user['theme'] ?? 'light') === 'light' ? 'border-primary-500 shadow-primary-100' : 'border-gray-200 dark:border-gray-600' ?>"
                onclick="selectTheme('light', this)">
                <div class="bg-white p-4">
                  <div class="h-2 w-12 bg-gray-300 rounded mb-2"></div>
                  <div class="h-2 w-20 bg-gray-200 rounded mb-3"></div>
                  <div class="h-8 bg-gray-100 rounded-xl"></div>
                </div>
                <div class="px-4 pb-3 bg-white text-center">
                  <div class="text-sm font-bold text-gray-900">☀️ Light Mode</div>
                </div>
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="theme" value="dark" <?= ($current_user['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?> class="sr-only">
              <div class="theme-card border-2 rounded-2xl overflow-hidden transition-all hover:shadow-md <?= ($current_user['theme'] ?? 'light') === 'dark' ? 'border-primary-500' : 'border-gray-200 dark:border-gray-600' ?>"
                onclick="selectTheme('dark', this)">
                <div class="bg-gray-900 p-4">
                  <div class="h-2 w-12 bg-gray-600 rounded mb-2"></div>
                  <div class="h-2 w-20 bg-gray-700 rounded mb-3"></div>
                  <div class="h-8 bg-gray-800 rounded-xl"></div>
                </div>
                <div class="px-4 pb-3 bg-gray-900 text-center">
                  <div class="text-sm font-bold text-white">🌙 Dark Mode</div>
                </div>
              </div>
            </label>
          </div>
          <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">Save Theme</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
function addLinkRow() {
  const container = document.getElementById('links-container');
  const row = document.createElement('div');
  row.className = 'flex items-center gap-2 link-row';
  row.innerHTML = `
    <input type="text" name="link_name[]" placeholder="Label (e.g. Twitter)"
      class="w-32 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
    <input type="url" name="link_url[]" placeholder="https://..."
      class="flex-1 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
    <button type="button" onclick="this.closest('.link-row').remove()" class="p-2 text-red-400 hover:text-red-600 rounded-lg">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>`;
  container.appendChild(row);
}

function copyRefLink() {
  const link = document.getElementById('ref-link');
  navigator.clipboard.writeText(link.value).then(() => showToast('Referral link copied!'));
}

function selectTheme(theme, el) {
  document.querySelectorAll('.theme-card').forEach(c => {
    c.classList.remove('border-primary-500', 'shadow-primary-100');
    c.classList.add('border-gray-200');
  });
  el.classList.add('border-primary-500');
  el.classList.remove('border-gray-200');
}
</script>
