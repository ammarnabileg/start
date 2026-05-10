<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('roles.view');

$pageTitle = 'الأدوار والصلاحيات';
$canManage = has_perm('roles.manage');

$roles = db_all('
    SELECT r.*, (SELECT COUNT(*) FROM ' . tbl('users') . ' u WHERE u.role_id = r.id) AS user_count
    FROM ' . tbl('roles') . ' r
    ORDER BY r.is_system DESC, r.name
');

require __DIR__ . '/../../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
  <div></div>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/roles/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ دور جديد</a>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($roles as $r):
    $perms = json_decode($r['permissions'], true) ?: [];
    $isWildcard = in_array('*', $perms, true);
  ?>
  <div class="bg-white p-5 rounded-xl shadow-sm border">
    <div class="flex justify-between items-start">
      <div>
        <h3 class="font-bold text-lg"><?= e($r['name']) ?></h3>
        <span class="text-xs text-gray-500"><?= e($r['key']) ?></span>
      </div>
      <?php if ($r['is_system']): ?>
        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">نظام</span>
      <?php endif; ?>
    </div>
    <div class="mt-3 text-sm text-gray-600">
      <?php if ($isWildcard): ?>
        <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded">جميع الصلاحيات</span>
      <?php else: ?>
        <?= count($perms) ?> صلاحية
      <?php endif; ?>
      · <?= (int)$r['user_count'] ?> مستخدم
    </div>
    <?php if ($canManage): ?>
      <a href="<?= url('modules/roles/edit.php?id=' . $r['id']) ?>" class="block mt-4 text-emerald-600 text-sm hover:underline">تعديل ←</a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
