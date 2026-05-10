<?php
require_once __DIR__ . '/includes/auth.php';
require_perm('dashboard.view');

$pageTitle = 'لوحة التحكم';
$user = auth_user();

// scope
[$ownerSqlClients, $ownerParamsClients] = scope_owned('clients');
[$ownerSqlDeals,   $ownerParamsDeals]   = scope_owned('deals');
[$ownerSqlTasks,   $ownerParamsTasks]   = scope_owned('tasks', 'assignee_id');

// stats
$stats = [
    'clients_total'   => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('clients') . " WHERE 1 $ownerSqlClients", $ownerParamsClients),
    'clients_active'  => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('clients') . " WHERE stage='active' $ownerSqlClients", $ownerParamsClients),
    'deals_open'      => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('deals') . " WHERE stage NOT IN ('won','lost') $ownerSqlDeals", $ownerParamsDeals),
    'deals_won_month' => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('deals') . " WHERE stage='won' AND actual_close_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) $ownerSqlDeals", $ownerParamsDeals),
    'pipeline_value'  => (float)db_scalar("SELECT COALESCE(SUM(amount * probability / 100),0) FROM " . tbl('deals') . " WHERE stage NOT IN ('won','lost') $ownerSqlDeals", $ownerParamsDeals),
    'tasks_open'      => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('tasks') . " WHERE status IN ('open','in_progress') $ownerSqlTasks", $ownerParamsTasks),
    'tasks_overdue'   => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('tasks') . " WHERE status IN ('open','in_progress') AND due_at < NOW() $ownerSqlTasks", $ownerParamsTasks),
];

// recent activities (only if perm)
$activities = [];
if (has_perm('activities.view')) {
    $activities = db_all("
        SELECT a.*, u.name AS user_name
        FROM " . tbl('activities') . " a
        LEFT JOIN " . tbl('users') . " u ON u.id = a.user_id
        ORDER BY a.created_at DESC LIMIT 8
    ");
}

// my upcoming tasks
$myTasks = db_all("
    SELECT * FROM " . tbl('tasks') . "
    WHERE assignee_id = :uid AND status IN ('open','in_progress')
    ORDER BY (due_at IS NULL), due_at ASC LIMIT 5
", ['uid' => auth_id()]);

// pipeline by stage
$pipeline = db_all("
    SELECT stage, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
    FROM " . tbl('deals') . "
    WHERE 1 $ownerSqlDeals
    GROUP BY stage
", $ownerParamsDeals);

$stageLabels = ['lead'=>'بداية','qualified'=>'مؤهل','proposal'=>'عرض','negotiation'=>'تفاوض','won'=>'فوز','lost'=>'خسارة'];
$priorityColors = ['low'=>'bg-gray-100 text-gray-700','medium'=>'bg-blue-100 text-blue-700','high'=>'bg-orange-100 text-orange-700','urgent'=>'bg-red-100 text-red-700'];

require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-5 rounded-xl shadow-sm border">
    <div class="text-sm text-gray-500">العملاء</div>
    <div class="text-3xl font-bold mt-1"><?= $stats['clients_total'] ?></div>
    <div class="text-xs text-emerald-600 mt-1"><?= $stats['clients_active'] ?> نشط</div>
  </div>
  <div class="bg-white p-5 rounded-xl shadow-sm border">
    <div class="text-sm text-gray-500">صفقات مفتوحة</div>
    <div class="text-3xl font-bold mt-1"><?= $stats['deals_open'] ?></div>
    <div class="text-xs text-gray-500 mt-1">قيمة مرجحة: <?= format_money($stats['pipeline_value']) ?></div>
  </div>
  <div class="bg-white p-5 rounded-xl shadow-sm border">
    <div class="text-sm text-gray-500">صفقات مكسوبة (30 يوم)</div>
    <div class="text-3xl font-bold mt-1 text-emerald-600"><?= $stats['deals_won_month'] ?></div>
  </div>
  <div class="bg-white p-5 rounded-xl shadow-sm border">
    <div class="text-sm text-gray-500">مهام مفتوحة</div>
    <div class="text-3xl font-bold mt-1"><?= $stats['tasks_open'] ?></div>
    <?php if ($stats['tasks_overdue'] > 0): ?>
      <div class="text-xs text-red-600 mt-1">⚠ <?= $stats['tasks_overdue'] ?> متأخرة</div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border">
    <h2 class="text-lg font-bold mb-4">مسار الصفقات</h2>
    <div class="space-y-3">
      <?php if (!$pipeline): ?>
        <p class="text-gray-500 text-sm">لا توجد صفقات بعد. <a href="<?= url('modules/deals/create.php') ?>" class="text-emerald-600 hover:underline">أضف صفقة جديدة</a></p>
      <?php else:
        $maxCnt = max(array_column($pipeline, 'cnt')) ?: 1;
        foreach ($pipeline as $p):
          $w = ($p['cnt'] / $maxCnt) * 100;
      ?>
        <div>
          <div class="flex justify-between text-sm mb-1">
            <span><?= e($stageLabels[$p['stage']] ?? $p['stage']) ?></span>
            <span class="text-gray-500"><?= $p['cnt'] ?> صفقة · <?= format_money($p['total']) ?></span>
          </div>
          <div class="bg-gray-100 rounded-full h-2 overflow-hidden">
            <div class="bg-emerald-500 h-full rounded-full" style="width: <?= $w ?>%"></div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="bg-white p-6 rounded-xl shadow-sm border">
    <h2 class="text-lg font-bold mb-4">مهامي القادمة</h2>
    <div class="space-y-2">
      <?php if (!$myTasks): ?>
        <p class="text-gray-500 text-sm">لا مهام مفتوحة 👌</p>
      <?php else: foreach ($myTasks as $t): ?>
        <a href="<?= url('modules/tasks/edit.php?id=' . $t['id']) ?>" class="block border rounded-lg p-3 hover:bg-gray-50">
          <div class="flex justify-between items-start gap-2">
            <span class="text-sm font-medium"><?= e($t['title']) ?></span>
            <span class="text-xs px-2 py-0.5 rounded-full <?= $priorityColors[$t['priority']] ?? '' ?>"><?= e($t['priority']) ?></span>
          </div>
          <?php if ($t['due_at']): ?>
            <div class="text-xs text-gray-500 mt-1">📅 <?= format_date($t['due_at']) ?></div>
          <?php endif; ?>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php if ($activities): ?>
<div class="bg-white p-6 rounded-xl shadow-sm border mt-6">
  <h2 class="text-lg font-bold mb-4">آخر الأنشطة</h2>
  <ul class="divide-y">
    <?php foreach ($activities as $a): ?>
      <li class="py-2 flex justify-between text-sm">
        <span><strong><?= e($a['user_name'] ?? 'النظام') ?></strong> · <?= e($a['action']) ?> <?= $a['entity_type'] ? '· ' . e($a['entity_type']) . ' #' . (int)$a['entity_id'] : '' ?></span>
        <span class="text-gray-400"><?= time_ago($a['created_at']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
