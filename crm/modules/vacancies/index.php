<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('vacancies.view');

$pageTitle = 'الشواغر';
$canManage = has_perm('vacancies.manage');

$status = $_GET['status'] ?? '';
$where = '1';
$params = [];
if ($status) { $where .= ' AND v.status = :s'; $params['s'] = $status; }

$vacancies = db_all("
    SELECT v.*, c.name AS client_name, u.name AS owner_name,
           (SELECT COUNT(*) FROM " . tbl('placements') . " p WHERE p.vacancy_id = v.id) AS submission_count
    FROM " . tbl('vacancies') . " v
    LEFT JOIN " . tbl('clients') . " c ON c.id = v.client_id
    LEFT JOIN " . tbl('users') . " u ON u.id = v.owner_id
    WHERE $where
    ORDER BY v.created_at DESC
", $params);

$statuses = ['open'=>'مفتوح','onhold'=>'مؤجل','closed'=>'مغلق','cancelled'=>'ملغى'];
$levels = ['intern'=>'تدريب','junior'=>'مبتدئ','mid'=>'متوسط','senior'=>'كبير','lead'=>'قائد','manager'=>'مدير','director'=>'مدير عام'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
  <div class="flex gap-2">
    <a href="?" class="<?= !$status ? 'font-bold text-emerald-700' : 'text-gray-500' ?>">الكل</a>
    <?php foreach ($statuses as $k => $v): ?>
      <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'font-bold text-emerald-700' : 'text-gray-500' ?>">· <?= e($v) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($canManage): ?>
    <a href="<?= url('modules/vacancies/create.php') ?>" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">+ شاغر جديد</a>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($vacancies as $v):
    $progress = $v['headcount'] > 0 ? ($v['placed_count'] / $v['headcount']) * 100 : 0;
  ?>
  <a href="<?= url('modules/vacancies/edit.php?id=' . $v['id']) ?>" class="bg-white p-5 rounded-xl shadow-sm border hover:shadow-md transition">
    <div class="flex justify-between items-start mb-2">
      <h3 class="font-bold"><?= e($v['title']) ?></h3>
      <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><?= e($statuses[$v['status']] ?? '') ?></span>
    </div>
    <p class="text-sm text-gray-500 mb-3"><?= e($v['client_name']) ?> · <?= e($levels[$v['level']] ?? '—') ?></p>
    <div class="text-xs text-gray-500 mb-1"><?= (int)$v['placed_count'] ?>/<?= (int)$v['headcount'] ?> تم تعيينه · <?= (int)$v['submission_count'] ?> مرشح</div>
    <div class="bg-gray-100 rounded-full h-2 overflow-hidden">
      <div class="bg-emerald-500 h-full" style="width:<?= $progress ?>%"></div>
    </div>
    <?php if ($v['salary_min'] || $v['salary_max']): ?>
      <div class="text-xs text-gray-500 mt-2">💰 <?= number_format((float)$v['salary_min']) ?> - <?= number_format((float)$v['salary_max']) ?> <?= e($v['currency']) ?></div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
  <?php if (!$vacancies): ?>
    <div class="col-span-3 text-center p-12 text-gray-500 bg-white rounded-xl border">لا توجد شواغر.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
