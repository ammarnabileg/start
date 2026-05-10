<?php
require_once __DIR__ . '/../../includes/auth.php';
if (!has_any_perm(['tasks.view.own', 'tasks.view.all', 'tasks.manage'])) require_perm('tasks.view.own');

$pageTitle = 'المهام';
$canManage = has_perm('tasks.manage');
[$ownerSql, $ownerParams] = scope_owned('tasks', 't.assignee_id');

$status = $_GET['status'] ?? '';
$where = '1' . $ownerSql;
$params = $ownerParams;
if ($status) { $where .= ' AND t.status = :status'; $params['status'] = $status; }

$tasks = db_all("
    SELECT t.*, u.name AS assignee_name
    FROM " . tbl('tasks') . " t
    LEFT JOIN " . tbl('users') . " u ON u.id = t.assignee_id
    WHERE $where
    ORDER BY (t.status = 'done'), (t.due_at IS NULL), t.due_at ASC, t.priority DESC
    LIMIT 300
", $params);

$priorityColors = ['low'=>'bg-gray-100 text-gray-700','medium'=>'bg-blue-100 text-blue-700','high'=>'bg-orange-100 text-orange-700','urgent'=>'bg-red-100 text-red-700'];
$priorityLabels = ['low'=>'منخفضة','medium'=>'متوسطة','high'=>'عالية','urgent'=>'عاجلة'];
$statusLabels = ['open'=>'مفتوحة','in_progress'=>'قيد التنفيذ','done'=>'مكتملة','cancelled'=>'ملغاة'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
  <div class="flex items-center gap-2 text-sm">
    <a href="?" class="<?= !$status ? 'font-bold text-emerald-700' : 'text-gray-500' ?>">الكل</a>
    <?php foreach ($statusLabels as $k => $v): ?>
      <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'font-bold text-emerald-700' : 'text-gray-500' ?>">· <?= e($v) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/tasks/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ مهمة جديدة</a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
  <table class="w-full">
    <thead class="bg-gray-50 border-b text-sm">
      <tr>
        <th class="text-right p-3">المهمة</th>
        <th class="text-right p-3">الأولوية</th>
        <th class="text-right p-3">الحالة</th>
        <th class="text-right p-3">المسؤول</th>
        <th class="text-right p-3">الاستحقاق</th>
        <th class="p-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($tasks as $t):
        $overdue = $t['due_at'] && $t['status'] !== 'done' && $t['status'] !== 'cancelled' && strtotime($t['due_at']) < time();
      ?>
        <tr class="hover:bg-gray-50 <?= $t['status'] === 'done' ? 'opacity-60' : '' ?>">
          <td class="p-3">
            <a href="<?= url('modules/tasks/edit.php?id=' . $t['id']) ?>" class="font-medium text-emerald-700 hover:underline <?= $t['status'] === 'done' ? 'line-through' : '' ?>"><?= e($t['title']) ?></a>
          </td>
          <td class="p-3"><span class="text-xs px-2 py-0.5 rounded-full <?= $priorityColors[$t['priority']] ?? '' ?>"><?= e($priorityLabels[$t['priority']] ?? $t['priority']) ?></span></td>
          <td class="p-3 text-sm"><?= e($statusLabels[$t['status']] ?? $t['status']) ?></td>
          <td class="p-3 text-sm text-gray-600"><?= e($t['assignee_name']) ?></td>
          <td class="p-3 text-sm <?= $overdue ? 'text-red-600 font-medium' : 'text-gray-600' ?>">
            <?= $t['due_at'] ? format_date($t['due_at']) : '—' ?>
            <?= $overdue ? ' ⚠' : '' ?>
          </td>
          <td class="p-3 text-left">
            <?php if ($canManage && $t['status'] !== 'done'): ?>
              <form method="post" action="<?= url('modules/tasks/complete.php') ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="text-emerald-600 text-sm hover:underline">إنهاء</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><tr><td colspan="6" class="text-center p-8 text-gray-500">لا توجد مهام.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
