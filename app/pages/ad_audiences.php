<?php
/** الجماهير المخصصة: عرض، إنشاء (أنواع بسيطة)، حذف */
$err = null;
$z = zernio_or_null($err);
$sel = (string)($_GET['t'] ?? $_POST['t'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    [$connId, $adAccId] = array_pad(explode('|', $sel), 2, '');
    try {
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
        } elseif ($action === 'create') {
            $type = (string)($_POST['type'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            $days = max(1, min(180, (int)($_POST['days'] ?? 30)));
            $p = ['accountId' => $connId, 'adAccountId' => $adAccId, 'name' => $name, 'type' => $type];
            $bad = null;
            if (mb_strlen($name) < 3) {
                $bad = t('اكتب اسم للجمهور (3 حروف على الأقل).');
            } elseif ($type === 'website') {
                $p['pixelId'] = trim((string)($_POST['pixel_id'] ?? ''));
                $p['retentionDays'] = $days;
                $u = trim((string)($_POST['url_contains'] ?? ''));
                if ($u !== '') $p['urlContains'] = $u;
                if ($p['pixelId'] === '') $bad = t('اكتب رقم الـ Pixel.');
            } elseif ($type === 'meta_engagement') {
                $p['engagementSource'] = ($_POST['source'] ?? '') === 'instagram' ? 'instagram' : 'page';
                $p['sourceId'] = trim((string)($_POST['source_id'] ?? ''));
                $p['retentionDays'] = $days;
                if ($p['sourceId'] === '') $bad = t('اكتب رقم الصفحة أو حساب انستجرام.');
            } elseif ($type === 'lookalike') {
                $p['sourceAudienceId'] = trim((string)($_POST['source_audience'] ?? ''));
                $p['country'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_POST['country'] ?? '')), 0, 2));
                $p['ratio'] = max(0.01, min(0.2, ((int)($_POST['ratio'] ?? 1)) / 100));
                if ($p['sourceAudienceId'] === '' || strlen($p['country']) !== 2) $bad = t('اختار الجمهور الأصلي واكتب كود الدولة.');
            } else {
                $bad = t('نوع جمهور مش مدعوم.');
            }
            if ($bad) {
                flash('error', $bad);
            } else {
                ads_create_audience($z, $p);
                log_activity('ads.audience_create', $type . ': ' . $name);
                flash('success', t('تم إنشاء الجمهور. ممكن ياخد شوية وقت لحد ما يتملي.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('ad_audiences', ['t' => $sel]));
}

$targets = [];
$audiences = [];
$apiError = null;
$current = null;
if ($z) {
    try {
        $fxT = getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/ad_targets.json' : ''; // اختبارات بس
        $targets = $fxT && is_file($fxT) ? (json_decode((string)file_get_contents($fxT), true)['targets'] ?? []) : ads_usable_accounts($z, ads_connections($z));
        foreach ($targets as $t) {
            if ($t['conn']['_id'] . '|' . $t['acc']['id'] === $sel) $current = $t;
        }
        if (!$current && $targets) {
            $current = $targets[0];
            $sel = $current['conn']['_id'] . '|' . $current['acc']['id'];
        }
        $fx = getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/ad_audiences.json' : '';
        if ($fx && is_file($fx)) {
            $audiences = json_decode((string)file_get_contents($fx), true) ?: [];
        } elseif ($current) {
            $audiences = ads_audiences($z, $current['conn']['_id'], (string)$current['acc']['id']);
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('الجماهير'), 'ads', function () use ($z, $apiError, $targets, $current, $sel, $audiences) {
    if (!$z) { no_api_box(); return; }
    $types = ['customer_list' => t('قائمة عملاء'), 'website' => t('زوار الموقع'), 'lookalike' => t('جمهور مشابه'),
        'meta_engagement' => t('اتفاعلوا مع الصفحة'), 'engagement' => t('تفاعل'), 'saved_targeting' => t('استهداف محفوظ'),
        'company_list' => t('قائمة شركات'), 'website_retargeting' => t('زوار الموقع')];
    $isMeta = $current && ads_is_meta($current['conn']['platform'] ?? '');
    ?>
    <div class="space-y-5">
      <a href="<?= e(url('ads')) ?>" class="link text-sm"><?= e(t('→ رجوع للحملات')) ?></a>
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
          <p class="p-6 text-sm text-ink-500 text-center"><?= e(t('مفيش جماهير على الحساب ده لسه.')) ?></p>
        <?php else: ?>
          <div class="divide-y divide-ink-100">
            <?php $maxSize = max(array_map(function ($a) { return (float)($a['size'] ?? 0); }, $audiences ?: [['size' => 0]])) ?: 1; foreach ($audiences as $a): $ty = (string)($a['type'] ?? ''); $stt = (string)($a['status'] ?? ''); ?>
              <div class="aud-row p-4 flex flex-wrap items-center gap-3" data-type="<?= e($ty) ?>" data-tl="<?= e($types[$ty] ?? $ty) ?>" data-q="<?= e(mb_strtolower(($a['name'] ?? '') . ' ' . ($types[$ty] ?? $ty))) ?>">
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-ink-900 flex flex-wrap items-center gap-2"><?= e($a['name'] ?? '') ?>
                    <?php if ($stt !== ''): $sc = ['ready' => 'bg-lime-100 text-lime-800', 'populating' => 'bg-amber-50 text-amber-800', 'too_small' => 'bg-red-50 text-red-700'][$stt] ?? 'bg-ink-100 text-ink-700'; ?>
                      <span class="text-[11px] rounded px-1.5 py-0.5 <?= $sc ?>"><?= e(['ready' => t('جاهز'), 'populating' => t('بيتجهّز'), 'too_small' => t('صغير جدًا للإعلان')][$stt] ?? $stt) ?></span>
                    <?php endif; ?></div>
                  <?php if (isset($a['size'])): ?><div class="mt-2 h-1.5 rounded-full bg-ink-100 overflow-hidden max-w-xs" title="<?= e(t('حوالي %s شخص', ads_num($a['size']))) ?>"><div class="aud-bar h-full bg-brand-500 rounded-full" style="width:0" data-w="<?= e(max(2, round(log10(max(1, $a['size'])) / log10(max(10, $maxSize)) * 100))) ?>"></div></div><?php endif; ?>
                  <div class="text-xs text-ink-500 mt-0.5"><?= e($types[$a['type'] ?? ''] ?? ($a['type'] ?? '')) ?>
                    <?php if (isset($a['size'])): ?> · <?= e(t('حوالي %s شخص', ads_num($a['size']))) ?><?php endif; ?>
</div>
                </div>
                <?php if (can('ads.spend') && !empty($a['id'])): ?>
                  <form method="post" onsubmit="return confirm('<?= tjs('حذف الجمهور ده من المنصة نهائياً؟ أي إعلان بيستخدمه ممكن يتأثر.') ?>')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="t" value="<?= e($sel) ?>"><input type="hidden" name="audience_id" value="<?= e($a['id']) ?>">
                    <button class="text-sm text-red-600 hover:underline"><?= e(t('حذف')) ?></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <p id="aud-empty" class="p-6 text-sm text-ink-500 text-center" hidden><?= e(t('مفيش جماهير مطابقة')) ?></p>
          </div>
        <?php endif; ?>
      </section>

      <section class="card p-5 lg:sticky lg:top-4" id="aud-new">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('جمهور جديد')) ?></h2>
        <form method="post" class="space-y-3" id="audForm">
          <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="t" value="<?= e($sel) ?>">
          <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label"><?= e(t('الاسم')) ?></label><input name="name" class="input" required minlength="3" maxlength="200"></div>
            <div><label class="label"><?= e(t('النوع')) ?></label>
              <select name="type" class="input" id="audType">
                <?php if ($isMeta): ?><option value="meta_engagement"><?= e(t('ناس اتفاعلوا مع صفحتك / انستجرام')) ?></option><?php endif; ?>
                <option value="website"><?= e(t('زوار موقعك (محتاج Pixel)')) ?></option>
                <?php if ($audiences): ?><option value="lookalike"><?= e(t('جمهور مشابه لجمهور عندك')) ?></option><?php endif; ?>
              </select></div>
          </div>
          <div data-aud="meta_engagement" class="grid sm:grid-cols-2 gap-3">
            <div><label class="label"><?= e(t('المصدر')) ?></label>
              <select name="source" class="input"><option value="page"><?= e(t('صفحة فيسبوك')) ?></option><option value="instagram"><?= e(t('حساب انستجرام')) ?></option></select></div>
            <div><label class="label"><?= e(t('رقم الصفحة / الحساب')) ?></label><input name="source_id" class="input" dir="ltr" value="<?= e($current['conn']['metadata']['selectedPageId'] ?? '') ?>"></div>
          </div>
          <div data-aud="website" class="grid sm:grid-cols-2 gap-3">
            <div><label class="label"><?= e(t('رقم الـ Pixel')) ?></label><input name="pixel_id" class="input" dir="ltr"></div>
            <div><label class="label"><?= e(t('الرابط بيحتوي على (اختياري)')) ?></label><input name="url_contains" class="input" dir="ltr" placeholder="/products"></div>
          </div>
          <div data-aud="meta_engagement website">
            <label class="label"><?= e(t('آخر كام يوم')) ?></label><input name="days" type="number" min="1" max="180" value="30" class="input !w-32">
          </div>
          <div data-aud="lookalike" class="grid sm:grid-cols-3 gap-3">
            <div><label class="label"><?= e(t('الجمهور الأصلي')) ?></label>
              <select name="source_audience" class="input"><?php foreach ($audiences as $a): if (empty($a['id'] ?? $a['platformAudienceId'] ?? null)) continue; ?><option value="<?= e($a['id'] ?? $a['platformAudienceId']) ?>"><?= e($a['name'] ?? '') ?></option><?php endforeach; ?></select></div>
            <div><label class="label"><?= e(t('الدولة (كود من حرفين)')) ?></label><input name="country" class="input" dir="ltr" maxlength="2" value="<?= e(ws_country()) ?>"></div>
            <div><label class="label"><?= e(t('الحجم %')) ?></label><input name="ratio" type="number" min="1" max="20" value="1" class="input"></div>
          </div>
          <p class="text-xs text-ink-500"><?= e(t('الجمهور بيتعمل على المنصة نفسها، ومش بيصرف فلوس لوحده.')) ?></p>
          <button class="btn-primary"><?= e(t('إنشاء الجمهور')) ?></button>
        </form>
        <script>
        (function () {
          var s = document.getElementById('audType');
          function sync() { document.querySelectorAll('#audForm [data-aud]').forEach(function (el) { el.style.display = el.dataset.aud.split(' ').indexOf(s.value) >= 0 ? '' : 'none'; }); }
          s.addEventListener('change', sync); sync();
        })();
        </script>
      </section>
      </div>
      <script>window.AUDX = <?= json_encode(['all' => t('الكل'), 'keys' => t('اختصارات لوحة المفاتيح'), 'kSearch' => t('البحث في الجماهير'), 'kNew' => t('جمهور جديد'),
        'hints' => ['meta_engagement' => t('الأنسب للبداية: ناس عرفوك فعلًا، وغالبًا بيتحولوا أسرع.'), 'website' => t('محتاج Pixel متركّب على موقعك، وإلا الجمهور هيفضل فاضي.'), 'lookalike' => t('ابدأ بـ 1% لأدق تشابه، وكبّر الحجم لو عاوز وصول أكبر.')]], JSON_UNESCAPED_UNICODE) ?>;</script>
      <script src="assets/ui-ad_audiences.js?v=<?= e(APP_VERSION) ?>"></script>
      <script>window.AUDU = <?= json_encode(['list' => array_map(function ($a) { return ['id' => (string)($a['id'] ?? $a['platformAudienceId'] ?? ''), 'name' => (string)($a['name'] ?? ''), 'type' => (string)($a['type'] ?? ''), 'size' => (float)($a['size'] ?? 0), 'status' => (string)($a['status'] ?? '')]; }, $audiences),
        'canLook' => (bool)$audiences, 'isMeta' => (bool)$isMeta, 'builder' => url('ad_builder'), 'people' => t('حوالي %s شخص'),
        'fSmall' => t('صغير جدًا'), 'fNiche' => t('متخصص'), 'fGood' => t('حجم ممتاز'), 'fBroad' => t('واسع'), 'makeLook' => t('مشابه منه'), 'copyId' => t('انسخ الـ ID'), 'copied' => t('اتنسخ ✓'), 'del' => t('حذف'), 'lookName' => t('مشابه 1% - %s'),
        'sortDef' => t('الترتيب الأصلي'), 'sortSize' => t('الأكبر الأول'), 'sortName' => t('بالاسم'),
        'ladT' => t('سلّم الجماهير'), 'ladD' => t('ابدأ بالناس اللي عرفوك، اعمل منهم مشابه، وبعدين وسّع.'), 'tWarm' => t('دافي'), 'tWarmD' => t('تفاعلوا أو زاروا أو عملاء'), 'tLook' => t('مشابه'), 'tLookD' => t('ناس شبه الدافيين'), 'tBroad' => t('واسع'), 'tBroadD' => t('استهداف محفوظ واهتمامات'),
        'nHave' => t('%s جمهور'), 'none' => t('لسه مفيش'), 'nWarm' => t('أول خطوة: اعمل جمهور من الناس اللي اتفاعلوا معاك آخر 90 يوم.'), 'nLook' => t('الخطوة الجاية: اعمل جمهور مشابه 1% من «%s» عشان توصل لناس جديدة شبههم.'), 'nScale' => t('عندك دافي ومشابه. الواسع بيتعمل بالاهتمامات جوه منشئ الحملات: اعمل مجموعة لكل درجة عشان تقارن.'),
        'doIt' => t('جهّزهولي'), 'toBuilder' => t('افتح منشئ الحملات'), 'warmName' => t('متفاعلين آخر 90 يوم'), 'dN' => t('%s يوم'), 'precise' => t('أدق'), 'wide' => t('أوسع'),
        'rTip1' => t('1–2%: أقرب ناس لجمهورك، الأفضل للمبيعات.'), 'rTip2' => t('3–5%: توازن بين الدقة والحجم.'), 'rTip3' => t('6–10%: وصول أكبر بدقة أقل، مناسب للانتشار.')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
      <script src="assets/ad-aud-ui.js?v=<?= e(APP_VERSION) ?>"></script>
      <?php endif; ?>
    </div>
    <?php
}, ['full' => true]);
