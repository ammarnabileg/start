<?php
require_once __DIR__ . '/../../includes/auth.php';
if (!has_any_perm(['deals.view.own', 'deals.view.all', 'deals.manage'])) require_perm('deals.view.own');

$pageTitle = 'الصفقات';
$canManage = has_perm('deals.manage');
[$ownerSql, $ownerParams] = scope_owned('deals');

$stages = ['lead'=>'بداية','qualified'=>'مؤهل','proposal'=>'عرض','negotiation'=>'تفاوض','won'=>'فوز','lost'=>'خسارة'];

$where = '1' . $ownerSql;
$deals = db_all("
    SELECT d.*, c.name AS client_name, u.name AS owner_name
    FROM " . tbl('deals') . " d
    LEFT JOIN " . tbl('clients') . " c ON c.id = d.client_id
    LEFT JOIN " . tbl('users') . " u ON u.id = d.owner_id
    WHERE $where
    ORDER BY d.created_at DESC
", $ownerParams);

// Group by stage for kanban
$kanban = [];
foreach ($stages as $k => $_) $kanban[$k] = [];
foreach ($deals as $d) $kanban[$d['stage']][] = $d;

require __DIR__ . '/../../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
  <div class="flex items-center gap-2">
    <a href="?view=kanban" class="px-3 py-1 rounded <?= ($_GET['view'] ?? 'kanban') === 'kanban' ? 'bg-emerald-600 text-white' : 'bg-white border' ?>">كانبان</a>
    <a href="?view=list"   class="px-3 py-1 rounded <?= ($_GET['view'] ?? '') === 'list' ? 'bg-emerald-600 text-white' : 'bg-white border' ?>">قائمة</a>
  </div>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/deals/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ صفقة جديدة</a>
  <?php endif; ?>
</div>

<?php if (($_GET['view'] ?? 'kanban') === 'kanban'): ?>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
  <?php foreach ($stages as $k => $label):
    $stageDeals = $kanban[$k] ?? [];
    $sum = array_sum(array_column($stageDeals, 'amount'));
  ?>
  <div class="bg-gray-100 rounded-lg p-3 min-h-[300px]">
    <div class="font-bold text-sm mb-2 flex justify-between">
      <span><?= e($label) ?></span>
      <span class="text-gray-500"><?= count($stageDeals) ?></span>
    </div>
    <div class="text-xs text-gray-500 mb-3"><?= format_money($sum) ?></div>
    <div class="space-y-2">
      <?php foreach ($stageDeals as $d): ?>
        <a href="<?= url('modules/deals/edit.php?id=' . $d['id']) ?>" class="block bg-white p-3 rounded-lg shadow-sm hover:shadow border">
          <div class="font-medium text-sm"><?= e($d['title']) ?></div>
          <div class="text-xs text-gray-500 mt-1"><?= e($d['client_name'] ?? '—') ?></div>
          <div class="flex justify-between text-xs mt-2">
            <span class="font-bold text-emerald-700"><?= format_money($d['amount'], $d['currency']) ?></span>
            <span class="text-gray-400"><?= (int)$d['probability'] ?>%</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
  <table class="w-full">
    <thead class="bg-gray-50 border-b text-sm">
      <tr>
        <th class="text-right p-3">العنوان</th>
        <th class="text-right p-3">العميل</th>
        <th class="text-right p-3">المرحلة</th>
        <th class="text-right p-3">القيمة</th>
        <th class="text-right p-3">الاحتمالية</th>
        <th class="text-right p-3">المسؤول</th>
        <th class="text-right p-3">إغلاق متوقع</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($deals as $d): ?>
        <tr class="hover:bg-gray-50">
          <td class="p-3"><a href="<?= url('modules/deals/edit.php?id=' . $d['id']) ?>" class="text-emerald-700 font-medium hover:underline"><?= e($d['title']) ?></a></td>
          <td class="p-3 text-sm"><?= e($d['client_name']) ?></td>
          <td class="p-3"><span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full"><?= e($stages[$d['stage']] ?? '') ?></span></td>
          <td class="p-3 text-sm"><?= format_money($d['amount'], $d['currency']) ?></td>
          <td class="p-3 text-sm"><?= (int)$d['probability'] ?>%</td>
          <td class="p-3 text-sm text-gray-600"><?= e($d['owner_name']) ?></td>
          <td class="p-3 text-sm text-gray-600"><?= format_date($d['expected_close_at'], 'Y-m-d') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$deals): ?><tr><td colspan="7" class="text-center p-8 text-gray-500">لا توجد صفقات.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
