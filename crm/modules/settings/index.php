<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = 'الإعدادات';
$uid = auth_id();

// Generate API token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_token' && has_perm('api.use')) {
        $name = trim($_POST['token_name'] ?? '') ?: 'API Token';
        $token = 'crm_' . bin2hex(random_bytes(24));
        db_insert(tbl('api_tokens'), [
            'user_id'    => $uid,
            'name'       => $name,
            'token_hash' => hash('sha256', $token),
        ]);
        flash('success', '🔑 الـ token: ' . $token . ' (احفظه الآن، لن تراه مرة أخرى!)');
        redirect('modules/settings/');
    }

    if ($action === 'revoke_token') {
        $tid = (int)($_POST['id'] ?? 0);
        db_update(tbl('api_tokens'),
            ['revoked_at' => date('Y-m-d H:i:s')],
            'id = :id AND user_id = :u', ['id' => $tid, 'u' => $uid]
        );
        flash('success', 'تم إلغاء الـ token.');
        redirect('modules/settings/');
    }

    if ($action === 'change_password') {
        $current = $_POST['current'] ?? '';
        $new     = $_POST['new'] ?? '';
        $user = db_one('SELECT password_hash FROM ' . tbl('users') . ' WHERE id = :u', ['u' => $uid]);
        if (!password_verify($current, $user['password_hash'])) {
            flash('error', 'كلمة المرور الحالية خطأ.');
        } elseif (strlen($new) < 8) {
            flash('error', 'كلمة المرور الجديدة 8 أحرف على الأقل.');
        } else {
            db_update(tbl('users'),
                ['password_hash' => password_hash($new, CRM_PASSWORD_ALGO)],
                'id = :u', ['u' => $uid]
            );
            flash('success', 'تم تغيير كلمة المرور.');
        }
        redirect('modules/settings/');
    }
}

$tokens = db_all('SELECT * FROM ' . tbl('api_tokens') . ' WHERE user_id = :u ORDER BY created_at DESC', ['u' => $uid]);
$user = db_one('SELECT * FROM ' . tbl('users') . ' WHERE id = :u', ['u' => $uid]);

require __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="bg-white p-6 rounded-xl shadow-sm border">
    <h2 class="font-bold text-lg mb-4">🔑 تغيير كلمة المرور</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <div>
        <label class="block text-sm mb-1">الحالية</label>
        <input type="password" name="current" required class="w-full px-3 py-2 border rounded-lg">
      </div>
      <div>
        <label class="block text-sm mb-1">الجديدة (8+)</label>
        <input type="password" name="new" minlength="8" required class="w-full px-3 py-2 border rounded-lg">
      </div>
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">تغيير</button>
    </form>
  </div>

  <?php if (has_perm('api.use')): ?>
  <div class="bg-white p-6 rounded-xl shadow-sm border">
    <h2 class="font-bold text-lg mb-4">🔌 API Tokens</h2>
    <form method="post" class="flex gap-2 mb-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_token">
      <input name="token_name" placeholder="اسم Token (مثل: My App)" class="flex-1 px-3 py-2 border rounded-lg text-sm">
      <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-emerald-700">إنشاء</button>
    </form>
    <ul class="divide-y text-sm">
      <?php foreach ($tokens as $t): ?>
        <li class="py-2 flex justify-between items-center">
          <div>
            <div class="font-medium <?= $t['revoked_at'] ? 'line-through text-gray-400' : '' ?>"><?= e($t['name']) ?></div>
            <div class="text-xs text-gray-500">
              أُنشئ <?= time_ago($t['created_at']) ?>
              <?= $t['last_used_at'] ? ' · آخر استخدام ' . time_ago($t['last_used_at']) : ' · لم يُستخدم' ?>
              <?= $t['revoked_at'] ? ' · ⛔ مُلغى' : '' ?>
            </div>
          </div>
          <?php if (!$t['revoked_at']): ?>
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="action" value="revoke_token">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button onclick="return confirm('إلغاء هذا الـ token؟')" class="text-red-600 text-xs hover:underline">إلغاء</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if (!$tokens): ?><li class="text-gray-500 text-center py-4">لا توجد tokens.</li><?php endif; ?>
    </ul>
    <p class="text-xs text-gray-500 mt-4">استخدم الـ token في header: <code class="bg-gray-100 px-2 py-0.5 rounded">Authorization: Bearer crm_xxx...</code></p>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
