<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';
require_perm('arena.view');

$pageTitle = 'Arena · جيميفيكيشن';
$uid = auth_id();

user_stats_ensure($uid);
$stats = db_one('SELECT * FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => $uid]);
$level = (int)($stats['level'] ?? 1);
$totalXp = (int)($stats['total_xp'] ?? 0);
$nextLevelXp = xp_required_for_level($level + 1);
$thisLevelXp = xp_required_for_level($level);
$progress = $nextLevelXp > $thisLevelXp ? (($totalXp - $thisLevelXp) / ($nextLevelXp - $thisLevelXp)) * 100 : 0;

$badges = db_all('
    SELECT b.*, ub.user_id IS NOT NULL AS owned, ub.awarded_at
    FROM ' . tbl('badges') . ' b
    LEFT JOIN ' . tbl('user_badges') . ' ub ON ub.badge_id = b.id AND ub.user_id = :u
    ORDER BY owned DESC, FIELD(rarity, "mythic","legendary","epic","rare","common")
', ['u' => $uid]);

$xpHistory = db_all('
    SELECT * FROM ' . tbl('xp_ledger') . '
    WHERE user_id = :u
    ORDER BY at DESC LIMIT 20
', ['u' => $uid]);

$leaderboard = db_all('
    SELECT u.id, u.name, COALESCE(s.total_xp,0) AS xp, COALESCE(s.level,1) AS lvl,
           COALESCE(s.current_streak,0) AS streak
    FROM ' . tbl('users') . ' u
    LEFT JOIN ' . tbl('user_stats') . " s ON s.user_id = u.id
    WHERE u.status = 'active'
    ORDER BY xp DESC LIMIT 10
");

$rarityColors = [
    'common' => 'bg-gray-100 border-gray-300 text-gray-700',
    'rare' => 'bg-blue-50 border-blue-300 text-blue-700',
    'epic' => 'bg-purple-50 border-purple-300 text-purple-700',
    'legendary' => 'bg-amber-50 border-amber-300 text-amber-700',
    'mythic' => 'bg-rose-50 border-rose-300 text-rose-700',
];

require __DIR__ . '/../../includes/header.php';
?>

<div class="bg-gradient-to-l from-purple-600 via-emerald-600 to-amber-500 text-white p-6 rounded-xl mb-6">
  <div class="flex justify-between items-start">
    <div>
      <h2 class="text-3xl font-bold">Level <?= $level ?></h2>
      <p class="opacity-90 mt-1">🔥 streak: <?= (int)($stats['current_streak'] ?? 0) ?> يوم · أطول: <?= (int)($stats['longest_streak'] ?? 0) ?></p>
    </div>
    <div class="text-left">
      <div class="text-3xl font-bold"><?= number_format($totalXp) ?> XP</div>
      <div class="opacity-90 mt-1"><?= number_format($nextLevelXp - $totalXp) ?> XP للمستوى التالي</div>
    </div>
  </div>
  <div class="bg-white/20 rounded-full h-3 mt-4 overflow-hidden">
    <div class="bg-white h-full transition-all" style="width:<?= max(0, min(100, $progress)) ?>%"></div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <div class="bg-white p-6 rounded-xl shadow-sm border">
      <h2 class="font-bold text-lg mb-4">🏅 الشارات (<?= array_sum(array_column($badges, 'owned')) ?>/<?= count($badges) ?>)</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <?php foreach ($badges as $b): ?>
          <div class="border-2 rounded-xl p-4 text-center <?= $rarityColors[$b['rarity']] ?? '' ?> <?= $b['owned'] ? '' : 'opacity-40 grayscale' ?>">
            <div class="text-4xl mb-1"><?= e($b['icon']) ?></div>
            <div class="text-sm font-bold"><?= e($b['name']) ?></div>
            <div class="text-xs mt-1 uppercase tracking-wider"><?= e($b['rarity']) ?></div>
            <?php if ($b['description']): ?>
              <div class="text-xs mt-1 text-gray-500"><?= e($b['description']) ?></div>
            <?php endif; ?>
            <?php if ($b['xp_reward']): ?>
              <div class="text-xs mt-1 font-bold">+<?= (int)$b['xp_reward'] ?> XP</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="space-y-6">
    <div class="bg-white p-6 rounded-xl shadow-sm border">
      <h2 class="font-bold text-lg mb-4">🏆 المتصدرون</h2>
      <ol class="space-y-2">
        <?php foreach ($leaderboard as $i => $row):
          $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : '·'));
        ?>
          <li class="flex justify-between items-center text-sm <?= $row['id'] == $uid ? 'font-bold text-emerald-700' : '' ?>">
            <span><?= $medal ?> <?= e($row['name']) ?> <span class="text-xs text-gray-400">L<?= $row['lvl'] ?></span></span>
            <span class="text-gray-500"><?= number_format((int)$row['xp']) ?> XP</span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border">
      <h2 class="font-bold text-lg mb-4">📜 سجل XP</h2>
      <ul class="text-xs space-y-1 max-h-80 overflow-y-auto">
        <?php foreach ($xpHistory as $x): ?>
          <li class="flex justify-between border-b pb-1">
            <span><?= e($x['reason'] ?? $x['source_type']) ?></span>
            <span class="<?= $x['delta'] > 0 ? 'text-emerald-600' : 'text-red-600' ?> font-bold"><?= $x['delta'] > 0 ? '+' : '' ?><?= (int)$x['delta'] ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$xpHistory): ?>
          <li class="text-gray-500 text-center py-4">ابدأ العمل لتكسب XP!</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
