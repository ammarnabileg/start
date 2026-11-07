<?php
/** تفاصيل حملة: المجموعات والإعلانات، رسم يومي، تعديل الميزانية، إيقاف/تشغيل */
$err = null;
$z = zernio_or_null($err);
$cid = (string)($_GET['id'] ?? '');
$pf = (string)($_GET['pf'] ?? '');
if ($pf !== '' && !in_array($pf, ads_campaign_platforms(), true)) $pf = '';
$range = (string)($_GET['r'] ?? '30');
if (!isset(ads_date_presets()[$range])) $range = '30';
$back = url('ad_campaign', array_filter(['id' => $cid, 'pf' => $pf, 'r' => $range]));

if ($cid === '') {
    redirect(url('ads'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$z && getenv('UI_FIXTURES')) { header('Content-Type: application/json'); echo '{"ok":true,"msg":"fixture"}'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $ajax = !empty($_POST['ajax']); $res = ['error', t('طلب غير صحيح.')];
    $say = function (string $k, string $m) use (&$res, $ajax) { $res = [$k, $m]; if (!$ajax) flash($k, $m); };
    if (($_POST['return'] ?? '') === 'ads') $back = url('ads', ['r' => $range]);
    $action = (string)($_POST['action'] ?? '');
    $platform = (string)($_POST['platform'] ?? $pf);
    $to = ($_POST['to'] ?? '') === 'active' ? 'active' : 'paused';
    $needsOwner = ($action === 'budget') || (in_array($action, ['campaign_status', 'adset_status', 'ad_status'], true) && $to === 'active');
    try {
        if (!in_array($platform, ads_campaign_platforms(), true)) {
            $say('error', t('بيانات الحملة ناقصة.'));
        } elseif ($needsOwner && !can('ads.spend')) {
            $say('error', t('التشغيل وتعديل الميزانية لصاحب الشركة بس.'));
        } elseif ($needsOwner && empty($_POST['confirm_spend'])) {
            $say('error', t('لازم تعلّم على خانة التأكيد الأول.'));
        } elseif ($action === 'campaign_status') {
            $r = ads_set_campaign_status($z, $cid, $platform, $to);
            log_activity('ads.campaign_' . $to, $cid);
            $say('success', !empty($r['skipped']) ? t('الحملة كانت أصلاً في الحالة دي.') : ($to === 'active' ? t('تم تشغيل الحملة.') : t('تم إيقاف الحملة.')));
        } elseif ($action === 'adset_status') {
            $sid = (string)($_POST['adset_id'] ?? '');
            ads_set_adset_status($z, $sid, $platform, $to);
            log_activity('ads.adset_' . $to, $sid);
            $say('success', $to === 'active' ? t('تم تشغيل المجموعة.') : t('تم إيقاف المجموعة.'));
        } elseif ($action === 'ad_status') {
            $aid = (string)($_POST['ad_id'] ?? '');
            $r = ads_set_ad_status($z, $aid, $to);
            log_activity('ads.ad_' . $to, $aid);
            $say('success', !empty($r['skipped']) ? ($r['message'] ?? t('الإعلان كان أصلاً في الحالة دي.')) : ($to === 'active' ? t('تم تشغيل الإعلان.') : t('تم إيقاف الإعلان.')));
        } elseif ($action === 'budget') {
            $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? ''));
            $type = ($_POST['type'] ?? '') === 'lifetime' ? 'lifetime' : 'daily';
            $sid = (string)($_POST['adset_id'] ?? '');
            if ($amount <= 0) {
                $say('error', t('اكتب ميزانية أكبر من صفر.'));
            } else {
                if ($sid !== '') {
                    ads_update_adset_budget($z, $sid, $platform, $amount, $type);
                } else {
                    ads_update_campaign_budget($z, $cid, $platform, $amount, $type);
                }
                log_activity('ads.budget', ($sid ?: $cid) . ' ' . $type . ' ' . $amount);
                $say('success', t('تم تعديل الميزانية.'));
            }
        }
    } catch (ZernioException $ex) {
        $say('error', $ex->getMessage());
    }
    if ($ajax) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => $res[0] === 'success', 'msg' => $res[1]], JSON_UNESCAPED_UNICODE); exit; }
    redirect($back);
}

