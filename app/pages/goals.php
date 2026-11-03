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
if ($hasApi && $month <= $thisMonth && $goals) {
    $act = rp_month_actuals($wid, $month);
}

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

render(t('الأهداف الشهرية'), 'goals', function () use ($metrics, $goals, $act, $month, $thisMonth, $now, $history, $canManage, $hasApi) {
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
            $pct = ($val !== null && $target > 0) ? ($mt['lower'] ? ($val > 0 ? $target / $val * 100 : 100) : $val / $target * 100) : 0;
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
                <p class="text-xs text-ink-500 mt-2"><?= e(t('المتوقع لحد النهارده: %s · محتاج %s في اليوم عشان توصل للهدف.', an_num($target * $elapsed), an_num(($target - $val) / max(1, $daysIn - $day + 1)))) ?></p>
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
            <?php foreach ($metrics as $k => $mt): ?>
              <label class="block"><span class="label"><?= e($mt['label']) ?></span>
                <input type="number" min="0" step="any" name="target[<?= e($k) ?>]" class="input" dir="ltr" value="<?= isset($goals[$k]) ? e(rtrim(rtrim(number_format((float)$goals[$k]['target'], 2, '.', ''), '0'), '.')) : '' ?>" placeholder="—"></label>
            <?php endforeach; ?>
            <p class="text-xs text-ink-500"><?= e(t('سيب الخانة فاضية لو مش عايز هدف للمقياس ده.')) ?></p>
            <button class="btn-primary w-full"><?= e(t('حفظ الأهداف')) ?></button>
          </form>
        <?php endif; ?>

        <div class="card p-5">
          <h2 class="font-bold text-ink-900 mb-3"><?= e(t('الشهور اللي فاتت')) ?></h2>
          <?php if (!$history): ?>
            <p class="text-sm text-ink-500"><?= e(t('لسه مفيش سجل.')) ?></p>
          <?php endif; ?>
          <div class="space-y-4">
            <?php foreach ($history as $hm => $g): ?>
              <div>
                <a class="link text-sm" href="<?= e(url('goals', ['m' => $hm])) ?>" dir="ltr"><?= e($mLabel($hm)) ?></a>
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
