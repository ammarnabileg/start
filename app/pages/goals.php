<?php
/** الأهداف الشهرية للشركة ومتابعة التقدم */
$wid = (int)ws('id');
$canManage = can('settings.manage');
$metrics = rp_goal_metrics();
$tz = rp_tz($wid);
$now = new DateTime('now', new DateTimeZone($tz));
$thisMonth = $now->format('Y-m');
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)($_GET['m'] ?? '')) ? (string)$_GET['m'] : $thisMonth;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)($_POST['month'] ?? '')) ? (string)$_POST['month'] : $thisMonth;
    if (!$canManage) {
        flash('error', t('مالكش صلاحية تعدل الأهداف.'));
        redirect(url('goals', ['m' => $m]));
    }
    $ins = db()->prepare("INSERT INTO report_goals (workspace_id, month, metric, target, updated_at) VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE target = VALUES(target), updated_at = NOW()");
    $del = db()->prepare("DELETE FROM report_goals WHERE workspace_id = ? AND month = ? AND metric = ?");
    foreach ($metrics as $k => $_) {
        $v = trim((string)($_POST['target'][$k] ?? ''));
        if ($v === '' || !is_numeric($v) || (float)$v <= 0) {
            $del->execute([$wid, $m, $k]);
        } else {
            $ins->execute([$wid, $m, $k, min(1e12, (float)$v)]);
        }
    }
    log_activity('goals.update', $m);
    flash('success', t('اتحفظ.'));
    redirect(url('goals', ['m' => $m]));
}

$goals = rp_goals($wid, $month);
$hasApi = (bool)rp_clients($wid);
$act = null;
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/goals.json') ? json_decode((string)file_get_contents($fxd . '/goals.json'), true) : null;
if ($fx && $month === $thisMonth) { $hasApi = true; $act = ['values' => $fx['values'], 'errors' => []]; }
elseif ($hasApi && $month <= $thisMonth && $goals) {
    $act = rp_month_actuals($wid, $month);
}
// الشهر اللي قبله: أهدافه ونتيجته — للاقتراحات في الفورم
$prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
$prevGoals = rp_goals($wid, $prevMonth);

// السجل: آخر 6 شهور قبل الشهر ده اللي فيها أهداف. الشهور اللي خلصت بنحفظ نتيجتها مرة واحدة.
$history = [];
$st = db()->prepare("SELECT DISTINCT month FROM report_goals WHERE workspace_id = ? AND month < ? ORDER BY month DESC LIMIT 6");
$st->execute([$wid, $thisMonth]);
$upd = db()->prepare("UPDATE report_goals SET achieved = ? WHERE workspace_id = ? AND month = ? AND metric = ?");
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $hm) {
    $g = rp_goals($wid, $hm);
    $missing = array_filter($g, function ($r) { return $r['achieved'] === null; });
    if ($missing && $hasApi) {
        $a = rp_month_actuals($wid, $hm);
        foreach ($missing as $k => $r) {
            $v = $a['values'][$k] ?? null;
            if ($v !== null && !isset($a['errors'][$k === 'response' ? 'response' : ($k === 'followers' ? 'followers' : 'daily')])) {
                $upd->execute([$v, $wid, $hm, $k]);
                $g[$k]['achieved'] = $v;
            }
        }
    }
    $history[$hm] = $g;
}

