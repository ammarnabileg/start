<?php
/** @var string $title  @var string $content  @var string $active */
$user = current_user();
$siteName = setting('site_name', 'لوحة النشر');
$myWorkspaces = user_workspaces();
$curWs = current_workspace();
$invites = pending_invites();
$owner = is_ws_owner();

// أيقونات بسيطة (خطوط)
$I = [
    'dashboard' => 'M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1z',
    'compose' => 'M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z',
    'posts' => 'M4 6h16M4 12h16M4 18h10',
    'calendar' => 'M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
    'inbox' => 'M21 12a8.5 8.5 0 01-12.6 7.4L3 21l1.6-5.4A8.5 8.5 0 1121 12z',
    'comments' => 'M7 9h10M7 13h6m-9 7l3-3h12a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v15z',
    'accounts' => 'M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1m1 3.7a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1',
    'team' => 'M16 20v-1a4 4 0 00-4-4H6a4 4 0 00-4 4v1M9 11a4 4 0 100-8 4 4 0 000 8m13 9v-1a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8',
    'connectors' => 'M15 7a4 4 0 11-3.5 6L5 19.5 2.5 17l1.5-1.5L2.5 14 4 12.5l1.5 1.5 6-6A4 4 0 0115 7z',
    'settings' => 'M12 15a3 3 0 100-6 3 3 0 000 6zm7.4-3a7.4 7.4 0 00-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 00-2-1.2L14.5 3h-5l-.4 2.6a7.6 7.6 0 00-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 000 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 002 1.2l.4 2.6h5l.4-2.6a7.6 7.6 0 002-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z',
];
$sections = [];
if ($curWs) {
    $sections[''] = ['dashboard' => 'الرئيسية'];
    $sections['المحتوى'] = ['compose' => 'منشور جديد', 'posts' => 'المنشورات', 'calendar' => 'التقويم'];
    $sections['التواصل'] = ['inbox' => 'الرسائل', 'comments' => 'التعليقات'];
    $sections['الشركة'] = ['accounts' => 'الحسابات المربوطة'];
    if ($owner) {
        $sections['الشركة'] += ['team' => 'الفريق', 'connectors' => 'Connectors', 'settings' => 'الإعدادات'];
    }
}
$icon = function (string $d, string $cls = 'w-5 h-5') {
    return '<svg class="' . $cls . ' shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' . $d . '"/></svg>';
};
$initial = mb_substr(trim($user['name'] ?? '?'), 0, 1);
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<?php page_head($title . ' · ' . $siteName); ?>
</head>
<body class="min-h-screen">

<!-- الشريط العلوي -->
<header class="fixed top-0 inset-x-0 h-16 z-40 bg-[#1a1918] border-b border-ink-200 flex items-center gap-3 px-4">
  <button id="menuBtn" class="lg:hidden p-2 rounded-lg hover:bg-ink-100" aria-label="القائمة"><?= $icon('M4 6h16M4 12h16M4 18h16', 'w-6 h-6') ?></button>
  <a href="<?= e(url('dashboard')) ?>" class="flex items-center gap-2.5">
    <span class="w-8 h-8 rounded-lg bg-[#d97757] text-[#1a1918] flex items-center justify-center font-bold"><?= e(mb_substr($siteName, 0, 1)) ?></span>
    <span class="serif text-xl font-bold text-ink-900 hidden sm:inline"><?= e($siteName) ?></span>
  </a>
  <div class="flex-1"></div>
  <?php if ($invites): ?>
    <a href="<?= e(url('workspaces')) ?>" class="relative p-2 rounded-lg hover:bg-ink-100" title="دعوات">
      <?= $icon('M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1') ?>
      <span class="absolute top-0.5 left-0.5 bg-[#e5484d] text-[#1a1918] text-[10px] font-bold rounded-full min-w-[17px] h-[17px] flex items-center justify-center"><?= count($invites) ?></span>
    </a>
  <?php else: ?>
    <span class="p-2 text-ink-400"><?= $icon('M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1') ?></span>
  <?php endif; ?>
  <details class="relative" data-dropdown>
    <summary class="flex items-center gap-2 rounded-lg bg-ink-100 border border-ink-200 px-2.5 py-1.5 cursor-pointer hover:bg-ink-200/60">
      <span class="w-6 h-6 rounded-full bg-ink-300 text-ink-900 text-xs flex items-center justify-center font-bold"><?= e($initial) ?></span>
      <span class="text-sm text-ink-900 hidden sm:inline"><?= e($user['name'] ?? '') ?></span>
    </summary>
    <div class="menu left-0 mt-2">
      <div class="px-3 py-2 text-xs text-ink-500" dir="ltr" style="text-align:right"><?= e($user['email'] ?? '') ?></div>
      <a href="<?= e(url('account')) ?>" class="menu-item">حسابي</a>
      <?php if (is_admin()): ?><a href="<?= e(url('system')) ?>" class="menu-item">إعدادات النظام</a><?php endif; ?>
      <div class="border-t border-ink-200 my-1.5"></div>
      <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="menu-item">تسجيل خروج</button></form>
    </div>
  </details>
