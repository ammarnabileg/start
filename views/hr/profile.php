<?php
ob_start();
$db = Database::getInstance();
$user = Auth::user();
$uid = $user['id'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        if ($full_name && $email) {
            $nameParts = explode(' ', $full_name, 2);
            $db->update('users', [
                'first_name' => $nameParts[0],
                'last_name'  => $nameParts[1] ?? '',
                'email'      => $email,
            ], ['id' => $uid]);
            // Refresh session
            $updated = $db->fetch("SELECT * FROM users WHERE id = ?", [$uid]);
            if ($updated) {
                $_SESSION['user']['first_name'] = $updated['first_name'];
                $_SESSION['user']['last_name']  = $updated['last_name'];
                $_SESSION['user']['email']      = $updated['email'];
                $_SESSION['user']['full_name']  = trim($updated['first_name'] . ' ' . $updated['last_name']);
            }
            $success = 'Profile updated successfully.';
        } else {
            $error = 'Name and email are required.';
        }
    } elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $dbUser   = $db->fetch("SELECT password_hash FROM users WHERE id = ?", [$uid]);
        if (!password_verify($current, $dbUser['password_hash'] ?? '')) {
            $pwError = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $pwError = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $pwError = 'Passwords do not match.';
        } else {
            $db->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], ['id' => $uid]);
            $pwSuccess = 'Password changed successfully.';
        }
    }
}

// Reload user
$profile = $db->fetch("SELECT u.*, r.name as role_name FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.id = ? LIMIT 1", [$uid]) ?: $user;
$profile['full_name'] = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
?>
<div class="max-w-2xl mx-auto space-y-6">
  <div class="mb-2">
    <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
    <p class="text-sm text-gray-500 mt-1">Manage your personal information and password</p>
  </div>

  <?php if (!empty($success)): ?>
  <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Profile Info -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <div class="flex items-center gap-5 mb-6">
      <div class="w-16 h-16 rounded-2xl bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-2xl flex-shrink-0">
        <?= strtoupper(substr($profile['full_name'] ?: ($profile['email'] ?? 'U'), 0, 1)) ?>
      </div>
      <div>
        <div class="font-semibold text-gray-900 text-lg"><?= htmlspecialchars($profile['full_name'] ?: 'No name set') ?></div>
        <div class="text-sm text-gray-500"><?= htmlspecialchars($profile['email'] ?? '') ?></div>
        <?php if (!empty($profile['role_name'])): ?>
        <span class="inline-block mt-1 text-xs font-medium bg-violet-100 text-violet-700 rounded-full px-2.5 py-0.5"><?= htmlspecialchars($profile['role_name']) ?></span>
        <?php elseif (!empty($user['is_super_admin'])): ?>
        <span class="inline-block mt-1 text-xs font-medium bg-red-100 text-red-700 rounded-full px-2.5 py-0.5">Super Admin</span>
        <?php endif; ?>
      </div>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="update_profile">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
          <input type="text" name="full_name" value="<?= htmlspecialchars($profile['full_name']) ?>" required
            class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
          <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required
            class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
        </div>
      </div>
      <div class="flex justify-end pt-2">
        <button type="submit" class="bg-violet-700 hover:bg-violet-800 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition-colors">Save Changes</button>
      </div>
    </form>
  </div>

  <!-- Change Password -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <h3 class="font-semibold text-gray-900 mb-1">Change Password</h3>
    <p class="text-sm text-gray-500 mb-5">Use a strong password of at least 8 characters</p>
    <?php if (!empty($pwSuccess)): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm mb-4"><?= htmlspecialchars($pwSuccess) ?></div>
    <?php endif; ?>
    <?php if (!empty($pwError)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-4"><?= htmlspecialchars($pwError) ?></div>
    <?php endif; ?>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="change_password">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Current Password</label>
        <input type="password" name="current_password" required
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
        <input type="password" name="new_password" required minlength="8"
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password</label>
        <input type="password" name="confirm_password" required
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
      </div>
      <div class="flex justify-end pt-2">
        <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold rounded-xl px-5 py-2.5 transition-colors">Change Password</button>
      </div>
    </form>
  </div>
</div>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