render(t('الأهداف الشهرية'), 'goals', function () use ($metrics, $goals, $act, $month, $thisMonth, $now, $history, $canManage, $hasApi, $prevGoals) {
    $mLabel = function ($m) { return date('m/Y', strtotime($m . '-01')); };
    $daysIn = (int)date('t', strtotime($month . '-01'));
    $day = $month === $thisMonth ? (int)$now->format('j') : ($month < $thisMonth ? $daysIn : 0);
    $elapsed = $daysIn ? $day / $daysIn : 0;
    $prevM = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextM = date('Y-m', strtotime($month . '-01 +1 month'));
    $fmtVal = function ($k, $v) { return $v === null ? '—' : ($k === 'response' ? t('%s دقيقة', an_num($v)) : an_num($v)); };
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-5">
      <a class="btn-ghost" href="<?= e(url('goals', ['m' => $prevM])) ?>" aria-label="<?= e(t('الشهر اللي قبله')) ?>"><?= is_rtl() ? '→' : '←' ?></a>
      <span class="font-bold text-ink-900 text-lg px-2" dir="ltr"><?= e($mLabel($month)) ?></span>
      <a class="btn-ghost" href="<?= e(url('goals', ['m' => $nextM])) ?>" aria-label="<?= e(t('الشهر اللي بعده')) ?>"><?= is_rtl() ? '←' : '→' ?></a>
      <?php if ($month !== $thisMonth): ?><a class="link text-sm ms-2" href="<?= e(url('goals')) ?>"><?= e(t('الشهر الحالي')) ?></a><?php endif; ?>
      <?php if ($month === $thisMonth): ?><span class="badge ms-auto"><?= e(t('اليوم %s من %s', $day, $daysIn)) ?></span><?php endif; ?>
    </div>

    <?php if (!$hasApi): ?><div class="mb-5"><?php no_api_box(); ?></div><?php endif; ?>

    <?php
    // حالة كل هدف (نفس منطق الكروت تحت) للملخص
    $sum = ['hit' => 0, 'ok' => 0, 'late' => 0];
    foreach ($metrics as $k => $mt) {
        if (!isset($goals[$k]) || $month > $thisMonth) continue;
        $tg = (float)$goals[$k]['target']; $v = $act['values'][$k] ?? null; if ($v === null) continue;
        if ($mt['lower']) { $v <= $tg ? $sum['ok']++ : $sum['late']++; continue; }
        if ($v >= $tg) $sum['hit']++; elseif ($v >= $tg * $elapsed * 0.95) $sum['ok']++; else $sum['late']++;
    }
    $totG = count($goals);
    ?>
    <?php if ($goals && $month <= $thisMonth): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('أهداف الشهر')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= $totG ?></div></div>
      <div class="rounded-2xl border border-lime-200 bg-lime-50/60 p-4"><div class="text-xs text-ink-500"><?= e(t('اتحققت')) ?></div><div class="text-2xl font-bold text-lime-700 mt-1"><?= $sum['hit'] ?></div></div>
      <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-4"><div class="text-xs text-ink-500"><?= e(t('ماشية كويس')) ?></div><div class="text-2xl font-bold text-brand-700 mt-1"><?= $sum['ok'] ?></div></div>
      <div class="rounded-2xl border <?= $sum['late'] ? 'border-red-200 bg-red-50/60' : 'border-ink-200 bg-white' ?> p-4"><div class="text-xs text-ink-500"><?= e($month < $thisMonth ? t('ما اتحققتش') : t('متأخرة')) ?></div><div class="text-2xl font-bold mt-1 <?= $sum['late'] ? 'text-red-600' : 'text-ink-900' ?>"><?= $sum['late'] ?></div></div>
    </div>
    <?php endif; ?>
    <div class="grid lg:grid-cols-3 gap-5">
      <div class="lg:col-span-2 space-y-3">
        <?php if (!$goals): ?>
          <?= an_empty(t('مفيش أهداف للشهر ده'), $canManage ? t('حط أرقام مستهدفة من النموذج اللي جنبك.') : t('صاحب الشركة يقدر يحط أهداف للشهر.'), '🎯') ?>
        <?php endif; ?>
        <?php foreach ($metrics as $k => $mt): if (!isset($goals[$k])) continue;
            $target = (float)$goals[$k]['target'];
            $val = $act['values'][$k] ?? null;
            $errKey = $k === 'response' ? 'response' : ($k === 'followers' ? 'followers' : 'daily');
            $err = $act['errors'][$errKey] ?? null;
            $pct = ($val !== null && $target > 0) ? ($mt['lower'] ? ($val <= $target ? 100 : $target / $val * 100) : $val / $target * 100) : 0;
            if ($month > $thisMonth) { $status = ['badge', t('لسه ما بدأش')]; }
            elseif ($val === null) { $status = ['badge', t('مفيش بيانات')]; }
            elseif ($mt['lower']) { $status = $val <= $target ? ['badge badge-green', t('ماشي كويس')] : ['badge bg-red-50 text-red-700', t('متأخر')]; }
            else {
                $expected = $target * $elapsed;
                if ($val >= $target) $status = ['badge badge-green', t('اتحقق')];
                elseif ($val >= $expected * 0.95) $status = ['badge badge-green', t('ماشي كويس')];
                else $status = ['badge bg-red-50 text-red-700', $month < $thisMonth ? t('ما اتحققش') : t('متأخر')];
            }
            ?>
          <div class="card p-5">
            <div class="flex items-center gap-3">
              <h3 class="font-semibold text-ink-900"><?= e($mt['label']) ?></h3>
              <span class="<?= e($status[0]) ?> ms-auto"><?= e($status[1]) ?></span>
            </div>
            <?php if ($err): ?>
              <p class="text-sm text-ink-500 mt-2"><?= e($err) ?></p>
            <?php else: ?>
              <div class="flex items-baseline gap-2 mt-2">
                <span class="text-2xl font-bold text-ink-900 tabular-nums"><bdi dir="ltr"><?= e($fmtVal($k, $val)) ?></bdi></span>
                <span class="text-sm text-ink-500"><?= e($mt['lower'] ? t('الهدف: أقل من %s', $fmtVal($k, $target)) : t('من %s', $fmtVal($k, $target))) ?></span>
                <span class="text-sm font-semibold text-ink-700 ms-auto tabular-nums" dir="ltr"><?= e(number_format(min(999, $pct), 0)) ?>%</span>
              </div>
              <div class="relative h-3 rounded-full bg-ink-100 mt-3 overflow-hidden" role="progressbar" aria-valuenow="<?= (int)min(100, $pct) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="absolute inset-y-0 start-0 rounded-full <?= strpos($status[0], 'red') !== false ? 'bg-amber-500' : 'bg-brand-600' ?>" style="width:<?= e(number_format(min(100, $pct), 1, '.', '')) ?>%"></div>
                <?php if (!$mt['lower'] && $month === $thisMonth): ?>
                  <div class="absolute inset-y-0 w-0.5 bg-ink-900/60" style="inset-inline-start:<?= e(number_format($elapsed * 100, 1, '.', '')) ?>%" title="<?= e(t('المفروض توصل لحد هنا النهارده')) ?>"></div>
                <?php endif; ?>
              </div>
              <?php if (!$mt['lower'] && $month === $thisMonth && $val !== null && $val < $target): ?>
                <p class="text-xs text-ink-500 mt-2"><?= e(t('المتوقع لحد النهارده: %s · محتاج %s في اليوم عشان توصل للهدف.', an_num(round($target * $elapsed)), an_num(ceil(($target - $val) / max(1, $daysIn - $day + 1))))) ?></p>
              <?php endif; ?>
              <?php if (!$mt['lower'] && $month === $thisMonth && $val !== null && $elapsed > 0.05): $proj = $val / $elapsed; $pp = $target > 0 ? $proj / $target * 100 : 0; ?>
                <div class="mt-3 flex items-center gap-2 rounded-xl px-3 py-2 text-xs <?= $pp >= 100 ? 'bg-lime-50 text-lime-800' : ($pp >= 85 ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-800') ?>">
                  <span><?= $pp >= 100 ? '🎯' : ($pp >= 85 ? '⏳' : '⚠️') ?></span>
                  <span><?= e(t('بنفس السرعة دي هتقفل الشهر على %s (%s%% من الهدف).', an_num($proj), number_format(min(999, $pp), 0))) ?></span>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="space-y-5">
        <?php if ($canManage): ?>
          <form method="post" class="card p-5 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <h2 class="font-bold text-ink-900"><?= e(t('أهداف شهر %s', $mLabel($month))) ?></h2>
            <?php if ($prevGoals): ?>
            <div class="flex flex-wrap gap-1.5">
              <button type="button" class="gl-copy rounded-full border border-ink-200 px-3 py-1 text-xs hover:border-brand-400 hover:bg-brand-50"><?= e(t('زي الشهر اللي فات')) ?></button>
              <button type="button" class="gl-up rounded-full border border-ink-200 px-3 py-1 text-xs hover:border-brand-400 hover:bg-brand-50" data-f="1.1" dir="ltr">+10%</button>
              <button type="button" class="gl-up rounded-full border border-ink-200 px-3 py-1 text-xs hover:border-brand-400 hover:bg-brand-50" data-f="1.2" dir="ltr">+20%</button>
            </div>
            <?php endif; ?>
            <?php foreach ($metrics as $k => $mt): $pg = $prevGoals[$k] ?? null; $nf = function ($v) { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); }; ?>
              <label class="block"><span class="label"><?= e($mt['label']) ?></span>
                <input type="number" min="0" step="any" name="target[<?= e($k) ?>]" class="input gl-in" dir="ltr" data-prev="<?= $pg ? e($nf($pg['target'])) : '' ?>" data-lower="<?= $mt['lower'] ? 1 : 0 ?>" value="<?= isset($goals[$k]) ? e($nf($goals[$k]['target'])) : '' ?>" placeholder="—">
                <?php if ($pg): ?><span class="text-[11px] text-ink-500"><?= e(t('الشهر اللي فات: الهدف %s', $fmtVal($k, (float)$pg['target']))) ?><?= $pg['achieved'] !== null ? ' · ' . e(t('اللي اتعمل %s', $fmtVal($k, (float)$pg['achieved']))) : '' ?></span><?php endif; ?></label>
            <?php endforeach; ?>
            <p class="text-xs text-ink-500"><?= e(t('سيب الخانة فاضية لو مش عايز هدف للمقياس ده.')) ?></p>
            <button class="btn-primary w-full"><?= e(t('حفظ الأهداف')) ?></button>
          </form>
          <script>
          (function () {
            var ins = document.querySelectorAll('.gl-in');
            var c = document.querySelector('.gl-copy'); if (c) c.onclick = function () { ins.forEach(function (i) { if (i.dataset.prev) i.value = i.dataset.prev; }); };
            document.querySelectorAll('.gl-up').forEach(function (b) { b.onclick = function () { var f = +b.dataset.f; ins.forEach(function (i) { var base = +(i.value || i.dataset.prev || 0); if (!base) return; i.value = i.dataset.lower === '1' ? Math.round(base / f) : Math.round(base * f); }); }; });
          })();
          </script>
        <?php endif; ?>

        <div class="card p-5">
          <h2 class="font-bold text-ink-900 mb-3"><?= e(t('الشهور اللي فاتت')) ?></h2>
          <?php if (!$history): ?>
            <p class="text-sm text-ink-500"><?= e(t('لسه مفيش سجل.')) ?></p>
          <?php endif; ?>
          <div class="space-y-4">
            <?php foreach ($history as $hm => $g): ?>
              <?php $hitN = 0; $known = 0; foreach ($metrics as $k => $mt) { if (!isset($g[$k]) || $g[$k]['achieved'] === null) continue; $known++; $tg = (float)$g[$k]['target']; $a = (float)$g[$k]['achieved']; if ($mt['lower'] ? $a <= $tg : $a >= $tg) $hitN++; } ?>
              <div>
                <div class="flex items-center gap-2"><a class="link text-sm" href="<?= e(url('goals', ['m' => $hm])) ?>" dir="ltr"><?= e($mLabel($hm)) ?></a>
                  <?php if ($known): ?><span class="ms-auto text-[11px] rounded-full px-2 py-0.5 <?= $hitN === $known ? 'bg-lime-50 text-lime-700' : ($hitN ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') ?>"><?= e(t('%s من %s', $hitN, $known)) ?></span><?php endif; ?></div>
                <ul class="mt-1 space-y-1">
                  <?php foreach ($metrics as $k => $mt): if (!isset($g[$k])) continue;
                      $a = $g[$k]['achieved']; $tg = (float)$g[$k]['target'];
                      $hit = $a !== null && ($mt['lower'] ? (float)$a <= $tg : (float)$a >= $tg); ?>
                    <li class="flex items-center gap-2 text-xs">
                      <span class="<?= $a === null ? 'text-ink-400' : ($hit ? 'text-lime-700' : 'text-red-600') ?>" aria-hidden="true"><?= $a === null ? '•' : ($hit ? '✓' : '✗') ?></span>
                      <span class="text-ink-700"><?= e($mt['label']) ?></span>
                      <span class="ms-auto text-ink-500 tabular-nums"><bdi dir="ltr"><?= e($fmtVal($k, $a === null ? null : (float)$a) . ' / ' . $fmtVal($k, $tg)) ?></bdi></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <?php
});
