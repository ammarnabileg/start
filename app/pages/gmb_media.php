<?php
$err = null;
$z = zernio_or_null($err);
$pageToken = (string)($_GET['page'] ?? '');

$accounts = [];
$options = [];
$apiError = null;
if ($z) {
    try {
        $accounts = gmb_accounts($z);
        $options = gmb_location_options($z, $accounts);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/gmb_media.json') ? json_decode((string)file_get_contents($fxd . '/gmb_media.json'), true) : null;
if ($fx && is_file($fxd . '/gmb_fake_z.php')) $z = require $fxd . '/gmb_fake_z.php'; // اختبارات بس: بيسجّل الكتابات بدل ما يبعتها
if ($fx) { $accounts = $accounts ?: [['id' => 'acc_gmb_demo']]; $options = $fx['locations']; $apiError = null; }
$loc = gmb_pick_location($options);
$cats = gmb_media_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $loc) {
    $action = (string)($_POST['action'] ?? '');
    $ajax = !empty($_POST['ajax']);
    $out = ['ok' => false];
    try {
        if ($action === 'upload') {
            $f = media_lib_file('photo') ?: ($_FILES['photo'] ?? null);
            $cat = (string)($_POST['category'] ?? 'ADDITIONAL');
            if (!isset($cats[$cat])) $cat = 'ADDITIONAL';
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new ZernioException(t('اختار صورة الأول.'));
            }
            $mime = (string)(function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : ($f['type'] ?? ''));
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                throw new ZernioException(t('الصورة لازم تكون JPG أو PNG.'));
            }
            if ($f['size'] < 10240 || $f['size'] > 5 * 1024 * 1024) {
                throw new ZernioException(t('حجم الصورة لازم يكون بين 10 كيلوبايت و 5 ميجا.'));
            }
            $info = @getimagesize($f['tmp_name']);
            if ($info && ($info[0] < 250 || $info[1] < 250)) {
                throw new ZernioException(t('أقل مقاس للصورة 250×250 بكسل.'));
            }
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$f['name'])) ?: 'photo.jpg';
            $desc = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 300);
            // الصورة بتترفع مرة واحدة، وبعدين تتضاف على الفرع ده + أي فروع تانية مختارة
            $url = $z->uploadFile($f['tmp_name'], $name, $mime);
            if (empty($f['lib'])) media_capture($f['tmp_name'], (string)$f['name'], 'Google Business'); else media_log_use((int)ws('id'), (int)$f['lib_id'], 'gmb', (string)($loc['location'] ?? ''));
            gmb_add_media($z, $loc['account'], $loc['location'], $url, $cat, $desc);
            log_activity('gmb.media_add', $cat);
            $want = array_map('strval', (array)($_POST['also'] ?? []));
            $okN = 1; $fail = [];
            foreach ($options as $o) {
                if (!in_array($o['key'], $want, true) || $o['key'] === $loc['key'] || $o['location'] === '') continue;
                try { gmb_add_media($z, $o['account'], $o['location'], $url, $cat, $desc); log_activity('gmb.media_add', $cat); $okN++; }
                catch (ZernioException $ex) { $fail[] = $o['label'] . ': ' . $ex->getMessage(); }
            }
            $out = ['ok' => true, 'branches' => $okN, 'fail' => $fail];
            flash('success', $okN > 1 ? t('اترفعت الصورة على %s فروع. ممكن تاخد شوية وقت لحد ما تظهر على جوجل.', $okN) : t('تم رفع الصورة. ممكن تاخد شوية وقت لحد ما تظهر على جوجل.'));
            if ($fail) flash('error', t('ما اترفعتش على الفروع دي:') . ' ' . implode(' · ', $fail));
        } elseif ($action === 'delete' && can('gmb.manage')) {
            $mediaId = (string)($_POST['media_id'] ?? '');
            if ($mediaId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $mediaId)) throw new ZernioException(t('الصورة مش موجودة.'));
            gmb_delete_media($z, $loc['account'], $loc['location'], $mediaId);
            log_activity('gmb.media_delete', $mediaId);
            $out = ['ok' => true];
            if (!$ajax) flash('success', t('تم حذف الصورة.'));
        }
    } catch (ZernioException $ex) {
        $out = ['ok' => false, 'error' => $ex->getMessage()];
        if (!$ajax) flash('error', $ex->getMessage());
    }
    if ($ajax) {
        // الرسائل اتحطت للصفحة الجاية؛ في الـ ajax إحنا اللي بنعرضها، فنشيلها
        unset($_SESSION['flash']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    redirect(url('gmb_media', ['loc' => $loc['key']]));
}

$items = [];
$next = null;
$total = 0;
if ($fx) {
    $items = $fx['items'];
    $total = (int)($fx['total'] ?? count($items));
} elseif ($z && $loc) {
    try {
        $r = gmb_media($z, $loc['account'], $loc['location'], $pageToken ?: null);
        $items = $r['mediaItems'] ?? [];
        $next = $r['nextPageToken'] ?? null;
        $total = (int)($r['totalMediaItemsCount'] ?? count($items));
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('صور جوجل بيزنس'), 'gmb_media', function () use ($z, $accounts, $options, $loc, $items, $next, $total, $cats, $apiError) {
    if (!$z) { no_api_box(); return; }
    if (!$accounts && !$apiError) { gmb_no_account_box(); return; }
    if ($apiError) { error_box($apiError); echo '<div class="h-4"></div>'; }
    if (!$loc) return;
    $p = explode(' · ', (string)$loc['label'], 2);
    $canDel = can('gmb.manage');
    $list = [];
    foreach ($items as $m) {
        $list[] = [
            'id' => basename((string)($m['name'] ?? '')),
            'cat' => (string)($m['locationAssociation']['category'] ?? ''),
            'video' => ($m['mediaFormat'] ?? '') === 'VIDEO',
            'src' => (string)($m['thumbnailUrl'] ?? $m['googleUrl'] ?? ''),
            'big' => (string)($m['googleUrl'] ?? $m['thumbnailUrl'] ?? ''),
            'at' => (string)($m['createTime'] ?? ''),
            'date' => fmt_date($m['createTime'] ?? null, 'd/m/Y'),
            'desc' => (string)($m['description'] ?? ''),
            'views' => isset($m['insights']['viewCount']) ? (int)$m['insights']['viewCount'] : null,
            'w' => (int)($m['dimensions']['widthPixels'] ?? 0), 'h' => (int)($m['dimensions']['heightPixels'] ?? 0),
        ];
    }
    $others = array_values(array_filter($options, function ($o) use ($loc) { return $o['key'] !== $loc['key'] && $o['location'] !== ''; }));
    ?>
    <div class="relative rounded-2xl border border-ink-200 bg-white mb-6 p-5 flex flex-wrap items-center gap-4">
      <span class="w-12 h-12 shrink-0 rounded-xl bg-gradient-to-br from-brand-500 to-cyan-500 text-white flex items-center justify-center">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      </span>
      <div class="min-w-0 flex-1">
        <h2 class="text-lg font-bold text-ink-900 truncate"><?= e($p[0]) ?></h2>
        <div class="text-sm text-ink-500 truncate"><?= e(implode(' · ', array_filter([$p[1] ?? '', t('%s صورة', $total)]))) ?></div>
      </div>
      <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
        <a class="btn-ghost !py-1.5 text-sm" href="<?= e(url('gmb_location', ['loc' => $loc['key']])) ?>"><?= e(t('بيانات الفرع')) ?></a>
        <?php gmb_switcher($options, $loc, 'gmb_media'); ?>
      </div>
    </div>

    <div class="grid lg:grid-cols-[minmax(0,1fr)_320px] gap-6 items-start">
      <div class="min-w-0 space-y-6">
        <?php /* ---------- الرفع ---------- */ ?>
        <div id="gm-up" class="card p-5">
          <div id="gm-drop" class="rounded-2xl border-2 border-dashed border-ink-200 hover:border-brand-400 transition p-8 text-center cursor-pointer">
            <div class="w-12 h-12 mx-auto rounded-full bg-brand-50 text-brand-600 flex items-center justify-center mb-3">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            </div>
            <div class="font-medium text-ink-900"><?= e(t('اسحب الصور هنا أو دوس عشان تختار')) ?></div>
            <div class="text-xs text-ink-500 mt-1"><?= e(t('JPG أو PNG · من 10 كيلوبايت لـ 5 ميجا · أقل مقاس 250×250 · ممكن أكتر من صورة مرة واحدة')) ?></div>
            <div class="mt-4 flex justify-center gap-2">
              <button type="button" id="gm-pick" class="btn-primary !py-1.5"><?= e(t('اختار من الجهاز')) ?></button>
              <button type="button" id="gm-lib" class="btn-ghost !py-1.5"><?= e(t('من المكتبة')) ?></button>
            </div>
            <input type="file" id="gm-file" accept="image/jpeg,image/png" multiple class="hidden">
          </div>
          <div id="gm-q" class="mt-4 space-y-2" style="display:none"></div>
          <div id="gm-qfoot" class="mt-4 pt-4 border-t border-ink-100" style="display:none">
            <?php if ($others): ?>
            <details class="mb-3 rounded-xl border border-ink-200 group">
              <summary class="cursor-pointer list-none flex items-center gap-2 px-4 py-2.5 text-sm"><span class="font-medium text-ink-800"><?= e(t('ارفعها كمان على فروع تانية')) ?></span><span id="gm-also-n" class="rounded-full bg-brand-600 text-white text-xs px-2 py-0.5" style="display:none"></span><svg class="w-4 h-4 text-ink-400 ms-auto group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg></summary>
              <div class="px-4 pb-4 flex flex-wrap gap-2" id="gm-also">
                <?php foreach ($others as $o): $q = explode(' · ', $o['label'], 2); ?>
                  <label class="cursor-pointer select-none"><input type="checkbox" value="<?= e($o['key']) ?>" class="peer sr-only">
                    <span class="inline-flex flex-col rounded-xl border border-ink-200 bg-white px-3 py-1.5 text-sm hover:border-brand-400 peer-checked:bg-brand-600 peer-checked:border-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 transition"><span class="font-medium"><?= e($q[0]) ?></span><?php if (!empty($q[1])): ?><span class="text-[11px] opacity-75"><?= e($q[1]) ?></span><?php endif; ?></span></label>
                <?php endforeach; ?>
              </div>
            </details>
            <?php endif; ?>
            <div class="flex flex-wrap items-center gap-3">
              <span id="gm-qinfo" class="text-sm text-ink-500 me-auto"></span>
              <button type="button" id="gm-clear" class="btn-ghost"><?= e(t('امسح القايمة')) ?></button>
              <button type="button" id="gm-go" class="btn-primary"></button>
            </div>
          </div>
        </div>

        <?php /* ---------- المعرض ---------- */ ?>
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-4">
            <div id="gm-chips" class="flex flex-wrap gap-1.5"></div>
            <select id="gm-sort" class="input !mt-0 !w-auto !py-1.5 text-sm ms-auto">
              <option value="new"><?= e(t('الأحدث')) ?></option><option value="old"><?= e(t('الأقدم')) ?></option><option value="views"><?= e(t('الأكتر مشاهدة')) ?></option>
            </select>
          </div>
          <div id="gm-grid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3"></div>
          <div id="gm-empty" class="rounded-2xl border border-dashed border-ink-200 p-10 text-center text-sm text-ink-500" style="display:none"></div>
          <?php if ($next): ?>
            <div class="text-center mt-4"><a class="btn-ghost" href="<?= e(url('gmb_media', ['loc' => $loc['key'], 'page' => $next])) ?>"><?= e(t('صور أكتر')) ?></a></div>
          <?php endif; ?>
        </div>
      </div>

      <aside class="space-y-4 lg:sticky lg:top-4 order-first lg:order-none">
        <div id="gm-cov" class="card p-5"></div>
      </aside>
    </div>

    <div id="gm-lb" class="fixed inset-0 z-[200] bg-black/95 flex flex-col" style="display:none" role="dialog" aria-modal="true">
      <div class="flex items-center gap-3 px-4 py-3 text-white">
        <span id="gm-lb-i" class="text-sm opacity-70"></span>
        <span id="gm-lb-cat" class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs"></span>
        <a id="gm-lb-open" target="_blank" rel="noopener" class="ms-auto text-sm opacity-80 hover:opacity-100"><?= e(t('افتح الأصل ↗')) ?></a>
        <?php if ($canDel): ?><button type="button" id="gm-lb-del" class="text-sm rounded-lg bg-red-600/80 hover:bg-red-600 px-3 py-1"><?= e(t('حذف من جوجل')) ?></button><?php endif; ?>
        <button type="button" id="gm-lb-x" class="w-9 h-9 rounded-full hover:bg-white/15 text-xl" aria-label="<?= e(t('قفل')) ?>">✕</button>
      </div>
      <div class="flex-1 min-h-0 relative flex items-center justify-center px-14">
        <button type="button" id="gm-lb-prev" class="absolute start-3 w-11 h-11 rounded-full bg-white/10 hover:bg-white/25 text-white text-2xl">‹</button>
        <img id="gm-lb-img" class="max-h-full max-w-full object-contain rounded-lg" alt="" referrerpolicy="no-referrer">
        <button type="button" id="gm-lb-next" class="absolute end-3 w-11 h-11 rounded-full bg-white/10 hover:bg-white/25 text-white text-2xl">›</button>
      </div>
      <div id="gm-lb-meta" class="px-4 py-3 text-center text-sm text-white/80"></div>
    </div>

    <script>window.GMX = <?= json_encode(['csrf' => csrf_token(), 'post' => url('gmb_media'), 'loc' => $loc['key'], 'canDel' => $canDel, 'items' => $list, 'total' => $total, 'cats' => $cats, 's' => [
      'all' => t('الكل'), 'cov' => t('تغطية الصور'), 'covHint' => t('جوجل بيقول إن الفروع اللي فيها صور كتير ومتنوعة بتاخد طلبات اتجاهات وزيارات أكتر.'), 'covDone' => t('%s من %s'), 'uploadHere' => t('ارفع هنا'),
      'empty' => t('مفيش صور في القسم ده لسه.'), 'emptyAll' => t('مفيش صور على الفرع ده. ابدأ بالغلاف واللوجو.'), 'views' => t('%s مشاهدة'),
      'go' => t('ارفع %s صورة'), 'goN' => t('ارفع %s صورة على %s فروع'), 'qinfo' => t('%s جاهزة · %s فيها مشكلة'), 'cat' => t('مكانها'), 'desc' => t('وصف (اختياري)'), 'remove' => t('شيل'),
      'badType' => t('لازم JPG أو PNG'), 'tooSmall' => t('أصغر من 10 كيلوبايت'), 'tooBig' => t('أكبر من 5 ميجا'), 'tooFew' => t('أقل من 250×250'), 'lowRes' => t('مقاس صغير — جوجل بينصح بـ 720×720 أو أكتر'),
      'wait' => t('مستني'), 'sending' => t('بيترفع...'), 'done' => t('اترفعت ✓'), 'doneN' => t('اترفعت على %s فروع ✓'), 'failed' => t('فشلت'), 'partial' => t('ما اترفعتش على: %s'),
      'allDone' => t('خلص الرفع: %s نجحت · %s فشلت. الصور بتاخد وقت لحد ما تظهر على جوجل — حدّث الصفحة بعد شوية.'),
      'confirmDel' => t('حذف الصورة دي من جوجل؟ مفيش رجوع.'), 'deleted' => t('اتحذفت الصورة'), 'leave' => t('فيه صور لسه بتترفع. تخرج؟'), 'cover16' => t('الغلاف أحسن يكون عرضي 16:9'), 'logoSq' => t('اللوجو أحسن يكون مربع'),
      'k' => t('← → تنقل · Del حذف · Esc قفل'),
    ], 'targets' => ['COVER' => 1, 'LOGO' => 1, 'EXTERIOR' => 3, 'INTERIOR' => 3, 'FOOD_AND_DRINK' => 3, 'PRODUCT' => 3, 'TEAMS' => 1, 'AT_WORK' => 2]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-gmb_media.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
