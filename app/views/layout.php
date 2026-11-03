<?php
/** @var string $title  @var string $content  @var string $active */
$user = current_user();
$siteName = setting('site_name', 'لوحة النشر');
$nav = [
    'dashboard' => ['الرئيسية', 'M3 12l9-9 9 9M5 10v10h14V10'],
    'compose'   => ['منشور جديد', 'M12 4v16m8-8H4'],
    'posts'     => ['المنشورات', 'M4 6h16M4 12h16M4 18h10'],
    'calendar'  => ['التقويم', 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z'],
    'inbox'     => ['الرسائل', 'M8 10h8M8 14h5M21 12a9 9 0 01-13.5 7.8L3 21l1.2-4.5A9 9 0 1121 12z'],
    'comments'  => ['التعليقات', 'M7 8h10M7 12h6m-9 8l3-3h11a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14z'],
    'accounts'  => ['الحسابات المربوطة', 'M13.8 10.2a4 4 0 010 5.6l-3 3a4 4 0 01-5.6-5.6l1.5-1.5m5.1-1.5a4 4 0 010-5.6l3-3a4 4 0 015.6 5.6l-1.5 1.5'],
];
$nav['workspaces'] = ['شركاتي', 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1m-6 4h1m4 0h1m-6 4h1m4 0h1'];
if (is_ws_owner()) {
    $nav['team'] = ['فريق الشركة', 'M17 20h5v-2a4 4 0 00-5-3.9M9 20H2v-2a4 4 0 015-3.9m5-4.1a4 4 0 100-8 4 4 0 000 8z'];
    $nav['settings'] = ['إعدادات الشركة', 'M10.3 4.3a1.7 1.7 0 013.4 0 1.7 1.7 0 002.6 1.1 1.7 1.7 0 012.4 2.4 1.7 1.7 0 001 2.5 1.7 1.7 0 010 3.4 1.7 1.7 0 00-1 2.6 1.7 1.7 0 01-2.4 2.4 1.7 1.7 0 00-2.6 1 1.7 1.7 0 01-3.4 0 1.7 1.7 0 00-2.5-1 1.7 1.7 0 01-2.4-2.4 1.7 1.7 0 00-1.1-2.6 1.7 1.7 0 010-3.4 1.7 1.7 0 001.1-2.5 1.7 1.7 0 012.4-2.4 1.7 1.7 0 002.5-1.1zM12 15a3 3 0 100-6 3 3 0 000 6z'];
}
$nav['account'] = ['حسابي', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'];
if (is_admin()) {
    $nav['system'] = ['إعدادات النظام', 'M4 6h16M4 12h16M4 18h16M8 4v4m8 4v4m-4 4v4'];
}
$myWorkspaces = user_workspaces();
$curWs = current_workspace();
$invCount = count(pending_invites());
// لو مالوش شركة، نخفي صفحات الشغل
if (!$curWs) {
    $nav = array_intersect_key($nav, array_flip(['workspaces', 'account', 'system']));
}
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Cairo','sans-serif']}}}}</script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen">
<div class="flex min-h-screen">
  <aside id="sidebar" class="fixed inset-y-0 right-0 z-30 w-64 translate-x-full lg:translate-x-0 lg:static bg-white border-l border-slate-200 transition-transform">
    <div class="h-16 flex items-center px-5 border-b border-slate-200">
      <span class="font-bold text-lg text-indigo-700 truncate"><?= e($siteName) ?></span>
    </div>
    <?php if ($myWorkspaces): ?>
    <form method="post" action="<?= e(url('switch')) ?>" class="p-3 border-b border-slate-200">
      <?= csrf_field() ?>
      <label class="text-[11px] font-semibold text-slate-400 block mb-1">الشركة</label>
      <select name="id" onchange="this.form.submit()" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-bold bg-slate-50">
        <?php foreach ($myWorkspaces as $w): ?><option value="<?= (int)$w['id'] ?>" <?= $curWs && (int)$curWs['id'] === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
    <nav class="p-3 space-y-1">
      <?php foreach ($nav as $key => [$label, $icon]): $on = $active === $key; ?>
        <a href="<?= e(url($key)) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold <?= $on ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100' ?>">
          <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
          <span class="flex-1"><?= e($label) ?></span>
          <?php if ($key === 'workspaces' && $invCount): ?><span class="bg-rose-600 text-white text-[11px] rounded-full px-2 py-0.5"><?= $invCount ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div id="backdrop" class="fixed inset-0 bg-black/30 z-20 hidden lg:hidden"></div>

  <div class="flex-1 min-w-0 flex flex-col">
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8">
      <div class="flex items-center gap-3">
        <button id="menuBtn" class="lg:hidden p-2 rounded-lg hover:bg-slate-100" aria-label="القائمة">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 class="font-bold text-lg"><?= e($title) ?></h1>
      </div>
      <div class="flex items-center gap-3 text-sm">
        <span class="hidden sm:inline text-slate-500"><?= e($user['name'] ?? '') ?></span>
        <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?>
          <button class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100">خروج</button>
        </form>
      </div>
    </header>

    <main class="p-4 lg:p-8 flex-1">
      <?php foreach (take_flashes() as $f):
        $cls = $f['type'] === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-green-50 text-green-800 border-green-200'; ?>
        <div class="mb-4 border rounded-lg px-4 py-3 text-sm <?= $cls ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <?php if ($invCount && $active !== 'workspaces'): ?>
        <a href="<?= e(url('workspaces')) ?>" class="block mb-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3 text-sm font-semibold">عندك <?= $invCount ?> دعوة لشركة مستنية ردك ← اضغط هنا</a>
      <?php endif; ?>
      <?= $content ?>
    </main>
    <footer class="px-8 py-4 text-xs text-slate-400">الإصدار <?= e(APP_VERSION) ?></footer>
  </div>
</div>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
