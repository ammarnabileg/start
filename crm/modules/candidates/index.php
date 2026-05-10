<?php
require_once __DIR__ . '/../../includes/auth.php';
if (!has_any_perm(['candidates.view.own','candidates.view.all','candidates.manage'])) require_perm('candidates.view.own');

$pageTitle = 'المرشحون';
$canManage = has_perm('candidates.manage');
[$ownerSql, $ownerParams] = scope_owned('candidates');

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$where = '1' . $ownerSql;
$params = $ownerParams;
if ($q) { $where .= ' AND (c.name LIKE :q OR c.email LIKE :q OR c.headline LIKE :q OR JSON_SEARCH(c.skills, "one", :q) IS NOT NULL)'; $params['q'] = "%$q%"; }
if ($status) { $where .= ' AND c.status = :s'; $params['s'] = $status; }

$candidates = db_all("
    SELECT c.*, u.name AS owner_name
    FROM " . tbl('candidates') . " c
    LEFT JOIN " . tbl('users') . " u ON u.id = c.owner_id
    WHERE $where
    ORDER BY c.created_at DESC
    LIMIT 200
", $params);

$statuses = ['new'=>'جديد','screening'=>'فرز','interviewing'=>'مقابلات','shortlisted'=>'قائمة قصيرة','offered'=>'عُرض عليه','placed'=>'تم تعيينه','rejected'=>'مرفوض','onhold'=>'مؤجل'];
$levels = ['intern'=>'تدريب','junior'=>'مبتدئ','mid'=>'متوسط','senior'=>'كبير','lead'=>'قائد','manager'=>'مدير','director'=>'مدير عام'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-wrap justify-between items-center gap-3 mb-6">
  <form method="get" class="flex gap-2 flex-1 max-w-2xl">
    <input name="q" value="<?= e($q) ?>" placeholder="بحث بالاسم/الإيميل/مهارة..." class="flex-1 px-4 py-2 border rounded-lg">
    <select name="status" class="px-3 py-2 border rounded-lg">
      <option value="">كل الحالات</option>
      <?php foreach ($statuses as $k => $v): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="px-4 py-2 bg-gray-200 rounded-lg">بحث</button>
  </form>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/candidates/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ مرشح جديد</a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
  <table class="w-full">
    <thead class="bg-gray-50 border-b text-sm">
      <tr>
        <th class="text-right p-3">الاسم</th>
        <th class="text-right p-3">المنصب الحالي</th>
        <th class="text-right p-3">المستوى</th>
        <th class="text-right p-3">الحالة</th>
        <th class="text-right p-3">الراتب المتوقع</th>
        <th class="text-right p-3">المسؤول</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($candidates as $c): ?>
        <tr class="hover:bg-gray-50">
          <td class="p-3">
            <a href="<?= url('modules/candidates/edit.php?id=' . $c['id']) ?>" class="font-medium text-emerald-700 hover:underline"><?= e($c['name']) ?></a>
            <div class="text-xs text-gray-500"><?= e($c['email'] ?? $c['phone'] ?? '') ?></div>
          </td>
          <td class="p-3 text-sm">
            <?= e($c['current_role'] ?? '—') ?>
            <?php if ($c['current_company']): ?><div class="text-xs text-gray-500">@ <?= e($c['current_company']) ?></div><?php endif; ?>
          </td>
          <td class="p-3 text-sm"><?= e($levels[$c['level']] ?? '—') ?></td>
          <td class="p-3"><span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><?= e($statuses[$c['status']] ?? $c['status']) ?></span></td>
          <td class="p-3 text-sm"><?= $c['salary_expectation'] ? format_money($c['salary_expectation'], $c['currency']) : '—' ?></td>
          <td class="p-3 text-sm text-gray-600"><?= e($c['owner_name']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$candidates): ?><tr><td colspan="6" class="text-center p-8 text-gray-500">لا يوجد مرشحون.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
