<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_perm('roles.manage');

$id = (int)($_GET['id'] ?? 0);
$role = $id ? db_one('SELECT * FROM ' . tbl('roles') . ' WHERE id = :id', ['id' => $id]) : null;
if ($id && !$role) { flash('error', 'الدور غير موجود.'); redirect('modules/roles/'); }
$existingPerms = $role ? (json_decode($role['permissions'], true) ?: []) : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'delete' && $role) {
        if ($role['is_system']) { flash('error', 'لا يمكن حذف دور نظام.'); redirect('modules/roles/edit.php?id=' . $id); }
        $count = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE role_id = :id', ['id' => $id]);
        if ($count > 0) { flash('error', "لا يمكن الحذف. يوجد $count مستخدم بهذا الدور."); redirect('modules/roles/edit.php?id=' . $id); }
        db_delete(tbl('roles'), 'id = :id', ['id' => $id]);
        activity_log('delete', 'role', $id, null);
        flash('success', 'تم حذف الدور.');
        redirect('modules/roles/');
    }

    $key  = trim($_POST['key'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $perms = $_POST['permissions'] ?? [];
    if (!is_array($perms)) $perms = [];

    if (in_array('*', $perms, true)) $perms = ['*'];

    if ($key === '')  $errors[] = 'المفتاح مطلوب';
    if ($name === '') $errors[] = 'الاسم مطلوب';

    $dup = db_one('SELECT id FROM ' . tbl('roles') . ' WHERE `key` = :k AND id != :id', ['k' => $key, 'id' => $id ?: 0]);
    if ($dup) $errors[] = 'المفتاح مستخدم بالفعل';

    if (!$errors) {
        $payload = [
            'key' => $key, 'name' => $name,
            'permissions' => json_encode(array_values($perms), JSON_UNESCAPED_UNICODE),
        ];
        if ($role) {
            db_update(tbl('roles'), $payload, 'id = :id', ['id' => $id]);
            activity_log('update', 'role', $id, ['name' => $name]);
            // Refresh sessions of users with this role on next request via auth_load_permissions on login.
            flash('success', 'تم تحديث الدور.');
            redirect('modules/roles/edit.php?id=' . $id);
        } else {
            $payload['is_system'] = 0;
            $newId = db_insert(tbl('roles'), $payload);
            activity_log('create', 'role', (int)$newId, ['name' => $name]);
            flash('success', 'تم إنشاء الدور.');
            redirect('modules/roles/edit.php?id=' . $newId);
        }
    }
    if ($role) $role = array_merge($role, ['key' => $key, 'name' => $name]);
    $existingPerms = $perms;
}

$pageTitle = $role ? 'تعديل دور: ' . $role['name'] : 'دور جديد';
require __DIR__ . '/../../includes/header.php';
?>

<form method="post" class="bg-white rounded-xl shadow-sm border p-6 max-w-4xl">
  <?= csrf_field() ?>
  <?php if ($errors): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg mb-4 text-sm">
      <ul class="list-disc list-inside"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-2 gap-4 mb-6">
    <div>
      <label class="block text-sm mb-1">المفتاح (English) *</label>
      <input name="key" required value="<?= e($role['key'] ?? ($_POST['key'] ?? '')) ?>" <?= ($role['is_system'] ?? false) ? 'readonly' : '' ?> class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm mb-1">الاسم *</label>
      <input name="name" required value="<?= e($role['name'] ?? ($_POST['name'] ?? '')) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
  </div>

  <h3 class="font-bold text-lg mb-3">الصلاحيات</h3>

  <label class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg mb-4 cursor-pointer">
    <input type="checkbox" name="permissions[]" value="*" <?= in_array('*', $existingPerms, true) ? 'checked' : '' ?>
           onchange="document.querySelectorAll('.perm-cb').forEach(cb=>cb.disabled=this.checked)">
    <span class="font-bold text-amber-800">* جميع الصلاحيات (Admin)</span>
  </label>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <?php foreach (crm_permission_groups() as $group => $perms): ?>
    <div class="border rounded-lg p-4">
      <h4 class="font-bold mb-2 text-emerald-700"><?= e($group) ?></h4>
      <?php foreach ($perms as $key => $label): ?>
        <label class="flex items-start gap-2 py-1 cursor-pointer">
          <input type="checkbox" name="permissions[]" value="<?= e($key) ?>"
                 class="perm-cb mt-1"
                 <?= in_array($key, $existingPerms, true) ? 'checked' : '' ?>
                 <?= in_array('*', $existingPerms, true) ? 'disabled' : '' ?>>
          <span class="text-sm">
            <span class="font-medium"><?= e($label) ?></span>
            <span class="text-xs text-gray-400 block"><?= e($key) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  </div>

  <div class="flex justify-between mt-6">
    <div class="flex gap-2">
      <button class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">حفظ</button>
      <a href="<?= url('modules/roles/') ?>" class="px-6 py-2 border rounded-lg">رجوع</a>
    </div>
    <?php if ($role && !$role['is_system']): ?>
      <button type="submit" name="action" value="delete" onclick="return confirm('متأكد؟')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">حذف الدور</button>
    <?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
