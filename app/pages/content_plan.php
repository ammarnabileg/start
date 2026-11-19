<?php
/* خطة المحتوى بالذكاء الاصطناعي — الخطوة 1: معالج (هدف، عدد، صور مرجعية) ← توليد على مراحل محفوظة
   الخطوة 2: مراجعة واعتماد ← المعتمد يروح «مخزون المحتوى» */
$wid = (int)ws('id');
$J = function (array $d, int $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); exit(json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); };
$pid = (int)($_GET['plan'] ?? $_POST['plan'] ?? 0);

if (isset($_GET['export']) && ($p = cpl_plan($pid))) {
    $items = array_values(array_filter(cpl_items($pid), function ($i) { return $i['linkedin'] !== null && $i['status'] !== 'rejected'; }));
    $fn = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', (string)$p['name']) ?: 'content-plan';
    if ($_GET['export'] === 'xlsx') {
        $all = array_values(array_filter(cpl_items($pid), function ($i) { return $i['linkedin'] !== null; })); // كل الحالات: تقدر تغيّر الحالة من الملف
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="' . rawurlencode($fn) . '.xlsx"');
        exit(cpl_export_xlsx($p, $all));
    }
    if ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . rawurlencode($fn) . '.csv"');
        $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['#', 'topic', 'angle', 'category', 'status', 'linkedin', 'x', 'headline', 'image_prompt', 'video_prompt', 'carousel'], ',', '"', '');
        foreach ($items as $i) fputcsv($o, [$i['seq'], $i['topic']['title'] ?? '', $i['topic']['angle'] ?? '', $i['topic']['category'] ?? '', $i['status'], $i['linkedin'], $i['x'], $i['headline'], $i['image_prompt'], $i['video_prompt'],
            implode("\n", array_map(function ($s) { return $s['role'] . ': ' . $s['title'] . ' — ' . $s['body']; }, $i['carousel']))], ',', '"', '');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8'); header('Content-Disposition: attachment; filename="' . rawurlencode($fn) . '.html"');
    exit(cpl_export_html($p, $items));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    try {
        if ($a === 'ref') {
            $out = []; $f = $_FILES['files'] ?? null;
            if ($f && is_array($f['name'])) foreach ($f['name'] as $i => $n) {
                if ($i >= 12) break;
                $row = media_store_upload($wid, ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]], 'References');
                if (media_kind((string)$row['mime']) !== 'image') continue;
                $out[] = ['id' => (int)$row['id'], 'thumb' => url('media', ['file' => $row['id'], 'thumb' => 1])];
            }
            $J(['ok' => true, 'items' => $out]);
        }
        if ($a === 'create') { $id = cpl_create(json_decode((string)($_POST['data'] ?? '{}'), true) ?: []); $J(['ok' => true, 'id' => $id, 'url' => url('content_plan', ['plan' => $id])]); }
        $p = cpl_plan($pid); if (!$p) $J(['ok' => false, 'error' => t('الخطة غير موجودة.')], 404);
        if ($a === 'step') { session_write_close(); ignore_user_abort(true); /* النت فصل وسط الخطوة: الدفعة تكمل وتتحفظ */ $J(['ok' => true] + cpl_step($pid)); }
        if ($a === 'status') { session_write_close(); $J(['ok' => true] + cpl_status($p)); }
        if ($a === 'resume') { db()->prepare('UPDATE cp_plans SET status = "generating", fails = 0, error = NULL WHERE id = ? AND status IN ("paused","generating")')->execute([$pid]); $J(['ok' => true]); }
        if ($a === 'import') { if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) $J(['ok' => false, 'error' => t('الرفع فشل، جرّب تاني.')]); [$u, $s] = cpl_import_xlsx($pid, $_FILES['file']['tmp_name']); $J(['ok' => true, 'updated' => $u, 'skipped' => $s]); }
        if ($a === 'items') $J(['ok' => true, 'items' => cpl_items($pid)]);
        if ($a === 'set') { $ids = array_map('intval', (array)json_decode((string)($_POST['ids'] ?? '[]'), true)); $J(['ok' => true, 'n' => cpl_set_status($ids, (string)($_POST['status'] ?? ''))]); }
        if ($a === 'save') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 404); cpl_save_item($it['id'], (array)json_decode((string)($_POST['f'] ?? '{}'), true)); $J(['ok' => true, 'item' => cpl_item($it['id'])]); }
        if ($a === 'regen') {
            $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid || !in_array($it['status'], ['draft', 'rejected', 'approved'], true)) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 404);
            db()->prepare('UPDATE cp_items SET linkedin = NULL, status = "pending" WHERE id = ?')->execute([$it['id']]);
            db()->prepare('UPDATE cp_plans SET status = "generating", stage = "posts", fails = 0, error = NULL WHERE id = ?')->execute([$pid]);
            $J(['ok' => true]);
        }
        if ($a === 'delete') { db()->prepare('DELETE FROM cp_items WHERE plan_id = ? AND status IN ("pending","draft","rejected","approved")')->execute([$pid]); $st = db()->prepare('SELECT COUNT(*) FROM cp_items WHERE plan_id = ?'); $st->execute([$pid]); if (!(int)$st->fetchColumn()) db()->prepare('DELETE FROM cp_plans WHERE id = ?')->execute([$pid]); else db()->prepare('UPDATE cp_plans SET status = "done" WHERE id = ?')->execute([$pid]); $J(['ok' => true]); }
    } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
    $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
}

