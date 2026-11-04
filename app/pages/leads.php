<?php
/** العملاء المحتملين (Lead Gen): الفورمات والـ Leads وتنزيلهم CSV */
$err = null;
$z = zernio_or_null($err);
$connSel = (string)($_GET['c'] ?? '');
$adAcc = (string)($_GET['acc'] ?? '');
$formId = (string)($_GET['form'] ?? '');
$cursor = (string)($_GET['cursor'] ?? '');

$conns = [];
$forms = [];
$leads = [];
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

            $r = ads_leads($z, $filters + ['limit' => 25, 'cursor' => $cursor ?: null]);
            $leads = $r['leads'] ?? [];
            $next = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['cursor'] ?? null) : null;
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('العملاء المحتملين'), 'leads', function () use ($z, $apiError, $formsError, $conns, $current, $adAccounts, $adAcc, $forms, $formId, $leads, $next) {
    if (!$z) { no_api_box(); return; }
    $base = ['c' => $current['_id'] ?? null, 'acc' => $adAcc ?: null, 'form' => $formId ?: null];
    $statuses = ['new' => t('جديد'), 'contacted' => t('اتكلمنا معاه'), 'unreachable' => t('مش بيرد'), 'qualified' => t('مهتم'), 'converted' => t('بقى عميل'), 'disqualified' => t('مش مناسب')];
    ?>
    <div class="space-y-5">
      <?php if ($apiError) error_box($apiError); ?>
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

      <section class="card overflow-hidden">
        <div class="p-4 border-b border-ink-200"><h2 class="font-bold text-ink-900"><?= e(t('الـ Leads')) ?></h2></div>
        <?php if (!$leads): ?>
          <p class="p-6 text-sm text-ink-500 text-center"><?= e(t('مفيش Leads لسه.')) ?></p>
        <?php else: ?>
          <div class="divide-y divide-ink-100">
            <?php foreach ($leads as $l): ?>
              <div class="p-4">
                <div class="flex flex-wrap items-center gap-2 text-xs text-ink-500">
                  <span><?= e(fmt_date($l['createdTime'] ?? null, 'd/m/Y h:i A')) ?></span>
                  <span>· <?= e($l['formName'] ?? $l['formId'] ?? '') ?></span>
                  <?php if (!empty($l['status'])): ?><span class="badge"><?= e($statuses[$l['status']] ?? $l['status']) ?></span><?php endif; ?>
                  <?php if (!empty($l['isOrganic'])): ?><span class="badge"><?= e(t('مجاني')) ?></span><?php endif; ?>
                </div>
                <dl class="mt-2 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1 text-sm">
                  <?php foreach ((array)($l['fields'] ?? []) as $k => $v): ?>
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
    <?php
});
