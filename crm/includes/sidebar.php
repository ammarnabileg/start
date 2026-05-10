<?php
$nav = [
    ['key' => 'dashboard.php', 'label' => 'لوحة التحكم', 'icon' => '📊', 'perm' => 'dashboard.view'],
    ['key' => 'modules/clients/',    'label' => 'العملاء',       'icon' => '🏢', 'perm' => ['clients.view.own', 'clients.view.all', 'clients.manage']],
    ['key' => 'modules/deals/',      'label' => 'الصفقات',       'icon' => '💼', 'perm' => ['deals.view.own', 'deals.view.all', 'deals.manage']],
    ['key' => 'modules/tasks/',      'label' => 'المهام',        'icon' => '✅', 'perm' => ['tasks.view.own', 'tasks.view.all', 'tasks.manage']],
    ['key' => 'modules/users/',      'label' => 'المستخدمون',    'icon' => '👥', 'perm' => ['users.view', 'users.manage']],
    ['key' => 'modules/roles/',      'label' => 'الأدوار',       'icon' => '🛡️', 'perm' => ['roles.view', 'roles.manage']],
    ['key' => 'modules/activities/', 'label' => 'سجل الأنشطة',   'icon' => '📜', 'perm' => 'activities.view'],
];
$current = $_SERVER['REQUEST_URI'] ?? '';
?>
<aside class="w-64 bg-gradient-to-b from-emerald-700 to-emerald-900 text-white flex-shrink-0 hidden md:flex flex-col">
  <div class="p-6 border-b border-emerald-600/40">
    <div class="text-2xl font-bold">⚡ <?= e(CRM_APP_NAME) ?></div>
    <div class="text-xs text-emerald-200 mt-1">منصة التشغيل الذكية</div>
  </div>
  <nav class="flex-1 py-4">
    <?php foreach ($nav as $item):
      $perms = (array)$item['perm'];
      $allowed = false;
      foreach ($perms as $p) { if (has_perm($p)) { $allowed = true; break; } }
      if (!$allowed) continue;
      $isActive = strpos($current, $item['key']) !== false;
    ?>
    <a href="<?= url($item['key']) ?>" class="flex items-center gap-3 px-6 py-3 hover:bg-emerald-800 <?= $isActive ? 'bg-emerald-800 border-l-4 border-white' : '' ?>">
      <span class="text-lg"><?= $item['icon'] ?></span>
      <span><?= e($item['label']) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="p-4 border-t border-emerald-600/40 text-xs text-emerald-200">
    v1.0 · PHP <?= PHP_VERSION ?>
  </div>
</aside>
