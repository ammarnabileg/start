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

render('الرئيسية', 'dashboard', function () use ($z, $stats, $upcoming, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $cards = [
        ['الحسابات المربوطة', $stats['accounts'], url('accounts'), 'text-indigo-700'],
        ['منشورات مجدولة', $stats['scheduled'], url('posts', ['status' => 'scheduled']), 'text-blue-700'],
        ['رسائل غير مقروءة', $stats['unread'], url('inbox'), 'text-rose-700'],
    ];
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <?php foreach ($cards as [$label, $num, $link, $color]): ?>
        <a href="<?= e($link) ?>" class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-sm">
          <div class="text-sm text-slate-500"><?= e($label) ?></div>
          <div class="text-3xl font-bold mt-1 <?= $color ?>"><?= (int)$num ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap gap-3 mb-8">
      <a href="<?= e(url('compose')) ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-3 rounded-lg">+ منشور جديد</a>
      <a href="<?= e(url('inbox')) ?>" class="bg-white border border-slate-300 font-semibold px-5 py-3 rounded-lg hover:bg-slate-50">رد على الرسائل</a>
      <a href="<?= e(url('comments')) ?>" class="bg-white border border-slate-300 font-semibold px-5 py-3 rounded-lg hover:bg-slate-50">رد على التعليقات</a>
    </div>

    <?= card_open() ?>
      <div class="px-5 py-4 border-b border-slate-200 font-bold">المنشورات القادمة</div>
      <?php if (!$upcoming): ?>
        <p class="p-5 text-slate-500 text-sm">مفيش منشورات مجدولة.</p>
      <?php else: foreach ($upcoming as $p): ?>
        <div class="px-5 py-4 border-b last:border-0 border-slate-100 flex items-start justify-between gap-4">
          <p class="text-sm line-clamp-2 flex-1"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 200)) ?: '<span class="text-slate-400">(بدون نص)</span>' ?></p>
          <span class="text-xs text-slate-500 whitespace-nowrap"><?= e(fmt_date($p['scheduledFor'] ?? null)) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php
});
