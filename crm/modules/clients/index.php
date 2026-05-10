<?php
require_once __DIR__ . '/../../includes/auth.php';
if (!has_any_perm(['clients.view.own', 'clients.view.all', 'clients.manage'])) require_perm('clients.view.own');

$pageTitle = 'العملاء';
$canManage = has_perm('clients.manage');
[$ownerSql, $ownerParams] = scope_owned('clients');

$q = trim($_GET['q'] ?? '');
$stage = $_GET['stage'] ?? '';
$where = '1';
$params = $ownerParams;
if ($q !== '')   { $where .= ' AND (c.name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q)'; $params['q'] = "%$q%"; }
if ($stage)      { $where .= ' AND c.stage = :stage'; $params['stage'] = $stage; }
$where .= $ownerSql;

$clients = db_all("
    SELECT c.*, u.name AS owner_name
    FROM " . tbl('clients') . " c
    LEFT JOIN " . tbl('users') . " u ON u.id = c.owner_id
    WHERE $where
    ORDER BY c.created_at DESC
    LIMIT 200
", $params);

$stages = ['lead'=>'بداية','qualified'=>'مؤهل','active'=>'نشط','closed'=>'مغلق','lost'=>'ضائع'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-wrap justify-between items-center gap-3 mb-6">
  <form method="get" class="flex gap-2 flex-1 max-w-2xl">
    <input name="q" value="<?= e($q) ?>" placeholder="بحث..." class="flex-1 px-4 py-2 border rounded-lg">
    <select name="stage" class="px-3 py-2 border rounded-lg">
      <option value="">كل المراحل</option>
      <?php foreach ($stages as $k => $v): ?>
        <option value="<?= $k ?>" <?= $stage === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="px-4 py-2 bg-gray-200 rounded-lg">بحث</button>
  </form>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/clients/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ عميل جديد</a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
  <table class="w-full">
    <thead class="bg-gray-50 border-b text-sm">
      <tr>
        <th class="text-right p-3">الاسم</th>
        <th class="text-right p-3">النوع</th>
        <th class="text-right p-3">القطاع</th>
        <th class="text-right p-3">المرحلة</th>
        <th class="text-right p-3">القيمة</th>
        <th class="text-right p-3">المسؤول</th>
        <th class="p-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($clients as $c): ?>
        <tr class="hover:bg-gray-50">
          <td class="p-3">
            <a href="<?= url('modules/clients/view.php?id=' . $c['id']) ?>" class="font-medium text-emerald-700 hover:underline"><?= e($c['name']) ?></a>
            <div class="text-xs text-gray-500"><?= e($c['email'] ?? $c['phone'] ?? '') ?></div>
          </td>
          <td class="p-3 text-sm"><?= e(['company'=>'شركة','individual'=>'فرد','partner'=>'شريك'][$c['type']] ?? $c['type']) ?></td>
          <td class="p-3 text-sm text-gray-600"><?= e($c['industry'] ?? '—') ?></td>
          <td class="p-3"><span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><?= e($stages[$c['stage']] ?? $c['stage']) ?></span></td>
          <td class="p-3 text-sm"><?= format_money($c['value']) ?></td>
          <td class="p-3 text-sm text-gray-600"><?= e($c['owner_name'] ?? '—') ?></td>
          <td class="p-3 text-left">
            <a href="<?= url('modules/clients/view.php?id=' . $c['id']) ?>" class="text-emerald-600 text-sm hover:underline">عرض</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?>
        <tr><td colspan="7" class="text-center p-8 text-gray-500">لا يوجد عملاء.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
