<?php
$nav = [
    ['label' => 'لوحة التحكم', 'icon' => '📊', 'href' => 'dashboard.php',           'perm' => 'dashboard.view'],
    ['label' => 'الأداء',       'icon' => '⚡', 'href' => 'modules/performance/',    'perm' => ['performance.view.own','performance.view.all']],
    ['label' => 'Arena',        'icon' => '🎮', 'href' => 'modules/arena/',          'perm' => 'arena.view'],
    'divider',
    ['label' => 'العملاء',       'icon' => '🏢', 'href' => 'modules/clients/',        'perm' => ['clients.view.own','clients.view.all','clients.manage']],
    ['label' => 'الصفقات',       'icon' => '💼', 'href' => 'modules/deals/',          'perm' => ['deals.view.own','deals.view.all','deals.manage']],
    ['label' => 'المهام',        'icon' => '✅', 'href' => 'modules/tasks/',          'perm' => ['tasks.view.own','tasks.view.all','tasks.manage']],
    'divider',
    ['label' => 'المرشحون',      'icon' => '👤', 'href' => 'modules/candidates/',     'perm' => ['candidates.view.own','candidates.view.all','candidates.manage']],
    ['label' => 'الشواغر',       'icon' => '📋', 'href' => 'modules/vacancies/',      'perm' => ['vacancies.view','vacancies.manage']],
    ['label' => 'التعيينات',     'icon' => '🤝', 'href' => 'modules/placements/',     'perm' => ['placements.view','placements.manage']],
    'divider',
    ['label' => 'AI Copilot',    'icon' => '🤖', 'href' => 'modules/ai/',             'perm' => 'ai.use'],
    'divider',
    ['label' => 'المستخدمون',    'icon' => '👥', 'href' => 'modules/users/',          'perm' => ['users.view','users.manage']],
    ['label' => 'الأدوار',        'icon' => '🛡️', 'href' => 'modules/roles/',          'perm' => ['roles.view','roles.manage']],
    ['label' => 'سجل الأنشطة',   'icon' => '📜', 'href' => 'modules/activities/',     'perm' => 'activities.view'],
    ['label' => 'الإعدادات',     'icon' => '⚙️', 'href' => 'modules/settings/',       'perm' => null],
];
$current = $_SERVER['REQUEST_URI'] ?? '';
?>
<aside class="w-64 bg-gradient-to-b from-emerald-700 to-emerald-900 text-white flex-shrink-0 hidden md:flex flex-col">
  <div class="p-6 border-b border-emerald-600/40">
    <div class="text-2xl font-bold">⚡ <?= e(CRM_APP_NAME) ?></div>
    <div class="text-xs text-emerald-200 mt-1">منصة التشغيل الذكية</div>
  </div>
  <nav class="flex-1 py-3 overflow-y-auto">
    <?php foreach ($nav as $item):
      if ($item === 'divider') {
          echo '<div class="border-t border-emerald-600/30 my-2"></div>';
          continue;
      }
      if ($item['perm'] !== null) {
          $perms = (array)$item['perm'];
          $allowed = false;
          foreach ($perms as $p) { if (has_perm($p)) { $allowed = true; break; } }
          if (!$allowed) continue;
      }
      $isActive = strpos($current, rtrim($item['href'], '/')) !== false && $item['href'] !== 'dashboard.php' || ($item['href'] === 'dashboard.php' && strpos($current, 'dashboard.php') !== false);
    ?>
    <a href="<?= url($item['href']) ?>" class="flex items-center gap-3 px-6 py-2.5 hover:bg-emerald-800 text-sm <?= $isActive ? 'bg-emerald-800 border-r-4 border-white' : '' ?>">
      <span class="text-base"><?= $item['icon'] ?></span>
      <span><?= e($item['label']) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="p-3 border-t border-emerald-600/40 text-xs text-emerald-200 text-center">
    v1.1 · PHP <?= PHP_VERSION ?>
  </div>
</aside>
