<?php
$err = null;
$z = zernio_or_null($err);
$days = an_range_days($_GET['d'] ?? 30);
$acc = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['a'] ?? ''));
$sortKeys = ['date', 'impressions', 'reach', 'engagement', 'likes', 'comments', 'shares'];
$sort = in_array($_GET['s'] ?? '', $sortKeys, true) ? $_GET['s'] : 'date';
$order = ($_GET['o'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$pg = max(1, (int)($_GET['pg'] ?? 1));
[$from, $to] = an_dates($days);
$pid = zernio_profile();

$data = ['followers' => null, 'daily' => null, 'posts' => null, 'top' => null, 'best' => null];
$errs = [];
if ($z) {
    $base = ['profileId' => $pid, 'accountId' => $acc ?: null, 'fromDate' => $from, 'toDate' => $to];
    [$data['followers'], $errs['followers']] = an_try(function () use ($z, $pid, $from, $to, $acc) {
        return an_follower_stats($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to, 'accountIds' => $acc ?: null]);
    });
    [$data['daily'], $errs['daily']] = an_try(function () use ($z, $base) {
        return an_daily_metrics($z, $base);
    });
    [$data['posts'], $errs['posts']] = an_try(function () use ($z, $base, $sort, $order, $pg) {
        return an_posts($z, $base + ['limit' => 20, 'page' => $pg, 'sortBy' => $sort, 'order' => $order]);
    });
    [$data['top'], $errs['top']] = an_try(function () use ($z, $base) {
        return an_posts($z, $base + ['limit' => 5, 'page' => 1, 'sortBy' => 'engagement', 'order' => 'desc'])['posts'] ?? [];
    });
    [$data['best'], $errs['best']] = an_try(function () use ($z, $pid, $acc) {
        return an_best_time($z, ['profileId' => $pid, 'accountId' => $acc ?: null]);
    });
}

render(t('التحليلات'), 'analytics', function () use ($z, $data, $errs, $days, $acc, $sort, $order, $pg) {
    if (!$z) { no_api_box(); return; }
    $accounts = $data['posts']['accounts'] ?? ($data['followers']['accounts'] ?? []);
    $q = function (array $over = []) use ($days, $acc, $sort, $order) {
        return url('analytics', array_filter($over + ['d' => $days, 'a' => $acc, 's' => $sort, 'o' => $order], function ($v) { return $v !== '' && $v !== null; }));
    };
    $metricCols = ['impressions' => t('مرات الظهور'), 'reach' => t('الوصول'), 'likes' => t('إعجابات'), 'comments' => t('تعليقات'), 'shares' => t('مشاركات')];
    ?>
    <div class="space-y-5">
      <!-- الفلاتر -->
      <form method="get" class="card p-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="p" value="analytics">
        <input type="hidden" name="s" value="<?= e($sort) ?>"><input type="hidden" name="o" value="<?= e($order) ?>">
        <div>
          <label class="label"><?= e(t('الفترة')) ?></label>
          <div class="flex gap-1">
            <?php foreach (an_ranges() as $r): ?>
              <a href="<?= e($q(['d' => $r, 'pg' => null])) ?>" class="px-3 py-2 rounded-lg text-sm border <?= $r === $days ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700 hover:bg-ink-50' ?>"><?= e(t('آخر %s يوم', $r)) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <input type="hidden" name="d" value="<?= (int)$days ?>">
        <div class="min-w-[12rem]">
          <label class="label"><?= e(t('الحساب')) ?></label>
          <select name="a" class="input" onchange="this.form.submit()">
            <option value=""><?= e(t('كل الحسابات')) ?></option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?= e($a['_id']) ?>" <?= $acc === $a['_id'] ? 'selected' : '' ?>><?= e(($a['displayName'] ?? $a['username'] ?? '') . ' — ' . platform_label($a['platform'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="btn-primary"><?= e(t('عرض')) ?></button></noscript>
      </form>

      <!-- المتابعين -->
      <section class="card p-5">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('المتابعين')) ?></h2>
        <?php if ($errs['followers']): ?>
          <?= an_notice($errs['followers']) ?>
        <?php elseif (empty($data['followers']['accounts'])): ?>
          <p class="text-sm text-ink-500"><?= e(t('مفيش حسابات متصلة لسه.')) ?></p>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($data['followers']['accounts'] as $a):
              $g = (float)($a['growth'] ?? 0);
              $pts = $data['followers']['stats'][$a['_id']] ?? [];
              ?>
              <div class="rounded-xl border border-ink-200 p-4">
                <div class="flex items-center gap-3">
                  <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? '', 'w-10 h-10') ?>
                  <div class="min-w-0">
                    <p class="font-semibold text-ink-900 truncate"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></p>
                    <p class="text-xs text-ink-500"><?= e(platform_label($a['platform'] ?? '')) ?></p>
                  </div>
                </div>
                <div class="flex items-end justify-between mt-3">
                  <div>
                    <p class="text-2xl font-bold text-ink-900"><?= e(an_num($a['currentFollowers'] ?? 0)) ?></p>
                    <p class="text-xs text-ink-500"><?= e(t('متابع')) ?></p>
                  </div>
                  <span class="badge <?= $g > 0 ? 'badge-green' : ($g < 0 ? 'bg-red-50 text-red-700' : '') ?>" dir="ltr"><?= $g > 0 ? '+' : '' ?><?= e(an_num($g)) ?> (<?= e(round((float)($a['growthPercentage'] ?? 0), 1)) ?>%)</span>
                </div>
                <?php if (count($pts) > 1):
                  $vals = array_map(function ($p) { return (float)($p['followers'] ?? 0); }, $pts);
                  $mn = min($vals); $mx = max($vals); $n = count($vals); $line = [];
                  foreach ($vals as $i => $v) { $line[] = round($i / ($n - 1) * 100, 2) . ',' . round(28 - ($mx > $mn ? ($v - $mn) / ($mx - $mn) * 26 : 13), 2); }
                  ?>
                  <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full h-8 mt-2 text-brand-500" dir="ltr"><polyline fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" points="<?= e(implode(' ', $line)) ?>"/></svg>
                <?php else: ?>
                  <p class="text-[11px] text-ink-400 mt-2"><?= e(t('لسه مفيش تاريخ كفاية لرسم النمو.')) ?></p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- الإجماليات والرسم اليومي -->
      <section class="card p-5">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('أداء المنشورات في الفترة')) ?></h2>
        <?php if ($errs['daily']): ?>
          <?= an_notice($errs['daily']) ?>
        <?php else:
          $daily = $data['daily'] ?: [];
          $tot = ['impressions' => 0, 'reach' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0];
          $postCount = 0;
          $byDate = [];
          foreach ($daily as $d) {
              foreach ($tot as $k => $_) $tot[$k] += (float)($d['metrics'][$k] ?? 0);
              $postCount += (int)($d['postCount'] ?? 0);
              $byDate[$d['date'] ?? ''] = (float)($d['metrics']['impressions'] ?? 0);
          }
          $bars = [];
          for ($i = $days - 1; $i >= 0; $i--) {
              $dt = gmdate('Y-m-d', time() - $i * 86400);
              $v = $byDate[$dt] ?? 0;
              $bars[] = [gmdate('d/m', strtotime($dt)), $v, gmdate('d/m', strtotime($dt)) . ' — ' . t('%s ظهور', an_num($v))];
          }
          ?>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
            <div class="rounded-xl bg-ink-50 p-3"><p class="text-xs text-ink-500"><?= e(t('منشورات')) ?></p><p class="text-xl font-bold text-ink-900"><?= e(an_num($postCount)) ?></p></div>
            <?php foreach ($metricCols as $k => $label): ?>
              <div class="rounded-xl bg-ink-50 p-3"><p class="text-xs text-ink-500"><?= e($label) ?></p><p class="text-xl font-bold text-ink-900"><?= e(an_num($tot[$k])) ?></p></div>
            <?php endforeach; ?>
          </div>
          <?php if (!$daily): ?>
            <p class="text-sm text-ink-500"><?= e(t('مفيش بيانات في الفترة دي.')) ?></p>
          <?php else: ?>
            <p class="text-xs text-ink-500 mb-2"><?= e(t('مرات الظهور يوم بيوم')) ?></p>
            <?= an_bars($bars) ?>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <div class="grid lg:grid-cols-2 gap-5">
        <!-- أفضل المنشورات -->
        <section class="card p-5">
          <h2 class="font-bold text-ink-900 mb-3"><?= e(t('أفضل المنشورات (حسب التفاعل)')) ?></h2>
          <?php if ($errs['top']): ?>
            <?= an_notice($errs['top']) ?>
          <?php elseif (!$data['top']): ?>
            <p class="text-sm text-ink-500"><?= e(t('مفيش منشورات في الفترة دي.')) ?></p>
          <?php else: ?>
            <ol class="space-y-3">
              <?php foreach ($data['top'] as $i => $p): $an = $p['analytics'] ?? []; ?>
                <li class="flex gap-3">
                  <span class="w-6 h-6 shrink-0 rounded-full bg-brand-100 text-brand-700 text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span>
                  <?php if (!empty($p['thumbnailUrl'])): ?><img src="<?= e($p['thumbnailUrl']) ?>" alt="" class="w-12 h-12 rounded-lg object-cover bg-ink-100 shrink-0" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm text-ink-800 line-clamp-2"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 160)) ?: e(t('(منشور بدون نص)')) ?></p>
                    <p class="text-[11px] text-ink-500 mt-1">
                      <?= e(platform_label($p['platform'] ?? '')) ?> · <?= e(fmt_date($p['publishedAt'] ?? null, 'd/m/Y')) ?> ·
                      <?= e(t('تفاعل %s%%', round((float)($an['engagementRate'] ?? 0), 2))) ?> · <?= e(t('%s ظهور', an_num($an['impressions'] ?? 0))) ?>
                      <?php if (!empty($p['platformPostUrl'])): ?> · <a class="link" href="<?= e($p['platformPostUrl']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php endif; ?>
                    </p>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>

        <!-- أفضل وقت للنشر -->
        <section class="card p-5">
          <h2 class="font-bold text-ink-900 mb-1"><?= e(t('أفضل وقت للنشر')) ?></h2>
          <p class="text-xs text-ink-500 mb-3"><?= e(t('محسوب من تفاعل منشوراتك القديمة، بتوقيت الشركة (%s).', site_timezone())) ?></p>
          <?php if ($errs['best']): ?>
            <?= an_notice($errs['best']) ?>
          <?php elseif (!$data['best']): ?>
            <p class="text-sm text-ink-500"><?= e(t('لسه مفيش منشورات كفاية نحسب منها أفضل وقت.')) ?></p>
          <?php else:
            $slots = $data['best'];
            usort($slots, function ($a, $b) { return ($b['avg_engagement'] ?? 0) <=> ($a['avg_engagement'] ?? 0); });
            $slots = array_slice($slots, 0, 6);
            $mx = (float)($slots[0]['avg_engagement'] ?? 0);
            $dn = an_day_names();
            ?>
            <ul class="space-y-3">
              <?php foreach ($slots as $s): [$d, $h] = an_local_slot((int)$s['day_of_week'], (int)$s['hour']); ?>
                <li>
                  <div class="flex justify-between text-sm mb-1">
                    <span class="font-semibold text-ink-800"><?= e($dn[$d]) ?> — <span dir="ltr"><?= e(an_hour_label($h)) ?></span></span>
                    <span class="text-ink-500 text-xs"><?= e(t('متوسط تفاعل %s من %s منشور', an_num($s['avg_engagement'] ?? 0), (int)($s['post_count'] ?? 0))) ?></span>
                  </div>
                  <?= an_hbar((float)($s['avg_engagement'] ?? 0), $mx, 'bg-lime-500') ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>

      <!-- جدول المنشورات -->
      <section class="card p-5">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('كل المنشورات')) ?></h2>
        <?php if ($errs['posts']): ?>
          <?= an_notice($errs['posts']) ?>
        <?php elseif (empty($data['posts']['posts'])): ?>
          <p class="text-sm text-ink-500"><?= e(t('مفيش منشورات في الفترة دي.')) ?></p>
        <?php else:
          $cols = ['date' => t('التاريخ')] + $metricCols + ['engagement' => t('التفاعل %')];
          $pgn = $data['posts']['pagination'] ?? [];
          ?>
          <p class="text-xs text-ink-500 mb-2"><?= e(t('دوس على عنوان العمود عشان ترتب بيه.')) ?></p>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-ink-500 text-xs border-b border-ink-200">
                  <th class="text-start py-2 pe-3 font-semibold"><?= e(t('المنشور')) ?></th>
                  <?php foreach ($cols as $k => $label):
                    $on = $sort === $k; $no = $on && $order === 'desc' ? 'asc' : 'desc'; ?>
                    <th class="text-start py-2 px-2 font-semibold whitespace-nowrap">
                      <a href="<?= e($q(['s' => $k, 'o' => $no, 'pg' => null])) ?>" class="<?= $on ? 'text-brand-700' : 'hover:text-ink-800' ?>"><?= e($label) ?><?= $on ? ($order === 'desc' ? ' ↓' : ' ↑') : '' ?></a>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($data['posts']['posts'] as $p): $an = $p['analytics'] ?? []; ?>
                  <tr class="border-b border-ink-100 align-top">
                    <td class="py-2 pe-3 min-w-[14rem] max-w-md">
                      <p class="line-clamp-2 text-ink-800"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 140)) ?: e(t('(منشور بدون نص)')) ?></p>
                      <p class="text-[11px] text-ink-500"><?= e(platform_label($p['platform'] ?? '')) ?><?php foreach (array_slice($p['platforms'] ?? [], 0, 1) as $pl): ?> · <?= e($pl['accountUsername'] ?? '') ?><?php endforeach; ?>
                        <?php if (!empty($p['platformPostUrl'])): ?> · <a class="link" href="<?= e($p['platformPostUrl']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php endif; ?></p>
                    </td>
                    <td class="py-2 px-2 whitespace-nowrap text-ink-600"><?= e(fmt_date($p['publishedAt'] ?? null, 'd/m/Y')) ?></td>
                    <?php foreach ($metricCols as $k => $_): ?><td class="py-2 px-2 tabular-nums"><?= e(an_num($an[$k] ?? 0)) ?></td><?php endforeach; ?>
                    <td class="py-2 px-2 tabular-nums"><?= e(round((float)($an['engagementRate'] ?? 0), 2)) ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (($pgn['pages'] ?? 1) > 1): ?>
            <div class="flex items-center justify-between mt-3 text-sm">
              <span class="text-ink-500"><?= e(t('صفحة %s من %s', (int)$pg, (int)$pgn['pages'])) ?></span>
              <div class="flex gap-2">
                <?php if ($pg > 1): ?><a class="btn-ghost" href="<?= e($q(['pg' => $pg - 1])) ?>"><?= e(t('السابق')) ?></a><?php endif; ?>
                <?php if ($pg < (int)$pgn['pages']): ?><a class="btn-ghost" href="<?= e($q(['pg' => $pg + 1])) ?>"><?= e(t('التالي')) ?></a><?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
    <?php
});
