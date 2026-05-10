<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('users.manage');

$id = (int)($_GET['id'] ?? 0);
$user = db_one('SELECT * FROM ' . tbl('users') . ' WHERE id = :id', ['id' => $id]);
if (!$user) { flash('error', 'المستخدم غير موجود.'); redirect('modules/users/'); }

$roles = db_all('SELECT id, name FROM ' . tbl('roles') . ' ORDER BY name');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'delete') {
        if ($id === auth_id()) { flash('error', 'لا يمكنك حذف نفسك.'); redirect('modules/users/edit.php?id=' . $id); }
        db_delete(tbl('users'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'user', $id, null);
        flash('success', 'تم حذف المستخدم.');
        redirect('modules/users/');
    }

    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = (int)($_POST['role_id'] ?? 0);
    $status= $_POST['status'] ?? 'active';

    if ($name === '') $errors[] = 'الاسم مطلوب';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد غير صحيح';
    if (!$role) $errors[] = 'الدور مطلوب';
    $dup = db_one('SELECT id FROM ' . tbl('users') . ' WHERE email = :e AND id != :id', ['e' => $email, 'id' => $id]);
    if ($dup) $errors[] = 'البريد مستخدم بالفعل';

    if (!$errors) {
        $data = [
            'name' => $name, 'email' => $email,
            'phone' => $phone ?: null, 'role_id' => $role, 'status' => $status,
        ];
        if ($pass !== '') {
            if (strlen($pass) < 8) { $errors[] = 'كلمة المرور 8 أحرف على الأقل'; }
            else $data['password_hash'] = password_hash($pass, CRM_PASSWORD_ALGO);
        }
        if (!$errors) {
            db_update(tbl('users'), $data, 'id = :id', ['id' => $id]);
            activity_log('update', 'user', $id, ['name' => $name]);
            flash('success', 'تم تحديث المستخدم.');
            redirect('modules/users/edit.php?id=' . $id);
        }
    }
    $user = array_merge($user, $_POST);
}

$pageTitle = 'تعديل: ' . $user['name'];
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
      <label class="block text-sm mb-1">الاسم *</label>
      <input name="name" required value="<?= e($user['name']) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">البريد *</label>
      <input type="email" name="email" required value="<?= e($user['email']) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الهاتف</label>
      <input name="phone" value="<?= e($user['phone']) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">كلمة مرور جديدة (اختياري)</label>
      <input type="password" name="password" minlength="8" class="w-full px-3 py-2 border rounded-lg" placeholder="اتركها فارغة للإبقاء عليها">
    </div>
    <div>
      <label class="block text-sm mb-1">الدور *</label>
      <select name="role_id" required class="w-full px-3 py-2 border rounded-lg">
        <?php foreach ($roles as $r): ?>
          <option value="<?= $r['id'] ?>" <?= ((int)$user['role_id'] === (int)$r['id']) ? 'selected' : '' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">الحالة</label>
      <select name="status" class="w-full px-3 py-2 border rounded-lg">
        <option value="active"   <?= $user['status'] === 'active'   ? 'selected' : '' ?>>نشط</option>
        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>موقوف</option>
      </select>
    </div>
  </div>

  <div class="flex justify-between mt-6">
    <div class="flex gap-2">
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
      <a href="<?= url('modules/users/') ?>" class="px-6 py-2 border rounded-lg">رجوع</a>
    </div>
    <?php if ($id !== auth_id()): ?>
      <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟ سيتم حذف المستخدم نهائيًا.')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف</button>
    <?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