</header>

<!-- القائمة الجانبية: ثابتة، واختيار الشركة في آخرها -->
<aside id="sidebar" class="fixed top-16 bottom-0 right-0 w-64 z-30 bg-[#1a1918] border-l border-ink-200 flex flex-col translate-x-full lg:translate-x-0 transition-transform">
  <nav class="flex-1 overflow-y-auto p-3">
    <?php foreach ($sections as $label => $items): ?>
      <?php if ($label !== ''): ?><div class="nav-label"><?= e($label) ?></div><?php endif; ?>
      <?php foreach ($items as $key => $text): ?>
        <a href="<?= e(url($key)) ?>" class="nav-item <?= $active === $key ? 'nav-on' : '' ?>"><?= $icon($I[$key]) ?><span><?= e($text) ?></span></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if (!$curWs): ?>
      <a href="<?= e(url('workspaces')) ?>" class="nav-item nav-on"><?= $icon($I['team']) ?><span>شركاتي</span></a>
    <?php endif; ?>
  </nav>

  <details class="relative border-t border-ink-200" data-dropdown>
    <summary class="flex items-center gap-3 p-4 cursor-pointer hover:bg-ink-100">
      <span class="w-10 h-10 rounded-lg border border-ink-300 bg-ink-100 flex items-center justify-center text-ink-700"><?= $icon('M4 21V5a1 1 0 011-1h9a1 1 0 011 1v16M15 9h4a1 1 0 011 1v11M3 21h18M8 8h3m-3 4h3m-3 4h3') ?></span>
      <span class="flex-1 min-w-0">
        <span class="block text-[15px] text-ink-900 truncate"><?= e($curWs['name'] ?? 'مفيش شركة') ?></span>
        <span class="block text-xs text-ink-500 truncate"><?= $curWs ? e(role_label($curWs['my_role'])) : 'اعمل شركة أو اقبل دعوة' ?><?= $invites ? ' · <span class="text-[#e5484d]">' . count($invites) . ' دعوة</span>' : '' ?></span>
      </span>
      <?= $icon('M8 15l4 4 4-4M8 9l4-4 4 4', 'w-4 h-4 text-ink-500') ?>
    </summary>
    <div class="menu bottom-full right-3 mb-2">
      <?php if ($myWorkspaces): ?><div class="px-3 pt-2 pb-1 text-xs text-ink-400">شركاتك</div><?php endif; ?>
      <?php foreach ($myWorkspaces as $w): $on = $curWs && (int)$curWs['id'] === (int)$w['id']; ?>
        <form method="post" action="<?= e(url('switch')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
          <button class="menu-item <?= $on ? 'bg-ink-200/60 text-ink-900' : '' ?>">
            <span class="w-6 h-6 rounded-md bg-ink-300 text-ink-900 text-xs flex items-center justify-center font-bold shrink-0"><?= e(mb_substr($w['name'], 0, 1)) ?></span>
            <span class="flex-1 truncate"><?= e($w['name']) ?></span>
            <?php if ($on): ?><?= $icon('M5 13l4 4L19 7', 'w-4 h-4') ?><?php endif; ?>
          </button>
        </form>
      <?php endforeach; ?>
      <div class="border-t border-ink-200 my-1.5"></div>
      <a href="<?= e(url('workspaces')) ?>" class="menu-item"><?= $icon('M12 5v14M5 12h14', 'w-4 h-4') ?> شركة جديدة / إدارة الشركات<?= $invites ? ' <span class="badge bg-rose-50 text-rose-600 mr-auto">' . count($invites) . '</span>' : '' ?></a>
    </div>
  </details>
</aside>
<div id="backdrop" class="fixed inset-0 top-16 bg-black/60 z-20 hidden lg:hidden"></div>

<main class="pt-16 lg:pr-64 min-h-screen">
  <div class="max-w-6xl mx-auto px-4 sm:px-8 py-8 lg:py-10">
    <?php if (empty($hideTitle)): ?>
      <h1 class="text-3xl font-semibold text-ink-900 mb-7"><?= e($title) ?></h1>
    <?php endif; ?>
    <?php foreach (take_flashes() as $f):
      $cls = $f['type'] === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'; ?>
      <div class="mb-4 border rounded-lg px-4 py-3 text-sm <?= $cls ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <?php if ($invites && $active !== 'workspaces'): ?>
      <a href="<?= e(url('workspaces')) ?>" class="block mb-4 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-3 text-sm">عندك <?= count($invites) ?> دعوة لشركة مستنية ردك ←</a>
    <?php endif; ?>
    <?= $content ?>
    <p class="mt-12 text-xs text-ink-400">الإصدار <?= e(APP_VERSION) ?></p>
  </div>
</main>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
