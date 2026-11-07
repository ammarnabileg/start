<?php
/** العملاء المحتملين (Lead Gen): الفورمات والـ Leads وتنزيلهم CSV */
$err = null;
$z = zernio_or_null($err);
$connSel = (string)($_GET['c'] ?? '');
$adAcc = (string)($_GET['acc'] ?? '');
$formId = (string)($_GET['form'] ?? '');
$cursor = (string)($_GET['cursor'] ?? '');

$stages = ['new' => t('جديد'), 'contacted' => t('اتكلمنا معاه'), 'qualified' => t('مهتم'), 'converted' => t('بقى عميل'), 'lost' => t('مش مناسب')];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lead_save') {
    header('Content-Type: application/json; charset=utf-8');
    $lid = mb_substr((string)($_POST['lead_id'] ?? ''), 0, 120);
    $stage = (string)($_POST['stage'] ?? 'new');
    if ($lid === '' || !isset($stages[$stage])) { echo json_encode(['ok' => false, 'error' => t('بيانات ناقصة')]); exit; }
    $val = trim((string)($_POST['value'] ?? ''));
    $val = $val === '' ? null : max(0, (float)str_replace(',', '.', $val));
    $st = db()->prepare("INSERT INTO lead_meta (workspace_id, lead_id, campaign_id, stage, deal_value, note, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE stage = VALUES(stage), deal_value = VALUES(deal_value), note = VALUES(note), campaign_id = COALESCE(VALUES(campaign_id), campaign_id), updated_by = VALUES(updated_by)");
    $st->execute([(int)ws('id'), $lid, ($_POST['campaign_id'] ?? '') !== '' ? mb_substr((string)$_POST['campaign_id'], 0, 120) : null, $stage, $val, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500) ?: null, (int)(current_user()['id'] ?? 0) ?: null]);
    log_activity('leads.stage', $lid . ' ' . $stage);
    // نفس المرحلة في الـ Leads الموحدة
    $st = db()->prepare('UPDATE crm_leads SET stage = ?, deal_value = COALESCE(?, deal_value) WHERE workspace_id = ? AND ext_id = ?');
    $st->execute([crm_stage_from_ad($stage), $val, (int)ws('id'), mb_substr('ad:' . $lid, 0, 64)]);
    echo json_encode(['ok' => true]);
    exit;
}

