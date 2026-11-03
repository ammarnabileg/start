<?php
/** @var string $title  @var string $content  @var string $active */
$user = current_user();
$siteName = setting('site_name', 'لوحة النشر');
$myWorkspaces = user_workspaces();
$curWs = current_workspace();
$invites = pending_invites();
$owner = is_ws_owner();

$tabs = [];
if ($curWs) {
    $tabs = [
        'dashboard' => 'الرئيسية', 'compose' => 'منشور جديد', 'posts' => 'المنشورات', 'calendar' => 'التقويم',
        'inbox' => 'الرسائل', 'comments' => 'التعليقات', 'accounts' => 'الحسابات',
    ];
    if ($owner) {
        $tabs += ['team' => 'الفريق', 'connectors' => 'Connectors', 'settings' => 'الإعدادات'];
    }
}
$initial = mb_substr(trim($user['name'] ?? '?'), 0, 1);
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<?php page_head($title . ' · ' . $siteName); ?>
</head>
<body class="min-h-screen">
<header class="bg-white border-b border-ink-200 sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 lg:px-6">
    <div class="h-16 flex items-center gap-3">
      <a href="<?= e(url('dashboard')) ?>" class="flex items-center gap-2 shrink-0">
        <span class="w-8 h-8 rounded-lg bg-brand-600 text-white flex items-center justify-center font-bold"><?= e(mb_substr($siteName, 0, 1)) ?></span>
        <span class="font-bold hidden sm:inline"><?= e($siteName) ?></span>
      </a>

      <span class="text-ink-300 text-xl font-light hidden sm:inline">/</span>

      <!-- اختيار الشركة -->
      <details class="relative" data-dropdown>
        <summary class="flex items-center gap-2 rounded-xl px-2.5 py-1.5 hover:bg-ink-50 cursor-pointer select-none">
          <?php if ($curWs): ?>
            <span class="w-6 h-6 rounded-md bg-ink-800 text-white text-xs flex items-center justify-center font-bold"><?= e(mb_substr($curWs['name'], 0, 1)) ?></span>
            <span class="font-semibold text-sm max-w-[140px] truncate"><?= e($curWs['name']) ?></span>
          <?php else: ?>
            <span class="text-sm text-ink-500">اختار شركة</span>
          <?php endif; ?>
          <svg class="w-4 h-4 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
        </summary>
        <div class="menu right-0">
          <div class="px-3 pt-2 pb-1 text-[11px] font-semibold text-ink-400">شركاتك</div>
          <?php foreach ($myWorkspaces as $w): $on = $curWs && (int)$curWs['id'] === (int)$w['id']; ?>
            <form method="post" action="<?= e(url('switch')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
              <button class="menu-item <?= $on ? 'bg-brand-50 text-brand-800' : '' ?>">
                <span class="w-6 h-6 rounded-md <?= $on ? 'bg-brand-600' : 'bg-ink-300' ?> text-white text-xs flex items-center justify-center font-bold shrink-0"><?= e(mb_substr($w['name'], 0, 1)) ?></span>
                <span class="flex-1 truncate font-medium"><?= e($w['name']) ?></span>
                <?php if ($on): ?><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg><?php endif; ?>
              </button>
            </form>
          <?php endforeach; ?>
          <div class="border-t border-ink-100 my-1.5"></div>
          <a href="<?= e(url('workspaces')) ?>" class="menu-item">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> شركة جديدة / إدارة الشركات
          </a>
        </div>
      </details>

      <div class="flex-1"></div>

      <?php if ($invites): ?>
        <a href="<?= e(url('workspaces')) ?>" class="relative p-2 rounded-xl hover:bg-ink-50" title="دعوات">
          <svg class="w-5 h-5 text-ink-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <span class="absolute -top-0.5 -left-0.5 bg-rose-600 text-white text-[10px] rounded-full min-w-[18px] h-[18px] flex items-center justify-center"><?= count($invites) ?></span>
        </a>
      <?php endif; ?>

      <?php if ($curWs): ?>
        <a href="<?= e(url('compose')) ?>" class="btn-primary hidden md:inline-flex !py-2">+ منشور</a>
      <?php endif; ?>

      <!-- قائمة المستخدم -->
      <details class="relative" data-dropdown>
        <summary class="w-9 h-9 rounded-full bg-brand-100 text-brand-800 font-bold flex items-center justify-center cursor-pointer"><?= e($initial) ?></summary>
        <div class="menu left-0">
          <div class="px-3 py-2">
            <div class="font-semibold text-sm"><?= e($user['name'] ?? '') ?></div>
            <div class="text-xs text-ink-500" dir="ltr" style="text-align:right"><?= e($user['email'] ?? '') ?></div>
          </div>
          <div class="border-t border-ink-100 my-1.5"></div>
          <a href="<?= e(url('account')) ?>" class="menu-item">حسابي</a>
          <a href="<?= e(url('workspaces')) ?>" class="menu-item">شركاتي<?= $invites ? ' <span class="badge bg-rose-100 text-rose-700 mr-auto">' . count($invites) . ' دعوة</span>' : '' ?></a>
          <?php if (is_admin()): ?><a href="<?= e(url('system')) ?>" class="menu-item">إعدادات النظام</a><?php endif; ?>
          <div class="border-t border-ink-100 my-1.5"></div>
          <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="menu-item text-red-600">تسجيل خروج</button></form>
        </div>
      </details>
    </div>

    <?php if ($tabs): ?>
    <nav class="flex gap-1 overflow-x-auto -mb-px" style="scrollbar-width:none">
      <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= e(url($key)) ?>" class="tab <?= $active === $key ? 'tab-on' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 lg:px-6 py-6 lg:py-8">
  <h1 class="text-2xl font-bold text-ink-900 mb-6"><?= e($title) ?></h1>
  <?php foreach (take_flashes() as $f):
    $cls = $f['type'] === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'; ?>
    <div class="mb-4 border rounded-xl px-4 py-3 text-sm <?= $cls ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <?php if ($invites && $active !== 'workspaces'): ?>
    <a href="<?= e(url('workspaces')) ?>" class="block mb-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-4 py-3 text-sm font-semibold">عندك <?= count($invites) ?> دعوة لشركة مستنية ردك ←</a>
  <?php endif; ?>
  <?= $content ?>
</main>
<footer class="max-w-7xl mx-auto px-6 pb-6 text-xs text-ink-400">الإصدار <?= e(APP_VERSION) ?></footer>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
