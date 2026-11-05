<?php
/** المستخدم مابيكتبش البروفايل: بنشيل عمود profiles لو موجود ونحط البروفايل الحالي في كل سطر */
function bulk_with_profile(string $path, string $profileId): string
{
    $in = fopen($path, 'r');
    $bom = fread($in, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($in);
    $head = fgetcsv($in, 0, ',', '"', '');
    if (!$head) { fclose($in); return $path; }
    $head = array_map(function ($h) { return trim((string)$h); }, $head);
    $drop = array_search('profiles', array_map('strtolower', $head), true);
    $outPath = tempnam(sys_get_temp_dir(), 'bulk');
    $out = fopen($outPath, 'w');
    $h2 = $head; if ($drop !== false) unset($h2[$drop]);
    fputcsv($out, array_merge(array_values($h2), ['profiles']), ',', '"', '');
    while (($r = fgetcsv($in, 0, ',', '"', '')) !== false) {
        if (!array_filter($r, function ($x) { return trim((string)$x) !== ''; })) continue;
        $r = array_pad($r, count($head), '');
        if ($drop !== false) unset($r[$drop]);
        fputcsv($out, array_merge(array_values($r), [$profileId]), ',', '"', '');
    }
    fclose($in); fclose($out);
    register_shutdown_function(function () use ($outPath) { if (is_file($outPath)) unlink($outPath); });
    return $outPath;
}

$err = null;
$z = zernio_or_null($err);
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/bulk_fake_z.php')) $z = require $fxd . '/bulk_fake_z.php'; // اختبارات بس: مفيش رفع حقيقي

/** أكواد أخطاء Zernio → كلام واضح + إزاي تصلحها */
$bkErr = function (string $raw): array {
    [$code, $arg] = array_pad(explode(':', trim($raw), 2), 2, '');
    $m = [
        'unknown_profile' => [t('البروفايل مش موجود في حساب Zernio.'), t('افتح التكاملات واتأكد إن مفتاح Zernio والبروفايل صح.')],
        'no_account_for_platform' => [t('مفيش حساب %s مربوط.', $arg), t('اربط الحساب من "الحسابات المربوطة"، أو شيل %s من عمود platforms.', $arg)],
        'rows_exceed_advisory_limit' => [t('الملف فيه أكتر من %s سطر.', $arg), t('Zernio بينصح بـ %s سطر أو أقل في المرة. قسّم الملف لملفين أو أكتر.', $arg)],
        'unknown_platform' => [t('منصة مش معروفة: %s', $arg), t('استخدم أسماء المنصات بالإنجليزي زي facebook أو instagram.')],
        'invalid_platform' => [t('منصة مش معروفة: %s', $arg), t('استخدم أسماء المنصات بالإنجليزي زي facebook أو instagram.')],
        'missing_content' => [t('نص المنشور فاضي.'), t('اكتب النص في عمود post_content.')],
        'missing_post_content' => [t('نص المنشور فاضي.'), t('اكتب النص في عمود post_content.')],
        'invalid_schedule_time' => [t('الميعاد مكتوب غلط: %s', $arg), t('اكتبه بالشكل 2026-12-01 10:00.')],
        'schedule_time_in_past' => [t('الميعاد ده عدّى.'), t('حط ميعاد في المستقبل، أو اكتب true في use_queue.')],
        'missing_schedule' => [t('مفيش ميعاد للمنشور.'), t('اكتب schedule_time، أو true في use_queue أو publish_now أو is_draft.')],
        'invalid_timezone' => [t('المنطقة الزمنية غلط: %s', $arg), t('اكتبها زي %s، أو سيبها فاضية.', site_timezone())],
        'invalid_media_url' => [t('رابط الصورة أو الفيديو مش صالح: %s', $arg), t('لازم يكون رابط عام بيبدأ بـ https://')],
        'content_too_long' => [t('النص أطول من المسموح للمنصة: %s', $arg), t('قصّر النص، أو اكتب نسخة أقصر في custom_content_%s.', $arg ?: '<platform>')],
    ];
    return $m[$code] ?? [$raw, ''];
};

/* الرفع على دفعات من الصفحة (كل دفعة 500 سطر أو أقل) — بيرجع JSON */
if ($z && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $dry = ($_POST['mode'] ?? 'check') !== 'create';
    $csv = (string)($_POST['csv_text'] ?? '');
    try {
        if (trim($csv) === '') throw new ZernioException(t('اختار ملف أو اكتب سطر واحد على الأقل.'));
        if (strlen($csv) > 5 * 1024 * 1024) throw new ZernioException(t('الملف أكبر من 5 ميجا.'));
        $tmp = tempnam(sys_get_temp_dir(), 'bkx');
        file_put_contents($tmp, $csv);
        register_shutdown_function(function () use ($tmp) { if (is_file($tmp)) unlink($tmp); });
        $r = pub_bulk_upload($z, bulk_with_profile($tmp, current_profile_id($z)), $dry);
        $codes = [];
        foreach ((array)($r['warnings'] ?? []) as $w) $codes[(string)$w] = 1;
        foreach ((array)($r['results'] ?? []) as $row) foreach ((array)($row['errors'] ?? []) as $er) $codes[(string)$er] = 1;
        $explain = [];
        foreach (array_keys($codes) as $c) $explain[$c] = $bkErr($c);
        if (!$dry) log_activity('post.bulk', ($r['valid'] ?? 0) . '/' . ($r['total'] ?? 0) . ' ' . mb_substr((string)($_POST['fname'] ?? ''), 0, 80));
        echo json_encode(['ok' => true, 'data' => $r, 'explain' => $explain], JSON_UNESCAPED_UNICODE);
    } catch (ZernioException $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($z && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['csv'] ?? null;
    $dry = ($_POST['mode'] ?? 'check') !== 'create';
    try {
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            throw new ZernioException(t('اختار ملف CSV الأول.'));
        }
        if (!preg_match('/\.csv$/i', (string)$f['name'])) {
            throw new ZernioException(t('الملف لازم يكون بامتداد .csv'));
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            throw new ZernioException(t('الملف أكبر من 5 ميجا.'));
        }
        $r = pub_bulk_upload($z, bulk_with_profile($f['tmp_name'], current_profile_id($z)), $dry);
        $_SESSION['bulk_result'] = ['dry' => $dry, 'data' => $r, 'file' => (string)$f['name']];
        if (!$dry) {
            log_activity('post.bulk', ($r['valid'] ?? 0) . '/' . ($r['total'] ?? 0));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('bulk'));
}

$result = $_SESSION['bulk_result'] ?? null;
unset($_SESSION['bulk_result']);

/* البروفايلات والمنصات المربوطة فعلاً (قراءة بس، متخزنة 10 دقايق) — عشان ملف المثال والفحص يبقوا بقيمك الحقيقية */
$profs = [];
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/bulk.json')) {
    $profs = (array)(json_decode((string)file_get_contents($fxd . '/bulk.json'), true)['profiles'] ?? []);
} elseif ($z) {
    $ck = 'bulk_profs_' . (int)ws('id');
    if (isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 600) {
        $profs = $_SESSION[$ck]['d'];
    } else {
        try {
            foreach ($z->profiles() as $p) {
                $pid = (string)($p['_id'] ?? $p['id'] ?? '');
                if ($pid === '') continue;
                $pl = [];
                foreach ($z->accounts($pid) as $a) if (!empty($a['platform']) && ($a['enabled'] ?? true) !== false) $pl[strtolower((string)$a['platform'])] = 1;
                $profs[] = ['id' => $pid, 'name' => (string)($p['name'] ?? $pid), 'platforms' => array_keys($pl)];
            }
            $_SESSION[$ck] = ['t' => time(), 'd' => $profs];
        } catch (ZernioException $ex) { /* الفحص هيبقى من غير البروفايلات */ }
    }
}

if (isset($_GET['sample'])) {
    $pr = null;
    foreach ($profs as $p) if ($p['platforms']) { $pr = $p; break; }
    $pr = $pr ?: ($profs[0] ?? ['name' => 'MyProfile', 'platforms' => ['facebook']]);
    $pls = $pr['platforms'] ?: ['facebook'];
    $tz = site_timezone();
    $d = new DateTime('tomorrow 10:00', new DateTimeZone($tz));
    $at = function (int $days, string $hm) use ($d) { $x = clone $d; $x->modify('+' . $days . ' day'); [$h, $m] = explode(':', $hm); $x->setTime((int)$h, (int)$m); return $x->format('Y-m-d H:i'); };
    $two = implode('|', array_slice($pls, 0, 2));
    $first = $pls[0];
    $ar = lang() === 'ar';
    $rows = [
        ['post_content', 'platforms', 'schedule_time', 'tz', 'media_urls', 'use_queue', 'custom_content_' . $first],
        [$ar ? 'صباح الخير! المنيو الجديد نزل النهارده 🎉' : 'Good morning! Our new menu is out today 🎉', $first, $at(0, '10:00'), $tz, '', '', ''],
        [$ar ? 'عرض نهاية الأسبوع: خصم 20% على كل حاجة' : 'Weekend offer: 20% off everything', $two, $at(3, '18:30'), $tz, 'https://example.com/offer.jpg', '', $ar ? 'نسخة خاصة بـ ' . $first . ': خصم 20%!' : 'Special ' . $first . ' version: 20% off!'],
        [$ar ? 'منشور هيتحط في أقرب ميعاد فاضي في الطابور' : 'This post goes into the next free queue slot', $first, '', $tz, '', 'true', ''],
    ];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bulk-template.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    foreach ($rows as $r) fputcsv($o, $r);
    exit;
}


render(t('رفع منشورات بالجملة'), 'bulk', function () use ($z, $result, $profs, $bkErr) {
    if (!$z && !getenv('UI_FIXTURES')) { no_api_box(); return; }
    $cols = [
        ['post_content', t('نص المنشور.'), true],
        ['platforms', t('المنصات، مفصولة بـ | لو أكتر من واحدة (مثلاً facebook|instagram).'), true],
        ['schedule_time', t('ميعاد النشر بالشكل 2026-12-01 10:00.'), true],
        ['tz', t('المنطقة الزمنية (مثلاً %s). لو فاضي بياخد منطقة الـ Profile.', cx('tz')), false],
        ['media_urls', t('روابط صور أو فيديو عامة، مفصولة بـ |.'), false],
        ['title', t('عنوان (يوتيوب وبنترست مثلاً).'), false],
        ['use_queue', t('اكتب true عشان يتحط في أقرب ميعاد في الطابور بدل schedule_time.'), false],
        ['publish_now', t('اكتب true للنشر فوراً.'), false],
        ['is_draft', t('اكتب true للحفظ كمسودة.'), false],
        ['custom_content_<platform>', t('نص خاص بمنصة معينة، مثلاً custom_content_facebook.'), false],
        ['schedule_time_<platform>', t('ميعاد مختلف لمنصة معينة.'), false],
    ];
    $allPl = [];
    foreach ($profs as $p) foreach ($p['platforms'] as $pl) $allPl[$pl] = 1;
    $allPl = array_keys($allPl);
    $icons = platform_icons();
    $plIcon = function (string $pl, string $cls = 'w-3.5 h-3.5') use ($icons) {
        if (!isset($icons[$pl])) return '';
        return '<svg viewBox="0 0 24 24" class="' . $cls . '" fill="' . e($icons[$pl][0]) . '" aria-hidden="true"><path d="' . e($icons[$pl][1]) . '"/></svg>';
    };
    $steps = [t('جهّز المنشورات'), t('راجع وعدّل'), t('افحص وارفع')];
    ?>
    <ol id="bk-steps" class="flex items-center gap-2 mb-5 text-sm" aria-label="<?= e(t('الخطوات')) ?>">
      <?php foreach ($steps as $i => $st): ?>
        <li class="bk-step flex items-center gap-2 <?= $i ? 'text-ink-400' : 'text-brand-700 font-semibold' ?>" data-s="<?= $i + 1 ?>">
          <span class="bk-dot w-7 h-7 rounded-full grid place-items-center text-xs font-bold border <?= $i ? 'border-ink-200 bg-white' : 'border-brand-600 bg-brand-600 text-white' ?>"><?= $i + 1 ?></span>
          <span class="hidden sm:inline"><?= e($st) ?></span>
        </li>
        <?php if ($i < 2): ?><li class="flex-1 h-px bg-ink-200 min-w-4" aria-hidden="true"></li><?php endif; ?>
      <?php endforeach; ?>
    </ol>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-5 min-w-0">
        <form method="post" enctype="multipart/form-data" class="space-y-5" id="bk-form">
          <?= csrf_field() ?>

          <!-- 1) البداية -->
          <section id="bk-start" class="card p-5">
            <h2 class="font-bold text-ink-900"><?= e(t('هتجيب المنشورات منين؟')) ?></h2>
            <p class="text-sm text-ink-500 mt-1"><?= e(t('كل سطر = منشور. هتشوفهم في جدول وتعدّل قبل ما أي حاجة تتبعت.')) ?></p>
            <div class="grid sm:grid-cols-3 gap-3 mt-4">
              <label id="bk-drop" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-dashed border-ink-200 hover:border-brand-400 hover:bg-brand-50/40 p-5 cursor-pointer transition focus-within:ring-2 ring-brand-300">
                <span class="w-11 h-11 rounded-xl bg-brand-50 text-brand-600 grid place-items-center text-xl group-hover:scale-110 transition">⇪</span>
                <span class="font-semibold text-ink-900" id="bk-fn"><?= e(t('ارفع ملف')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('CSV من Excel أو Google Sheets، أو اسحبه هنا')) ?></span>
                <input type="file" name="csv" id="bk-file" accept=".csv,.tsv,.txt,text/csv" class="sr-only">
              </label>
              <button type="button" id="bk-paste-btn" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-ink-200 hover:border-brand-400 hover:bg-brand-50/40 p-5 transition">
                <span class="w-11 h-11 rounded-xl bg-lime-50 text-lime-700 grid place-items-center text-xl group-hover:scale-110 transition">📋</span>
                <span class="font-semibold text-ink-900"><?= e(t('الصق من Excel')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('حدد الخانات في الشيت وانسخها والصقها هنا')) ?></span>
              </button>
              <button type="button" id="bk-new" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-ink-200 hover:border-brand-400 hover:bg-brand-50/40 p-5 transition">
                <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 grid place-items-center text-xl group-hover:scale-110 transition">✎</span>
                <span class="font-semibold text-ink-900"><?= e(t('اكتب هنا')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('جدول فاضي تكتب فيه على طول')) ?></span>
              </button>
            </div>
            <div id="bk-paste" class="hidden mt-4">
              <textarea id="bk-paste-txt" rows="6" class="input font-mono text-xs" dir="auto" placeholder="<?= e(t('الصق هنا (Ctrl+V). أول سطر لازم يكون أسماء الأعمدة، زي: النص، المنصات، الميعاد')) ?>"></textarea>
              <div class="flex gap-2 mt-2"><button type="button" id="bk-paste-go" class="btn-primary"><?= e(t('اعرض في الجدول')) ?></button><button type="button" id="bk-paste-x" class="btn-ghost"><?= e(t('إلغاء')) ?></button></div>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-4 text-sm">
              <a class="link" download="bulk-template.csv" href="<?= e(url('bulk', ['sample' => 1])) ?>">⇣ <?= e(t('نزّل الملف الجاهز')) ?></a>
              <span class="text-xs text-ink-500"><?= e(t('أسماء الأعمدة ممكن تبقى بالعربي (النص، المنصات، الميعاد، الصورة) وهنفهمها.')) ?></span>
            </div>
          </section>

          <!-- 2) المراجعة -->
          <section id="bk-grid-wrap" class="card p-4 space-y-3" style="display:none">
            <div class="flex flex-wrap items-center gap-2">
              <div class="min-w-0">
                <div class="font-semibold text-ink-900 truncate" id="bk-fname"></div>
                <div id="bk-sum" class="text-sm mt-0.5"></div>
              </div>
              <div class="ms-auto flex flex-wrap items-center gap-1.5">
                <button type="button" id="bk-add" class="btn-ghost !py-1 text-xs">+ <?= e(t('سطر')) ?></button>
                <button type="button" id="bk-dl" class="btn-ghost !py-1 text-xs">⇣ CSV</button>
                <button type="button" id="bk-reset" class="btn-ghost !py-1 text-xs"><?= e(t('ابدأ من جديد')) ?></button>
              </div>
            </div>
            <div id="bk-notes" class="space-y-1.5"></div>
            <div id="bk-map" class="hidden rounded-xl border border-amber-200 bg-amber-50/60 p-3"></div>

            <details id="bk-tools" class="rounded-xl border border-ink-200 bg-ink-50/50" open>
              <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-ink-800">⚡ <?= e(t('أدوات سريعة لكل السطور')) ?></summary>
              <div class="grid md:grid-cols-2 gap-3 p-3 pt-1">
                <div class="rounded-xl bg-white border border-ink-200 p-3">
                  <div class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('المنصات')) ?></div>
                  <div class="flex flex-wrap gap-1.5" id="bk-pl-pick">
                    <?php foreach (($allPl ?: ['facebook', 'instagram', 'linkedin', 'twitter', 'tiktok']) as $pl): ?>
                      <label class="cursor-pointer"><input type="checkbox" value="<?= e($pl) ?>" class="peer sr-only"><span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-focus-visible:ring-2 ring-brand-300"><?= $plIcon($pl) ?><?= e($pl) ?></span></label>
                    <?php endforeach; ?>
                  </div>
                  <div class="flex flex-wrap items-center gap-2 mt-2">
                    <button type="button" id="bk-pl-apply" class="btn-primary !py-1 text-xs"><?= e(t('طبّق')) ?></button>
                    <label class="text-xs text-ink-600 flex items-center gap-1"><input type="checkbox" id="bk-pl-empty"> <?= e(t('على الخانات الفاضية بس')) ?></label>
                  </div>
                </div>
                <div class="rounded-xl bg-white border border-ink-200 p-3">
                  <div class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('وزّع المواعيد')) ?></div>
                  <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span><?= e(t('ابدأ')) ?></span><input type="datetime-local" id="bk-sp-start" class="input !h-8 !py-0 text-xs !w-auto">
                    <span><?= e(t('وكل')) ?></span><input type="number" id="bk-sp-n" min="1" max="720" value="1" class="input !h-8 !py-0 text-xs !w-16">
                    <select id="bk-sp-u" class="input !h-8 !py-0 text-xs !w-auto"><option value="d"><?= e(t('يوم')) ?></option><option value="h"><?= e(t('ساعة')) ?></option></select>
                  </div>
                  <div class="flex flex-wrap items-center gap-2 mt-2">
                    <button type="button" id="bk-sp-apply" class="btn-primary !py-1 text-xs"><?= e(t('طبّق')) ?></button>
                    <label class="text-xs text-ink-600 flex items-center gap-1"><input type="checkbox" id="bk-sp-empty"> <?= e(t('على الخانات الفاضية بس')) ?></label>
                  </div>
                </div>
              </div>
            </details>

            <div id="bk-days" class="hidden"></div>

            <div class="flex flex-wrap items-center gap-2">
              <div id="bk-filter" class="flex flex-wrap gap-1.5" role="group">
                <button type="button" class="chip on text-xs" data-f=""><?= e(t('الكل')) ?></button>
                <button type="button" class="chip text-xs" data-f="bad"><?= e(t('فيها مشكلة')) ?></button>
                <button type="button" class="chip text-xs hidden" data-f="srv"><?= e(t('رفضها Zernio')) ?></button>
              </div>
              <input id="bk-q" type="search" class="input !h-8 text-xs w-full sm:w-48 ms-auto" placeholder="<?= e(t('دوّر في النصوص…')) ?>">
            </div>
            <div class="overflow-auto max-h-[30rem] rounded-xl border border-ink-200" dir="ltr"><table class="w-full text-xs" id="bk-grid"></table></div>
            <p class="text-[11px] text-ink-500"><?= e(t('دوس على أي خانة عشان تعدّلها. التعديلات بتتبعت مع الملف.')) ?></p>
          </section>

          <!-- 3) الرفع -->
          <section id="bk-send" class="card p-5 space-y-3" style="display:none">
            <div class="grid sm:grid-cols-2 gap-2">
              <label class="cursor-pointer"><input type="radio" name="mode" value="check" checked class="peer sr-only">
                <span class="block h-full rounded-xl border-2 border-ink-200 p-3 peer-checked:border-brand-600 peer-checked:bg-brand-50/50 peer-focus-visible:ring-2 ring-brand-300">
                  <span class="font-semibold text-ink-900">🔍 <?= e(t('افحص بس')) ?></span>
                  <span class="block text-xs text-ink-500 mt-0.5"><?= e(t('مش هيتعمل أي منشور. Zernio هيقولك كل سطر سليم ولا لأ.')) ?></span></span></label>
              <label class="cursor-pointer"><input type="radio" name="mode" value="create" class="peer sr-only">
                <span class="block h-full rounded-xl border-2 border-ink-200 p-3 peer-checked:border-brand-600 peer-checked:bg-brand-50/50 peer-focus-visible:ring-2 ring-brand-300">
                  <span class="font-semibold text-ink-900">🚀 <?= e(t('اعمل المنشورات')) ?></span>
                  <span class="block text-xs text-ink-500 mt-0.5"><?= e(t('كل سطر سليم هيتجدول أو يتنشر. السطور اللي اتعملت بتتشال من الجدول عشان ماتتكررش.')) ?></span></span></label>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <button class="btn-primary" id="bk-go"><?= e(t('ابدأ')) ?></button>
              <span id="bk-go-hint" class="text-xs text-ink-500"></span>
            </div>
            <div id="bk-prog" class="hidden">
              <div class="flex justify-between text-xs text-ink-600 mb-1"><span id="bk-prog-t"></span><span id="bk-prog-p"></span></div>
              <div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div id="bk-prog-bar" class="h-full bg-gradient-to-l from-brand-600 to-cyan-500 transition-all" style="width:0"></div></div>
            </div>
          </section>
        </form>

        <div id="bk-res" aria-live="polite"></div>

        <?php if ($result): $d = $result['data'];
            // نتيجة الرفع من غير جافاسكربت
            $groups = [];
            foreach ($d['results'] ?? [] as $row) {
                if (!empty($row['ok'])) continue;
                foreach ((array)($row['errors'] ?? []) as $er) { $groups[(string)$er][] = (int)($row['rowIndex'] ?? 0); }
            }
            uasort($groups, function ($a, $b) { return count($b) - count($a); });
            ?>
          <div class="card p-5 space-y-3">
            <h2 class="font-bold"><?= e($result['dry'] ? t('نتيجة الفحص') : t('نتيجة الرفع')) ?> · <span class="text-ink-500 text-sm" dir="ltr"><?= e($result['file']) ?></span></h2>
            <p class="text-sm"><?= e(t('إجمالي %s سطر: %s سليم و %s فيه مشكلة.', (int)($d['total'] ?? 0), (int)($d['valid'] ?? 0), (int)($d['invalid'] ?? 0))) ?></p>
            <?php foreach ($groups as $code => $ix): [$msg, $fix] = $bkErr($code); ?>
              <div class="rounded-xl border border-red-200 bg-red-50/40 p-3"><div class="font-medium text-red-800"><?= e($msg) ?> <span class="text-xs text-red-600">(<?= e(t('%s سطر', count(array_unique($ix)))) ?>)</span></div><?php if ($fix): ?><div class="text-sm text-ink-700 mt-1">✅ <?= e($fix) ?></div><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside class="space-y-4 h-fit lg:sticky lg:top-20">
        <?php if ($profs): ?>
          <div class="card p-4 space-y-2">
            <div class="text-sm font-semibold text-ink-900"><?= e(t('المنصات المربوطة')) ?></div>
            <div class="flex flex-wrap gap-1.5">
              <?php foreach ($allPl as $pl): ?><button type="button" class="bk-copy inline-flex items-center gap-1 font-mono text-xs rounded-lg bg-white border border-ink-200 px-2 py-1 hover:border-brand-400" data-v="<?= e($pl) ?>"><?= $plIcon($pl) ?><span><?= e($pl) ?></span></button><?php endforeach; ?>
              <?php if (!$allPl): ?><span class="text-xs text-amber-700"><?= e(t('مفيش حسابات مربوطة')) ?></span><?php endif; ?>
            </div>
            <div class="text-[11px] text-ink-400"><?= e(t('دوس على أي منصة عشان تنسخها. لو أكتر من منصة افصل بـ | (مثلاً facebook|instagram).')) ?></div>
          </div>
        <?php endif; ?>
        <div class="card p-4 text-sm space-y-2">
          <div class="font-semibold text-ink-900"><?= e(t('هنصلح لوحدنا')) ?></div>
          <ul class="text-xs text-ink-600 space-y-1.5 list-disc ps-4">
            <li><?= e(t('أسماء الأعمدة بالعربي أو الإنجليزي')) ?></li>
            <li><?= e(t('المواعيد زي 1/12/2026 10 م أو تاريخ Excel')) ?></li>
            <li><?= e(t('المنصات زي فيسبوك، انستجرام، FB، IG')) ?></li>
            <li><?= e(t('الملفات الكبيرة بتترفع على دفعات 500 سطر')) ?></li>
            <li><?= e(t('عمود profiles بيتجاهل، والبروفايل بيتحط لوحده')) ?></li>
          </ul>
        </div>
        <details class="card p-4">
          <summary class="cursor-pointer font-semibold text-ink-900 text-sm"><?= e(t('دليل الأعمدة')) ?></summary>
          <ul class="space-y-2 text-sm mt-3">
            <?php foreach ($cols as [$c, $desc, $req]): ?>
              <li><code class="badge" dir="ltr"><?= e($c) ?></code> <?php if ($req): ?><span class="text-xs text-red-600"><?= e(t('مطلوب')) ?></span><?php endif; ?>
                <span class="block text-ink-600 text-xs mt-0.5"><?= e($desc) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <p class="text-xs text-ink-500 mt-2"><?= e(t('لازم يكون فيه ميعاد لكل سطر: schedule_time أو use_queue أو publish_now أو is_draft. الحد الأقصى 5000 سطر.')) ?></p>
        </details>
      </aside>
    </div>
    <script>window.BKX = <?= json_encode([
        'tz' => site_timezone(), 'url' => url('bulk'), 'csrf' => csrf_token(), 'cal' => url('calendar'), 'edit' => url('post_edit'),
        'platforms' => array_keys(platform_icons()), 'connected' => $allPl, 'icons' => array_map(function ($i) { return $i; }, $icons),
        'rtl' => is_rtl(), 'lang' => lang(),
        's' => [
            'rows' => t('%s سطر'), 'ok' => t('%s سليم'), 'bad' => t('%s فيه مشكلة'), 'need' => t('عمود ناقص: %s'), 'req' => t('فاضي'), 'date' => t('الميعاد لازم يكون بالشكل 2026-12-01 10:00'),
            'past' => t('الميعاد ده عدّى'), 'plat' => t('منصة مش معروفة: %s'), 'x' => t('أطول من 280 حرف (X)'), 'url' => t('الرابط لازم يبدأ بـ http'), 'when' => t('محتاج ميعاد أو use_queue أو publish_now أو is_draft'),
            'noacc' => t('مفيش حساب %s مربوط'), 'copied' => t('اتنسخ'), 'max' => t('أكتر من 5000 سطر'), 'pick' => t('اختار ملف أو اكتب سطر واحد على الأقل.'), 'del' => t('شيل'), 'edited' => t('(متعدّل)'),
            'mapped' => t('فهمنا الأعمدة: %s'), 'unknown' => t('أعمدة مش معروفة — اختار هي إيه أو سيبها تتجاهل:'), 'ignore' => t('تجاهل'), 'merged' => t('دمجنا عمودين التاريخ والوقت في ميعاد واحد'),
            'fixdates' => t('صلّحنا %s ميعاد لصيغة 2026-12-01 10:00'), 'fixpl' => t('صلّحنا أسماء المنصات في %s سطر'), 'notime' => t('%s ميعاد كان من غير ساعة، حطينا 10:00'),
            'dropped' => t('عمود profiles اتشال، البروفايل بيتحط لوحده'), 'days' => t('توزيع المنشورات على الأيام'), 'queue' => t('%s في الطابور'), 'now' => t('%s فوري'), 'draft' => t('%s مسودة'),
            'plpick' => t('اختار منصة واحدة على الأقل'), 'spstart' => t('اختار ميعاد البداية'), 'applied' => t('اتطبّق على %s سطر'),
            'goCheck' => t('هنفحص %s سطر على %s دفعة'), 'goCreate' => t('هيتعمل منشور لكل سطر سليم (%s سطر على %s دفعة)'), 'confirm' => t('هيتعمل منشور لكل سطر سليم من %s سطر، و%s منهم هيتنشر فوراً. تكمل؟'),
            'batch' => t('دفعة %s من %s'), 'done' => t('خلصنا'), 'stopped' => t('وقفنا عند الدفعة %s: %s'), 'res' => t('نتيجة الفحص'), 'resC' => t('نتيجة الرفع'),
            'tot' => t('السطور'), 'okL' => t('سليم'), 'badL' => t('فيه مشكلة'), 'probs' => t('المشاكل وإزاي تصلحها'), 'nrows' => t('%s سطر'), 'show' => t('اعرضهم في الجدول'),
            'allok' => t('كل السطور سليمة 🎉 اختار "اعمل المنشورات" ودوس ابدأ.'), 'created' => t('اتعمل %s منشور واتشالوا من الجدول.'), 'opencal' => t('افتح التقويم'),
            'srv' => t('Zernio: %s'), 'left' => t('فاضل %s سطر محتاجين تصليح'), 'reset' => t('هتمسح الجدول وتبدأ من الأول. متأكد؟'), 'empty' => t('مفيش سطور'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-bulk.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
