<?php
/** الإعلانات: حسابات الإعلانات + قائمة الحملات بالأرقام، وإيقاف/تشغيل حملة */
$err = null;
$z = zernio_or_null($err);

$platform = (string)($_GET['pf'] ?? '');
if ($platform !== '' && !in_array($platform, ads_campaign_platforms(), true)) $platform = '';
$adAcc = (string)($_GET['acc'] ?? '');
$status = (string)($_GET['st'] ?? '');
if ($status !== '' && !in_array($status, ['active', 'paused', 'pending_review', 'rejected', 'completed', 'error'], true)) $status = '';
$range = (string)($_GET['r'] ?? '30');
if (!isset(ads_date_presets()[$range])) $range = '30';
$pg = max(1, (int)($_GET['pg'] ?? 1));
$back = url('ads', array_filter(['pf' => $platform, 'acc' => $adAcc, 'st' => $status, 'r' => $range, 'pg' => $pg > 1 ? $pg : null]));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'connect') {
            if (!can('ads.spend')) {
                flash('error', t('ربط حساب الإعلانات لصاحب الشركة بس.'));
            } else {
                $pfm = (string)($_POST['platform'] ?? '');
                if (!isset(ads_connect_platforms()[$pfm])) {
                    flash('error', t('اختار منصة صحيحة.'));
                } else {
                    $r = ads_connect_url($z, $pfm, base_url() . '/' . url('ads'));
                    if (!empty($r['authUrl'])) {
                        log_activity('ads.connect', $pfm);
                        header('Location: ' . $r['authUrl']);
                        exit;
                    }
                    flash('success', t('حساب الإعلانات ده مربوط بالفعل.'));
                }
            }
        } elseif ($action === 'status') {
            $cid = (string)($_POST['campaign_id'] ?? '');
            $cpf = (string)($_POST['platform'] ?? '');
            $to = ($_POST['to'] ?? '') === 'active' ? 'active' : 'paused';
            if ($cid === '' || !in_array($cpf, ads_campaign_platforms(), true)) {
                flash('error', t('بيانات الحملة ناقصة.'));
            } elseif ($to === 'active' && !can('ads.spend')) {
                flash('error', t('تشغيل الحملات (صرف فلوس) لصاحب الشركة بس.'));
            } elseif ($to === 'active' && empty($_POST['confirm_spend'])) {
                flash('error', t('لازم تعلّم على خانة التأكيد قبل التشغيل.'));
            } else {
                $r = ads_set_campaign_status($z, $cid, $cpf, $to);
                log_activity('ads.campaign_' . $to, $cid);
                if (!empty($r['skipped'])) {
                    flash('success', t('الحملة كانت أصلاً في الحالة دي.'));
                } else {
                    flash('success', $to === 'active' ? t('تم تشغيل الحملة.') : t('تم إيقاف الحملة.'));
                }
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

$connections = [];
$adAccounts = [];   // [ ['conn'=>..., 'accounts'=>[...], 'error'=>?] ]
$campaigns = [];
$pagination = [];
$apiError = null;
[$from, $to] = ads_date_range($range);
if ($z) {
    try {
        $connections = ads_connections($z);
        foreach ($connections as $c) {
            $row = ['conn' => $c, 'accounts' => [], 'error' => null];
            try {
                $row['accounts'] = ads_ad_accounts($z, $c['_id'], isset($_GET['refresh']));
            } catch (ZernioException $ex) {
                $row['error'] = $ex->getMessage();
            }
            $adAccounts[] = $row;
        }
        $r = ads_campaigns($z, [
            'platform' => $platform ?: null, 'adAccountId' => $adAcc ?: null, 'status' => $status ?: null,
            'fromDate' => $from, 'toDate' => $to, 'page' => $pg, 'limit' => 25,
        ]);
        $campaigns = $r['campaigns'] ?? [];
        $pagination = $r['pagination'] ?? [];
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

// الصرف لكل حساب إعلانات (من الحملات المعروضة)
$spendByAcc = [];
$totals = [];
foreach ($campaigns as $c) {
    $m = $c['metrics'] ?? [];
    $a = (string)($c['platformAdAccountId'] ?? '');
    $spendByAcc[$a] = ($spendByAcc[$a] ?? 0) + (float)($m['spend'] ?? 0);
    $cur = (string)($c['currency'] ?? '');
    $t = $totals[$cur] ?? ['spend' => 0, 'impressions' => 0, 'clicks' => 0, 'results' => 0];
    $t['spend'] += (float)($m['spend'] ?? 0);
    $t['impressions'] += (int)($m['impressions'] ?? 0);
    $t['clicks'] += (int)($m['clicks'] ?? 0);
    $t['results'] += ads_results($m);
    $totals[$cur] = $t;
}
$usableCount = 0;
foreach ($adAccounts as $row) {
    foreach ($row['accounts'] as $a) if (ads_account_usable($a)) $usableCount++;
}

render(t('الإعلانات'), 'ads', function () use ($z, $apiError, $adAccounts, $campaigns, $pagination, $platform, $adAcc, $status, $range, $pg, $spendByAcc, $totals, $usableCount, $from, $to) {
    if (!$z) { no_api_box(); return; }
    $q = function (array $over = []) use ($platform, $adAcc, $status, $range) {
        return url('ads', array_filter($over + ['pf' => $platform, 'acc' => $adAcc, 'st' => $status, 'r' => $range], function ($v) { return $v !== '' && $v !== null; }));
    };
    ?>
    <div data-ai-insights="ads_analyze,audience,budget_advice" class="mb-6"></div>
    <div class="space-y-5">
      <?php if ($apiError) error_box($apiError); ?>

      <div class="flex flex-wrap items-center gap-2">
        <a href="<?= e(url('ad_create')) ?>" class="btn-primary"><?= e(t('+ حملة جديدة')) ?></a>
        <a href="<?= e(url('ad_audiences')) ?>" class="btn-ghost"><?= e(t('الجماهير')) ?></a>
        <a href="<?= e(url('leads')) ?>" class="btn-ghost"><?= e(t('العملاء المحتملين (Leads)')) ?></a>
      </div>

      <?php if (!$adAccounts || $usableCount === 0 && !array_filter($adAccounts, function ($r) { return $r['accounts']; })): ?>
        <?php ads_empty_state(); ?>
      <?php else: ?>
      <!-- حسابات الإعلانات -->
      <section class="card p-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <h2 class="font-bold text-ink-900"><?= e(t('حسابات الإعلانات')) ?></h2>
          <a href="<?= e($q(['refresh' => 1])) ?>" class="link text-sm"><?= e(t('تحديث القائمة')) ?></a>
        </div>
        <div class="space-y-4">
          <?php foreach ($adAccounts as $row): $c = $row['conn'];
            $usable = array_values(array_filter($row['accounts'], 'ads_account_usable'));
            $off = array_values(array_filter($row['accounts'], function ($a) { return !ads_account_usable($a); }));
            ?>
            <div class="rounded-xl border border-ink-200 p-4">
              <div class="flex items-center gap-3">
                <?= avatar($c['profilePicture'] ?? null, $c['displayName'] ?? '', 'w-9 h-9') ?>
                <div class="min-w-0">
                  <div class="font-semibold text-ink-900 truncate"><?= e($c['displayName'] ?? $c['username'] ?? '') ?></div>
                  <div class="text-xs text-ink-500"><?= e(ads_platform_label($c['platform'] ?? '')) ?></div>
                </div>
              </div>
              <?php if ($row['error']): ?>
                <p class="text-sm text-ink-500 mt-2"><?= e(t('مش قادرين نقرا حسابات الإعلانات هنا: %s', $row['error'])) ?></p>
              <?php elseif (!$row['accounts']): ?>
                <p class="text-sm text-ink-500 mt-2"><?= e(t('مفيش حسابات إعلانات على الحساب ده.')) ?></p>
              <?php else: ?>
                <div class="mt-3 grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                  <?php foreach ($usable as $a): $id = (string)$a['id']; ?>
                    <a href="<?= e($q(['acc' => $id, 'pg' => null])) ?>" class="rounded-lg border p-3 hover:bg-ink-50 <?= $adAcc === $id ? 'border-brand-500 bg-brand-50' : 'border-ink-200' ?>">
                      <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-sm text-ink-900 truncate"><?= e($a['name'] ?? $id) ?></span>
                        <span class="badge badge-green"><?= e(t('شغال')) ?></span>
                      </div>
                      <div class="text-xs text-ink-500 mt-1" dir="ltr"><?= e($id) ?> · <?= e($a['currency'] ?? '') ?></div>
                      <?php if (isset($spendByAcc[$id])): ?><div class="text-xs text-ink-700 mt-1"><?= e(t('الصرف: %s', ads_money($spendByAcc[$id], $a['currency'] ?? null))) ?></div><?php endif; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
                <?php if ($off): ?>
                  <details class="mt-2">
                    <summary class="text-xs text-ink-500 cursor-pointer"><?= e(t('حسابات موقوفة أو مش متاحة (%s)', count($off))) ?></summary>
                    <ul class="mt-2 text-xs text-ink-600 space-y-1">
                      <?php foreach ($off as $a): ?>
                        <li><span class="font-medium"><?= e($a['name'] ?? $a['id']) ?></span> <span dir="ltr">(<?= e($a['id']) ?>)</span> — <?= e($a['unusableReason'] ?? t('غير متاح')) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </details>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <details class="mt-4">
          <summary class="text-sm link cursor-pointer"><?= e(t('ربط حساب إعلانات تاني')) ?></summary>
          <?php ads_connect_buttons(); ?>
        </details>
      </section>
      <?php endif; ?>

      <!-- الفلاتر -->
      <form method="get" class="card p-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="p" value="ads">
        <div>
          <label class="label"><?= e(t('الفترة')) ?></label>
          <select name="r" class="input" onchange="this.form.submit()">
            <?php foreach (ads_date_presets() as $k => $v): ?><option value="<?= e($k) ?>" <?= (string)$k === $range ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label"><?= e(t('المنصة')) ?></label>
          <select name="pf" class="input" onchange="this.form.submit()">
            <option value=""><?= e(t('كل المنصات')) ?></option>
            <?php foreach (ads_campaign_platforms() as $p): ?><option value="<?= e($p) ?>" <?= $p === $platform ? 'selected' : '' ?>><?= e(ads_platform_label($p)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label"><?= e(t('حساب الإعلانات')) ?></label>
          <select name="acc" class="input" onchange="this.form.submit()">
            <option value=""><?= e(t('كل الحسابات')) ?></option>
            <?php foreach ($adAccounts as $row) foreach ($row['accounts'] as $a): if (!ads_account_usable($a) && $adAcc !== (string)$a['id']) continue; ?>
              <option value="<?= e($a['id']) ?>" <?= $adAcc === (string)$a['id'] ? 'selected' : '' ?>><?= e(($a['name'] ?? $a['id']) . ' — ' . ads_platform_label($row['conn']['platform'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label"><?= e(t('الحالة')) ?></label>
          <select name="st" class="input" onchange="this.form.submit()">
            <option value=""><?= e(t('كل الحالات')) ?></option>
            <?php foreach (['active' => t('شغالة'), 'paused' => t('متوقفة'), 'pending_review' => t('تحت المراجعة'), 'rejected' => t('مرفوضة'), 'completed' => t('خلصت'), 'error' => t('فيها مشكلة')] as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $k === $status ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="btn-primary"><?= e(t('عرض')) ?></button></noscript>
        <span class="text-xs text-ink-500 ms-auto" dir="ltr"><?= e($from . ' → ' . $to) ?></span>
      </form>

      <!-- الإجماليات -->
      <?php foreach ($totals as $cur => $tt): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <?php foreach ([
              [t('الصرف'), ads_money($tt['spend'], $cur ?: null)],
              [t('مرات الظهور'), ads_num($tt['impressions'])],
              [t('الضغطات'), ads_num($tt['clicks'])],
              [t('النتايج'), ads_num($tt['results'])],
          ] as $k): ?>
            <div class="card p-4"><div class="text-xs text-ink-500"><?= e($k[0]) ?></div><div class="text-xl font-bold text-ink-900 mt-1"><?= e($k[1]) ?></div></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <!-- الحملات -->
      <section class="card overflow-hidden">
        <div class="p-4 border-b border-ink-200 flex items-center justify-between">
          <h2 class="font-bold text-ink-900"><?= e(t('الحملات')) ?></h2>
          <span class="text-xs text-ink-500"><?= e(t('%s حملة', (int)($pagination['total'] ?? count($campaigns)))) ?></span>
        </div>
        <?php if (!$campaigns): ?>
          <div class="p-8 text-center text-sm text-ink-500">
            <?= e(t('مفيش حملات بالفلاتر دي.')) ?>
            <div class="mt-3"><a href="<?= e(url('ad_create')) ?>" class="link"><?= e(t('اعمل أول حملة')) ?></a></div>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-ink-50 text-ink-600 text-xs">
                <tr>
                  <th class="text-start p-3"><?= e(t('الحملة')) ?></th>
                  <th class="text-start p-3"><?= e(t('الحالة')) ?></th>
                  <th class="text-start p-3"><?= e(t('الميزانية')) ?></th>
                  <th class="text-end p-3"><?= e(t('الصرف')) ?></th>
                  <th class="text-end p-3"><?= e(t('الظهور')) ?></th>
                  <th class="text-end p-3"><?= e(t('الضغطات')) ?></th>
                  <th class="text-end p-3">CTR</th>
                  <th class="text-end p-3">CPC</th>
                  <th class="text-end p-3"><?= e(t('النتايج')) ?></th>
                  <th class="p-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-ink-100">
              <?php foreach ($campaigns as $c):
                $m = $c['metrics'] ?? [];
                $cur = $c['currency'] ?? null;
                $cid = (string)($c['platformCampaignId'] ?? '');
                $cpf = (string)($c['platform'] ?? '');
                $budget = $c['campaignBudget'] ?? $c['budget'] ?? null;
                $st = (string)($c['status'] ?? '');
                $detail = url('ad_campaign', ['id' => $cid, 'pf' => $cpf, 'r' => $range]);
                ?>
                <tr class="align-top">
                  <td class="p-3 min-w-[14rem]">
                    <a href="<?= e($detail) ?>" class="font-semibold text-ink-900 hover:underline"><?= e($c['campaignName'] ?? $cid) ?></a>
                    <div class="text-xs text-ink-500 mt-0.5"><?= e(ads_platform_label($cpf)) ?> · <?= e($c['platformAdAccountName'] ?? $c['platformAdAccountId'] ?? '') ?> · <?= e(t('%s إعلان', (int)($c['adCount'] ?? 0))) ?></div>
                  </td>
                  <td class="p-3"><?= ads_status_badge($st) ?></td>
                  <td class="p-3 whitespace-nowrap"><?= e(ads_budget_text($budget, $cur)) ?></td>
                  <td class="p-3 text-end whitespace-nowrap font-medium"><?= e(ads_money($m['spend'] ?? 0, $cur)) ?></td>
                  <td class="p-3 text-end"><?= e(ads_num($m['impressions'] ?? 0)) ?></td>
                  <td class="p-3 text-end"><?= e(ads_num($m['clicks'] ?? 0)) ?></td>
                  <td class="p-3 text-end"><?= e(number_format((float)($m['ctr'] ?? 0), 2)) ?>%</td>
                  <td class="p-3 text-end"><?= e(ads_money($m['cpc'] ?? 0)) ?></td>
                  <td class="p-3 text-end"><?= e(ads_num(ads_results($m))) ?></td>
                  <td class="p-3 whitespace-nowrap">
                    <?php if ($st === 'active' || $st === 'pending_review'): ?>
                      <form method="post" onsubmit="return confirm('<?= tjs('إيقاف الحملة دي؟') ?>')">
                        <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="to" value="paused">
                        <input type="hidden" name="campaign_id" value="<?= e($cid) ?>"><input type="hidden" name="platform" value="<?= e($cpf) ?>">
                        <button class="btn-ghost !py-1 !px-3"><?= e(t('إيقاف')) ?></button>
                      </form>
                    <?php elseif ($st === 'paused'): ?>
                      <?php if (can('ads.spend')): ?>
                        <details class="relative">
                          <summary class="btn-ghost !py-1 !px-3 cursor-pointer list-none"><?= e(t('تشغيل…')) ?></summary>
                          <form method="post" class="absolute end-0 z-10 mt-1 w-72 card p-3 space-y-2 text-start">
                            <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="to" value="active">
                            <input type="hidden" name="campaign_id" value="<?= e($cid) ?>"><input type="hidden" name="platform" value="<?= e($cpf) ?>">
                            <?= ads_spend_confirm(t('موافق إن الحملة دي هتبدأ تصرف فلوس فعلاً. الميزانية: %s', ads_budget_text($budget, $cur))) ?>
                            <button class="btn-primary w-full"><?= e(t('تشغيل الحملة')) ?></button>
                          </form>
                        </details>
                      <?php else: ?>
                        <span class="text-xs text-ink-400"><?= e(t('التشغيل لصاحب الشركة')) ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?= e($detail) ?>" class="link text-xs block mt-1"><?= e(t('التفاصيل')) ?></a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <div class="p-3 flex justify-between text-sm border-t border-ink-200">
              <?php if ($pg > 1): ?><a class="link" href="<?= e($q(['pg' => $pg - 1])) ?>"><?= e(t('السابق')) ?></a><?php else: ?><span></span><?php endif; ?>
              <span class="text-ink-500"><?= e(t('صفحة %s من %s', $pg, (int)$pagination['pages'])) ?></span>
              <?php if ($pg < (int)$pagination['pages']): ?><a class="link" href="<?= e($q(['pg' => $pg + 1])) ?>"><?= e(t('التالي')) ?></a><?php else: ?><span></span><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
    <?php
});
