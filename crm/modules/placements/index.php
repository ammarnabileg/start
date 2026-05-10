<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('placements.view');

$pageTitle = 'التعيينات';

$rows = db_all("
    SELECT p.*, c.name AS candidate_name, c.headline, v.title AS vacancy_title, cl.name AS client_name
    FROM " . tbl('placements') . " p
    JOIN " . tbl('candidates') . " c ON c.id = p.candidate_id
    JOIN " . tbl('vacancies') . " v ON v.id = p.vacancy_id
    LEFT JOIN " . tbl('clients') . " cl ON cl.id = v.client_id
    ORDER BY p.created_at DESC
    LIMIT 200
");

$stages = ['submitted'=>'مُرسل','interview'=>'مقابلة','offer'=>'عرض','placed'=>'تم تعيينه','probation_passed'=>'اجتاز التجربة','probation_failed'=>'فشل التجربة','rejected'=>'مرفوض'];
$canManage = has_perm('placements.manage');

require __DIR__ . '/../../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
  <div></div>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/placements/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ تقديم جديد</a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border overflow-x-auto">
  <table class="w-full">
    <thead class="bg-gray-50 border-b text-sm">
      <tr>
        <th class="text-right p-3">المرشح</th>
        <th class="text-right p-3">الشاغر</th>
        <th class="text-right p-3">العميل</th>
        <th class="text-right p-3">المرحلة</th>
        <th class="text-right p-3">تاريخ التعيين</th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($rows as $p): ?>
        <tr class="hover:bg-gray-50">
          <td class="p-3">
            <a href="<?= url('modules/placements/edit.php?id=' . $p['id']) ?>" class="font-medium text-emerald-700 hover:underline"><?= e($p['candidate_name']) ?></a>
            <div class="text-xs text-gray-500"><?= e($p['headline'] ?? '') ?></div>
          </td>
          <td class="p-3 text-sm"><?= e($p['vacancy_title']) ?></td>
          <td class="p-3 text-sm text-gray-600"><?= e($p['client_name']) ?></td>
          <td class="p-3"><span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><?= e($stages[$p['stage']] ?? '') ?></span></td>
          <td class="p-3 text-sm text-gray-600"><?= format_date($p['placed_at'], 'Y-m-d') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="text-center p-8 text-gray-500">لا توجد تعيينات.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
