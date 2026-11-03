<?php
$err = null;
$z = zernio_or_null($err);
$tz = new DateTimeZone(site_timezone());

$ym = (string)($_GET['m'] ?? '');
$first = DateTime::createFromFormat('!Y-m', $ym, $tz) ?: new DateTime('first day of this month 00:00', $tz);
$first->modify('first day of this month');
$last = (clone $first)->modify('last day of this month');

$byDay = [];
$apiError = null;
if ($z) {
    try {
        $r = $z->posts([
            'dateFrom' => $first->format('Y-m-d'), 'dateTo' => $last->format('Y-m-d'),
            'limit' => 500, 'sortBy' => 'scheduled-asc', 'profileId' => setting('profile_id') ?: null,
        ]);
        foreach ($r['posts'] ?? [] as $p) {
            $when = $p['scheduledFor'] ?? $p['publishedAt'] ?? $p['createdAt'] ?? null;
            if (!$when) continue;
            $d = new DateTime($when);
            $d->setTimezone($tz);
            $byDay[$d->format('Y-m-d')][] = ['time' => $d->format('h:i A'), 'post' => $p];
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render('التقويم', 'calendar', function () use ($z, $first, $byDay, $apiError, $tz) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    $months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
    $prev = (clone $first)->modify('-1 month')->format('Y-m');
    $next = (clone $first)->modify('+1 month')->format('Y-m');
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    // السبت أول الأسبوع
    $offset = ((int)$first->format('w') + 1) % 7;
    $days = (int)$first->format('t');
    ?>
    <div class="flex items-center justify-between mb-4">
      <a href="<?= e(url('calendar', ['m' => $prev])) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-lg">→ الشهر اللي فات</a>
      <h2 class="text-xl font-bold"><?= $months[(int)$first->format('n') - 1] ?> <?= $first->format('Y') ?></h2>
      <a href="<?= e(url('calendar', ['m' => $next])) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-lg">الشهر الجاي ←</a>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
      <div class="grid grid-cols-7 min-w-[700px] text-center text-xs font-bold text-slate-500 border-b border-slate-200">
        <?php foreach (['السبت','الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة'] as $d): ?><div class="py-2"><?= $d ?></div><?php endforeach; ?>
      </div>
      <div class="grid grid-cols-7 min-w-[700px]">
        <?php for ($i = 0; $i < $offset; $i++): ?><div class="min-h-[110px] border-b border-l border-slate-100 bg-slate-50"></div><?php endfor; ?>
        <?php for ($d = 1; $d <= $days; $d++):
            $key = $first->format('Y-m-') . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
            $items = $byDay[$key] ?? []; ?>
          <div class="min-h-[110px] border-b border-l border-slate-100 p-1.5 <?= $key === $today ? 'bg-indigo-50' : '' ?>">
            <div class="text-xs font-bold mb-1 <?= $key === $today ? 'text-indigo-700' : 'text-slate-500' ?>"><?= $d ?></div>
            <?php foreach (array_slice($items, 0, 4) as $it): [$lbl, $cls] = status_label((string)($it['post']['status'] ?? '')); ?>
              <div class="text-[11px] rounded px-1.5 py-1 mb-1 truncate <?= $cls ?>" title="<?= e(mb_substr((string)($it['post']['content'] ?? ''), 0, 300)) ?>">
                <?= e($it['time']) ?> · <?= e(mb_substr((string)($it['post']['content'] ?? ''), 0, 30)) ?>
              </div>
            <?php endforeach; ?>
            <?php if (count($items) > 4): ?><div class="text-[11px] text-slate-500">+<?= count($items) - 4 ?> كمان</div><?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
    <p class="text-xs text-slate-500 mt-3">عشان تضيف منشور ليوم معين: <a class="text-indigo-700 underline" href="<?= e(url('compose')) ?>">منشور جديد</a> واختار التاريخ.</p>
    <?php
});
