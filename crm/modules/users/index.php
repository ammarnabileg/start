<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('users.view');

$pageTitle = 'المستخدمون';
$canManage = has_perm('users.manage');

$q = trim($_GET['q'] ?? '');
$where = '1';
$params = [];
if ($q !== '') {
    $where .= ' AND (u.name LIKE :q OR u.email LIKE :q)';
    $params['q'] = "%$q%";
}

$users = db_all("
    SELECT u.*, r.name AS role_name
    FROM " . tbl('users') . " u
    LEFT JOIN " . tbl('roles') . " r ON r.id = u.role_id
    WHERE $where
    ORDER BY u.created_at DESC
", $params);

require __DIR__ . '/../../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
  <form method="get" class="flex gap-2 flex-1 max-w-md">
    <input name="q" value="<?= e($q) ?>" placeholder="ابحث بالاسم أو البريد..." class="flex-1 px-4 py-2 border rounded-lg">
    <button class="px-4 py-2 bg-gray-200 rounded-lg">بحث</button>
  </form>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/users/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ مستخدم جديد</a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
  <table class="w-full">
    <thead class="bg-gray-50 border-b text-sm">
      <tr>
        <th class="text-right p-3">الاسم</th>
        <th class="text-right p-3">البريد</th>
        <th class="text-right p-3">الدور</th>
        <th class="text-right p-3">الحالة</th>
        <th class="text-right p-3">آخر دخول</th>
        <th class="p-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($users as $u): ?>
        <tr class="hover:bg-gray-50">
          <td class="p-3 font-medium"><?= e($u['name']) ?></td>
          <td class="p-3 text-gray-600"><?= e($u['email']) ?></td>
          <td class="p-3"><span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full text-xs"><?= e($u['role_name'] ?? '—') ?></span></td>
          <td class="p-3">
            <?php if ($u['status'] === 'active'): ?>
              <span class="text-emerald-600">● نشط</span>
            <?php else: ?>
              <span class="text-gray-400">● موقوف</span>
            <?php endif; ?>
          </td>
          <td class="p-3 text-gray-500 text-sm"><?= time_ago($u['last_login_at']) ?></td>
          <td class="p-3 text-left">
            <?php if ($canManage): ?>
              <a href="<?= url('modules/users/edit.php?id=' . $u['id']) ?>" class="text-emerald-600 hover:underline text-sm">تعديل</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="6" class="text-center p-8 text-gray-500">لا يوجد مستخدمون.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
