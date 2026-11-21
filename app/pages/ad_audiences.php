<?php
/** الجماهير المخصصة: عرض، إنشاء (حسب المنصة)، حذف */
/** الأنواع اللي Zernio بيقدر ينشئها على كل منصة (من التوثيق) */
function aud_types_for(string $plat): array
{
    $m = ['metaads' => ['meta_engagement', 'website', 'customer_list', 'lookalike'], 'facebookads' => ['meta_engagement', 'website', 'customer_list', 'lookalike'], 'instagramads' => ['meta_engagement', 'website', 'customer_list', 'lookalike'],
        'linkedinads' => ['engagement', 'customer_list'], 'tiktokads' => ['tiktok_engagement', 'website', 'customer_list', 'lookalike'], 'googleads' => ['website', 'customer_list', 'lookalike'],
        'pinterestads' => ['pinterest_engagement', 'website', 'customer_list', 'lookalike'], 'xads' => ['customer_list'], 'twitterads' => ['customer_list']];
    if (isset($m[$plat])) return $m[$plat];
    return ads_is_meta($plat) ? $m['metaads'] : ['customer_list'];
}
$err = null;
$z = zernio_or_null($err);
$sel = (string)($_GET['t'] ?? $_POST['t'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    [$connId, $adAccId] = array_pad(explode('|', $sel), 2, '');
    try {
        if ($action === 'pixel_code') {
            header('Content-Type: application/json; charset=utf-8');
            if ($connId === '' || $adAccId === '') exit(json_encode(['ok' => false, 'error' => t('اختار حساب الإعلانات.')], JSON_UNESCAPED_UNICODE));
            try { $tg = ads_pixel($z, $connId, (string)($_POST['tag'] ?? ''), $adAccId); } catch (Throwable $ex) { exit(json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE)); }
            exit(json_encode(['ok' => true, 'code' => (string)($tg['code'] ?? ''), 'events' => array_values((array)($tg['events'] ?? []))], JSON_UNESCAPED_UNICODE));
        }
        if ($action === 'pixel_create') {
            $pname = mb_substr(trim((string)($_POST['pixel_name'] ?? '')), 0, 100);
            if (!can('ads.spend')) flash('error', t('إنشاء البيكسل لصاحب صلاحية الإعلانات بس.'));
            elseif ($connId === '' || $adAccId === '') flash('error', t('اختار حساب الإعلانات.'));
            elseif (mb_strlen($pname) < 3) flash('error', t('اكتب اسم للبيكسل (3 حروف على الأقل).'));
            else {
                // Zernio مش بيمنع التكرار على Meta وTikTok، والبيكسل مابيتحذفش: نتأكد إن الاسم مش موجود قبل الإنشاء
                $exists = array_filter(ads_pixels($z, $connId, $adAccId), function ($x) use ($pname) { return mb_strtolower(trim((string)($x['name'] ?? ''))) === mb_strtolower($pname); });
                if ($exists) flash('error', t('فيه بيكسل بنفس الاسم بالفعل على الحساب ده.'));
                else { $tg = ads_pixel_create($z, $connId, $adAccId, $pname); log_activity('ads.pixel_create', $pname); flash('success', t('تم إنشاء البيكسل. انسخ كود التركيب وحطه في موقعك.')); redirect(url('ad_audiences', ['t' => $sel, 'px' => (string)($tg['id'] ?? '')])); }
            }
            redirect(url('ad_audiences', ['t' => $sel]));
        }
        if ($connId === '' || $adAccId === '') {
            flash('error', t('اختار حساب الإعلانات.'));
        } elseif ($action === 'delete') {
            if (!can('ads.spend')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } else {
                $id = (string)($_POST['audience_id'] ?? '');
                ads_delete_audience($z, $id);
                log_activity('ads.audience_delete', $id);
                flash('success', t('تم حذف الجمهور.'));
            }
        } elseif ($action === 'create' && !can('ads.spend')) {
            flash('error', t('إنشاء الجماهير لصاحب صلاحية الإعلانات فقط.'));
        } elseif ($action === 'create') {
            $type = (string)($_POST['type'] ?? '');
            $plat = '';
            foreach ((getenv('UI_FIXTURES') && is_file(getenv('UI_FIXTURES') . '/ad_targets.json')) ? (json_decode((string)file_get_contents(getenv('UI_FIXTURES') . '/ad_targets.json'), true)['targets'] ?? []) : ads_usable_accounts($z, ads_connections($z)) as $t0) if ($t0['conn']['_id'] . '|' . $t0['acc']['id'] === $sel) $plat = (string)($t0['conn']['platform'] ?? '');
            $allowed = aud_types_for($plat);
            $name = trim((string)($_POST['name'] ?? ''));
            $days = max(1, min(180, (int)($_POST['days'] ?? 30)));
            $p = ['accountId' => $connId, 'adAccountId' => $adAccId, 'name' => $name, 'type' => $type];
            $bad = null;
            $rows = [];
            if (mb_strlen($name) < 3) {
                $bad = t('اكتب اسم للجمهور (3 حروف على الأقل).');
            } elseif (!in_array($type, $allowed, true)) {
                $bad = t('نوع الجمهور ده مش مدعوم على المنصة دي.');
            } elseif ($type === 'website') {
                $p['retentionDays'] = $days;
                if ($plat !== 'googleads') { $p['pixelId'] = trim((string)($_POST['pixel_id'] ?? '')); if ($p['pixelId'] === '') $bad = t('اختار البيكسل.'); }
                if ($plat === 'tiktokads') { $ok = [7, 14, 30, 60, 90, 180]; $best = 7; foreach ($ok as $o) if (abs($o - $days) < abs($best - $days)) $best = $o; $p['retentionDays'] = $best; $ev = (string)($_POST['event'] ?? ''); if ($ev !== '') $p['event'] = $ev; }
                $u = trim((string)($_POST['url_contains'] ?? ''));
                if ($u !== '' && $plat !== 'pinterestads') $p['urlContains'] = $u;
            } elseif (in_array($type, ['engagement', 'tiktok_engagement', 'pinterest_engagement'], true)) {
                // بتتملي من تفاعل الناس مع حسابك على المنصة: مفيش حقول زيادة
            } elseif ($type === 'customer_list') {
                $raw = preg_split('/[\s,;]+/u', (string)($_POST['list'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($raw as $x) { $x = trim($x); if (filter_var($x, FILTER_VALIDATE_EMAIL)) $rows[mb_strtolower($x)] = ['email' => mb_strtolower($x)]; elseif (ads_is_meta($plat) && preg_match('/^\+?\d{8,15}$/', $x)) $rows[$x] = ['phone' => $x]; }
                if (!empty($_POST['from_crm']) && !can('crm.manage')) $bad = t('إضافة عملاء الـ CRM تحتاج صلاحية إدارة العملاء.');
                elseif (!empty($_POST['from_crm'])) {
                    $stg = (string)($_POST['crm_stage'] ?? '') === 'won' ? " AND stage = 'won'" : '';
                    $st = db()->prepare("SELECT email, phone FROM crm_leads WHERE workspace_id = ? AND email IS NOT NULL AND email <> ''" . $stg . ' LIMIT 50000'); $st->execute([(int)ws('id')]);
                    foreach ($st->fetchAll() as $r) { $em = mb_strtolower(trim((string)$r['email'])); if (filter_var($em, FILTER_VALIDATE_EMAIL)) $rows[$em] = array_filter(['email' => $em, 'phone' => ads_is_meta($plat) ? (string)$r['phone'] : '']); }
                }
                if (!$rows) $bad = t('مفيش إيميلات صالحة. الصق قائمة أو اختار عملاء الـ CRM.');
            } elseif ($type === 'meta_engagement') {
                $p['engagementSource'] = ($_POST['source'] ?? '') === 'instagram' ? 'instagram' : 'page';
                $p['sourceId'] = trim((string)($_POST['source_id'] ?? ''));
                $p['retentionDays'] = $days;
                if ($p['sourceId'] === '') $bad = t('اكتب رقم الصفحة أو حساب انستجرام.');
            } elseif ($type === 'lookalike') {
                $p['sourceAudienceId'] = trim((string)($_POST['source_audience'] ?? ''));
                $p['country'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['country'] ?? '')), 0, 2));
                if (in_array($plat, ['tiktokads', 'googleads'], true)) $p['size'] = in_array($_POST['size'] ?? '', ['narrow', 'balanced', 'broad'], true) ? $_POST['size'] : 'narrow';
                else $p['ratio'] = max(0.01, min($plat === 'pinterestads' ? 0.1 : 0.2, ((int)($_POST['ratio'] ?? 1)) / 100));
                if ($p['sourceAudienceId'] === '' || strlen($p['country']) !== 2) $bad = t('اختار الجمهور الأصلي واكتب كود الدولة.');
            } else {
                $bad = t('نوع جمهور مش مدعوم.');
            }
            if ($bad) {
                flash('error', $bad);
            } else {
                $res = ads_create_audience($z, $p);
                log_activity('ads.audience_create', $type . ': ' . $name);
                if ($rows) {
                    // الإيميلات بتتبعت نص عادي وZernio بيعمل لها تشفير SHA256 قبل ما يبعتها للمنصة
                    $aid = (string)($res['audience']['id'] ?? $res['audience']['_id'] ?? ''); $sent = 0;
                    try { if ($aid !== '') foreach (array_chunk(array_values($rows), 10000) as $ch) { $z->request('POST', '/v1/ads/audiences/' . rawurlencode($aid) . '/users', [], ['users' => $ch]); $sent += count($ch); } }
                    catch (ZernioException $ex) { flash('error', t('الجمهور اتعمل، لكن رفع القائمة فشل: %s', $ex->getMessage())); redirect(url('ad_audiences', ['t' => $sel])); }
                    flash('success', t('تم إنشاء الجمهور ورفع %s جهة اتصال. المطابقة بتاخد لحد 48 ساعة.', ads_num($sent)));
                } else flash('success', t('تم إنشاء الجمهور. ممكن ياخد شوية وقت لحد ما يتملي.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('ad_audiences', ['t' => $sel]));
}

$targets = [];
$audiences = [];
$needAds = false;
$pixels = []; $pixelErr = null;
$apiError = null;
$current = null;
if ($z) {
    try {
        $fxT = getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/ad_targets.json' : ''; // اختبارات بس
        $targets = $fxT && is_file($fxT) ? (json_decode((string)file_get_contents($fxT), true)['targets'] ?? []) : ads_usable_accounts($z, ads_connections($z));
        // نفس حساب الإعلانات بيظهر مرتين: من حساب النشر (linkedin) ومن إضافة الإعلانات (linkedinads).
        // البيكسل والجماهير في Zernio بيقبلوا حساب الإعلانات بس، فنفضّله ونشيل التكرار.
        $isAdsConn = function ($t) { return (bool)preg_match('/ads$/', (string)($t['conn']['platform'] ?? '')); };
        $by = [];
        foreach ($targets as $t) { $k = (string)$t['acc']['id']; if (!isset($by[$k]) || (!$isAdsConn($by[$k]) && $isAdsConn($t))) $by[$k] = $t; }
        $targets = array_values($by);
        foreach ($targets as $t) {
            if ($t['conn']['_id'] . '|' . $t['acc']['id'] === $sel) $current = $t;
        }
        if (!$current && $sel !== '') { [, $selAcc] = array_pad(explode('|', $sel), 2, ''); foreach ($targets as $t) if ((string)$t['acc']['id'] === $selAcc) $current = $t; }
        if (!$current && $targets) {
            $current = $targets[0];
            $sel = $current['conn']['_id'] . '|' . $current['acc']['id'];
        }
        $needAds = $current && !$isAdsConn($current) && !ads_is_meta((string)($current['conn']['platform'] ?? ''));
        if ($current) $sel = $current['conn']['_id'] . '|' . $current['acc']['id'];
        if ($current && !$needAds) { try { $pixels = ads_pixels($z, $current['conn']['_id'], (string)$current['acc']['id']); } catch (ZernioException $ex) { $pixelErr = $ex->getMessage(); } }
        $fx = getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/ad_audiences.json' : '';
        if ($fx && is_file($fx)) {
            $audiences = json_decode((string)file_get_contents($fx), true) ?: [];
        } elseif ($current && !$needAds) {
            $audiences = ads_audiences($z, $current['conn']['_id'], (string)$current['acc']['id']);
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('الجماهير'), 'ad_audiences', function () use ($z, $apiError, $targets, $current, $sel, $audiences, $pixels, $pixelErr, $needAds) {
    if (!$z) { no_api_box(); return; }
    $types = ['customer_list' => t('قائمة عملاء'), 'website' => t('زوار الموقع'), 'lookalike' => t('جمهور مشابه'),
        'meta_engagement' => t('اتفاعلوا مع الصفحة'), 'engagement' => t('تفاعل'), 'saved_targeting' => t('استهداف محفوظ'),
        'company_list' => t('قائمة شركات'), 'website_retargeting' => t('زوار الموقع')];
    $isMeta = $current && ads_is_meta($current['conn']['platform'] ?? '');
    $plat = (string)($current['conn']['platform'] ?? ''); $allowed = aud_types_for($plat);
    $crmN = 0; $crmW = 0; try { $st = db()->prepare("SELECT COUNT(*), SUM(stage = 'won') FROM crm_leads WHERE workspace_id = ? AND email IS NOT NULL AND email <> ''"); $st->execute([(int)ws('id')]); [$crmN, $crmW] = array_map('intval', $st->fetch(PDO::FETCH_NUM)); } catch (Throwable $e) {}
    ?>
    <div class="space-y-5">

      <?php if ($apiError) error_box($apiError); ?>
      <?php if (!$targets && !$apiError): ads_empty_state(); else: ?>
      <form method="get" class="card p-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="p" value="ad_audiences">
        <div class="min-w-[16rem]">
          <label class="label"><?= e(t('حساب الإعلانات')) ?></label>
          <select name="t" class="input" onchange="this.form.submit()">
            <?php foreach ($targets as $t): $val = $t['conn']['_id'] . '|' . $t['acc']['id']; ?>
              <option value="<?= e($val) ?>" <?= $val === $sel ? 'selected' : '' ?>><?= e(($t['acc']['name'] ?? $t['acc']['id']) . ' — ' . ads_platform_label($t['conn']['platform'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="btn-primary"><?= e(t('عرض')) ?></button></noscript>
      </form>

      <?php if ($needAds): ?>
      <section class="card p-6 text-center">
        <h2 class="font-bold text-ink-900"><?= e(t('هذا الحساب مربوط للنشر فقط')) ?></h2>
        <p class="text-sm text-ink-600 mt-2 max-w-xl mx-auto"><?= e(t('البيكسل والجماهير المخصصة تحتاج ربط إضافة الإعلانات للمنصة نفسها (مثل LinkedIn Ads)، وليس حساب النشر. اربطها مرة واحدة من هنا ثم ارجع لهذه الصفحة.')) ?></p>
        <?php ads_connect_buttons(); ?>
      </section>
      <?php else: ?>
      <!-- البيكسل: تتبع زوار موقعك (أساس جمهور «زوار الموقع» وقياس التحويلات) -->
      <section class="card p-5" id="px-card">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <h2 class="font-bold text-ink-900"><?= e(t('البيكسل')) ?> <span class="text-xs font-normal text-ink-500"><?= e(t('يتتبع زوار موقعك عشان تعمل لهم جمهور وتقيس نتايج إعلاناتك')) ?></span></h2>
          <?php if (can('ads.spend')): ?><button type="button" class="btn-ghost !text-xs border border-ink-200" data-px-new><?= ui_icon('plus', 'w-4 h-4') ?><?= e(t('إنشاء Pixel جديد')) ?></button><?php endif; ?>
        </div>
        <?php if ($pixelErr): ?><p class="text-xs text-amber-700"><?= e(t('تعذّر جلب البيكسلات: %s', $pixelErr)) ?></p><?php endif; ?>
        <?php if (!$pixels && !$pixelErr): ?><p class="text-sm text-ink-500"><?= e(t('لا يوجد Pixel على حساب الإعلانات ده. أنشئ واحدًا من هنا وركّب كوده في موقعك.')) ?></p><?php endif; ?>
        <div class="divide-y divide-ink-100">
          <?php foreach ($pixels as $px): $pid = (string)($px['id'] ?? ''); $st = (string)($px['status'] ?? ''); $lf = (string)($px['lastFiredTime'] ?? $px['lastFired'] ?? ''); ?>
            <div class="py-2.5 flex flex-wrap items-center gap-3" data-px="<?= e($pid) ?>">
              <span class="w-2 h-2 rounded-full <?= $st === 'active' ? 'bg-lime-500' : 'bg-ink-300' ?>" title="<?= e($st === 'active' ? t('نشط') : t('غير نشط')) ?>"></span>
              <div class="min-w-0 flex-1"><b class="text-sm text-ink-900"><?= e((string)($px['name'] ?? $pid)) ?></b>
                <span class="block text-[11px] text-ink-500" dir="ltr" style="text-align:start"><?= e($pid) ?></span></div>
              <span class="text-xs <?= $lf !== '' ? 'text-ink-600' : 'text-amber-700' ?>"><?= $lf !== '' ? e(t('آخر نشاط: %s', fmt_date($lf, 'd/m H:i'))) : e(t('لم يسجّل أي زيارة بعد')) ?></span>
              <button type="button" class="btn-ghost !text-xs" data-px-code="<?= e($pid) ?>"><?= e(t('كود التركيب')) ?></button>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="px-codebox" class="hidden mt-3"><textarea readonly rows="7" class="input font-mono text-[11px]" dir="ltr" id="px-code"></textarea>
          <div class="flex items-center gap-2 mt-2"><button type="button" class="btn-primary !text-xs" id="px-copy"><?= e(t('انسخ الكود')) ?></button><span class="text-[11px] text-ink-500"><?= e(t('حطّه في <head> في كل صفحات موقعك.')) ?></span></div></div>
        <?php if (can('ads.spend')): ?>
        <form method="post" id="px-newform" class="hidden mt-3 flex flex-wrap items-end gap-2" data-confirm="<?= e(t('البيكسل لا يمكن حذفه بعد إنشائه على أي منصة. تريد المتابعة؟')) ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="pixel_create"><input type="hidden" name="t" value="<?= e($sel) ?>">
          <div class="flex-1 min-w-[12rem]"><label class="label"><?= e(t('اسم البيكسل')) ?></label><input name="pixel_name" class="input" required minlength="3" maxlength="100" value="<?= e(ws('name') . ' — ' . t('الموقع')) ?>"></div>
          <button class="btn-primary"><?= e(t('إنشاء')) ?></button>
          <p class="basis-full text-[11px] text-amber-700"><?= e(t('تنبيه: البيكسل لا يُحذف بعد إنشائه (قيد من المنصات نفسها). لو مش هتستخدمه، شيل كوده من موقعك.')) ?></p>
        </form>
        <?php endif; ?>
      </section>

      <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_400px] items-start">
      <section class="card overflow-hidden">
        <div class="p-4 border-b border-ink-200 flex flex-wrap items-center gap-2">
          <h2 class="font-bold text-ink-900 me-auto"><?= e(t('الجماهير المخصصة')) ?> <span class="text-ink-500 font-normal text-sm">(<span id="aud-count"><?= count($audiences) ?></span>)</span></h2>
          <?php if ($audiences): ?>
          <input id="aud-q" class="input !py-1.5 w-52 text-sm" placeholder="<?= e(t('ابحث في الجماهير...')) ?>" aria-label="<?= e(t('ابحث في الجماهير...')) ?>">
          <button type="button" id="aud-help" class="btn-ghost text-sm" aria-label="?">?</button>
          <div id="aud-chips" class="w-full flex flex-wrap gap-1.5"></div>
          <?php endif; ?>
        </div>
        <?php if (!$audiences): ?>
          <div class="ds-empty"><div class="ds-empty-ico"><?= ui_icon('users', 'w-6 h-6') ?></div><?= e(t('مفيش جماهير على الحساب ده لسه.')) ?></div>
        <?php else: ?>
          <div class="divide-y divide-ink-100">
            <?php $maxSize = max(array_map(function ($a) { return (float)($a['size'] ?? 0); }, $audiences ?: [['size' => 0]])) ?: 1; foreach ($audiences as $a): $ty = (string)($a['type'] ?? ''); $stt = (string)($a['status'] ?? ''); ?>
              <div class="aud-row p-4 flex flex-wrap items-center gap-3" data-type="<?= e($ty) ?>" data-tl="<?= e($types[$ty] ?? $ty) ?>" data-q="<?= e(mb_strtolower(($a['name'] ?? '') . ' ' . ($types[$ty] ?? $ty))) ?>">
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-ink-900 flex flex-wrap items-center gap-2"><?= e($a['name'] ?? '') ?>
                    <?php if ($stt !== ''): $sc = ['ready' => 'st-active', 'populating' => 'st-pending', 'too_small' => 'st-failed'][$stt] ?? 'st-muted'; ?>
                      <span class="st <?= $sc ?>"><?= e(['ready' => t('جاهز'), 'populating' => t('بيتجهّز'), 'too_small' => t('صغير جدًا للإعلان')][$stt] ?? $stt) ?></span>
                    <?php endif; ?></div>
                  <?php if (isset($a['size'])): ?><div class="mt-2 h-1.5 rounded-full bg-ink-100 overflow-hidden max-w-xs" title="<?= e(t('حوالي %s شخص', ads_num($a['size']))) ?>"><div class="aud-bar h-full bg-brand-500 rounded-full" style="width:0" data-w="<?= e(max(2, round(log10(max(1, $a['size'])) / log10(max(10, $maxSize)) * 100))) ?>"></div></div><?php endif; ?>
                  <div class="text-xs text-ink-500 mt-0.5"><?= e($types[$a['type'] ?? ''] ?? ($a['type'] ?? '')) ?>
                    <?php if (isset($a['size'])): ?> · <?= e(t('حوالي %s شخص', ads_num($a['size']))) ?><?php endif; ?>
</div>
                </div>
                <?php if (can('ads.spend') && !empty($a['id'])): ?>
                  <form method="post" data-confirm="<?= e(t('حذف الجمهور ده من المنصة نهائياً؟ أي إعلان بيستخدمه ممكن يتأثر.')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="t" value="<?= e($sel) ?>"><input type="hidden" name="audience_id" value="<?= e($a['id']) ?>">
                    <button class="btn-danger-ghost !py-1.5 !px-3 !text-sm"><?= e(t('حذف')) ?></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <p id="aud-empty" class="ds-empty" hidden><?= e(t('مفيش جماهير مطابقة')) ?></p>
          </div>
        <?php endif; ?>
      </section>

      <?php if (can('ads.spend')): ?>
      <section class="card p-5 lg:sticky lg:top-4" id="aud-new">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('جمهور جديد')) ?></h2>
        <form method="post" class="space-y-3" id="audForm">
          <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="t" value="<?= e($sel) ?>">
          <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label"><?= e(t('الاسم')) ?></label><input name="name" class="input" required minlength="3" maxlength="200"></div>
            <div><label class="label"><?= e(t('النوع')) ?></label>
              <select name="type" class="input" id="audType">
                <?php $lbl = ['meta_engagement' => t('ناس اتفاعلوا مع صفحتك / انستجرام'), 'engagement' => t('ناس اتفاعلوا مع صفحتك وإعلاناتك على لينكدإن'), 'tiktok_engagement' => t('ناس اتفاعلوا مع حسابك على تيك توك'), 'pinterest_engagement' => t('ناس اتفاعلوا مع حسابك على بنترست'),
                  'customer_list' => t('قائمة عملائك (إيميلات من الـ CRM أو ملف)'), 'website' => $plat === 'googleads' ? t('زوار موقعك (Google tag)') : t('زوار موقعك (محتاج Pixel)'), 'lookalike' => t('جمهور مشابه لجمهور عندك')];
                foreach ($allowed as $ty0): if ($ty0 === 'lookalike' && !$audiences) continue; ?><option value="<?= e($ty0) ?>"><?= e($lbl[$ty0]) ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <div data-aud="meta_engagement" class="grid sm:grid-cols-2 gap-3">
            <div><label class="label"><?= e(t('المصدر')) ?></label>
              <select name="source" class="input"><option value="page"><?= e(t('صفحة فيسبوك')) ?></option><option value="instagram"><?= e(t('حساب انستجرام')) ?></option></select></div>
            <div><label class="label"><?= e(t('رقم الصفحة / الحساب')) ?></label><input name="source_id" class="input" dir="ltr" value="<?= e($current['conn']['metadata']['selectedPageId'] ?? '') ?>"></div>
          </div>
          <div data-aud="customer_list" class="space-y-2">
            <label class="flex items-center gap-2 text-sm rounded-xl border border-ink-200 p-3 cursor-pointer"><input type="checkbox" name="from_crm" value="1" <?= $crmN && can('crm.manage') ? '' : 'disabled' ?>>
              <span class="flex-1"><?= e(t('ضيف عملاء الـ CRM اللي ليهم إيميل')) ?> <b class="text-brand-700">(<?= ads_num($crmN) ?>)</b></span><?php if ($crmN && !can('crm.manage')): ?><span class="basis-full text-[11px] text-amber-700"><?= e(t('إضافة عملاء الـ CRM تحتاج صلاحية إدارة العملاء.')) ?></span><?php endif; ?>
              <select name="crm_stage" class="input !w-auto !py-1 text-xs"><option value=""><?= e(t('كلهم')) ?></option><option value="won"><?= e(t('اللي اشتروا بس (%s)', ads_num($crmW))) ?></option></select></label>
            <div><label class="label"><?= e(t('أو الصق إيميلات (واحد في كل سطر)')) ?><?= $isMeta ? ' ' . e(t('أو أرقام موبايل بكود الدولة')) : '' ?></label>
              <textarea name="list" rows="4" class="input font-mono text-xs" dir="ltr" placeholder="name@company.com"></textarea>
              <p class="text-[11px] text-ink-500 mt-1"><span id="aud-lc">0</span> <?= e(t('صالح')) ?> · <?= e(t('بيتشفّر (SHA256) قبل ما يوصل للمنصة. المنصة محتاجة 300 مطابقة على الأقل عشان تقدر تعلن عليه.')) ?></p></div>
          </div>
          <div data-aud="engagement tiktok_engagement pinterest_engagement" class="rounded-xl bg-brand-50 p-3 text-xs text-brand-800"><?= e(t('بيتملي لوحده من الناس اللي اتفاعلوا مع حسابك وإعلاناتك على المنصة. مفيش حاجة تجهزها.')) ?></div>
          <div data-aud="website" class="grid sm:grid-cols-2 gap-3">
            <?php if ($plat !== 'googleads'): ?><div><label class="label"><?= e(t('البيكسل')) ?></label>
              <?php if ($pixels): ?><select name="pixel_id" class="input"><?php foreach ($pixels as $px): ?><option value="<?= e((string)($px['id'] ?? '')) ?>"><?= e((string)($px['name'] ?? '') . ' · ' . ($px['id'] ?? '')) ?></option><?php endforeach; ?></select>
              <?php else: ?><p class="text-xs text-amber-700 rounded-lg bg-amber-50 p-2"><?= e(t('لا يوجد Pixel على الحساب ده.')) ?> <button type="button" class="link" data-px-new><?= e(t('أنشئ واحدًا')) ?></button></p><?php endif; ?></div><?php endif; ?>
            <?php if ($plat === 'tiktokads'): ?><div><label class="label"><?= e(t('الحدث')) ?></label><select name="event" class="input"><?php foreach (['' => t('تصفح الصفحات'), 'PRODUCT DETAIL PAGE BROWSE' => t('شاهد منتج'), 'PIXEL ADD TO CART' => t('أضاف للسلة'), 'INITIATE CHECKOUT' => t('بدأ الدفع'), 'COMPLETE PAYMENT' => t('دفع'), 'PIXEL SUBMIT FORM' => t('أرسل نموذج'), 'CONTACT' => t('تواصل')] as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <?php if ($plat !== 'pinterestads'): ?><div><label class="label"><?= e(t('الرابط بيحتوي على (اختياري)')) ?></label><input name="url_contains" class="input" dir="ltr" placeholder="/products"></div><?php endif; ?>
          </div>
          <div data-aud="meta_engagement website">
            <label class="label"><?= e(t('آخر كام يوم')) ?></label><input name="days" type="number" min="1" max="180" value="30" class="input !w-32">
          </div>
          <div data-aud="lookalike" class="grid sm:grid-cols-3 gap-3">
            <div><label class="label"><?= e(t('الجمهور الأصلي')) ?></label>
              <select name="source_audience" class="input"><?php foreach ($audiences as $a): if (empty($a['id'] ?? $a['platformAudienceId'] ?? null)) continue; ?><option value="<?= e($a['id'] ?? $a['platformAudienceId']) ?>"><?= e($a['name'] ?? '') ?></option><?php endforeach; ?></select></div>
            <div><label class="label"><?= e(t('الدولة (كود من حرفين)')) ?></label><input name="country" class="input" dir="ltr" maxlength="2" value="<?= e(ws_country()) ?>"></div>
            <?php if (in_array($plat, ['tiktokads', 'googleads'], true)): ?><div><label class="label"><?= e(t('الحجم')) ?></label><select name="size" class="input"><option value="narrow"><?= e(t('ضيق (أدق)')) ?></option><option value="balanced"><?= e(t('متوازن')) ?></option><option value="broad"><?= e(t('واسع')) ?></option></select></div>
            <?php else: ?><div><label class="label"><?= e(t('الحجم %')) ?></label><input name="ratio" type="number" min="1" max="<?= $plat === 'pinterestads' ? 10 : 20 ?>" value="1" class="input"></div><?php endif; ?>
          </div>
          <p class="text-xs text-ink-500"><?= e(t('الجمهور بيتعمل على المنصة نفسها، ومش بيصرف فلوس لوحده.')) ?></p>
          <button class="btn-primary"><?= e(t('إنشاء الجمهور')) ?></button>
        </form>
        <script>
        (function () {
          var s = document.getElementById('audType');
          function sync() { document.querySelectorAll('#audForm [data-aud]').forEach(function (el) { el.style.display = el.dataset.aud.split(' ').indexOf(s.value) >= 0 ? '' : 'none'; }); }
          s.addEventListener('change', sync); sync();
          var ta = document.querySelector('#audForm textarea[name=list]'), lc = document.getElementById('aud-lc');
          if (ta) ta.addEventListener('input', function () { lc.textContent = ta.value.split(/[\s,;]+/).filter(function (x) { return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(x) || /^\+?\d{8,15}$/.test(x); }).length; });
        })();
        </script>
      </section>
      <?php else: ?><section class="card p-5 text-sm text-ink-600" id="aud-new"><?= e(t('إنشاء الجماهير يحتاج صلاحية الإعلانات. اطلبها من صاحب الشركة.')) ?></section><?php endif; ?>
      </div>
      <script>window.AUDX = <?= json_encode(['all' => t('الكل'), 'keys' => t('اختصارات لوحة المفاتيح'), 'kSearch' => t('البحث في الجماهير'), 'kNew' => t('جمهور جديد'),
        'hints' => ['meta_engagement' => t('الأنسب للبداية: ناس عرفوك فعلًا، وغالبًا بيتحولوا أسرع.'), 'website' => t('محتاج Pixel متركّب على موقعك، وإلا الجمهور هيفضل فاضي.'), 'lookalike' => t('ابدأ بـ 1% لأدق تشابه، وكبّر الحجم لو عاوز وصول أكبر.')]], JSON_UNESCAPED_UNICODE) ?>;</script>
      <script src="assets/ui-ad_audiences.js?v=<?= e(APP_VERSION) ?>"></script>
      <script>window.AUDU = <?= json_encode(['list' => array_map(function ($a) { return ['id' => (string)($a['id'] ?? $a['platformAudienceId'] ?? ''), 'name' => (string)($a['name'] ?? ''), 'type' => (string)($a['type'] ?? ''), 'size' => (float)($a['size'] ?? 0), 'status' => (string)($a['status'] ?? '')]; }, $audiences),
        'canLook' => (bool)$audiences, 'isMeta' => (bool)$isMeta, 'warmType' => array_values(array_intersect(['meta_engagement', 'engagement', 'tiktok_engagement', 'pinterest_engagement', 'customer_list', 'website'], $allowed))[0] ?? 'customer_list', 'builder' => url('ad_builder'), 'people' => t('حوالي %s شخص'),
        'fSmall' => t('صغير جدًا'), 'fNiche' => t('متخصص'), 'fGood' => t('حجم ممتاز'), 'fBroad' => t('واسع'), 'makeLook' => t('مشابه منه'), 'copyId' => t('انسخ الـ ID'), 'copied' => t('اتنسخ ✓'), 'del' => t('حذف'), 'lookName' => t('مشابه 1% - %s'),
        'sortDef' => t('الترتيب الأصلي'), 'sortSize' => t('الأكبر الأول'), 'sortName' => t('بالاسم'),
        'ladT' => t('سلّم الجماهير'), 'ladD' => t('ابدأ بالناس اللي عرفوك، اعمل منهم مشابه، وبعدين وسّع.'), 'tWarm' => t('دافي'), 'tWarmD' => t('تفاعلوا أو زاروا أو عملاء'), 'tLook' => t('مشابه'), 'tLookD' => t('ناس شبه الدافيين'), 'tBroad' => t('واسع'), 'tBroadD' => t('استهداف محفوظ واهتمامات'),
        'nHave' => t('%s جمهور'), 'none' => t('لسه مفيش'), 'nWarm' => t('أول خطوة: اعمل جمهور من الناس اللي اتفاعلوا معاك آخر 90 يوم.'), 'nLook' => t('الخطوة الجاية: اعمل جمهور مشابه 1% من «%s» عشان توصل لناس جديدة شبههم.'), 'nScale' => t('عندك دافي ومشابه. الواسع بيتعمل بالاهتمامات جوه منشئ الحملات: اعمل مجموعة لكل درجة عشان تقارن.'),
        'doIt' => t('جهّزهولي'), 'toBuilder' => t('افتح منشئ الحملات'), 'warmName' => t('متفاعلين آخر 90 يوم'), 'dN' => t('%s يوم'), 'precise' => t('أدق'), 'wide' => t('أوسع'),
        'rTip1' => t('1–2%: أقرب ناس لجمهورك، الأفضل للمبيعات.'), 'rTip2' => t('3–5%: توازن بين الدقة والحجم.'), 'rTip3' => t('6–10%: وصول أكبر بدقة أقل، مناسب للانتشار.')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
      <script src="assets/ad-aud-ui.js?v=<?= e(APP_VERSION) ?>"></script>
      <?php endif; ?>
      <?php endif; ?>
    </div>
<script>
// على مستوى الصفحة كلها: بيفضل شغال بعد ما live.js يبدّل المحتوى (من غير إعادة تحميل)
(function () {
  if (window.__pxBound) return; window.__pxBound = true;
  document.addEventListener('click', function (e) {
    var nb = e.target.closest('[data-px-new]'); if (nb && !nb.closest('#px-card')) document.getElementById('px-card').scrollIntoView({ behavior: 'smooth' });
    if (nb) { var nf = document.getElementById('px-newform'); if (nf) { nf.classList.toggle('hidden'); var i = nf.querySelector('input[name=pixel_name]'); if (i) i.focus(); } return; }
    var cp = e.target.closest('#px-copy');
    if (cp) { var t0 = document.getElementById('px-code'); t0.select(); try { navigator.clipboard.writeText(t0.value); } catch (x) { document.execCommand('copy'); } if (!cp.dataset.orig) cp.dataset.orig = cp.textContent; cp.textContent = '✓'; clearTimeout(cp._t); cp._t = setTimeout(function () { cp.textContent = cp.dataset.orig; }, 1500); return; }
    var b = e.target.closest('#px-card [data-px-code]'); if (!b) return;
    var fd = new FormData(); fd.append('action', 'pixel_code'); fd.append('tag', b.dataset.pxCode);
    var tsel = document.querySelector('select[name=t]'); fd.append('t', tsel ? tsel.value : '');
    var tk = document.querySelector('input[name=_csrf]'); if (tk) fd.append('_csrf', tk.value);
    b.disabled = true;
    fetch(location.pathname + '?p=ad_audiences', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      b.disabled = false; if (!d.ok) { alert(d.error || <?= json_encode(t('تعذّر جلب كود التركيب. حاول مرة أخرى.')) ?>); return; }
      var ta = document.getElementById('px-code'), box = document.getElementById('px-codebox'); ta.value = d.code || ''; box.classList.remove('hidden'); ta.focus(); ta.select();
    }).catch(function () { b.disabled = false; alert(<?= json_encode(t('تعذّر جلب كود التركيب. حاول مرة أخرى.'), JSON_UNESCAPED_UNICODE) ?>); });
  });
})();
</script>
    <?php
}, ['full' => true]);
