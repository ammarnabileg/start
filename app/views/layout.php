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
    'media' => 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M15 9h.01',
    'ad_builder' => 'M4 5h6v4H4zM14 5h6v4h-6zM9 15h6v4H9zM7 9v3h10V9M12 12v3',
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
$I['crm'] = 'M4 21V9l8-6 8 6v12M9 21v-6h6v6M9 11h.01M15 11h.01';
$I['crm_leads'] = 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z';
$I['crm_find'] = 'M11 18a7 7 0 100-14 7 7 0 000 14zM21 21l-5-5';
$I['crm_reports'] = 'M4 20V10M10 20V4M16 20v-7M22 20H2';
$I['crm_capture'] = 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2';
$I['crm_settings'] = 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z';
$I['connectors'] = 'M14 7h3a5 5 0 010 10h-3m-4 0H7A5 5 0 017 7h3m-2 5h8';
$navItems = $curWs ? nav_items() : array_values(array_filter(nav_items(), function ($i) { return $i['key'] === 'admin'; }));
$I['knowledge'] = 'M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2zM4 19V5M19 17H6a2 2 0 00-2 2M9 7h6M9 11h4';
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
<script>window.UIC = <?= json_encode(ui_icon_paths()) ?>; window.uic = function (n, c) { return '<svg class="' + (c || 'w-4 h-4') + ' shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' + (window.UIC[n] || window.UIC.chart) + '"/></svg>'; };</script>