$conns = [];
$forms = [];
$leads = [];
$recent = [];
$campMap = [];
$meta = [];
$next = null;
$apiError = null;
$formsError = null;
$current = null;
$adAccounts = [];
if ($z) {
    try {
        foreach (ads_connections($z) as $c) {
            if (in_array($c['platform'] ?? '', ['facebook', 'metaads', 'linkedin', 'linkedinads'], true)) $conns[] = $c;
        }
        foreach ($conns as $c) if ($c['_id'] === $connSel) $current = $c;
        if (!$current && $conns) $current = $conns[0];
        if ($current) {
            $isLi = strpos((string)$current['platform'], 'linkedin') === 0;
            if ($isLi) {
                try {
                    $adAccounts = array_values(array_filter(ads_ad_accounts($z, $current['_id']), 'ads_account_usable'));
                } catch (ZernioException $ex) {
                    $adAccounts = [];
                }
                $ids = array_map(function ($a) { return (string)$a['id']; }, $adAccounts);
                if (!in_array($adAcc, $ids, true)) $adAcc = $ids[0] ?? '';
            } else {
                $adAcc = '';
            }
            try {
                $forms = ads_lead_forms($z, $current['_id'], $adAcc ?: null)['forms'] ?? [];
            } catch (ZernioException $ex) {
                $formsError = $ex->getMessage();
            }
            $filters = ['accountId' => $current['_id'], 'adAccountId' => $adAcc ?: null, 'formId' => $formId ?: null,
                'platform' => $isLi ? 'linkedin' : 'meta'];

            // تنزيل CSV
            if (isset($_GET['csv'])) {
                $all = [];
                $cur = null;
                for ($i = 0; $i < 50; $i++) {
                    $r = ads_leads($z, $filters + ['limit' => 100, 'cursor' => $cur]);
                    $all = array_merge($all, $r['leads'] ?? []);
                    $cur = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['cursor'] ?? null) : null;
                    if (!$cur) break;
                }
                $cols = [];
                foreach ($all as $l) foreach (array_keys((array)($l['fields'] ?? [])) as $k) $cols[$k] = true;
                $cols = array_keys($cols);
                log_activity('ads.leads_export', count($all) . ' leads');
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_merge(['created_time', 'form', 'status', 'campaign_id', 'ad_id'], $cols));
                foreach ($all as $l) {
                    $row = [(string)($l['createdTime'] ?? ''), (string)($l['formName'] ?? $l['formId'] ?? ''), (string)($l['status'] ?? ''), (string)($l['campaignId'] ?? ''), (string)($l['adId'] ?? '')];
                    foreach ($cols as $k) {
                        $v = $l['fields'][$k] ?? '';
                        $row[] = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
                    }
                    fputcsv($out, $row);
                }
                fclose($out);
                exit;
            }

            $fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/leads.json') ? json_decode((string)file_get_contents($fxd . '/leads.json'), true) : null;
            if ($fx) {
                $forms = $fx['forms'] ?? [];
                $leads = array_values(array_filter($fx['leads'] ?? [], function ($l) use ($formId) { return !$formId || ($l['formId'] ?? '') === $formId; }));
                $recent = $leads;
            } else {
                $r = ads_leads($z, $filters + ['limit' => 25, 'cursor' => $cursor ?: null]);
                $leads = $r['leads'] ?? [];
                $next = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['cursor'] ?? null) : null;
                // للتحليل حسب الحملة: آخر 300 lead (3 صفحات قراءة بس)، متخزنة 10 دقايق
                $ck = 'leads_recent_' . md5(json_encode($filters));
                if (isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 600 && !isset($_GET['refresh'])) {
                    $recent = $_SESSION[$ck]['d'];
                } else {
                    $recent = $leads; $cur = $next;
                    for ($i = 0; $i < 2 && $cur; $i++) { $r2 = ads_leads($z, $filters + ['limit' => 100, 'cursor' => $cur]); $recent = array_merge($recent, $r2['leads'] ?? []); $cur = ($r2['pagination']['hasMore'] ?? false) ? ($r2['pagination']['cursor'] ?? null) : null; }
                    $_SESSION[$ck] = ['t' => time(), 'd' => array_map(function ($l) { return ['id' => $l['id'] ?? $l['leadId'] ?? '', 'campaignId' => $l['campaignId'] ?? '', 'createdTime' => $l['createdTime'] ?? '']; }, $recent)];
                }
            }
            // صرف الحملات آخر 30 يوم (قراءة بس) عشان تكلفة الـ lead
            [$cFrom, $cTo] = ads_date_range('30');
            try {
                $cr = ($fxd && is_file($fxd . '/ads.json')) ? json_decode((string)file_get_contents($fxd . '/ads.json'), true) : ads_campaigns($z, ['fromDate' => $cFrom, 'toDate' => $cTo, 'limit' => 100]);
                foreach ($cr['campaigns'] ?? [] as $c) $campMap[(string)$c['platformCampaignId']] = ['name' => (string)($c['campaignName'] ?? ''), 'spend' => (float)($c['metrics']['spend'] ?? 0), 'cur' => (string)($c['currency'] ?? ''), 'pf' => (string)($c['platform'] ?? '')];
            } catch (ZernioException $ex) { /* اختياري */ }
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

