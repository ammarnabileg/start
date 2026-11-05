<?php
$err = null;
$z = zernio_or_null($err);

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
        $r = pub_bulk_upload($z, $f['tmp_name'], $dry);
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

render(t('رفع منشورات بالجملة'), 'bulk', function () use ($z, $result) {
    if (!$z && !getenv('UI_FIXTURES')) { no_api_box(); return; }
    $cols = [
        ['post_content', t('نص المنشور.'), true],
        ['platforms', t('المنصات، مفصولة بـ | لو أكتر من واحدة (مثلاً facebook|instagram).'), true],
        ['profiles', t('اسم أو رقم الـ Profile في التكامل.'), true],
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
        <script>window.BKX = <?= json_encode(['tz' => site_timezone(), 'platforms' => array_keys(platform_icons()), 's' => [
          'rows' => t('%s سطر'), 'ok' => t('%s سليم'), 'bad' => t('%s فيه مشكلة'), 'need' => t('عمود ناقص: %s'), 'req' => t('فاضي'), 'date' => t('الميعاد لازم يكون بالشكل 2026-12-01 10:00'),
          'past' => t('الميعاد ده عدّى'), 'plat' => t('منصة مش معروفة: %s'), 'x' => t('أطول من 280 حرف (X)'), 'url' => t('الرابط لازم يبدأ بـ http'), 'when' => t('محتاج ميعاد أو use_queue أو publish_now أو is_draft'),
          'max' => t('أكتر من 5000 سطر'), 'pick' => t('اختار ملف أو اكتب سطر واحد على الأقل.'), 'del' => t('شيل'), 'edited' => t('(متعدّل)'),
        ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
        <script src="assets/ui-bulk.js?v=<?= e(APP_VERSION) ?>"></script>

        <?php if ($result): $d = $result['data']; ?>
          <div class="card p-5">
            <h2 class="font-bold mb-2"><?= e($result['dry'] ? t('نتيجة الفحص') : t('نتيجة الرفع')) ?> · <span class="text-ink-500 text-sm" dir="ltr"><?= e($result['file']) ?></span></h2>
            <p class="text-sm mb-3"><?= e(t('إجمالي %s سطر: %s سليم و %s فيه مشكلة.', (int)($d['total'] ?? 0), (int)($d['valid'] ?? 0), (int)($d['invalid'] ?? 0))) ?></p>
            <?php foreach ($d['warnings'] ?? [] as $w): ?><p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2 mb-2" dir="ltr"><?= e($w) ?></p><?php endforeach; ?>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead><tr class="text-ink-500 text-start"><th class="py-2 text-start"><?= e(t('السطر')) ?></th><th class="text-start"><?= e(t('الحالة')) ?></th><th class="text-start"><?= e(t('التفاصيل')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($d['results'] ?? [] as $row): ?>
                  <tr class="border-t border-ink-100">
                    <td class="py-2"><?= (int)($row['rowIndex'] ?? 0) ?></td>
                    <td><?= !empty($row['ok']) ? '<span class="badge badge-green">' . e(t('سليم')) . '</span>' : '<span class="badge bg-red-100 text-red-700">' . e(t('فيه مشكلة')) . '</span>' ?></td>
                    <td class="text-xs" dir="ltr">
                      <?php if (!empty($row['createdPostId'])): ?><a class="link" href="<?= e(url('post_edit', ['id' => $row['createdPostId']])) ?>"><?= e(t('افتح المنشور')) ?></a><?php endif; ?>
                      <?= e(implode(', ', $row['errors'] ?? [])) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="card p-5 space-y-3 h-fit">
        <div class="flex items-center justify-between">
          <h2 class="font-bold"><?= e(t('الأعمدة')) ?></h2>
          <a class="btn-ghost text-sm" href="assets/bulk-sample.csv" download><?= e(t('نزّل ملف مثال')) ?></a>
        </div>
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
