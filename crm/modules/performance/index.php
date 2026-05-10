<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
if (!has_any_perm(['performance.view.own', 'performance.view.all'])) require_perm('performance.view.own');

$pageTitle = 'الأداء';
$canViewAll = has_perm('performance.view.all');

$targetUserId = (int)($_GET['user'] ?? auth_id());
if (!$canViewAll && $targetUserId !== auth_id()) $targetUserId = auth_id();

$user = db_one('SELECT * FROM ' . tbl('users') . ' WHERE id = :id', ['id' => $targetUserId]);
$metrics = compute_performance($targetUserId);
$stats = db_one('SELECT * FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => $targetUserId]);

// list of users for selector
$allUsers = $canViewAll ? db_all('SELECT id, name FROM ' . tbl('users') . " WHERE status='active' ORDER BY name") : [];

// leaderboard if can view all
$leaderboard = $canViewAll ? db_all('
    SELECT u.id, u.name, COALESCE(s.performance_score,0) AS perf,
           COALESCE(s.reliability_score,0) AS rel,
           COALESCE(s.total_xp,0) AS xp,
           COALESCE(s.level,1) AS lvl
    FROM ' . tbl('users') . ' u
    LEFT JOIN ' . tbl('user_stats') . " s ON s.user_id = u.id
    WHERE u.status = 'active'
    ORDER BY perf DESC LIMIT 20
") : [];

require __DIR__ . '/../../includes/header.php';
?>

<?php if ($canViewAll): ?>
<form method="get" class="mb-6">
  <select name="user" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg">
    <?php foreach ($allUsers as $u): ?>
      <option value="<?= $u['id'] ?>" <?= $u['id'] == $targetUserId ? 'selected' : '' ?>><?= e($u['name']) ?></option>
    <?php endforeach; ?>
  </select>
</form>
<?php endif; ?>

<div class="bg-gradient-to-l from-emerald-600 to-emerald-700 text-white p-6 rounded-xl mb-6">
  <h2 class="text-2xl font-bold mb-1"><?= e($user['name']) ?></h2>
  <p class="text-emerald-100">آخر 30 يوم</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  <div class="bg-white p-6 rounded-xl shadow-sm border">
    <div class="text-sm text-gray-500">درجة الأداء</div>
    <div class="text-4xl font-bold mt-1 text-emerald-600"><?= number_format($metrics['performance'], 1) ?></div>
    <div class="bg-gray-100 rounded-full h-2 mt-3 overflow-hidden">
      <div class="bg-emerald-500 h-full" style="width:<?= $metrics['performance'] ?>%"></div>
    </div>
  </div>
  <div class="bg-white p-6 rounded-xl shadow-sm border">
    <div class="text-sm text-gray-500">درجة الموثوقية</div>
    <div class="text-4xl font-bold mt-1 text-blue-600"><?= number_format($metrics['reliability'], 1) ?></div>
    <div class="bg-gray-100 rounded-full h-2 mt-3 overflow-hidden">
      <div class="bg-blue-500 h-full" style="width:<?= $metrics['reliability'] ?>%"></div>
    </div>
  </div>
  <div class="bg-white p-6 rounded-xl shadow-sm border">
    <div class="text-sm text-gray-500">المستوى · XP</div>
    <div class="text-4xl font-bold mt-1 text-purple-600">L<?= (int)($stats['level'] ?? 1) ?></div>
    <div class="text-sm text-gray-500 mt-2"><?= number_format((int)($stats['total_xp'] ?? 0)) ?> XP</div>
  </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white p-4 rounded-xl border text-center">
    <div class="text-2xl font-bold"><?= $metrics['tasks_done_30d'] ?>/<?= $metrics['tasks_total_30d'] ?></div>
    <div class="text-sm text-gray-500">مهام منجزة</div>
  </div>
  <div class="bg-white p-4 rounded-xl border text-center">
    <div class="text-2xl font-bold"><?= $metrics['on_time_rate'] ?>%</div>
    <div class="text-sm text-gray-500">في الموعد</div>
  </div>
  <div class="bg-white p-4 rounded-xl border text-center">
    <div class="text-2xl font-bold text-emerald-600"><?= $metrics['deals_won_30d'] ?></div>
    <div class="text-sm text-gray-500">صفقات مكسوبة</div>
  </div>
  <div class="bg-white p-4 rounded-xl border text-center">
    <div class="text-2xl font-bold text-amber-600"><?= $metrics['placements_30d'] ?></div>
    <div class="text-sm text-gray-500">تعيينات</div>
  </div>
</div>

<?php if ($canViewAll && $leaderboard): ?>
<div class="bg-white p-6 rounded-xl shadow-sm border">
  <h2 class="font-bold text-lg mb-4">📊 لوحة الأداء — أعلى 20</h2>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="border-b text-gray-500"><tr>
        <th class="text-right p-2">#</th>
        <th class="text-right p-2">الاسم</th>
        <th class="text-right p-2">المستوى</th>
        <th class="text-right p-2">XP</th>
        <th class="text-right p-2">الأداء</th>
        <th class="text-right p-2">الموثوقية</th>
      </tr></thead>
      <tbody class="divide-y">
        <?php foreach ($leaderboard as $i => $u):
          $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ''));
        ?>
          <tr class="<?= $u['id'] == $targetUserId ? 'bg-emerald-50' : '' ?>">
            <td class="p-2"><?= $medal ?: ($i + 1) ?></td>
            <td class="p-2 font-medium"><a href="?user=<?= $u['id'] ?>" class="hover:underline"><?= e($u['name']) ?></a></td>
            <td class="p-2">L<?= (int)$u['lvl'] ?></td>
            <td class="p-2"><?= number_format((int)$u['xp']) ?></td>
            <td class="p-2"><?= number_format($u['perf'], 1) ?></td>
            <td class="p-2"><?= number_format($u['rel'], 1) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