$plan = $pid ? cpl_plan($pid) : null;
render($plan ? (string)$plan['name'] : t('خطة محتوى بالذكاء الاصطناعي'), 'content_plan', function () use ($plan, $wid) {
    $T = [
        'stVisual' => t('تحليل الصور المرجعية…'), 'stOutline' => t('تخطيط المواضيع…'), 'stPosts' => t('كتابة المنشورات: %s من %s'), 'stHuman' => t('مراجعة الأسلوب: %s من %s'), 'human' => t('روجع بالـ Humanizer'), 'stReview' => t('جاهزة للمراجعة'), 'stPaused' => t('توقف التوليد بعد أكثر من محاولة فاشلة.'),
        'resume' => t('أكمل من حيث توقف'), 'offline' => t('انقطع الاتصال. سنكمل تلقائيًا عند عودته، وكل ما تم محفوظ.'), 'bg' => t('يمكنك إغلاق الصفحة؛ التوليد يكمل في الخلفية وتجد كل شيء محفوظًا هنا.'),
        'approve' => t('اعتمده'), 'reject' => t('رفض'), 'undo' => t('إرجاع لمسودة'), 'approveSel' => t('اعتمد المحدد (%s)'), 'rejectSel' => t('ارفض المحدد'), 'all' => t('تحديد الكل'), 'regen' => t('أعد كتابته'),
        'copy' => t('نسخ'), 'copied' => t('تم النسخ'), 'edit' => t('تعديل'), 'save' => t('حفظ'), 'cancel' => t('إلغاء'), 'saved' => t('تم الحفظ'), 'err' => t('حصلت مشكلة. حاول مرة أخرى.'),
        'li' => 'LinkedIn', 'x' => 'X', 'img' => t('برومبت الصورة'), 'vid' => t('برومبت الفيديو'), 'car' => t('كاروسيل (5 شرائح)'), 'head' => t('عنوان الصورة'), 'words' => t('%s كلمة'), 'chars' => t('%s حرف'),
        's_pending' => t('قيد الكتابة'), 's_draft' => t('مسودة'), 's_approved' => t('معتمد'), 's_rejected' => t('مرفوض'), 's_scheduled' => t('مجدول'), 's_queued' => t('في الطابور'), 's_published' => t('تم النشر'),
        'toBank' => t('افتح مخزون المحتوى'), 'nApproved' => t('%s معتمد في المخزون'), 'f_all' => t('الكل'), 'delPlan' => t('حذف الخطة؟ المنشورات المجدولة أو المنشورة تبقى في المخزون.'),
        'uploading' => t('جارٍ رفع الصور…'), 'imported' => t('تم تحديث %s منشور من الملف (تخطّينا %s).'), 'needGoal' => t('اكتب هدف الحملة.'), 'draftRestored' => t('استرجعنا المسودة التي لم تُرسل.'), 'refMax' => t('حتى 12 صورة.'), 'uploaded' => t('تم رفع الصور.'), 'pdfPages' => t('جارٍ تحويل صفحات PDF إلى صور…'), 'qc' => t('فحص الجودة:'), 'cats' => array_map('t', cpl_categories()),
    ];
    require_once ROOT_DIR . '/app/views/cb_tabbar.php'; echo cb_tabbar('content_plan');
    $stepNow = !$plan ? 1 : (in_array($plan['status'], ['generating', 'paused'], true) ? 1 : 2);
    $steps = [1 => [t('الخطوة الأولى'), t('الهدف وعدد المنشورات والصور المرجعية ← تجهيز الملف والبرومبتات (بدون صور أو فيديو)')], 2 => [t('الخطوة الثانية'), t('وافق على بعضها أو كلها ← المعتمد يذهب إلى مخزون المحتوى')]];
    echo '<ol class="grid sm:grid-cols-2 gap-2 mb-5">'; foreach ($steps as $n => [$h, $d]) echo '<li class="rounded-2xl border p-3 ' . ($n === $stepNow ? 'border-brand-300 bg-brand-50/60' : 'border-ink-200 bg-white') . '"><div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold ' . ($n === $stepNow ? 'bg-brand-600 text-[#fff]' : ($n < $stepNow ? 'bg-lime-500 text-[#fff]' : 'bg-ink-100 text-ink-500')) . '">' . ($n < $stepNow ? '✓' : $n) . '</span><b class="text-sm">' . e($h) . '</b></div><p class="text-xs text-ink-500 mt-1">' . e($d) . '</p></li>'; echo '</ol>';
    $cfg = ['url' => url('content_plan'), 'bank' => url('content_bank'), 'csrf' => csrf_token(), 'wid' => $wid, 't' => $T];
    if ($plan): $cfg['plan'] = cpl_status($plan) + ['name' => (string)$plan['name']]; ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <a href="<?= e(url('content_plan')) ?>" class="btn-ghost !py-1.5">← <?= e(t('كل الخطط')) ?></a>
      <span class="text-sm text-ink-500 min-w-0 flex-1 truncate"><?= e(mb_substr((string)$plan['goal'], 0, 160)) ?></span>
      <a class="btn-primary !py-1.5" href="<?= e(url('content_plan', ['plan' => $plan['id'], 'export' => 'xlsx'])) ?>"><?= e(t('تنزيل Excel')) ?></a>
      <label class="btn-ghost !py-1.5 cursor-pointer" title="<?= e(t('عدّل الملف في Excel ثم ارفعه هنا لتحديث النصوص والحالات.')) ?>"><input id="cp-imp" type="file" accept=".xlsx" class="hidden"><?= e(t('رفع Excel معدّل')) ?></label>
      <a class="btn-ghost !py-1.5" href="<?= e(url('content_plan', ['plan' => $plan['id'], 'export' => 'html'])) ?>"><?= e(t('تنزيل HTML')) ?></a>
      <a class="btn-ghost !py-1.5" href="<?= e(url('content_plan', ['plan' => $plan['id'], 'export' => 'csv'])) ?>">CSV</a>
      <button type="button" id="cp-del" class="btn-ghost !py-1.5 text-red-600"><?= e(t('حذف')) ?></button>
    </div>
    <div id="cp-prog" class="card p-4 mb-4"></div>
    <div id="cp-bar" class="hidden sticky top-2 z-10 card p-2 mb-3 flex flex-wrap items-center gap-2 text-sm">
      <label class="flex items-center gap-1.5 px-2"><input type="checkbox" id="cp-all"><?= e($T['all']) ?></label>
      <select id="cp-f" class="input !mt-0 !py-1 !w-auto text-sm"><option value=""><?= e($T['f_all']) ?></option><?php foreach (['draft', 'approved', 'rejected'] as $s): ?><option value="<?= $s ?>"><?= e($T['s_' . $s]) ?></option><?php endforeach; ?><option value="qc"><?= e(t('يحتاج مراجعة (فحص الجودة)')) ?></option></select>
      <span class="flex-1"></span>
      <button type="button" data-bulk="approved" class="btn-primary !py-1.5" disabled></button>
      <button type="button" data-bulk="rejected" class="btn-ghost !py-1.5" disabled><?= e($T['rejectSel']) ?></button>
      <a href="<?= e(url('content_bank')) ?>" id="cp-tobank" class="btn-ghost !py-1.5 hidden"></a>
    </div>
    <div id="cp-list" class="space-y-3"></div>
    <?php else: $plans = cpl_plans(); $kb = kb_get($wid); $svc = array_values(array_filter(array_map(function ($r) { return trim((string)($r['name'] ?? '')); }, (array)($kb['services']['rows'] ?? []))));
      $hasLogo = function_exists('kb_logo') && kb_logo($wid); ?>
    <p class="text-sm text-ink-500 mb-4"><?= e(t('اكتب هدفك وعدد المنشورات وارفع تصميمات مرجعية. نكتب لك خطة كاملة من ملف شركتك: موضوع، نص لينكدإن، نص X، برومبت صورة، برومبت فيديو، وكاروسيل لكل منشور. لا نولّد صورًا هنا؛ أنت تعتمد ما يعجبك فيذهب إلى مخزون المحتوى للجدولة والنشر.')) ?></p>
    <form id="cp-wiz" class="card p-5 mb-6 space-y-4">
      <div class="grid lg:grid-cols-[2fr_1fr] gap-4">
        <label class="block"><span class="label"><?= e(t('هدف الحملة')) ?> *</span><textarea name="goal" rows="4" class="input" dir="auto" placeholder="<?= e(t('مثال: نريد طلبات عروض أسعار من مديري المشتريات في الشركات الكبيرة لخدمة تجهيز المطابخ التجارية خلال الربع القادم.')) ?>"></textarea></label>
        <div class="space-y-3">
          <label class="block"><span class="label"><?= e(t('عدد المنشورات')) ?></span><input name="count" type="number" min="1" max="<?= CPL_MAX ?>" value="12" class="input"></label>
          <label class="block"><span class="label"><?= e(t('اسم الخطة (اختياري)')) ?></span><input name="name" class="input" dir="auto"></label>
        </div>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="block"><span class="label"><?= e(t('اللغة')) ?></span><select name="lang" class="input"><option value="ar"><?= e(t('عربي')) ?></option><option value="en">English</option><option value="both"><?= e(t('لينكدإن عربي و X إنجليزي')) ?></option></select></label>
        <label class="block"><span class="label"><?= e(t('اللهجة (اختياري)')) ?></span><input name="dialect" class="input" placeholder="<?= e(t('فصحى، سعودية، مصرية…')) ?>"></label>
        <label class="block"><span class="label"><?= e(t('النبرة')) ?></span><input name="tone" class="input" placeholder="<?= e(t('واثقة، عملية، بلا مبالغة')) ?>"></label>
        <label class="block"><span class="label"><?= e(t('الدعوة للإجراء')) ?></span><input name="cta" class="input" placeholder="<?= e(t('اطلب عرض سعر، احجز مكالمة…')) ?>"></label>
      </div>
      <label class="block"><span class="label"><?= e(t('الجمهور المستهدف (اختياري)')) ?></span><input name="audience" class="input" dir="auto" placeholder="<?= e(t('مثال: مديرو المشتريات والمرافق في الفنادق والمستشفيات بالسعودية')) ?>"></label>
      <?php if ($svc): ?><div><span class="label"><?= e(t('ركّز على خدمات (من ملف الشركة)')) ?></span><div class="flex flex-wrap gap-2 mt-1"><?php foreach (array_slice($svc, 0, 30) as $s): ?><label class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-3 py-1 text-sm"><input type="checkbox" name="services" value="<?= e($s) ?>"><?= e($s) ?></label><?php endforeach; ?></div></div><?php endif; ?>
      <label class="block"><span class="label"><?= e(t('تجنّب (اختياري)')) ?></span><input name="avoid" class="input" dir="auto" placeholder="<?= e(t('كلمات أو وعود أو أساليب لا تريدها')) ?>"></label>
      <div>
        <span class="label"><?= e(t('تصميمات مرجعية (اختياري، حتى 12 صورة أو صفحة PDF)')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('نحلل أسلوبها (الألوان، الخطوط، التكوين) ونطبقه في كل برومبت صورة، بدون نسخ محتواها.')) ?> <?= $hasLogo ? e(t('الشعار موجود في ملف الشركة وسيُذكر موضعه في البرومبتات.')) : '' ?></p>
        <div id="cp-refs" class="flex flex-wrap gap-2"></div>
        <label class="btn-ghost !py-1.5 mt-2 cursor-pointer"><input id="cp-ref-in" type="file" accept="image/*,application/pdf,.pdf" multiple class="hidden"><?= e(t('أضف صورًا أو PDF')) ?></label>
      </div>
      <div class="flex flex-wrap gap-4 text-sm">
        <label class="flex items-center gap-1.5"><input type="checkbox" name="video" checked><?= e(t('برومبت فيديو لكل منشور')) ?></label>
        <label class="flex items-center gap-1.5"><input type="checkbox" name="carousel" checked><?= e(t('كاروسيل 5 شرائح')) ?></label>
        <label class="flex items-center gap-1.5" title="<?= e(t('بعد كتابة كل دفعة، يراجعها محرر بمهارة Humanizer كاملة ويزيل أنماط كتابة الذكاء الاصطناعي دون تغيير المعلومات. يضاعف تكلفة الكتابة تقريبًا.')) ?>"><input type="checkbox" name="humanize" checked><?= e(t('مراجعة الأسلوب (Humanizer)')) ?></label>
      </div>
      <div class="flex items-center gap-3"><button class="btn-primary"><?= e(t('اكتب الخطة')) ?></button><span class="text-xs text-ink-500"><?= e(t('المسودة تُحفظ على جهازك أثناء الكتابة.')) ?></span></div>
    </form>
    <?php if ($plans): ?>
    <h2 class="font-bold mb-2"><?= e(t('خططك')) ?></h2>
    <div class="card divide-y divide-ink-100">
      <?php foreach ($plans as $p): $st = ['generating' => [t('جارٍ التوليد'), 'badge-amber'], 'paused' => [t('متوقفة'), 'badge-amber'], 'review' => [t('للمراجعة'), 'badge-green'], 'done' => [t('مكتملة'), '']][$p['status']] ?? [$p['status'], '']; ?>
      <a href="<?= e(url('content_plan', ['plan' => $p['id']])) ?>" class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-ink-50">
        <span class="min-w-0 flex-1"><b class="block truncate text-ink-900"><?= e($p['name']) ?></b><span class="text-xs text-ink-500"><?= e(fmt_date((string)$p['created_at'], 'd/m/Y')) ?></span></span>
        <span class="text-xs text-ink-500"><?= e(t('%s من %s جاهز', (int)$p['ready'], (int)$p['cnt'])) ?> · <?= e(t('%s معتمد', (int)$p['approved'])) ?></span>
        <span class="badge <?= $st[1] ?>"><?= e($st[0]) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; endif; ?>
    <script>window.CP = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <?php if (!$plan): ?><script src="assets/extract.js?v=<?= e(APP_VERSION) ?>"></script><?php endif; ?>
    <script src="assets/content-plan.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
