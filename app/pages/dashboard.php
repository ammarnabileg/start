<?php
$err = null;
$z = zernio_or_null($err);
$stats = ['accounts' => 0, 'scheduled' => 0, 'unread' => 0];
$upcoming = [];
$apiError = null;

if ($z) {
    try {
        $accounts = $z->accounts(zernio_profile());
        $stats['accounts'] = count($accounts);
        $r = $z->posts(['status' => 'scheduled', 'limit' => 5, 'sortBy' => 'scheduled-asc', 'profileId' => zernio_profile()]);
        $upcoming = $r['posts'] ?? [];
        $stats['scheduled'] = (int)($r['pagination']['total'] ?? count($upcoming));
        $c = $z->conversations(['limit' => 50, 'profileId' => zernio_profile()]);
        foreach ($c['data'] ?? [] as $conv) {
            $stats['unread'] += (int)($conv['unreadCount'] ?? 0);
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('الرئيسية'), 'dashboard', function () use ($z, $stats, $upcoming, $apiError) {
    $h = (int)(new DateTime('now', new DateTimeZone(site_timezone())))->format('G');
    $greet = $h < 12 ? t('صباح الخير') : t('مساء الخير');
    $u = current_user();
    ?>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-7">
      <h1 class="text-3xl font-semibold text-ink-900"><?= e(t('%s، %s', $greet, explode(' ', trim($u['name']))[0])) ?></h1>
      <div class="flex gap-2">
        <a href="<?= e(url('inbox')) ?>" class="btn-ghost"><?= e(t('الرسائل')) ?></a>
        <a href="<?= e(url('compose')) ?>" class="btn-primary"><?= e(t('+ منشور جديد')) ?></a>
      </div>
    </div>
    <?php
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $cards = [
        [t('الحسابات المربوطة'), $stats['accounts'], e(t('على %s', ws('name'))), url('accounts'), t('إدارة')],
        [t('منشورات مجدولة'), $stats['scheduled'], t('مستنية ميعاد نشرها'), url('calendar'), t('التقويم')],
        [t('رسائل غير مقروءة'), $stats['unread'], t('في كل الحسابات'), url('inbox'), t('رد')],
    ];
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <?php foreach ($cards as [$label, $num, $sub, $link, $btn]): ?>
        <div class="card p-5">
          <div class="text-[15px] text-ink-700"><?= e($label) ?></div>
          <div class="flex items-end justify-between gap-3 mt-6">
            <div>
              <div class="text-3xl font-semibold text-ink-900"><?= (int)$num ?></div>
              <div class="text-sm text-ink-500 mt-1"><?= $sub ?></div>
            </div>
            <a href="<?= e($link) ?>" class="btn-ghost"><?= e($btn) ?></a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="px-5 py-4 flex items-center justify-between">
        <span class="text-[15px] text-ink-700"><?= e(t('المنشورات القادمة')) ?></span>
        <a href="<?= e(url('posts', ['status' => 'scheduled'])) ?>" class="link text-sm"><?= e(t('عرض الكل')) ?></a>
      </div>
      <?php if (!$upcoming): ?>
        <p class="px-5 pb-6 text-ink-500 text-sm"><?= e(t('مفيش منشورات مجدولة.')) ?> <a class="link" href="<?= e(url('compose')) ?>"><?= e(t('اعمل واحد')) ?></a></p>
      <?php else: foreach ($upcoming as $p): ?>
        <div class="px-5 py-4 border-t border-ink-200 flex items-start justify-between gap-4">
          <p class="text-sm text-ink-800 line-clamp-2 flex-1"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 200)) ?: t('<span class="text-ink-400">(بدون نص)</span>') ?></p>
          <span class="badge whitespace-nowrap"><?= e(fmt_date($p['scheduledFor'] ?? null, 'd/m h:i A')) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php
}, ['hideTitle' => true]);
