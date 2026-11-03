<?php
$err = null;
$z = zernio_or_null($err);
$days = an_range_days($_GET['d'] ?? 30);
$platforms = ['facebook', 'instagram', 'whatsapp', 'twitter', 'linkedin', 'telegram', 'tiktok'];
$plat = in_array($_GET['pl'] ?? '', $platforms, true) ? $_GET['pl'] : '';
[$from, $to] = an_dates($days);

$res = [];
$errs = [];
if ($z) {
    $q = ['fromDate' => $from, 'toDate' => $to, 'profileId' => zernio_profile(), 'platform' => $plat ?: null];
    foreach (['volume', 'response-time', 'source-breakdown', 'top-accounts', 'heatmap'] as $w) {
        [$res[$w], $errs[$w]] = an_try(function () use ($z, $w, $q) {
            return an_inbox($z, $w, $w === 'top-accounts' ? $q + ['limit' => 10] : $q);
        });
    }
}

render(t('تحليلات الرسائل'), 'inbox_analytics', function () use ($z, $res, $errs, $days, $plat, $platforms) {
    if (!$z) { no_api_box(); return; }
    $q = function (array $over = []) use ($days, $plat) {
        return url('inbox_analytics', array_filter($over + ['d' => $days, 'pl' => $plat], function ($v) { return $v !== '' && $v !== null; }));
    };
    $vol = $res['volume'] ?? null;
    $sum = $vol['summary'] ?? [];
    ?>
    <div class="space-y-5">
      <form method="get" class="card p-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="p" value="inbox_analytics">
        <input type="hidden" name="d" value="<?= (int)$days ?>">
        <div>
          <label class="label"><?= e(t('الفترة')) ?></label>
          <div class="flex gap-1">
            <?php foreach (an_ranges() as $r): ?>
              <a href="<?= e($q(['d' => $r])) ?>" class="px-3 py-2 rounded-lg text-sm border <?= $r === $days ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700 hover:bg-ink-50' ?>"><?= e(t('آخر %s يوم', $r)) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="min-w-[10rem]">
          <label class="label"><?= e(t('المنصة')) ?></label>
          <select name="pl" class="input" onchange="this.form.submit()">
            <option value=""><?= e(t('كل المنصات')) ?></option>
            <?php foreach ($platforms as $p): ?><option value="<?= e($p) ?>" <?= $plat === $p ? 'selected' : '' ?>><?= e(platform_label($p)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="btn-primary"><?= e(t('عرض')) ?></button></noscript>
      </form>

      <!-- حجم الرسائل -->
      <section class="card p-5">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('حجم الرسائل')) ?></h2>
        <?php if ($errs['volume']): ?>
          <?= an_notice($errs['volume']) ?>
        <?php else:
          $tiles = ['received' => t('رسائل وصلت'), 'sent' => t('رسائل اتبعتت'), 'read' => t('اتقرت'), 'failed' => t('فشلت'), 'uniqueConversations' => t('محادثات')];
          $byDate = [];
          foreach ($vol['timeseries'] ?? [] as $r) $byDate[$r['date'] ?? ''] = $r;
          $bars = [];
          for ($i = $days - 1; $i >= 0; $i--) {
              $dt = gmdate('Y-m-d', time() - $i * 86400);
              $rv = (int)($byDate[$dt]['received'] ?? 0); $sv = (int)($byDate[$dt]['sent'] ?? 0);
              $bars[] = [gmdate('d/m', strtotime($dt)), $rv + $sv, gmdate('d/m', strtotime($dt)) . ' — ' . t('وصل %s · اتبعت %s', $rv, $sv)];
          }
          ?>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
            <?php foreach ($tiles as $k => $label): ?>
              <div class="rounded-xl bg-ink-50 p-3"><p class="text-xs text-ink-500"><?= e($label) ?></p><p class="text-xl font-bold <?= $k === 'failed' && !empty($sum[$k]) ? 'text-red-600' : 'text-ink-900' ?>"><?= e(an_num($sum[$k] ?? 0)) ?></p></div>
            <?php endforeach; ?>
          </div>
          <?php if (empty($vol['timeseries'])): ?>
            <p class="text-sm text-ink-500"><?= e(t('مفيش رسائل في الفترة دي.')) ?></p>
          <?php else: ?>
            <p class="text-xs text-ink-500 mb-2"><?= e(t('الرسائل يوم بيوم (وصلت + اتبعتت)')) ?></p>
            <?= an_bars($bars, 'bg-cyan-500') ?>
          <?php endif; ?>
          <?php if (!empty($vol['byPlatform'])): $mx = 0; foreach ($vol['byPlatform'] as $b) $mx = max($mx, ($b['received'] ?? 0) + ($b['sent'] ?? 0)); ?>
            <div class="mt-5 space-y-2">
              <?php foreach ($vol['byPlatform'] as $b): $tt = ($b['received'] ?? 0) + ($b['sent'] ?? 0); ?>
                <div>
                  <div class="flex justify-between text-sm mb-1"><span class="text-ink-800"><?= e(platform_label($b['platform'] ?? '')) ?></span><span class="text-xs text-ink-500"><?= e(t('وصل %s · اتبعت %s', (int)($b['received'] ?? 0), (int)($b['sent'] ?? 0))) ?></span></div>
                  <?= an_hbar($tt, $mx, 'bg-cyan-500') ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <div class="grid lg:grid-cols-2 gap-5">
        <!-- سرعة الرد -->
        <section class="card p-5">
          <h2 class="font-bold text-ink-900 mb-3"><?= e(t('سرعة الرد')) ?></h2>
          <?php if ($errs['response-time']): ?>
            <?= an_notice($errs['response-time']) ?>
          <?php else: $rs = $res['response-time']['summary'] ?? []; ?>
            <?php if (empty($rs['sampleSize'])): ?>
              <p class="text-sm text-ink-500"><?= e(t('مفيش ردود في الفترة دي نحسب منها سرعة الرد.')) ?></p>
            <?php else: ?>
              <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="rounded-xl bg-ink-50 p-3"><p class="text-xs text-ink-500"><?= e(t('الرد المعتاد')) ?></p><p class="text-lg font-bold text-ink-900"><?= e(an_duration($rs['medianSeconds'] ?? null)) ?></p></div>
                <div class="rounded-xl bg-ink-50 p-3"><p class="text-xs text-ink-500"><?= e(t('أسرع رد')) ?></p><p class="text-lg font-bold text-ink-900"><?= e(an_duration($rs['fastestSeconds'] ?? null)) ?></p></div>
                <div class="rounded-xl bg-ink-50 p-3"><p class="text-xs text-ink-500"><?= e(t('أبطأ رد')) ?></p><p class="text-lg font-bold text-ink-900"><?= e(an_duration($rs['slowestSeconds'] ?? null)) ?></p></div>
              </div>
              <p class="text-xs text-ink-500 mb-2"><?= e(t('محسوب من %s رد. 9 من كل 10 ردود كانت خلال %s.', (int)$rs['sampleSize'], an_duration($rs['p90Seconds'] ?? null))) ?></p>
              <?php $hist = $res['response-time']['histogram'] ?? []; $mx = 0; foreach ($hist as $h) $mx = max($mx, (int)($h['count'] ?? 0)); ?>
              <div class="space-y-2">
                <?php foreach ($hist as $h): ?>
                  <div class="flex items-center gap-3 text-sm">
                    <span class="w-16 shrink-0 text-ink-600 text-xs" dir="ltr"><?= e($h['bucket'] ?? '') ?></span>
                    <div class="flex-1"><?= an_hbar((float)($h['count'] ?? 0), $mx, 'bg-lime-500') ?></div>
                    <span class="w-8 text-end text-xs text-ink-600 tabular-nums"><?= (int)($h['count'] ?? 0) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </section>

        <!-- المصادر -->
        <section class="card p-5">
          <h2 class="font-bold text-ink-900 mb-1"><?= e(t('مصادر الرسائل')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('مين اللي بدأ الرسالة: عميل، فريقك من البرنامج، أو تلقائي.')) ?></p>
          <?php if ($errs['source-breakdown']): ?>
            <?= an_notice($errs['source-breakdown']) ?>
          <?php elseif (empty($res['source-breakdown']['sources'])): ?>
            <p class="text-sm text-ink-500"><?= e(t('مفيش بيانات في الفترة دي.')) ?></p>
          <?php else:
            $srcNames = ['contact' => t('من العملاء'), 'api' => t('من البرنامج / API'), 'recipient' => t('قراءة من العميل'), 'dashboard' => t('من لوحة التكامل'), 'automation' => t('ردود تلقائية'), 'broadcast' => t('رسائل جماعية'), 'sequence' => t('رسائل متتابعة')];
            $srcs = $res['source-breakdown']['sources']; $mx = 0;
            foreach ($srcs as $s) $mx = max($mx, ($s['received'] ?? 0) + ($s['sent'] ?? 0) + ($s['read'] ?? 0));
            ?>
            <div class="space-y-3">
              <?php foreach ($srcs as $s): $tt = ($s['received'] ?? 0) + ($s['sent'] ?? 0) + ($s['read'] ?? 0); ?>
                <div>
                  <div class="flex justify-between text-sm mb-1"><span class="text-ink-800"><?= e($srcNames[$s['source'] ?? ''] ?? ($s['source'] ?? '')) ?></span><span class="text-xs text-ink-500"><?= e(t('وصل %s · اتبعت %s · اتقرت %s', (int)($s['received'] ?? 0), (int)($s['sent'] ?? 0), (int)($s['read'] ?? 0))) ?></span></div>
                  <?= an_hbar($tt, $mx, 'bg-brand-500') ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>

      <!-- أكتر الحسابات -->
      <section class="card p-5">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('أكتر الحسابات نشاطاً')) ?></h2>
        <?php if ($errs['top-accounts']): ?>
          <?= an_notice($errs['top-accounts']) ?>
        <?php elseif (empty($res['top-accounts']['accounts'])): ?>
          <p class="text-sm text-ink-500"><?= e(t('مفيش بيانات في الفترة دي.')) ?></p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead><tr class="text-ink-500 text-xs border-b border-ink-200">
                <th class="text-start py-2 pe-3 font-semibold"><?= e(t('الحساب')) ?></th>
                <th class="text-start py-2 px-2 font-semibold"><?= e(t('وصل')) ?></th>
                <th class="text-start py-2 px-2 font-semibold"><?= e(t('اتبعت')) ?></th>
                <th class="text-start py-2 px-2 font-semibold"><?= e(t('محادثات')) ?></th>
                <th class="text-start py-2 px-2 font-semibold"><?= e(t('اترد عليها')) ?></th>
                <th class="text-start py-2 px-2 font-semibold"><?= e(t('الرد المعتاد')) ?></th>
              </tr></thead>
              <tbody>
                <?php foreach ($res['top-accounts']['accounts'] as $a): ?>
                  <tr class="border-b border-ink-100">
                    <td class="py-2 pe-3"><p class="font-semibold text-ink-800"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></p><p class="text-[11px] text-ink-500"><?= e(platform_label($a['platform'] ?? '')) ?></p></td>
                    <td class="py-2 px-2 tabular-nums"><?= (int)($a['received'] ?? 0) ?></td>
                    <td class="py-2 px-2 tabular-nums"><?= (int)($a['sent'] ?? 0) ?></td>
                    <td class="py-2 px-2 tabular-nums"><?= (int)($a['conversations'] ?? 0) ?></td>
                    <td class="py-2 px-2 tabular-nums"><?= (int)($a['repliedCount'] ?? 0) ?></td>
                    <td class="py-2 px-2 whitespace-nowrap"><?= !empty($a['repliedCount']) ? e(an_duration((float)($a['medianResponseSeconds'] ?? 0))) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <!-- خريطة الأوقات -->
      <section class="card p-5">
        <h2 class="font-bold text-ink-900 mb-1"><?= e(t('أوقات وصول الرسائل')) ?></h2>
        <p class="text-xs text-ink-500 mb-3"><?= e(t('كل مربع يوم وساعة. كل ما اللون أغمق، كل ما الرسايل اللي بتوصل أكتر. بتوقيت الشركة (%s).', site_timezone())) ?></p>
        <?php if ($errs['heatmap']): ?>
          <?= an_notice($errs['heatmap']) ?>
        <?php else:
          $grid = array_fill(0, 7, array_fill(0, 24, 0));
          $mx = 0;
          foreach ($res['heatmap']['buckets'] ?? [] as $b) {
              // dow: 1 = الاتنين ... 7 = الحد (الساعة UTC)
              [$d, $h] = an_local_slot(((int)($b['dow'] ?? 1)) - 1, (int)($b['hour'] ?? 0));
              $grid[$d][$h] += (int)($b['received'] ?? 0);
              $mx = max($mx, $grid[$d][$h]);
          }
          $dn = an_day_names();
          $shade = function ($v) use ($mx) {
              if ($v <= 0 || $mx <= 0) return 'bg-ink-200';
              $r = $v / $mx;
              return $r > .75 ? 'bg-brand-700' : ($r > .5 ? 'bg-brand-500' : ($r > .25 ? 'bg-brand-300' : 'bg-brand-200'));
          };
          ?>
          <?php if ($mx === 0): ?><p class="text-sm text-ink-500 mb-3"><?= e(t('مفيش رسائل وصلت في الفترة دي.')) ?></p><?php endif; ?>
          <div class="overflow-x-auto">
            <div class="min-w-[40rem]" dir="ltr">
              <div class="flex gap-0.5 ps-16 mb-1 text-[10px] text-ink-500">
                <?php for ($h = 0; $h < 24; $h++): ?><div class="flex-1 text-center"><?= $h % 3 === 0 ? e(an_hour_label($h)) : '' ?></div><?php endfor; ?>
              </div>
              <?php foreach ($grid as $d => $row): ?>
                <div class="flex gap-0.5 mb-0.5 items-center">
                  <div class="w-16 shrink-0 text-xs text-ink-600 pe-2" dir="auto"><?= e($dn[$d]) ?></div>
                  <?php foreach ($row as $h => $v): ?>
                    <div class="flex-1 h-6 rounded-sm <?= $shade($v) ?>" title="<?= e($dn[$d] . ' ' . an_hour_label($h) . ' — ' . t('%s رسالة', $v)) ?>"></div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </div>
    <?php
});