<!-- الشريط العلوي -->
<header class="fixed top-0 inset-x-0 h-16 z-[56] bg-white/90 backdrop-blur border-b border-ink-200 flex items-center gap-3 px-4">
  <button id="menuBtn" class="lg:hidden p-2 rounded-lg hover:bg-ink-100" aria-label="<?= e(t('القائمة')) ?>"><?= $icon('M4 6h16M4 12h16M4 18h16', 'w-6 h-6') ?></button>
  <a href="<?= e(url('dashboard')) ?>" class="flex items-center gap-2.5">
    <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center font-bold"><?= e(mb_substr($siteName, 0, 1)) ?></span>
    <span class="text-lg font-semibold text-ink-900 hidden sm:inline"><?= e($siteName) ?></span>
  </a>
  <div class="flex-1 flex justify-center px-2">
    <?php if ($curWs): ?><button type="button" id="ck-open" class="hidden md:flex items-center gap-2 w-full max-w-md h-10 rounded-xl border border-ink-200 bg-ink-50 px-3 text-sm text-ink-400 hover:border-brand-300 hover:bg-white transition">
      <?= $icon('M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z', 'w-4 h-4') ?><span class="flex-1 text-start"><?= e(t('ابحث عن صفحة أو عميل أو إجراء…')) ?></span><kbd class="rounded border border-ink-200 bg-white px-1.5 text-[10px] font-mono" dir="ltr">Ctrl K</kbd></button>
    <button type="button" id="ck-open-m" class="md:hidden tb w-10 justify-center" aria-label="<?= e(t('بحث')) ?>"><?= $icon('M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z') ?></button><?php endif; ?>
  </div>
  <div class="hidden sm:flex items-center gap-2"><?= pref_switch() ?></div>
  <span class="hidden sm:block w-px h-6 bg-ink-200 mx-1"></span>
  <?php
    $alertsN = ($curWs && function_exists('alerts_unseen_count') && can('analytics.view') && module_enabled('analytics')) ? alerts_unseen_count((int)$curWs['id']) : 0;
    $crmN = ($curWs && function_exists('crm_notifs_unseen') && module_enabled('crm') && can('crm.view')) ? crm_notifs_unseen((int)$curWs['id'], (int)(current_user()['id'] ?? 0)) : 0;
    $bellN = count($invites) + $alertsN + $crmN;
    $bellHref = $invites ? url('workspaces') : ($crmN ? url('crm_leads') . '#notifs' : url('alerts'));
  ?>
  <a href="<?= e($bellHref) ?>" class="tb relative w-10 justify-center <?= $bellN ? '!text-ink-900' : '' ?>" title="<?= e(t('التنبيهات')) ?>">
    <?= $icon('M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1') ?>
    <?php if ($bellN): ?><span class="absolute -top-1.5 -end-1.5 bg-rose-600 text-[#fff] text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center ring-2 ring-white"><?= (int)$bellN ?></span><?php endif; ?>
  </a>
  <details class="relative" data-dropdown>
    <summary class="tb gap-2 ps-1 pe-3 cursor-pointer !text-ink-900">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-600 to-cyan-500 text-[#fff] text-xs flex items-center justify-center font-bold"><?= e($initial) ?></span>
      <span class="text-sm font-medium hidden sm:inline"><?= e($user['name'] ?? '') ?></span>
      <svg class="w-3.5 h-3.5 text-ink-400 hidden sm:block" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
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
      <?php $isAct = $navActive && $navActive['key'] === $it['key']; if (!empty($it['tabs']) && count($it['tabs']) > 1): ?>
        <details class="nav-grp group/ng" data-ng="<?= e($it['key']) ?>" <?= $isAct ? 'open' : '' ?>>
          <summary class="nav-item list-none cursor-pointer select-none <?= $isAct ? 'text-ink-900 font-medium' : '' ?>"><?= $icon($I[$it['key']] ?? $I['dashboard']) ?><span class="flex-1"><?= e($it['label']) ?></span><span class="text-[10px] text-ink-400 me-1 group-open/ng:hidden"><?= count($it['tabs']) ?></span><?= $icon('M6 9l6 6 6-6', 'w-4 h-4 text-ink-400 transition group-open/ng:rotate-180') ?></summary>
          <div class="relative ms-[22px] ps-3 border-s border-ink-200 my-0.5 space-y-0.5">
            <?php foreach ($it['tabs'] as $tk => $tl): $sub = $tk === $active || ($tk === $it['key'] && in_array($active, $it['also'] ?? [], true)); ?>
              <a href="<?= e(url($tk)) ?>" class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-[14px] transition <?= $sub ? 'bg-brand-50 text-brand-700 font-semibold' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' ?>"><span class="w-1.5 h-1.5 rounded-full <?= $sub ? 'bg-brand-600' : 'bg-ink-300' ?>"></span><span class="truncate"><?= e($tl) ?></span></a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php else: ?>
      <a href="<?= e(nav_href($it)) ?>" class="nav-item <?= $isAct ? 'nav-on' : '' ?>"><?= $icon($I[$it['key']] ?? $I['dashboard']) ?><span><?= e($it['label']) ?></span></a>
      <?php endif; ?>
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

<?php $cpOn = function_exists('ai_connection') && current_workspace() && can('ai.use') && ai_connection() && (ai_feature_on('assistant') || ai_page_insights((string)($GLOBALS['page'] ?? $active))); ?>
<main class="pt-16 lg:ps-64 min-h-screen transition-[padding] duration-300 <?= $cpOn ? 'lg:pe-14' : '' ?>" id="main">
  <div class="<?= !empty($fullWidth) ? 'max-w-[1800px] px-4 sm:px-6 py-6' : 'max-w-6xl px-4 sm:px-8 py-8 lg:py-10' ?> mx-auto">
    <?php // الصفحات مترابطة: كل صفحة بتوديك للخطوة اللي قبلها واللي بعدها في الرحلة
      $crmOn2 = $curWs && module_enabled('crm') && can('crm.view'); $R = [];
      $R['knowledge'] = [[t('اكتب منشور من ملفك'), url('compose')], [t('الردود التلقائية بتستخدمه'), url('automations')]];
      $R['compose'] = array_filter([[t('التقويم'), url('posts', ['view' => 'cal'])], $crmOn2 ? [t('اعمل صفحة هبوط للمنشور'), url('crm_capture') . '#lp'] : null, [t('أفضل وقت للنشر'), url('analytics')]]);
      $R['posts'] = $R['calendar'] = array_filter([[t('التعليقات على منشوراتك'), url('comments')], [t('أداء المنشورات'), url('analytics')], $crmOn2 ? [t('روابط المنشورات والعملاء اللي جابتهم'), url('crm_reports')] : null]);
      $R['analytics'] = array_filter([[t('جدول منشور في أحسن وقت'), url('compose')], $crmOn2 ? [t('مبيعات كل مصدر'), url('crm_reports')] : null]);
      $R['comments'] = $R['inbox'] = array_filter([$crmOn2 ? [t('العملاء اللي جم من هنا'), url('crm_leads')] : null, [t('ردود تلقائية'), url('automations')]]);
      if ($crmOn2) {
        $R['crm_capture'] = [[t('العملاء اللي اتجمعوا'), url('crm_leads')], [t('أنهي مصدر بيجيب أكتر'), url('crm_reports')], [t('اكتب منشور بلينك متتبع'), url('compose')]];
        $R['crm_leads'] = [[t('هات عملاء أكتر'), url('crm_capture')], [t('تقارير المبيعات'), url('crm_reports')], [t('الرسائل'), url('inbox')]];
        $R['crm_reports'] = [[t('شغل النهارده'), url('crm_leads') . '#due'], [t('حسّن المصادر الضعيفة'), url('crm_capture')], [t('أداء المحتوى'), url('analytics')]];
        $R['crm_settings'] = [[t('العملاء'), url('crm_leads')]];
      }
      $rel = $R[$active] ?? [];
    ?>
    <?php // هيدر الصفحة: العنوان نفسه زرار بيفتح popover فيه الصفحات الشقيقة والمرتبطة (بدل صف التابات)
      $sibs = (!empty($navActive['tabs']) && count($navActive['tabs']) > 1) ? $navActive['tabs'] : [];
      $sibOn = function ($tk) use ($active, $navActive) { return $tk === $active || ($tk === ($navActive['key'] ?? '') && in_array($active, $navActive['also'] ?? [], true)); };
      $keys = array_keys($sibs); $cur = null; foreach ($keys as $ix => $tk) if ($sibOn($tk)) $cur = $ix;
      $hasPop = $sibs || $rel;
      $pgDesc = ['crm_leads' => t('القايمة واللوحة والمتابعات'), 'leads' => t('اللي سجّلوا من إعلاناتك'), 'crm_reports' => t('الفريق والمصادر والتوقعات'), 'crm_settings' => t('المراحل والحقول والقواعد'),
        'analytics' => t('أداء منشوراتك'), 'inbox_analytics' => t('سرعة الرد وحجم الرسائل'), 'reports' => t('تقارير PDF ومجدولة'), 'goals' => t('أهداف الشهر والتقدم'), 'alerts' => t('اللي محتاج انتباهك'),
        'posts' => t('كل المنشورات بالفلاتر'), 'calendar' => t('شهر وأسبوع بالسحب والإفلات'), 'approvals' => t('منشورات مستنية موافقة'), 'post_log' => t('مين نشر إيه وإمتى'),
        'inbox' => t('كل المحادثات'), 'comments' => t('تعليقات منشوراتك'), 'knowledge' => t('خدماتك وأسعارك وجمهورك'), 'media' => t('صورك وفيديوهاتك'), 'accounts' => t('حسابات السوشيال'), 'connectors' => t('SMTP وApollo والذكاء الاصطناعي'),
        'crm_find' => t('دوّر على صناع القرار'), 'crm' => t('شركات تستهدفها'), 'ads' => t('حملاتك الشغالة'), 'ad_builder' => t('حملة خطوة بخطوة'), 'ad_create' => t('حملة في دقيقة'), 'ad_audiences' => t('جماهير محفوظة')];
    ?>
    <?php if (empty($hideTitle)): ?>
    <div class="flex items-center gap-2 mb-6 min-w-0">
      <?php if ($hasPop): ?>
        <button type="button" id="pg-sw-btn" popovertarget="pg-sw" class="group flex items-center gap-2 min-w-0 rounded-xl -ms-2 px-2 py-1 hover:bg-ink-100 transition text-start" aria-haspopup="menu">
          <span class="min-w-0"><?php if ($sibs && ($navActive['label'] ?? '') !== $title): ?><span class="block text-xs text-ink-500"><?= e($navActive['label']) ?><?= $cur !== null ? ' · ' . ($cur + 1) . '/' . count($sibs) : '' ?></span><?php endif; ?>
            <span class="flex items-center gap-2"><h1 class="text-2xl sm:text-3xl font-semibold text-ink-900 truncate"><?= e($title) ?></h1><?= $icon('M6 9l6 6 6-6', 'w-5 h-5 text-ink-400 group-hover:text-ink-700 transition') ?></span></span>
        </button>
        <?php if ($sibs && count($sibs) > 1 && $cur !== null): ?>
          <span class="ms-1 hidden sm:inline-flex items-center rounded-xl border border-ink-200 bg-white">
            <a href="<?= e(url($keys[($cur - 1 + count($keys)) % count($keys)])) ?>" class="p-2 text-ink-500 hover:text-ink-900" title="<?= e($sibs[$keys[($cur - 1 + count($keys)) % count($keys)]]) ?> ([)"><?= $icon(is_rtl() ? 'M9 6l6 6-6 6' : 'M15 6l-6 6 6 6', 'w-4 h-4') ?></a>
            <span class="w-px h-5 bg-ink-200"></span>
            <a href="<?= e(url($keys[($cur + 1) % count($keys)])) ?>" class="p-2 text-ink-500 hover:text-ink-900" title="<?= e($sibs[$keys[($cur + 1) % count($keys)]]) ?> (])"><?= $icon(is_rtl() ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6', 'w-4 h-4') ?></a>
          </span>
        <?php endif; ?>
        <div id="pg-sw" popover class="m-0 w-[min(560px,94vw)] rounded-2xl border border-ink-200 bg-white p-0 shadow-2xl text-ink-900 backdrop:bg-transparent">
          <div class="grid <?= $sibs && $rel ? 'sm:grid-cols-[1.3fr_1fr]' : '' ?>">
            <?php if ($sibs): ?><div class="p-2"><div class="px-2 pt-1 pb-2 text-[11px] font-semibold text-ink-400"><?= e($navActive['label']) ?></div>
              <?php $n = 0; foreach ($sibs as $tk => $tl): $n++; $on = $sibOn($tk); ?>
                <a href="<?= e(url($tk)) ?>" data-sw-k="<?= $n ?>" class="flex items-center gap-3 rounded-xl px-2.5 py-2 <?= $on ? 'bg-brand-50' : 'hover:bg-ink-50' ?>">
                  <span class="w-8 h-8 rounded-lg grid place-items-center <?= $on ? 'bg-brand-600 text-[#fff]' : 'bg-ink-100 text-ink-600' ?>"><?= $icon($I[$tk] ?? $I[$navActive['key']] ?? $I['dashboard'], 'w-4 h-4') ?></span>
                  <span class="flex-1 min-w-0"><span class="block text-sm <?= $on ? 'font-semibold text-brand-800' : 'font-medium' ?>"><?= e($tl) ?></span><?php if (!empty($pgDesc[$tk])): ?><span class="block text-[11px] text-ink-500 truncate"><?= e($pgDesc[$tk]) ?></span><?php endif; ?></span>
                  <kbd class="text-[10px] font-mono text-ink-400 border border-ink-200 rounded px-1"><?= $n ?></kbd>
                </a>
              <?php endforeach; ?></div><?php endif; ?>
            <?php if ($rel): ?><div class="p-2 <?= $sibs ? 'border-t sm:border-t-0 sm:border-s border-ink-100 bg-ink-50/60' : '' ?>"><div class="px-2 pt-1 pb-2 text-[11px] font-semibold text-ink-400"><?= e(t('الخطوة اللي بعدها')) ?></div>
              <?php foreach ($rel as [$rl, $ru]): ?><a href="<?= e($ru) ?>" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-ink-700 hover:bg-white hover:text-brand-700"><?= $icon('M5 12h14M13 6l6 6-6 6', 'w-4 h-4 text-ink-400 rtl:-scale-x-100') ?><span class="min-w-0 truncate"><?= e($rl) ?></span></a><?php endforeach; ?></div><?php endif; ?>
          </div>
          <div class="flex items-center gap-3 border-t border-ink-100 px-4 py-2 text-[11px] text-ink-400"><?php if ($sibs): ?><span>1–<?= min(9, count($sibs)) ?> <?= e(t('للتنقل')) ?></span><span>[ ] <?= e(t('اللي قبلها واللي بعدها')) ?></span><?php endif; ?><span class="ms-auto">Ctrl K <?= e(t('للبحث في كل حاجة')) ?></span></div>
        </div>
      <?php else: ?>
        <h1 class="text-2xl sm:text-3xl font-semibold text-ink-900"><?= e($title) ?></h1>
      <?php endif; ?>
    </div>
    <?php endif; ?>
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
<?php if ($cpOn): $cpKeys = ai_page_insights((string)($GLOBALS['page'] ?? $active)); ?>
<!-- لوحة ✨ الجانبية -->
<button type="button" id="cp-rail" data-cp-toggle class="group hidden lg:flex fixed top-16 bottom-0 end-0 z-50 w-14 flex-col items-center gap-4 py-5 border-s border-ink-200 bg-white text-brand-600 hover:bg-gradient-to-b hover:from-brand-50 hover:to-cyan-50/60 hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 transition-colors" title="<?= e(t('اسأل الذكاء الاصطناعي')) ?> (Ctrl+J)" aria-label="<?= e(t('اسأل الذكاء الاصطناعي')) ?>">
  <svg class="w-6 h-6 transition group-hover:scale-110 group-hover:rotate-12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>
  <span class="text-xs font-medium tracking-wide text-ink-500 group-hover:text-brand-700 transition" style="writing-mode:vertical-rl"><?= e(t('اسأل الذكاء الاصطناعي')) ?></span>
  <kbd class="mt-auto rounded-md border border-ink-200 bg-ink-50 px-1 py-0.5 text-[9px] font-mono text-ink-400 opacity-0 group-hover:opacity-100 transition" dir="ltr">Ctrl J</kbd>
</button>
<button type="button" data-cp-toggle class="lg:hidden fixed bottom-5 end-5 z-40 w-14 h-14 rounded-full text-[#fff] shadow-xl shadow-brand-600/30 bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 flex items-center justify-center" aria-label="<?= e(t('اسأل الذكاء الاصطناعي')) ?>">
  <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>
</button>
<aside id="cp" role="dialog" aria-label="AI" class="group/cp fixed z-40 top-16 bottom-0 end-0 w-full sm:w-[26rem] bg-white border-s border-ink-200 shadow-2xl lg:shadow-none flex flex-col translate-x-full rtl:-translate-x-full opacity-0 pointer-events-none transition-all duration-300 ease-out">
  <div id="cp-grip" class="hidden lg:block absolute inset-y-0 -start-1 w-2 cursor-col-resize z-10 after:absolute after:inset-y-0 after:start-[3px] after:w-0.5 after:bg-brand-500 after:opacity-0 hover:after:opacity-100 after:transition" title="<?= e(t('اسحب لتغيير العرض')) ?>"></div>
  <div class="flex items-center gap-2 px-4 h-14 border-b border-ink-200 shrink-0">
    <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-brand-600 to-cyan-500 text-[#fff] flex items-center justify-center"><?= ai_icon('spark', 'w-4 h-4') ?></span>
    <div class="flex-1 min-w-0"><b class="block text-sm text-ink-900 leading-tight"><?= e(t('الذكاء الاصطناعي')) ?></b><span class="block text-[11px] text-ink-500 truncate"><?= e($title) ?></span></div>
    <button type="button" id="cp-clear" class="p-2 rounded-lg text-ink-400 hover:text-ink-900 hover:bg-ink-100" title="<?= e(t('محادثة جديدة')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
    <button type="button" id="cp-wide" class="hidden lg:block p-2 rounded-lg text-ink-400 hover:text-ink-900 hover:bg-ink-100" title="<?= e(t('توسيع')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 9V4h5M20 15v5h-5M4 4l6 6M20 20l-6-6"/></svg></button>
    <button type="button" data-cp-toggle class="p-2 rounded-lg text-ink-400 hover:text-ink-900 hover:bg-ink-100" title="<?= e(t('إغلاق')) ?> (Esc)"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
  </div>
  <div id="cp-thread" class="flex-1 overflow-y-auto px-4 py-4 space-y-4 scroll-smooth"></div>
  <?php if (ai_feature_on('assistant')): ?>
  <form id="cp-form" class="p-3 border-t border-ink-200 shrink-0">
    <div class="rounded-2xl border border-ink-200 bg-ink-50 focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-500/15 transition">
      <textarea id="cp-input" rows="1" maxlength="2000" class="block w-full resize-none bg-transparent !bg-transparent border-0 px-3.5 pt-3 pb-1 text-sm focus:ring-0 outline-none max-h-40" placeholder="<?= e(t('اسأل عن أرقامك، اطلب كتابة منشور، أو اسأل كيف تفعل شيئًا...')) ?>"></textarea>
      <div class="flex items-center gap-2 px-2 pb-2">
        <label class="inline-flex items-center gap-1.5 text-[11px] text-ink-500 cursor-pointer select-none rounded-lg px-2 py-1 hover:bg-ink-100" title="<?= e(t('يرسل محتوى الصفحة الظاهر مع سؤالك')) ?>"><input type="checkbox" id="cp-page" class="w-3.5 h-3.5"> <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7zM14 3v5h5"/></svg> <?= e(t('اقرأ هذه الصفحة')) ?></label>
        <button type="button" id="cp-mic" class="hidden w-8 h-8 rounded-xl text-ink-500 hover:text-ink-900 hover:bg-ink-100 flex items-center justify-center transition" title="<?= e(t('تحدّث')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3a3 3 0 013 3v6a3 3 0 01-6 0V6a3 3 0 013-3zM5 11a7 7 0 0014 0M12 18v3"/></svg></button>
        <button id="cp-send" class="ms-auto w-8 h-8 rounded-xl bg-brand-600 text-[#fff] flex items-center justify-center hover:brightness-110 disabled:opacity-40" aria-label="<?= e(t('إرسال')) ?>"><svg class="w-4 h-4 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
      </div>
    </div>
    <p class="text-[10px] text-ink-400 mt-1.5 px-1">Enter <?= e(t('للإرسال')) ?> · Shift+Enter <?= e(t('لسطر جديد')) ?> · <b class="font-semibold text-ink-500">/</b> <?= e(t('للأوامر')) ?> · Ctrl+J</p>
  </form>
  <?php endif; ?>
</aside>
<script>window.CP = <?= json_encode(['title' => $title, 'chat' => ai_feature_on('assistant'),
  'hi' => t('أهلًا! أنا مساعدك في المنصة كلها.'), 'hiSub' => t('أعرف أرقام شركتك والصفحة التي أنت فيها. جرّب:'),
  'samples' => [t('ما أفضل منشور هذا الشهر ولماذا؟'), t('اكتب منشورًا لعرض نهاية الأسبوع'), t('ماذا أفعل اليوم لتحسين التفاعل؟'), t('كيف أجدول منشورًا؟')],
  'spark' => ai_icon('spark', 'w-3.5 h-3.5'), 'sparkBig' => ai_icon('spark', 'w-7 h-7'), 'copy' => t('نسخ'), 'insert' => t('إدراج في الخانة'), 'run' => t('تشغيل'), 'thinking' => t('يفكر...'), 'csv' => t('تحميل CSV'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'), 'confirmClear' => t('بدء محادثة جديدة؟'),
  'regen' => t('رد تاني'), 'good' => t('رد مفيد'), 'bad' => t('رد مش مفيد'), 'stop' => t('إيقاف'), 'stopped' => t('تم الإيقاف'), 'newer' => t('رسائل جديدة'), 'page' => $cpKeys,
  'refine' => [[t('أقصر'), t('اختصر ردك السابق للنصف بنفس المعنى.')], [t('أطول وأوضح'), t('وسّع ردك السابق بتفاصيل وأمثلة أكثر.')], [t('بالإنجليزي'), t('ترجم ردك السابق للإنجليزية.')], [t('نقاط'), t('أعد كتابة ردك السابق كنقاط مختصرة.')], [t('حوّله لمنشور'), t('حوّل ردك السابق لمنشور سوشيال ميديا جاهز للنشر.')]],
  'cmds' => [[t('اقرأ هذه الصفحة (تشغيل/إيقاف)'), 'page', 'M7 3h7l5 5v13H7zM14 3v5h5'], [t('توسيع اللوحة'), 'wide', 'M4 9V4h5M20 15v5h-5M4 4l6 6M20 20l-6-6'], [t('محادثة جديدة'), 'clear', 'M12 5v14M5 12h14'], [t('اكتب منشورًا'), t('اكتب منشورًا لشركتي عن أحدث عروضنا.'), 'M4 20h4L18.5 9.5a2.1 2.1 0 00-4-4L4 16v4z'], [t('لخّص أداء الأسبوع'), t('لخّص أداء الأسبوع الماضي بالأرقام.'), 'M4 20V10M10 20V4M16 20v-8M21 20H3']]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="assets/copilot.js?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php if ($curWs):
  $ckPages = []; foreach ($navItems as $it) { if ($it['key'] === 'admin') continue; foreach (($it['tabs'] ?? [$it['key'] => $it['label']]) as $tk => $tl) $ckPages[] = ['l' => $tl, 'g' => $it['label'] !== $tl ? $it['label'] : '', 's' => $it['section'], 'u' => url($tk), 'i' => $I[$it['key']] ?? $I['dashboard']]; }
  $ckAct = [];
  if (can('posts.create')) { $ckAct[] = ['l' => t('اكتب منشور جديد'), 'u' => url('compose'), 'k' => 'n']; $ckAct[] = ['l' => t('رفع منشورات من Excel'), 'u' => url('bulk')]; }
  if (module_enabled('crm') && can('crm.view')) { $ckAct[] = ['l' => t('ضيف عميل جديد'), 'u' => url('crm_leads') . '#add', 'k' => 'l']; $ckAct[] = ['l' => t('شغل النهارده (المتابعات المستحقة)'), 'u' => url('crm_leads') . '#due']; $ckAct[] = ['l' => t('اعمل صفحة هبوط أو QR'), 'u' => url('crm_capture') . '#lp']; }
  $ckAct[] = ['l' => t('الرسائل اللي مستنية رد'), 'u' => url('inbox')];
?>
<div id="ck" class="fixed inset-0 z-[90] hidden items-start justify-center bg-ink-900/40 backdrop-blur-sm p-4 pt-[12vh]" aria-hidden="true">
  <div class="w-full max-w-xl rounded-2xl bg-white shadow-2xl overflow-hidden" role="dialog" aria-label="<?= e(t('بحث')) ?>">
    <div class="flex items-center gap-2 px-4 h-14 border-b border-ink-200"><?= $icon('M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 110-15 7.5 7.5 0 010 15z', 'w-5 h-5 text-ink-400') ?><input id="ck-in" class="flex-1 h-full bg-transparent outline-none text-[15px]" placeholder="<?= e(t('ابحث عن صفحة أو عميل أو إجراء…')) ?>" autocomplete="off" dir="auto"><kbd class="rounded border border-ink-200 px-1.5 text-[10px] text-ink-400 font-mono">Esc</kbd></div>
    <div id="ck-list" class="max-h-[60vh] overflow-y-auto p-2"></div>
    <div class="flex items-center gap-3 px-4 py-2 border-t border-ink-100 text-[11px] text-ink-400"><span>↑↓ <?= e(t('للتنقل')) ?></span><span>Enter <?= e(t('للفتح')) ?></span><span class="ms-auto"><?= e(t('من غير ذكاء اصطناعي')) ?></span></div>
  </div>
</div>
<script>window.CK = <?= json_encode(['pages' => $ckPages, 'acts' => $ckAct, 'search' => url('search'), 'lead' => url('crm_leads') . '#lead-', 'tr' => ['pages' => t('الصفحات'), 'acts' => t('إجراءات سريعة'), 'leads' => t('العملاء'), 'none' => t('مفيش نتايج.'), 'recent' => t('فتحتها قريب')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="assets/cmdk.js?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>
</body>
</html>
