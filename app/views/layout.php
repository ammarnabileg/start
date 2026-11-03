<?php
/** @var string $title  @var string $content  @var string $active */
$user = current_user();
$siteName = setting('site_name', t('لوحة النشر'));
$myWorkspaces = user_workspaces();
$curWs = current_workspace();
$invites = pending_invites();
$owner = is_ws_owner();

// أيقونات بسيطة (خطوط)
$I = [
    'compose_pro' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4M17 15v6m-3-3h6',
    'queue' => 'M4 6h16M4 12h10M4 18h6m10-6l-4 4 4 4',
    'bulk' => 'M14 3v5h5M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8zM9 13h6M9 17h6',
    'inbox_pro' => 'M4 13h4l2 3h4l2-3h4M5 5h14l2 8v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6z',
    'automations' => 'M13 3L4 14h7l-1 7 9-11h-7z',
    'workflows' => 'M6 3a3 3 0 100 6 3 3 0 000-6zm12 12a3 3 0 100 6 3 3 0 000-6zM9 6h6a3 3 0 013 3v6',
    'broadcasts' => 'M3 11v2a1 1 0 001 1h2l5 4V6L6 10H4a1 1 0 00-1 1zm13-3a5 5 0 010 8m3-11a9 9 0 010 14',
    'wa_templates' => 'M4 5h16v10H8l-4 4zM8 9h8M8 12h5',
    'contacts' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0',
    'sequences' => 'M5 6h3v3H5zm0 9h3v3H5zm11-9h3v3h-3zM8 7.5h8M6.5 9v6M17.5 9v4l-9 3.5',
    'wa_profile' => 'M12 21a9 9 0 10-8-4.9L3 21l4.9-1A9 9 0 0012 21zM9 10h.01M15 10h.01M9.5 14a3.5 3.5 0 005 0',
    'analytics' => 'M4 20V10m6 10V4m6 16v-7m4 7H3',
    'inbox_analytics' => 'M21 12a8.5 8.5 0 01-12.6 7.4L3 21l1.6-5.4A8.5 8.5 0 1121 12zM9 14v-2m3 2V9m3 5v-3',
    'ads' => 'M3 11l15-6v14L3 13zm4 1.5V18a2 2 0 004 0v-3.5',
    'ad_create' => 'M12 5v14M5 12h14',
    'ad_audiences' => 'M17 20v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1M10 11a4 4 0 100-8 4 4 0 000 8m11 9v-1a4 4 0 00-3-3.9',
    'leads' => 'M4 4h16v4H4zm0 6h10v10H4zm14 0h2v10h-2z',
    'gmb_reviews' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z',
    'gmb_location' => 'M12 21s-7-6.2-7-11a7 7 0 0114 0c0 4.8-7 11-7 11zm0-8a3 3 0 100-6 3 3 0 000 6z',
    'gmb_media' => 'M4 5h16v14H4zm0 11l5-5 4 4 3-3 4 4M15 9h.01',
    'phone_numbers' => 'M5 4h4l2 5-3 2a11 11 0 005 5l2-3 5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2',
    'sms' => 'M4 5h16v11H8l-4 4zM8 10h.01M12 10h.01M16 10h.01',
    'calls' => 'M15 3h6v6M21 3l-7 7M5 4h4l2 5-3 2a11 11 0 005 5l2-3 5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2',
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
$I['whatsapp'] = 'M12 21a9 9 0 10-8-4.9L3 21l4.9-1A9 9 0 0012 21zM8.5 9.5c0 3 2.5 5.5 5.5 5.5l1.5-1.5-2-1-1 1a4 4 0 01-2.5-2.5l1-1-1-2z';
$I['gmb'] = $I['gmb_location'];
$I['phone'] = $I['phone_numbers'];
$I['connectors'] = 'M14 7h3a5 5 0 010 10h-3m-4 0H7A5 5 0 017 7h3m-2 5h8';
$navItems = $curWs ? nav_items() : array_values(array_filter(nav_items(), function ($i) { return $i['key'] === 'admin'; }));
$I['assistant'] = 'M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z';
$I['admin'] = 'M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7zm-3 9l2 2 4-4';
$navActive = nav_item_for($active);
$icon = function (string $d, string $cls = 'w-5 h-5') {
    return '<svg class="' . $cls . ' shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' . $d . '"/></svg>';
};
$initial = mb_substr(trim($user['name'] ?? '?'), 0, 1);
?><!doctype html>
<?= html_open() ?>
<head>
<?php page_head($title . ' · ' . $siteName); ?>
</head>
<body class="min-h-screen">

<!-- الشريط العلوي -->
<header class="fixed top-0 inset-x-0 h-16 z-40 bg-white/90 backdrop-blur border-b border-ink-200 flex items-center gap-3 px-4">
  <button id="menuBtn" class="lg:hidden p-2 rounded-lg hover:bg-ink-100" aria-label="<?= e(t('القائمة')) ?>"><?= $icon('M4 6h16M4 12h16M4 18h16', 'w-6 h-6') ?></button>
  <a href="<?= e(url('dashboard')) ?>" class="flex items-center gap-2.5">
    <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center font-bold"><?= e(mb_substr($siteName, 0, 1)) ?></span>
    <span class="text-lg font-semibold text-ink-900 hidden sm:inline"><?= e($siteName) ?></span>
  </a>
  <div class="flex-1"></div>
  <div class="hidden sm:flex items-center gap-2"><?= pref_switch() ?></div>
  <?php
    $alertsN = ($curWs && function_exists('alerts_unseen_count') && can('analytics.view') && module_enabled('analytics')) ? alerts_unseen_count((int)$curWs['id']) : 0;
    $bellN = count($invites) + $alertsN;
    $bellHref = $invites ? url('workspaces') : url('alerts');
  ?>
  <a href="<?= e($bellHref) ?>" class="relative p-2 rounded-lg hover:bg-ink-100 <?= $bellN ? '' : 'text-ink-400' ?>" title="<?= e(t('التنبيهات')) ?>">
    <?= $icon('M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1') ?>
    <?php if ($bellN): ?><span class="absolute top-0.5 end-0.5 bg-rose-600 text-[#fff] text-[10px] font-bold rounded-full min-w-[17px] h-[17px] flex items-center justify-center"><?= (int)$bellN ?></span><?php endif; ?>
  </a>
  <details class="relative" data-dropdown>
    <summary class="flex items-center gap-2 rounded-lg bg-ink-100 border border-ink-200 px-2.5 py-1.5 cursor-pointer hover:bg-ink-200/60">
      <span class="w-6 h-6 rounded-full bg-ink-300 text-ink-900 text-xs flex items-center justify-center font-bold"><?= e($initial) ?></span>
      <span class="text-sm text-ink-900 hidden sm:inline"><?= e($user['name'] ?? '') ?></span>
    </summary>
    <div class="menu end-0 mt-2">
      <div class="px-3 py-2 text-xs text-ink-500" dir="ltr" style="text-align:start"><?= e($user['email'] ?? '') ?></div>
      <div class="sm:hidden flex gap-2 px-3 py-2"><?= pref_switch() ?></div>
      <a href="<?= e(url('account')) ?>" class="menu-item"><?= e(t('حسابي')) ?></a>
      <?php if (is_admin()): ?><a href="<?= e(url('system')) ?>" class="menu-item"><?= e(t('إعدادات النظام')) ?></a><?php endif; ?>
      <div class="border-t border-ink-200 my-1.5"></div>
      <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="menu-item"><?= e(t('تسجيل خروج')) ?></button></form>
    </div>
  </details>
</header>

<!-- القائمة الجانبية: ثابتة، واختيار الشركة في آخرها -->
<aside id="sidebar" class="fixed top-16 bottom-0 start-0 w-64 z-30 bg-white border-e border-ink-200 flex flex-col ltr:-translate-x-full rtl:translate-x-full lg:!translate-x-0 transition-transform">
  <nav class="flex-1 overflow-y-auto p-3">
    <?php $lastSection = null; foreach ($navItems as $it): ?>
      <?php if ($it['section'] !== $lastSection): $lastSection = $it['section']; if ($lastSection !== ''): ?><div class="nav-label"><?= e($lastSection) ?></div><?php endif; endif; ?>
      <a href="<?= e(nav_href($it)) ?>" class="nav-item <?= $navActive && $navActive['key'] === $it['key'] ? 'nav-on' : '' ?>"><?= $icon($I[$it['key']] ?? $I['dashboard']) ?><span><?= e($it['label']) ?></span></a>
    <?php endforeach; ?>
    <?php if (!$curWs): ?>
      <a href="<?= e(url('workspaces')) ?>" class="nav-item nav-on"><?= $icon($I['team']) ?><span><?= e(t('شركاتي')) ?></span></a>
    <?php endif; ?>
  </nav>

  <details class="relative border-t border-ink-200" data-dropdown>
    <summary class="flex items-center gap-3 p-4 cursor-pointer hover:bg-ink-100">
      <span class="w-10 h-10 rounded-xl border border-ink-200 bg-brand-50 text-brand-700 flex items-center justify-center text-ink-700"><?= $icon('M4 21V5a1 1 0 011-1h9a1 1 0 011 1v16M15 9h4a1 1 0 011 1v11M3 21h18M8 8h3m-3 4h3m-3 4h3') ?></span>
      <span class="flex-1 min-w-0">
        <span class="block text-[15px] text-ink-900 truncate"><?= e($curWs['name'] ?? t('مفيش شركة')) ?></span>
        <span class="block text-xs text-ink-500 truncate"><?= $curWs ? e(role_label($curWs['my_role'])) : t('اعمل شركة أو اقبل دعوة') ?><?= $invites ? ' · <span class="text-rose-600">' . e(t('%s دعوة', count($invites))) . '</span>' : '' ?></span>
      </span>
      <?= $icon('M8 15l4 4 4-4M8 9l4-4 4 4', 'w-4 h-4 text-ink-500') ?>
    </summary>
    <div class="menu bottom-full start-3 mb-2">
      <?php if ($myWorkspaces): ?><div class="px-3 pt-2 pb-1 text-xs text-ink-400"><?= e(t('شركاتك')) ?></div><?php endif; ?>
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
      <a href="<?= e(url('workspaces')) ?>" class="menu-item"><?= $icon('M12 5v14M5 12h14', 'w-4 h-4') ?> <?= e(t('شركة جديدة / إدارة الشركات')) ?><?= $invites ? ' <span class="badge bg-rose-50 text-rose-600 ms-auto">' . count($invites) . '</span>' : '' ?></a>
    </div>
  </details>
</aside>
<div id="backdrop" class="fixed inset-0 top-16 bg-black/60 z-20 hidden lg:hidden"></div>

<main class="pt-16 lg:ps-64 min-h-screen">
  <div class="max-w-6xl mx-auto px-4 sm:px-8 py-8 lg:py-10">
    <?php if (!empty($navActive['tabs']) && count($navActive['tabs']) > 1 && $navActive['label'] !== $title): ?>
      <div class="text-sm text-ink-500 mb-1"><?= e($navActive['label']) ?></div>
    <?php endif; ?>
    <?php if (empty($hideTitle)): ?>
      <h1 class="text-2xl sm:text-3xl font-semibold text-ink-900 mb-5"><?= e($title) ?></h1>
    <?php endif; ?>
    <?php if (!empty($navActive['tabs']) && count($navActive['tabs']) > 1): ?>
      <nav class="flex gap-1 border-b border-ink-200 mb-7 overflow-x-auto" style="scrollbar-width:none">
        <?php foreach ($navActive['tabs'] as $tk => $tl): $on = $tk === $active || ($tk === $navActive['key'] && in_array($active, $navActive['also'] ?? [], true)); ?>
          <a href="<?= e(url($tk)) ?>" class="whitespace-nowrap px-4 py-2.5 text-sm border-b-2 -mb-px <?= $on ? 'border-brand-600 text-brand-700 font-medium' : 'border-transparent text-ink-500 hover:text-ink-900' ?>"><?= e($tl) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php elseif (empty($hideTitle)): ?><div class="mb-2"></div><?php endif; ?>
    <?php foreach (take_flashes() as $f):
      $cls = $f['type'] === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'; ?>
      <div data-flash="<?= e($f['type']) ?>" class="mb-4 border rounded-lg px-4 py-3 text-sm <?= $cls ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <?php if (!empty($_SESSION['impersonator'])): ?>
      <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm flex flex-wrap items-center gap-3">
        <span><?= e(t('انت داخل دلوقتي بحساب %s عشان تشوف اللي هو شايفه.', ($user['name'] ?? '') . ' (' . ($user['email'] ?? '') . ')')) ?></span>
        <form method="post" action="<?= e(url('impersonate_stop')) ?>" class="ms-auto"><?= csrf_field() ?><button class="btn-ghost !py-1 text-xs"><?= e(t('رجوع لحسابي')) ?></button></form>
      </div>
    <?php endif; ?>
    <?php $ann = trim((string)setting('announcement', '')); if ($ann !== ''): $annType = setting('announcement_type', 'info'); ?>
      <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= $annType === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-brand-50 border-brand-200 text-brand-800' ?>"><?= nl2br(e($ann)) ?></div>
    <?php endif; ?>
    <?php if (setting('maintenance', '0') === '1' && is_admin()): ?>
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-2 text-xs"><?= e(t('وضع الصيانة شغال: المستخدمين العاديين مش هيقدروا يدخلوا.')) ?> <a class="underline" href="<?= e(url('system')) ?>"><?= e(t('إعدادات المنصة')) ?></a></div>
    <?php endif; ?>
    <?php if ($invites && $active !== 'workspaces'): ?>
      <a href="<?= e(url('workspaces')) ?>" class="block mb-4 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-3 text-sm"><?= e(t('عندك')) ?> <?= count($invites) ?> <?= e(t('دعوة لشركة مستنية ردك ←')) ?></a>
    <?php endif; ?>
    <?= $content ?>
    <p class="mt-12 text-xs text-ink-400"><?= e(t('الإصدار')) ?> <?= e(APP_VERSION) ?></p>
  </div>
</main>
<dialog id="modal" class="w-[min(960px,95vw)] h-[min(760px,90vh)] p-0 rounded-2xl border border-ink-200 bg-ink-50 shadow-2xl backdrop:bg-black/50">
  <div class="flex items-center justify-between px-5 h-14 border-b border-ink-200 bg-white">
    <b id="modalTitle" class="text-ink-900"></b>
    <button type="button" data-modal-close class="p-2 rounded-lg hover:bg-ink-100" aria-label="<?= e(t('إغلاق')) ?>">✕</button>
  </div>
  <iframe id="modalFrame" class="w-full h-[calc(100%-3.5rem)] bg-ink-50" title=""></iframe>
</dialog>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
<script src="assets/live.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
