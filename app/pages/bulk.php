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
    ?>
    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <form method="post" enctype="multipart/form-data" class="card p-5 space-y-4" id="bk-form">
          <?= csrf_field() ?>
          <p class="text-sm text-ink-700"><?= e(t('جهّز ملف Excel أو Google Sheets بالأعمدة اللي تحت، احفظه بصيغة CSV، وارفعه هنا. كل سطر = منشور.')) ?></p>
          <label id="bk-drop" class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-ink-200 hover:border-brand-400 p-6 text-center cursor-pointer transition">
            <span class="w-11 h-11 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-xl">⇪</span>
            <span class="font-medium text-ink-900" id="bk-fn"><?= e(t('اسحب ملف CSV هنا أو دوس عشان تختاره')) ?></span>
            <span class="text-xs text-ink-500"><?= e(t('هتشوف الملف في جدول قبل ما يتبعت، وتقدر تصلح أي خانة')) ?></span>
            <input type="file" name="csv" id="bk-file" accept=".csv,text/csv" class="sr-only">
          </label>
          <div class="flex items-center gap-2 text-xs text-ink-500"><span class="flex-1 h-px bg-ink-100"></span><?= e(t('أو')) ?><span class="flex-1 h-px bg-ink-100"></span></div>
          <button type="button" id="bk-new" class="btn-ghost w-full"><?= e(t('اكتب المنشورات هنا في جدول')) ?></button>
          <div id="bk-grid-wrap" style="display:none">
            <div class="flex flex-wrap items-center gap-2 mb-2">
              <span id="bk-sum" class="text-sm"></span>
              <label class="ms-auto text-xs flex items-center gap-1.5"><input type="checkbox" id="bk-only"> <?= e(t('السطور اللي فيها مشكلة بس')) ?></label>
              <button type="button" id="bk-add" class="btn-ghost !py-1 text-xs"><?= e(t('+ سطر')) ?></button>
              <button type="button" id="bk-dl" class="btn-ghost !py-1 text-xs"><?= e(t('نزّل CSV')) ?></button>
            </div>
            <div class="overflow-auto max-h-[28rem] rounded-xl border border-ink-200" dir="ltr"><table class="w-full text-xs" id="bk-grid"></table></div>
            <p class="text-[11px] text-ink-500 mt-1"><?= e(t('دوس على أي خانة عشان تعدّلها. التعديلات بتتبعت مع الملف.')) ?></p>
          </div>
          <div class="space-y-2 text-sm">
            <label class="flex items-start gap-2"><input type="radio" name="mode" value="check" checked class="mt-1"> <span><b><?= e(t('افحص الملف بس')) ?></b> — <?= e(t('مش هيتعمل أي منشور، بس هنقولك فيه أخطاء ولا لأ.')) ?></span></label>
            <label class="flex items-start gap-2"><input type="radio" name="mode" value="create" class="mt-1"> <span><b><?= e(t('اعمل المنشورات فعلاً')) ?></b> — <?= e(t('كل سطر سليم هيتجدول أو يتنشر حسب الملف.')) ?></span></label>
          </div>
          <button class="btn-primary" id="bk-go" onclick="var m=this.form.querySelector('input[name=mode]:checked');return !m||m.value!=='create'||confirm('<?= tjs('هيتعمل منشور لكل سطر سليم في الملف، والمنشورات اللي عليها publish_now هتتنشر فوراً. تكمل؟') ?>')"><?= e(t('ارفع')) ?></button>
        </form>
        <script>window.BKX = <?= json_encode(['tz' => site_timezone(), 'platforms' => array_keys(platform_icons()), 'connected' => array_values(array_unique(array_merge([], ...array_map(function ($p) { return $p['platforms']; }, $profs)))), 's' => [
          'rows' => t('%s سطر'), 'ok' => t('%s سليم'), 'bad' => t('%s فيه مشكلة'), 'need' => t('عمود ناقص: %s'), 'req' => t('فاضي'), 'date' => t('الميعاد لازم يكون بالشكل 2026-12-01 10:00'),
          'past' => t('الميعاد ده عدّى'), 'plat' => t('منصة مش معروفة: %s'), 'x' => t('أطول من 280 حرف (X)'), 'url' => t('الرابط لازم يبدأ بـ http'), 'when' => t('محتاج ميعاد أو use_queue أو publish_now أو is_draft'),
          'noacc' => t('مفيش حساب %s مربوط'),
          'big' => t('أكتر من 500 سطر — Zernio بينصح تقسّم الملف'), 'copied' => t('اتنسخ'),
          'max' => t('أكتر من 5000 سطر'), 'pick' => t('اختار ملف أو اكتب سطر واحد على الأقل.'), 'del' => t('شيل'), 'edited' => t('(متعدّل)'),
        ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
        <script src="assets/ui-bulk.js?v=<?= e(APP_VERSION) ?>"></script>

        <?php if ($result): $d = $result['data'];
            // نجمّع نفس المشكلة: "المشكلة دي في 950 سطر" بدل 950 سطر متكررين
            $groups = [];
            foreach ($d['results'] ?? [] as $row) {
                if (!empty($row['ok'])) continue;
                foreach ((array)($row['errors'] ?? []) as $er) { $k = (string)$er; $groups[$k][] = (int)($row['rowIndex'] ?? 0); }
            }
            uasort($groups, function ($a, $b) { return count($b) - count($a); });
            $bad = array_values(array_filter($d['results'] ?? [], function ($r) { return empty($r['ok']); }));
            $good = array_values(array_filter($d['results'] ?? [], function ($r) { return !empty($r['ok']); }));
            $sep = is_rtl() ? '، ' : ', '; $rowsTxt = function (array $ix) use ($sep) { $ix = array_values(array_unique($ix)); return count($ix) > 12 ? implode($sep, array_slice($ix, 0, 10)) . ' … +' . (count($ix) - 10) : implode($sep, $ix); };
            ?>
          <div class="card p-5 space-y-4" id="bk-result">
            <div>
              <h2 class="font-bold"><?= e($result['dry'] ? t('نتيجة الفحص') : t('نتيجة الرفع')) ?> · <span class="text-ink-500 text-sm" dir="ltr"><?= e($result['file']) ?></span></h2>
              <div class="grid grid-cols-3 gap-2 mt-3">
                <div class="rounded-xl border border-ink-200 p-3"><div class="text-xs text-ink-500"><?= e(t('السطور')) ?></div><div class="text-xl font-bold text-ink-900"><?= (int)($d['total'] ?? 0) ?></div></div>
                <div class="rounded-xl border border-lime-200 bg-lime-50/60 p-3"><div class="text-xs text-ink-500"><?= e(t('سليم')) ?></div><div class="text-xl font-bold text-lime-700"><?= (int)($d['valid'] ?? 0) ?></div></div>
                <div class="rounded-xl border <?= ($d['invalid'] ?? 0) ? 'border-red-200 bg-red-50/60' : 'border-ink-200' ?> p-3"><div class="text-xs text-ink-500"><?= e(t('فيه مشكلة')) ?></div><div class="text-xl font-bold <?= ($d['invalid'] ?? 0) ? 'text-red-600' : 'text-ink-900' ?>"><?= (int)($d['invalid'] ?? 0) ?></div></div>
              </div>
            </div>
            <?php foreach ($d['warnings'] ?? [] as $w): [$msg, $fix] = $bkErr((string)$w); ?>
              <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm"><div class="font-medium text-amber-900">⚠️ <?= e($msg) ?></div><?php if ($fix): ?><div class="text-amber-800 text-xs mt-0.5"><?= e($fix) ?></div><?php endif; ?></div>
            <?php endforeach; ?>
            <?php if ($groups): ?>
              <div>
                <h3 class="font-semibold text-ink-900 mb-2"><?= e(t('المشاكل وإزاي تصلحها')) ?></h3>
                <div class="space-y-2">
                  <?php foreach ($groups as $code => $ix): [$msg, $fix] = $bkErr($code); ?>
                    <div class="rounded-xl border border-red-200 bg-red-50/40 p-3">
                      <div class="flex flex-wrap items-center gap-2"><span class="font-medium text-red-800"><?= e($msg) ?></span><span class="ms-auto text-xs rounded-full bg-red-100 text-red-700 px-2 py-0.5"><?= e(t('%s سطر', count(array_unique($ix)))) ?></span></div>
                      <?php if ($fix): ?><div class="text-sm text-ink-700 mt-1">✅ <?= e($fix) ?></div><?php endif; ?>
                      <div class="text-[11px] text-ink-500 mt-1"><?= e(t('السطور: %s', $rowsTxt($ix))) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($good && !$result['dry']): ?>
              <details><summary class="cursor-pointer text-sm font-medium text-lime-700"><?= e(t('المنشورات اللي اتعملت (%s)', count($good))) ?></summary>
                <div class="flex flex-wrap gap-1.5 mt-2"><?php foreach (array_slice($good, 0, 200) as $row): if (empty($row['createdPostId'])) continue; ?><a class="chip text-xs" href="<?= e(url('post_edit', ['id' => $row['createdPostId']])) ?>"><?= e(t('سطر %s', (int)($row['rowIndex'] ?? 0))) ?></a><?php endforeach; ?></div>
              </details>
            <?php endif; ?>
            <?php if ($bad): ?>
              <details><summary class="cursor-pointer text-sm text-ink-600"><?= e(t('كل السطور اللي فيها مشكلة بالتفصيل')) ?></summary>
                <div class="overflow-x-auto max-h-80 mt-2">
                  <table class="w-full text-sm">
                    <thead><tr class="text-ink-500"><th class="py-2 text-start"><?= e(t('السطر')) ?></th><th class="text-start"><?= e(t('المشكلة')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($bad, 0, 300) as $row): ?>
                      <tr class="border-t border-ink-100"><td class="py-1.5 w-16"><?= (int)($row['rowIndex'] ?? 0) ?></td>
                        <td class="text-xs"><?= e(implode(' · ', array_map(function ($x) use ($bkErr) { return $bkErr((string)$x)[0]; }, (array)($row['errors'] ?? [])))) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                  <?php if (count($bad) > 300): ?><p class="text-xs text-ink-500 mt-2"><?= e(t('بنعرض أول 300 بس.')) ?></p><?php endif; ?>
                </div>
              </details>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card p-5 space-y-3 h-fit">
        <div class="flex items-center justify-between">
          <h2 class="font-bold"><?= e(t('الأعمدة')) ?></h2>
        </div>
        <a class="btn-primary w-full justify-center" download="bulk-template.csv" href="<?= e(url('bulk', ['sample' => 1])) ?>">⇣ <?= e(t('نزّل الملف الجاهز')) ?></a>
        <p class="text-xs text-ink-500 -mt-1"><?= e(t('ملف فيه المنصات المربوطة عندك ومواعيد جاية. عدّل النصوص والمواعيد بس.')) ?></p>
        <?php $allPl = []; foreach ($profs as $p) foreach ($p['platforms'] as $pl) $allPl[$pl] = 1; ?>
        <?php if ($profs): ?>
          <div class="rounded-xl bg-ink-50 border border-ink-200 p-3 space-y-2">
            <div class="text-xs font-semibold text-ink-700"><?= e(t('المنصات المربوطة (اكتبها في عمود platforms)')) ?></div>
            <div class="flex flex-wrap gap-1">
              <?php foreach (array_keys($allPl) as $pl): ?><button type="button" class="bk-copy font-mono text-xs rounded bg-white border border-ink-200 px-1.5 py-0.5 hover:border-brand-400" data-v="<?= e($pl) ?>"><?= e($pl) ?></button><?php endforeach; ?>
              <?php if (!$allPl): ?><span class="text-xs text-amber-700"><?= e(t('مفيش حسابات مربوطة')) ?></span><?php endif; ?>
            </div>
            <div class="text-[11px] text-ink-400"><?= e(t('دوس على أي منصة عشان تنسخها. لو أكتر من منصة افصل بـ | (مثلاً facebook|instagram).')) ?></div>
          </div>
        <?php endif; ?>
        <ul class="space-y-2 text-sm">
          <?php foreach ($cols as [$c, $desc, $req]): ?>
            <li><code class="badge" dir="ltr"><?= e($c) ?></code> <?php if ($req): ?><span class="text-xs text-red-600"><?= e(t('مطلوب')) ?></span><?php endif; ?>
              <span class="block text-ink-600 text-xs mt-0.5"><?= e($desc) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <p class="text-xs text-ink-500"><?= e(t('لازم يكون فيه ميعاد لكل سطر: schedule_time أو use_queue أو publish_now أو is_draft. الحد الأقصى 5000 سطر.')) ?></p>
      </div>
    </div>
    <?php
});