$camp = null;
$apiError = null;
[$from, $to] = ads_date_range($range);
$fx = getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/ad_campaign.json' : '';
if ($fx && is_file($fx)) {
    $camp = json_decode((string)file_get_contents($fx), true);
} elseif ($z) {
    try {
        $camp = ads_campaign_tree($z, $cid, $from, $to, $pf ?: null);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('تفاصيل الحملة'), 'ads', function () use ($z, $apiError, $camp, $cid, $pf, $range, $from, $to) {
    if (!$z && !getenv('UI_FIXTURES')) { no_api_box(); return; }
    $back = url('ads', ['r' => $range]); ?>
    <div class="acp space-y-5">
      <a href="<?= e($back) ?>" class="acp-back"><?= ui_icon('chevron-right', 'w-4 h-4') ?><?= e(t('رجوع للحملات')) ?></a>
      <?php if ($apiError) error_box($apiError); ?>
      <?php if (!$camp && !$apiError): ?><div class="card p-10 text-center text-ink-500 text-sm"><?= e(t('الحملة دي مش موجودة أو مالهاش بيانات في الفترة دي.')) ?></div><?php endif; ?>
      <?php if ($camp):
        $platform = (string)($camp['platform'] ?? $pf); $cur = (string)($camp['currency'] ?? ''); $cBudget = $camp['campaignBudget'] ?? null;
        $isCbo = ($camp['budgetLevel'] ?? '') === 'campaign' || $cBudget; $pi = platform_icons()[$platform] ?? null;
        $sets = array_map(function ($set) {
            return ['id' => (string)($set['platformAdSetId'] ?? ''), 'name' => (string)($set['adSetName'] ?? ''), 'status' => (string)($set['status'] ?? ''), 'budget' => $set['adSetBudget'] ?? $set['budget'] ?? null, 'm' => $set['metrics'] ?? [],
                'ads' => array_map(function ($ad) { $cr = $ad['creative'] ?? [];
                    return ['id' => (string)($ad['_id'] ?? ''), 'name' => (string)($ad['name'] ?? ''), 'status' => (string)($ad['status'] ?? ''), 'm' => $ad['metrics'] ?? [],
                        'img' => (string)($cr['thumbnailUrl'] ?? $cr['imageUrl'] ?? ($cr['mediaUrls'][0] ?? '')), 'head' => (string)($cr['headline'] ?? ''), 'body' => (string)($cr['body'] ?? ''), 'link' => (string)($cr['linkUrl'] ?? ''),
                        'post' => (string)($cr['instagramPermalinkUrl'] ?? ''), 'cta' => (string)($cr['callToActionType'] ?? ''), 'video' => !empty($cr['videoId']) || !empty($cr['videoUrl']),
                        'reject' => empty($ad['rejectionReason']) ? '' : (is_string($ad['rejectionReason']) ? $ad['rejectionReason'] : json_encode($ad['rejectionReason'], JSON_UNESCAPED_UNICODE))]; }, $set['ads'] ?? [])];
        }, $camp['adSets'] ?? []); ?>
      <section class="acp-hero">
        <div class="acp-hero-bg" aria-hidden="true"></div>
        <div class="relative flex flex-wrap items-start gap-4">
          <span class="acp-pf"<?= $pi ? ' style="background:' . e($pi[0]) . '"' : '' ?>><?php if ($pi): ?><svg viewBox="0 0 24 24" class="w-7 h-7" fill="#fff"><path d="<?= e($pi[1]) ?>"/></svg><?php else: ?><?= ui_icon('megaphone', 'w-7 h-7') ?><?php endif; ?></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-black text-[#fff] leading-tight" dir="auto"><?= e($camp['campaignName'] ?? $cid) ?></h2>
            <div class="flex flex-wrap items-center gap-1.5 mt-2">
              <span class="acp-chip"><?= e(ads_platform_label($platform)) ?></span>
              <?php if (!empty($camp['platformAdAccountName'] ?? $camp['platformAdAccountId'] ?? '')): ?><span class="acp-chip"><?= ui_icon('building', 'w-3.5 h-3.5') ?><?= e($camp['platformAdAccountName'] ?? $camp['platformAdAccountId']) ?></span><?php endif; ?>
              <?php if (!empty($camp['platformObjective'])): ?><span class="acp-chip"><?= ui_icon('target', 'w-3.5 h-3.5') ?><span data-obj="<?= e($camp['platformObjective']) ?>"><?= e($camp['platformObjective']) ?></span></span><?php endif; ?>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="acp-budget" data-budget="" title="<?= e(t('تعديل الميزانية')) ?>"></button>
            <button type="button" class="acp-sw big" data-sw="campaign_status" data-id="" role="switch"></button>
          </div>
        </div>
        <div class="relative mt-4" id="acp-pace"></div>
        <?php foreach ($camp['campaignIssuesInfo'] ?? [] as $iss): ?><div class="relative mt-3 rounded-xl bg-[#7f1d1d]/60 p-3 text-sm text-[#fff]"><?= e($iss['error_summary'] ?? $iss['error_message'] ?? json_encode($iss)) ?></div><?php endforeach; ?>
      </section>

      <div class="flex flex-wrap items-center gap-2">
        <div class="acp-seg"><?php foreach (ads_date_presets() as $k => $v): ?><a href="<?= e(url('ad_campaign', ['id' => $cid, 'pf' => $pf, 'r' => $k])) ?>" class="<?= (string)$k === $range ? 'on' : '' ?>"><?= e($v) ?></a><?php endforeach; ?></div>
        <span class="text-xs text-ink-500 tabular-nums" dir="ltr"><?= e($from . ' → ' . $to) ?></span>
      </div>

      <div id="acp-kpi" class="grid grid-cols-2 lg:grid-cols-4 gap-3"></div>

      <div class="grid lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] gap-4">
        <section class="card p-5">
          <div class="flex flex-wrap items-center gap-2 mb-3"><b class="text-ink-900"><?= e(t('الأداء يوم بيوم')) ?></b><div id="acp-mt" class="acp-seg ms-auto !p-0.5"></div></div>
          <div id="acp-chart" class="h-64"></div>
        </section>
        <section class="card p-5"><b class="block text-ink-900 mb-3"><?= e(t('القمع')) ?></b><div id="acp-funnel" class="space-y-2"></div></section>
      </div>

      <section id="acp-ins" class="acp-ins hidden"></section>

      <section class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="font-black text-lg text-ink-900 me-auto"><?= e(t('المجموعات والإعلانات')) ?></h3>
          <div id="acp-fst" class="acp-seg !p-0.5"></div>
          <select id="acp-sort" class="input !mt-0 !py-1.5 !h-9 text-sm !w-auto"><option value="spend"><?= e(t('الأعلى صرفًا')) ?></option><option value="ctr"><?= e(t('أعلى نسبة ضغط')) ?></option><option value="clicks"><?= e(t('الأكتر ضغطات')) ?></option><option value="cpc"><?= e(t('الأرخص ضغطة')) ?></option></select>
          <label class="mk-search !w-56"><?= ui_icon('search') ?><input type="search" id="acp-q" placeholder="<?= e(t('ابحث في الإعلانات...')) ?>"><kbd>/</kbd></label>
        </div>
        <div id="acp-sets" class="space-y-3"></div>
      </section>
    </div>

    <dialog id="acp-pop" class="acp-pop"></dialog>
    <dialog id="acp-prev" class="mk-dlg !w-[min(820px,96vw)] !p-0"></dialog>
    <script>window.ACP = <?= json_encode(['csrf' => csrf_token(), 'url' => url('ad_campaign', ['id' => $cid, 'pf' => $pf, 'r' => $range]), 'back' => $back, 'platform' => $platform, 'cur' => $cur, 'cbo' => (bool)$isCbo,
        'budget' => $cBudget, 'status' => (string)($camp['status'] ?? ''), 'm' => $camp['metrics'] ?? [], 'daily' => $camp['daily'] ?? [], 'sets' => $sets, 'spend' => can('ads.spend'), 'acc' => (string)($camp['platformAdAccountName'] ?? ''),
        'days' => max(1, (int)round((strtotime($to) - strtotime($from)) / 86400) + 1),
        'T' => ['active' => t('شغالة'), 'paused' => t('متوقفة'), 'pending_review' => t('قيد المراجعة'), 'rejected' => t('مرفوضة'), 'ended' => t('انتهت'), 'all' => t('الكل'),
          'spend' => t('الإنفاق'), 'imp' => t('مرات الظهور'), 'reach' => t('الوصول'), 'clicks' => t('النقرات'), 'ctr' => t('نسبة النقر'), 'cpc' => t('تكلفة النقرة'), 'res' => t('النتائج'), 'cpm' => t('تكلفة الألف ظهور'), 'cpr' => t('تكلفة النتيجة'), 'freq' => t('التكرار'),
          'daily' => t('يومي'), 'lifetime' => t('إجمالي'), 'perDay' => t('/يوم'), 'byAdsets' => t('على مستوى المجموعات'), 'noBudget' => t('من غير ميزانية'),
          'pace' => t('متوسط الصرف اليومي %s من ميزانية %s'), 'paceLife' => t('اتصرف %s من %s'), 'pauseQ' => t('توقف «%s»؟'), 'pauseHint' => t('الإعلانات هتقف عن الظهور فورًا، وتقدر ترجعها في أي وقت.'), 'pause' => t('إيقاف'), 'resume' => t('تشغيل'),
          'resumeQ' => t('تشغيل «%s»'), 'spendOk' => t('موافق إن ده هيبدأ يصرف فلوس فعلًا. الميزانية: %s'), 'ownerOnly' => t('التشغيل وتعديل الميزانية لصاحب الشركة بس.'), 'cancel' => t('إلغاء'), 'save' => t('حفظ الميزانية'),
          'budgetT' => t('تعديل الميزانية'), 'budgetOk' => t('راجعت المبلغ الجديد وموافق إنه ممكن يزوّد الصرف.'), 'delta' => t('%s عن الحالية'), 'perMonth' => t('≈ %s في الشهر'), 'now' => t('الحالية: %s'),
          'ads' => t('%s إعلان'), 'share' => t('%s من صرف الحملة'), 'best' => t('الأفضل'), 'worst' => t('الأضعف'), 'noAds' => t('مفيش إعلانات مطابقة'), 'noSets' => t('مفيش مجموعات.'), 'preview' => t('معاينة'),
          'sponsored' => t('ممول'), 'learn' => t('اعرف أكتر'), 'open' => t('افتح الرابط'), 'post' => t('المنشور'), 'reject' => t('سبب الرفض'), 'video' => t('فيديو'),
          'insT' => t('ملاحظات على الحملة'), 'iBest' => t('«%s» أعلى نسبة نقر (%s). ادعمه بميزانية أكبر.'), 'iWorst' => t('«%s» صرف %s ونسبة النقر %s بس. وقفه ووفّر الفلوس.'), 'iRej' => t('%s إعلان مرفوض محتاج تعديل.'),
          'iConc' => t('«%s» واخدة %s من الصرف. وزّع على مجموعات تانية عشان تقارن.'), 'iLowCtr' => t('نسبة النقر %s أقل من 1%%. جرّب صورة أو عنوان أقوى.'), 'iPace' => t('الصرف ماشي أقل من الميزانية (%s). ممكن الجمهور ضيق.'),
          'doPause' => t('وقّفه'), 'doView' => t('شوفه'), 'doBudget' => t('زوّد الميزانية'), 'saved' => t('اتحفظ ✓'), 'err' => t('حصلت مشكلة.'), 'loading' => t('جاري التنفيذ…'),
          'keys' => t('اختصارات لوحة المفاتيح'), 'kSearch' => t('البحث في الإعلانات'), 'kBack' => t('رجوع للحملات'), 'kMetric' => t('غيّر مقياس الرسم')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <?php endif; ?>
    <script src="assets/vendor/echarts.min.js?v=5.5.1"></script>
    <script src="assets/ui-ad_campaign.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
