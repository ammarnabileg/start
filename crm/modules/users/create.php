<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('users.manage');

$roles = db_all('SELECT id, name FROM ' . tbl('roles') . ' ORDER BY name');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = (int)($_POST['role_id'] ?? 0);
    $status= $_POST['status'] ?? 'active';

    if ($name === '')                              $errors[] = 'الاسم مطلوب';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد غير صحيح';
    if (strlen($pass) < 8)                          $errors[] = 'كلمة المرور 8 أحرف على الأقل';
    if (!$role)                                     $errors[] = 'الدور مطلوب';
    if (db_one('SELECT id FROM ' . tbl('users') . ' WHERE email = :e', ['e' => $email])) $errors[] = 'البريد مستخدم بالفعل';

    if (!$errors) {
        $id = db_insert(tbl('users'), [
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone ?: null,
            'password_hash' => password_hash($pass, CRM_PASSWORD_ALGO),
            'role_id'       => $role,
            'status'        => $status,
        ]);
        activity_log('create', 'user', (int)$id, ['name' => $name, 'email' => $email]);
        flash('success', 'تم إنشاء المستخدم.');
        redirect('modules/users/');
    }
}

$pageTitle = 'مستخدم جديد';
require __DIR__ . '/../../includes/header.php';
?>

<form method="post" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
  <?= csrf_field() ?>
  <?php if ($errors): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm">
      <ul class="list-disc list-inside"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-2 gap-4">
    <div class="col-span-2">
      <label class="block text-sm mb-1">الاسم الكامل *</label>
      <input name="name" required value="<?= e($_POST['name'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">البريد الإلكتروني *</label>
      <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الهاتف</label>
      <input name="phone" value="<?= e($_POST['phone'] ?? '') ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">كلمة المرور (8+) *</label>
      <input type="password" name="password" minlength="8" required class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الدور *</label>
      <select name="role_id" required class="w-full px-3 py-2 border rounded-lg">
        <option value="">اختر دور...</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= $r['id'] ?>" <?= ((int)($_POST['role_id'] ?? 0) === (int)$r['id']) ? 'selected' : '' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الحالة</label>
      <select name="status" class="w-full px-3 py-2 border rounded-lg">
        <option value="active">نشط</option>
        <option value="inactive">موقوف</option>
      </select>
    </div>
  </div>

  <div class="flex gap-2 mt-6">
    <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
    <a href="<?= url('modules/users/') ?>" class="px-6 py-2 border rounded-lg">إلغاء</a>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