// أي lead بيظهر هنا بيتضاف للـ Leads الموحدة (من غير طلبات زيادة على Zernio)
$crmNew = 0;
if ($leads && module_enabled('crm')) {
    try { $crmNew = crm_import_ad_leads($leads)[0]; } catch (Throwable $t) { /* قبل التحديث */ }
}
// حالات الـ leads المحفوظة عندنا
$ids = array_values(array_unique(array_filter(array_merge(array_map(function ($l) { return (string)($l['id'] ?? $l['leadId'] ?? ''); }, $leads), array_map(function ($l) { return (string)($l['id'] ?? ''); }, $recent)))));
if ($ids) {
    try {
        $st = db()->prepare("SELECT lead_id, stage, deal_value, note, updated_at FROM lead_meta WHERE workspace_id = ? AND lead_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        $st->execute(array_merge([(int)ws('id')], $ids));
        foreach ($st->fetchAll() as $r) $meta[$r['lead_id']] = $r;
    } catch (Throwable $e) { /* الجدول لسه ما اتعملش: شغّل setup.php */ }
}
// التحليل حسب الحملة (آخر 30 يوم)
$attr = [];
$since = strtotime('-30 days');
foreach ($recent as $l) {
    $t = strtotime((string)($l['createdTime'] ?? '')) ?: 0; if ($t < $since) continue;
    $cid = (string)($l['campaignId'] ?? ''); $lid = (string)($l['id'] ?? $l['leadId'] ?? '');
    $a = &$attr[$cid]; $a['leads'] = ($a['leads'] ?? 0) + 1;
    $m = $meta[$lid] ?? null;
    if ($m && $m['stage'] === 'converted') { $a['won'] = ($a['won'] ?? 0) + 1; $a['value'] = ($a['value'] ?? 0) + (float)$m['deal_value']; }
    if ($m && in_array($m['stage'], ['qualified', 'converted'], true)) $a['qual'] = ($a['qual'] ?? 0) + 1;
    unset($a);
}
uasort($attr, function ($x, $y) { return ($y['leads'] ?? 0) <=> ($x['leads'] ?? 0); });

render(t('العملاء المحتملين'), 'leads', function () use ($stages, $meta, $attr, $campMap, $z, $apiError, $formsError, $conns, $current, $adAccounts, $adAcc, $forms, $formId, $leads, $next, $crmNew) {
    if (!$z) { no_api_box(); return; }
    $base = ['c' => $current['_id'] ?? null, 'acc' => $adAcc ?: null, 'form' => $formId ?: null];
    $campUrl = function ($cid) use ($campMap) { return url('ad_campaign', ['id' => $cid, 'pf' => $campMap[$cid]['pf'] ?? 'facebook']); };
    $statuses = ['new' => t('جديد'), 'contacted' => t('اتكلمنا معاه'), 'unreachable' => t('مش بيرد'), 'qualified' => t('مهتم'), 'converted' => t('بقى عميل'), 'disqualified' => t('مش مناسب')];
    ?>
    <div class="space-y-5">
      <?php if ($apiError) error_box($apiError); ?>
      <?php if (module_enabled('crm') && can('crm.view') && $leads): ?>
        <div class="rounded-xl border border-lime-200 bg-lime-50/60 px-4 py-2.5 text-sm text-ink-700 flex flex-wrap items-center gap-2">
          <span><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg> <?= e($crmNew ? t('اتضاف %s Lead جديد من هنا للـ Leads الموحدة.', $crmNew) : t('الـ Leads دي متربطة بالـ Leads الموحدة، والمرحلة بتتحدّث في المكانين.')) ?></span>
          <a class="link ms-auto" target="_top" href="<?= e(url('prospects', ['stat' => 'ads'])) ?>"><?= e(t('عملاء الإعلانات في صفحة العملاء')) ?> ←</a>
        </div>
      <?php endif; ?>
      <?php if (!$conns && !$apiError): ?>
        <div class="card p-6 text-center">
          <h2 class="font-bold text-ink-900"><?= e(t('مفيش حساب فيسبوك أو لينكدإن مربوط')) ?></h2>
          <p class="text-sm text-ink-600 mt-2"><?= e(t('الـ Leads بتيجي من فورمات الإعلانات على فيسبوك ولينكدإن. اربط الحساب من صفحة الحسابات أو صفحة الإعلانات.')) ?></p>
          <a href="<?= e(url('ads')) ?>" class="btn-primary mt-4"><?= e(t('صفحة الإعلانات')) ?></a>
        </div>
      <?php elseif ($current): ?>
      <form method="get" class="card p-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="p" value="leads">
        <div>
          <label class="label"><?= e(t('الحساب')) ?></label>
          <select name="c" class="input" onchange="this.form.submit()">
            <?php foreach ($conns as $c): ?><option value="<?= e($c['_id']) ?>" <?= $c['_id'] === $current['_id'] ? 'selected' : '' ?>><?= e(($c['displayName'] ?? '') . ' — ' . ads_platform_label($c['platform'] ?? '')) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php if ($adAccounts): ?>
        <div>
          <label class="label"><?= e(t('حساب الإعلانات')) ?></label>
          <select name="acc" class="input" onchange="this.form.submit()">
            <?php foreach ($adAccounts as $a): ?><option value="<?= e($a['id']) ?>" <?= (string)$a['id'] === $adAcc ? 'selected' : '' ?>><?= e($a['name'] ?? $a['id']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label class="label"><?= e(t('الفورم')) ?></label>
          <select name="form" class="input" onchange="this.form.submit()">
            <option value=""><?= e(t('كل الفورمات')) ?></option>
            <?php foreach ($forms as $f): $fid = (string)($f['id'] ?? $f['formId'] ?? ''); ?><option value="<?= e($fid) ?>" <?= $fid === $formId ? 'selected' : '' ?>><?= e($f['name'] ?? $fid) ?></option><?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="btn-primary"><?= e(t('عرض')) ?></button></noscript>
        <a href="<?= e(url('leads', array_filter($base + ['csv' => 1]))) ?>" class="btn-ghost ms-auto"><?= e(t('تنزيل CSV')) ?></a>
      </form>

      <section class="card p-5">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('فورمات الـ Leads')) ?></h2>
        <?php if ($formsError): ?><p class="text-sm text-ink-500"><?= e($formsError) ?></p>
        <?php elseif (!$forms): ?><p class="text-sm text-ink-500"><?= e(t('مفيش فورمات على الحساب ده. الفورمات بتتعمل من مدير الإعلانات في المنصة.')) ?></p>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <?php foreach ($forms as $f): $fid = (string)($f['id'] ?? $f['formId'] ?? ''); ?>
              <a href="<?= e(url('leads', array_filter(['form' => $fid] + $base))) ?>" class="rounded-lg border p-3 hover:bg-ink-50 <?= $fid === $formId ? 'border-brand-500 bg-brand-50' : 'border-ink-200' ?>">
                <div class="font-medium text-sm text-ink-900"><?= e($f['name'] ?? $fid) ?></div>
                <div class="text-xs text-ink-500 mt-1"><?= e($f['status'] ?? '') ?><?php if (isset($f['leadsCount']) || isset($f['leads_count'])): ?> · <?= e(t('%s lead', ads_num($f['leadsCount'] ?? $f['leads_count']))) ?><?php endif; ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($attr): ?>
      <section class="card overflow-hidden" id="ld-attr">
        <div class="p-4 border-b border-ink-200 flex flex-wrap items-center gap-2">
          <h2 class="font-bold text-ink-900 me-auto"><?= e(t('العملاء حسب الحملة')) ?> <span class="text-xs font-normal text-ink-500"><?= e(t('آخر 30 يوم')) ?></span></h2>
          <span class="text-xs text-ink-500"><?= e(t('العائد محسوب من قيمة الصفقات اللي سجلتها هنا للعملاء اللي "بقوا عملاء"')) ?></span>
        </div>
        <div class="overflow-x-auto"><table class="w-full text-sm">
          <thead class="bg-ink-50 text-ink-600 text-xs"><tr>
            <th class="text-start p-3"><?= e(t('الحملة')) ?></th><th class="text-end p-3"><?= e(t('العملاء المحتملين')) ?></th><th class="text-end p-3"><?= e(t('الصرف')) ?></th>
            <th class="text-end p-3"><?= e(t('تكلفة العميل المحتمل')) ?></th><th class="text-end p-3"><?= e(t('مهتمين')) ?></th><th class="text-end p-3"><?= e(t('بقوا عملاء')) ?></th><th class="text-end p-3"><?= e(t('قيمة الصفقات')) ?></th><th class="text-end p-3"><?= e(t('العائد على الصرف')) ?></th>
          </tr></thead>
          <tbody class="divide-y divide-ink-100">
          <?php foreach ($attr as $cid => $a): $c = $campMap[$cid] ?? null; $sp = $c['spend'] ?? 0; $cur = $c['cur'] ?? ''; $roas = $sp > 0 && !empty($a['value']) ? $a['value'] / $sp : null; ?>
            <tr class="ld-arow cursor-pointer hover:bg-ink-50" data-camp="<?= e($cid) ?>">
              <td class="p-3"><?php if ($cid === ''): ?><span class="text-ink-500"><?= e(t('من غير حملة (مجاني)')) ?></span><?php else: ?><b class="text-ink-900"><?= e($c['name'] ?? $cid) ?></b><?php if ($c): ?> <a href="<?= e($campUrl($cid)) ?>" class="link text-xs"><?= e(t('التفاصيل')) ?></a><?php endif; ?><?php endif; ?></td>
              <td class="p-3 text-end tabular-nums font-semibold"><?= (int)$a['leads'] ?></td>
              <td class="p-3 text-end tabular-nums" dir="ltr" style="text-align:end"><?= $c ? e(ads_money($sp, $cur)) : '—' ?></td>
              <td class="p-3 text-end tabular-nums font-semibold" dir="ltr" style="text-align:end"><?= $c && $sp > 0 ? e(ads_money($sp / $a['leads'], $cur)) : '—' ?></td>
              <td class="p-3 text-end tabular-nums"><?= (int)($a['qual'] ?? 0) ?></td>
              <td class="p-3 text-end tabular-nums"><?= (int)($a['won'] ?? 0) ?><?php if (!empty($a['won'])): ?> <span class="text-xs text-ink-500">(<?= round($a['won'] / $a['leads'] * 100) ?>%)</span><?php endif; ?></td>
              <td class="p-3 text-end tabular-nums" dir="ltr" style="text-align:end"><?= !empty($a['value']) ? e(ads_money($a['value'], $cur ?: null)) : '—' ?></td>
              <td class="p-3 text-end tabular-nums"><?php if ($roas !== null): ?><span class="rounded px-1.5 py-0.5 font-semibold <?= $roas >= 1 ? 'bg-lime-100 text-lime-800' : 'bg-red-50 text-red-700' ?>" dir="ltr"><?= e(number_format($roas, 1)) ?>×</span><?php else: ?>—<?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>
      <?php endif; ?>

      <section class="card overflow-hidden<?= is_embed() ? ' hidden' : '' ?>" id="ld-list">
        <div class="p-4 border-b border-ink-200 flex flex-wrap items-center gap-2">
          <h2 class="font-bold text-ink-900 me-auto"><?= e(t('الـ Leads')) ?> <span class="text-xs font-normal text-ink-500">(<span id="ld-count"><?= count($leads) ?></span>)</span></h2>
          <?php if ($leads): ?>
          <input id="ld-q" type="search" class="input !mt-0 !h-9 !py-0 w-60 text-sm" placeholder="<?= e(t('ابحث بالاسم أو الرقم أو الإيميل...')) ?> (/)" aria-label="<?= e(t('ابحث بالاسم أو الرقم أو الإيميل...')) ?>">
          <div id="ld-chips" class="w-full flex flex-wrap gap-1.5"></div>
          <?php endif; ?>
        </div>
        <?php if (!$leads): ?>
          <p class="p-6 text-sm text-ink-500 text-center"><?= e(t('مفيش Leads لسه.')) ?></p>
        <?php else: ?>
          <div class="divide-y divide-ink-100">
            <?php foreach ($leads as $l):
              $lid = (string)($l['id'] ?? $l['leadId'] ?? ''); $m = $meta[$lid] ?? null; $stage = $m['stage'] ?? 'new';
              $fl = []; foreach ((array)($l['fields'] ?? []) as $k => $v) $fl[mb_strtolower((string)$k)] = is_scalar($v) ? (string)$v : '';
              $pick = function (array $keys) use ($fl) { foreach ($fl as $k => $v) foreach ($keys as $want) if ($v !== '' && strpos($k, $want) !== false) return $v; return ''; };
              $name = $pick(['full_name', 'name', 'الاسم']); $phone = $pick(['phone', 'mobile', 'whatsapp', 'tel', 'رقم']); $mail = $pick(['email', 'mail']);
              $cid = (string)($l['campaignId'] ?? ''); $cur = $campMap[$cid]['cur'] ?? '';
              ?>
              <div class="ld-row p-4" data-id="<?= e($lid) ?>" data-camp="<?= e($cid) ?>" data-stage="<?= e($stage) ?>" data-t="<?= e((string)($l['createdTime'] ?? '')) ?>" data-phone-raw="<?= e($phone) ?>" data-q="<?= e(mb_strtolower(implode(' ', $fl) . ' ' . ($l['formName'] ?? '') . ' ' . ($campMap[$cid]['name'] ?? ''))) ?>">
                <div class="flex flex-wrap items-start gap-3 mb-2">
                  <div class="w-10 h-10 shrink-0 rounded-full bg-brand-100 text-brand-700 grid place-items-center font-bold"><?= e(mb_substr($name ?: '?', 0, 1)) ?></div>
                  <div class="min-w-0 flex-1">
                    <div class="font-semibold text-ink-900"><?= e($name ?: t('بدون اسم')) ?></div>
                    <div class="text-xs text-ink-500 flex flex-wrap gap-x-2" dir="auto"><?php if ($phone): ?><span dir="ltr" class="ld-ph"><?= e($phone) ?></span><?php endif; ?><?php if ($mail): ?><span dir="ltr"><?= e($mail) ?></span><?php endif; ?>
                      <?php if ($cid !== '' && isset($campMap[$cid])): ?><a href="<?= e($campUrl($cid)) ?>" class="link"><?= e(t('من حملة: %s', $campMap[$cid]['name'])) ?></a><?php endif; ?></div>
                  </div>
                  <div class="ld-acts flex items-center gap-1.5"></div>
                  <div class="flex items-center gap-2">
                    <select class="ld-stage input !mt-0 !h-9 !py-0 !w-auto text-sm" aria-label="<?= e(t('حالة العميل')) ?>"><?php foreach ($stages as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $k === $stage ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
                    <input class="ld-val input !mt-0 !h-9 !py-0 !w-32 text-sm <?= $stage === 'converted' ? '' : 'hidden' ?>" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" placeholder="<?= e(t('قيمة الصفقة') . ($cur ? ' (' . $cur . ')' : '')) ?>" aria-label="<?= e(t('قيمة الصفقة')) ?>" value="<?= e($m['deal_value'] ?? '') ?>">
                  </div>
                </div>
                <input class="ld-note input !mt-0 !py-1.5 text-sm mb-2" maxlength="500" placeholder="<?= e(t('ملاحظة (اختياري): مثلاً كلمته وعاوز يتصل بكرة')) ?>" value="<?= e($m['note'] ?? '') ?>" aria-label="<?= e(t('ملاحظة')) ?>">
                <div class="flex flex-wrap items-center gap-2 text-xs text-ink-500">
                  <span><?= e(fmt_date($l['createdTime'] ?? null, 'd/m/Y h:i A')) ?></span>
                  <span>· <?= e($l['formName'] ?? $l['formId'] ?? '') ?></span>
                  <?php if (!empty($l['status'])): ?><span class="badge"><?= e($statuses[$l['status']] ?? $l['status']) ?></span><?php endif; ?>
                  <?php if (!empty($l['isOrganic'])): ?><span class="badge"><?= e(t('مجاني')) ?></span><?php endif; ?>
                </div>
                <dl class="mt-2 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1 text-sm">
                  <?php foreach ((array)($l['fields'] ?? []) as $k => $v): if (is_scalar($v) && in_array((string)$v, array_filter([$name, $phone, $mail]), true)) continue; ?>
                    <div class="min-w-0"><dt class="inline text-ink-500"><?= e(str_replace('_', ' ', (string)$k)) ?>:</dt> <dd class="inline text-ink-900 break-words"><?= e(is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE)) ?></dd></div>
                  <?php endforeach; ?>
                </dl>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($next): ?><a href="<?= e(url('leads', array_filter($base + ['cursor' => $next]))) ?>" class="block text-center p-3 text-sm link border-t border-ink-200"><?= e(t('أقدم ←')) ?></a><?php endif; ?>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
    <script>window.LDX = <?= json_encode(['csrf' => csrf_token(), 'url' => url('leads'), 'stages' => $stages, 'all' => t('الكل'), 'saved' => t('اتحفظ'), 'err' => t('ما اتحفظش، جرّب تاني'),
      'wa' => t('واتساب'), 'call' => t('اتصال'), 'mail' => t('إيميل'), 'copy' => t('انسخ الرقم'), 'copied' => t('اتنسخ'), 'fresh' => t('جديد النهارده'), 'empty' => t('مفيش نتايج'),
      'filterCamp' => t('بيعرض عملاء حملة واحدة'), 'clear' => t('إلغاء'), 'ago' => ['m' => t('من %s دقيقة'), 'h' => t('من %s ساعة'), 'd' => t('من %s يوم')]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script>window.__phoneLoad = 1;</script><script src="assets/vendor/libphonenumber-max.js?v=1.11.12"></script><script src="assets/phone.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ui-leads.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>(function () { var t = document.querySelector('#ld-attr table'); if (!t) return; var hs = [].map.call(t.querySelectorAll('thead th'), function (h) { return h.textContent.trim(); });
      t.querySelectorAll('tbody tr').forEach(function (r) { [].forEach.call(r.children, function (td, i) { td.setAttribute('data-l', hs[i] || ''); }); }); document.getElementById('ld-attr').classList.add('ld-cards'); })();
    if (window.self !== window.top) document.querySelectorAll('main a[href]').forEach(function (a) { if (!a.target) a.target = '_top'; });</script>
    <?php
}, ['full' => true]);
