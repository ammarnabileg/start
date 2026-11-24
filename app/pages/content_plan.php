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
        if ($a === 'rename') { $nm = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150); if ($nm === '') $J(['ok' => false, 'error' => t('اكتب اسمًا للخطة.')], 422); db()->prepare('UPDATE cp_plans SET name = ? WHERE id = ?')->execute([$nm, $pid]); $J(['ok' => true, 'name' => $nm]); }
        if ($a === 'step') { session_write_close(); ignore_user_abort(true); /* النت فصل وسط الخطوة: الدفعة تكمل وتتحفظ */ $J(['ok' => true] + cpl_step($pid)); }
        if ($a === 'status') { session_write_close(); $J(['ok' => true] + cpl_status($p)); }
        if ($a === 'cost') { session_write_close(); $J(['ok' => true, 'cost' => cpl_cost($pid)]); }
        if ($a === 'resume') { cpl_log($pid, t('استئناف يدوي من حيث توقف.')); db()->prepare('UPDATE cp_plans SET status = "generating", fails = 0, error = NULL WHERE id = ? AND status IN ("paused","generating")')->execute([$pid]); $J(['ok' => true]); }
        // المسار الذكي: ولّد نصوص الأفكار المعتمدة (المتبقية) فقط
        if ($a === 'genposts') { $n = (int)db()->query('SELECT COUNT(*) FROM cp_items WHERE plan_id = ' . $pid . ' AND linkedin IS NULL')->fetchColumn(); if (!$n) $J(['ok' => false, 'error' => t('لا توجد أفكار معتمدة. أضف أفكارًا أولًا.')], 422); db()->prepare('UPDATE cp_plans SET status = "generating", stage = "posts", cnt = ?, fails = 0, error = NULL WHERE id = ? AND status = "topics"')->execute([$n, $pid]); cpl_log($pid, t('بدء كتابة النصوص لـ %s فكرة معتمدة…', $n)); $J(['ok' => true] + cpl_status(cpl_plan($pid))); }
        // رفض فكرة (حذف منشور لم تُكتب نصوصه بعد) — يوفّر كريدت النص والصورة
        if ($a === 'deltopic') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid || $it['linkedin'] !== null) $J(['ok' => false, 'error' => t('لا يمكن حذف فكرة كُتبت نصوصها.')], 422); db()->prepare('DELETE FROM cp_items WHERE id = ? AND plan_id = ? AND workspace_id = ?')->execute([$it['id'], $pid, $wid]); $J(['ok' => true] + cpl_status(cpl_plan($pid))); }
        if ($a === 'stop') { db()->prepare('UPDATE cp_plans SET status = "review", stage = "done" WHERE id = ? AND status IN ("generating","paused")')->execute([$pid]); cpl_log($pid, t('أوقفت التوليد يدويًا. المنشورات الجاهزة محفوظة، وتقدر تكمل التوليد في أي وقت.'), 'warn'); $J(['ok' => true] + cpl_status(cpl_plan($pid))); }
        if ($a === 'import') { if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) $J(['ok' => false, 'error' => t('الرفع فشل، جرّب تاني.')]); [$u, $s] = cpl_import_xlsx($pid, $_FILES['file']['tmp_name']); $J(['ok' => true, 'updated' => $u, 'skipped' => $s]); }
        if ($a === 'items') $J(['ok' => true, 'items' => cpl_items($pid), 'jobs' => cpl_jobs_status($pid)]);
        if ($a === 'gen') { $J(['ok' => true, 'n' => cpl_jobs_add($pid, array_map('intval', (array)json_decode((string)($_POST['ids'] ?? '[]'), true)), (string)($_POST['kind'] ?? '')), 'jobs' => cpl_jobs_status($pid)]); }
        if ($a === 'usefmt') { $it = cpl_item((int)($_POST['id'] ?? 0)); $k = (string)($_POST['kind'] ?? ''); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404);
            if ($k === 'text') db()->prepare('UPDATE cp_items SET fmt = NULL, media_ids = NULL, media_id = NULL WHERE id = ?')->execute([$it['id']]); else cpl_jobs_finalize($it['id'], $k);
            $J(['ok' => true]); }
        if ($a === 'jobs') { session_write_close(); ignore_user_abort(true); $GLOBALS['ai_timeout'] = 240; $did = cpl_jobs_tick($pid); $J(['ok' => true, 'did' => $did, 'jobs' => cpl_jobs_status($pid), 'log' => cpl_status(cpl_plan($pid))['log']]); }
        if ($a === 'logoinfo') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); $lg = function_exists('kb_logo') ? kb_logo($wid) : null;
            $raw = $it['media_raw'] ?: $it['media_ids']; $o = $it['logo'] ?: cpl_opts(cpl_plan($pid));
            $J(['ok' => true, 'images' => array_map(function ($m) { return url('media', ['file' => $m]); }, $raw), 'logo' => $lg ? url('media', ['file' => $lg['id']]) : null,
                'pos' => ['logo_x' => (int)($o['logo_x'] ?? 86), 'logo_y' => (int)($o['logo_y'] ?? 9), 'logo_w' => (int)($o['logo_w'] ?? 18), 'logo_op' => (int)($o['logo_op'] ?? 100), 'logo_tint' => $o['logo_tint'] ?? 'orig', 'logo_plate' => $o['logo_plate'] ?? 'none']]); }
        if ($a === 'logo') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); session_write_close(); $r = cpl_restamp_item($it['id'], (array)json_decode((string)($_POST['logo'] ?? '{}'), true)); $J(['ok' => true, 'item' => $r]); }
        if ($a === 'retxt') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); session_write_close(); $r = cpl_restamp_text($it['id'], (array)json_decode((string)($_POST['txt'] ?? '{}'), true)); $J(['ok' => true, 'item' => $r]); }
        if ($a === 'txtprev') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); session_write_close(); $url = cpl_text_preview($it['id'], (array)json_decode((string)($_POST['txt'] ?? '{}'), true)); $J(['ok' => (bool)$url, 'url' => $url]); }
        if ($a === 'retxt_all') { session_write_close(); @set_time_limit(300); $n = cpl_restamp_text_all($pid, (array)json_decode((string)($_POST['txt'] ?? '{}'), true)); $J(['ok' => true, 'n' => $n]); }
        if ($a === 'redesign') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); session_write_close(); @set_time_limit(180); $r = cpl_recompose($it['id']); $J(['ok' => (bool)$r, 'item' => $r]); }
        if ($a === 'savecanvas') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); session_write_close(); $r = cpl_save_canvas($it['id'], (string)($_POST['img'] ?? ''), isset($_POST['doc']) ? (string)$_POST['doc'] : null); $J(['ok' => (bool)$r, 'item' => $r]); }
        if ($a === 'getdoc') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); $st = db()->prepare('SELECT design_doc FROM cp_items WHERE id = ? AND workspace_id = ?'); $st->execute([$it['id'], $wid]); $doc = (string)($st->fetchColumn() ?: ''); $J(['ok' => true, 'doc' => $doc !== '' ? json_decode($doc, true) : null]); }
        if ($a === 'refield') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); session_write_close(); @set_time_limit(120); $r = cpl_regen_field($it['id'], (string)($_POST['field'] ?? '')); $J(['ok' => (bool)$r, 'item' => $r]); }
        if ($a === 'docvers') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); $st = db()->prepare('SELECT design_doc_hist FROM cp_items WHERE id = ? AND workspace_id = ?'); $st->execute([$it['id'], $wid]); $h = json_decode((string)($st->fetchColumn() ?: ''), true); $out = []; if (is_array($h)) foreach ($h as $i => $v) $out[] = ['i' => $i, 't' => (int)($v['t'] ?? 0)]; $J(['ok' => true, 'versions' => $out]); }
        if ($a === 'docver') { $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it || $it['plan_id'] !== $pid) $J(['ok' => false], 404); $i = (int)($_POST['i'] ?? -1); $st = db()->prepare('SELECT design_doc_hist FROM cp_items WHERE id = ? AND workspace_id = ?'); $st->execute([$it['id'], $wid]); $h = json_decode((string)($st->fetchColumn() ?: ''), true); $doc = (is_array($h) && isset($h[$i]['doc'])) ? json_decode((string)$h[$i]['doc'], true) : null; $J(['ok' => (bool)$doc, 'doc' => $doc]); }
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
render($plan ? (string)$plan['name'] : t('إنشاء المحتوى'), 'content_plan', function () use ($plan, $wid) {
    $T = [
        'stVisual' => t('تحليل الصور المرجعية…'), 'stOutline' => t('تخطيط المواضيع…'), 'stPosts' => t('كتابة المنشورات: %s من %s'), 'stHuman' => t('مراجعة الأسلوب: %s من %s'), 'human' => t('مكتوب بالـ Humanizer'), 'stReview' => t('جاهزة للمراجعة'), 'stPaused' => t('توقف التوليد بعد أكثر من محاولة فاشلة.'),
        'stTopics' => t('راجع الأفكار (%s) واحذف ما لا تريده، ثم ولّد النصوص'), 'genPosts' => t('ولّد النصوص للأفكار المعتمدة (%s)'), 'topicsHint' => t('دي الأفكار قبل كتابة النصوص. احذف اللي مش عايزه — هنكتب النصوص للمعتمد فقط (توفير كريدت).'), 'rejectTopic' => t('رفض الفكرة'),
        'resume' => t('أكمل من حيث توقف'), 'stop' => t('إيقاف التوليد'), 'stopAsk' => t('إيقاف التوليد الآن؟ المنشورات الجاهزة تبقى محفوظة، وتقدر تكمل لاحقًا.'), 'offline' => t('انقطع الاتصال. سنكمل تلقائيًا عند عودته، وكل ما تم محفوظ.'), 'bg' => t('يمكنك إغلاق الصفحة؛ التوليد يكمل في الخلفية وتجد كل شيء محفوظًا هنا.'),
        'approve' => t('اعتمده'), 'reject' => t('رفض'), 'undo' => t('إرجاع لمسودة'), 'approveSel' => t('اعتمد المحدد (%s)'), 'rejectSel' => t('ارفض المحدد'), 'all' => t('تحديد الكل'), 'regen' => t('أعد كتابته'),
        'copy' => t('نسخ'), 'copied' => t('تم النسخ'), 'edit' => t('تعديل'), 'save' => t('حفظ'), 'cancel' => t('إلغاء'), 'saved' => t('تم الحفظ'), 'err' => t('حصلت مشكلة. حاول مرة أخرى.'),
        'li' => 'LinkedIn', 'x' => 'X', 'img' => t('برومبت الصورة'), 'vid' => t('برومبت الفيديو'), 'car' => t('كاروسيل (5 شرائح)'), 'head' => t('عنوان الصورة'), 'words' => t('%s كلمة'), 'chars' => t('%s حرف'),
        's_pending' => t('قيد الكتابة'), 's_draft' => t('مسودة'), 's_approved' => t('معتمد'), 's_rejected' => t('مرفوض'), 's_scheduled' => t('مجدول'), 's_queued' => t('في الطابور'), 's_published' => t('تم النشر'),
        'toBank' => t('افتح مخزون المحتوى'), 'nApproved' => t('%s معتمد في المخزون'), 'f_all' => t('الكل'), 'emptyHere' => t('مفيش منشورات بالفلتر ده.'), 'delPlan' => t('حذف الخطة؟ المنشورات المجدولة أو المنشورة تبقى في المخزون.'),
        'uploading' => t('جارٍ رفع الصور…'), 'imported' => t('تم تحديث %s منشور من الملف (تخطّينا %s).'), 'needGoal' => t('اكتب هدف الحملة.'), 'draftRestored' => t('استرجعنا المسودة التي لم تُرسل.'), 'refMax' => t('حتى 12 صورة.'), 'uploaded' => t('تم رفع الصور.'), 'sum' => ['goal' => t('الهدف'), 'count' => t('عدد المنشورات'), 'aud' => t('الجمهور'), 'svc' => t('الخدمات'), 'lang' => t('اللغة'), 'dial' => t('اللهجة'), 'tone' => t('النبرة'), 'cta' => t('الدعوة للإجراء'), 'refs' => t('صور مرجعية'), 'logo' => t('اللوجو'), 'none' => t('—'), 'on' => t('مركّب'), 'off' => t('بدون'), 'li' => 'LinkedIn', 'x' => 'X', 'img' => t('الصور'), 'vo' => t('الصوت')], 'langN' => ['ar' => t('عربي'), 'en' => 'English', 'both' => t('عربي + English'), 'ar_en' => t('عربي + إنجليزي'), 'en_ar' => t('إنجليزي + عربي'), 'none' => t('بدون نص')], 'dialEx' => ['msa' => t('مثال: نساعد فرق المشتريات على تقليل وقت التوريد دون التضحية بالجودة.'), 'saudi' => t('مثال: نساعد فريق المشتريات يختصر وقت التوريد بدون ما يضحّي بالجودة.'), 'gulf' => t('مثال: نساعد فريق المشتريات يختصر وقت التوريد من دون ما يتأثر المستوى.'), 'emirati' => t('مثال: نساعد فريق المشتريات يختصر وقت التوريد بدون ما تتأثر الجودة.'), 'kuwaiti' => t('مثال: نساعد فريق المشتريات يختصر وقت التوريد بدون ما تنزل الجودة.'), 'egyptian' => t('مثال: بنساعد فريق المشتريات يختصر وقت التوريد من غير ما الجودة تتأثر.'), 'levantine' => t('مثال: منساعد فريق المشتريات يختصر وقت التوريد بدون ما تتأثر الجودة.'), 'maghrebi' => t('مثال: نعاونو فريق المشتريات باش يختصر وقت التوريد بلا ما تنقص الجودة.'), 'white' => t('مثال: نساعد فريق المشتريات يختصر وقت التوريد بدون ما تتأثر الجودة.'), 'other' => t('اكتب اللهجة أو الأسلوب الذي تريده في الخانة.')], 'fSel' => t('%s محدد'), 'genAsk' => t('توليد %s لـ %s منشور؟ يُخصم من رصيد مزوّد الصور/الفيديو.'), 'genAskCar' => t('سيولّد الكاروسيل 5 صور لكل منشور = %s صورة إجمالاً لـ %s منشور، وتُخصم من رصيد مزوّد الصور. يُفضّل توليد صورة واحدة أولاً لاعتماد النمط. متابعة؟'), 'estCost' => t('(تكلفة تقديرية ≈ $%s)'), 'genOne' => t('توليد'), 'kImage' => t('صورة'), 'kVideo' => t('فيديو'), 'kCarousel' => t('كاروسيل (5 صور)'), 'genAdded' => t('بدأ التوليد: %s مهمة. يكمل في الخلفية حتى لو أغلقت الصفحة.'), 'genNone' => t('لا شيء جديد للتوليد (ربما قيد التوليد بالفعل، أو لا يوجد كاروسيل).'),
        'jBusy' => t('جارٍ التوليد'), 'jDone' => t('جاهز'), 'jFail' => t('فشل'), 'toBankOne' => t('إرسال للمخزون'), 'fmtText' => t('نص فقط'), 'useFmt' => t('اضغط لاستخدام هذا الشكل في النشر'), 'inUse' => t('المستخدم في النشر'), 'sent' => t('أُرسل %s منشور للمخزون.'), 'logT' => t('سجل التنفيذ المباشر'), 'srcBg' => t('خلفية'), 'srcPage' => t('الصفحة'), 'elapsed' => t('%s ثانية'), 'busyBg' => t('خطوة تعمل في الخلفية، ننتظرها'), 'pdfPages' => t('جارٍ تحويل صفحات PDF إلى صور…'), 'qc' => t('فحص الجودة:'), 'cats' => array_map('t', cpl_categories()),
        'ilAdj' => t('اضبط اللوجو'), 'ilTitle' => t('ضبط اللوجو على هذه الصورة'), 'ilHint' => t('اسحب اللوجو لأي مكان — حتى خارج حدود الصورة. الخط المتقطع = المنطقة الآمنة.'), 'ilSize' => t('الحجم'), 'ilOp' => t('الشفافية'), 'ilColor' => t('لون اللوجو'), 'ilPlate' => t('خلفية خلف اللوجو'), 'ilSave' => t('حفظ'), 'ilSaved' => t('تم تحديث اللوجو على الصورة.'), 'ilNone' => t('لا يوجد لوجو في ملف الشركة.'), 'ilBusy' => t('جارٍ إعادة التركيب…'),
        'itAdj' => t('صمّم'), 'itReDo' => t('أعد التصميم'), 'itSaved' => t('تم حفظ التصميم ✓'),
        'layoutErr' => t('يوجد نص خارج حدود التصميم. قصّر النص أو كبّر المربع أو حرّكه للداخل قبل الحفظ.'), 'layoutWarn' => t('تنبيه: حجم بعض النصوص صغير وقد يصعب قراءته.'),
        'draftBack' => t('تم استرجاع آخر تعديل غير محفوظ.'), 'lkOn' => t('مقفول'), 'lkOff' => t('مفتوح'), 'reField' => t('إعادة توليد هذا الحقل فقط'),
        'brandNoLogo' => t('تنبيه: التصميم بدون شعار الشركة.'), 'noMatch' => t('لا نتائج مطابقة للبحث.'), 'verLabel' => t('⟲ نسخة سابقة'), 'verLoaded' => t('تم تحميل النسخة — احفظ لاعتمادها'), 'cropPick' => t('اختر صورة لقصّها'),
        'calc' => t('جارٍ الحساب…'), 'costNone' => t('لا توجد بيانات تكلفة لهذه الحملة بعد (تظهر مع التوليد القادم).'), 'costTotal' => t('الإجمالي'), 'costPer' => t('لكل منشور معتمد'), 'costText' => t('النصوص'), 'costMedia' => t('الصور/الفيديو'), 'costTask' => t('المهمة'), 'costProv' => t('المزوّد'), 'costCost' => t('التكلفة'), 'itAllAsk' => t('تطبيق المكان والحجم والنمط واللون على كل التصاميم؟ (يبقى نصّ كل منشور كما هو)'), 'itAllDone' => t('تم تطبيق الإعدادات على %s تصميم.'),
        'ilTint' => ['orig' => t('الأصلي'), 'white' => t('أبيض'), 'black' => t('أسود')], 'ilPlateV' => ['none' => t('بدون'), 'light' => t('فاتحة'), 'dark' => t('داكنة')],
    ];
    
    $stepNow = !$plan ? 1 : (in_array($plan['status'], ['generating', 'paused'], true) ? 1 : 2);
    $steps = [1 => [t('الخطوة الأولى'), t('الهدف وعدد المنشورات والصور المرجعية ← تجهيز الملف والبرومبتات (بدون صور أو فيديو)')], 2 => [t('الخطوة الثانية'), t('حدد منشورات ← ولّد لها صورة أو فيديو أو كاروسيل (أو اتركها نصًا) ← أرسلها للمخزون')]];
    echo '<ol class="grid sm:grid-cols-2 gap-2 mb-5">'; foreach ($steps as $n => [$h, $d]) echo '<li class="rounded-2xl border p-3 ' . ($n === $stepNow ? 'border-brand-300 bg-brand-50/60' : 'border-ink-200 bg-white') . '"><div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold ' . ($n === $stepNow ? 'bg-brand-600 text-[#fff]' : ($n < $stepNow ? 'bg-lime-500 text-[#fff]' : 'bg-ink-100 text-ink-500')) . '">' . ($n < $stepNow ? '✓' : $n) . '</span><b class="text-sm">' . e($h) . '</b></div><p class="text-xs text-ink-500 mt-1">' . e($d) . '</p></li>'; echo '</ol>';
    $cfg = ['url' => url('content_plan'), 'bank' => url('content_bank'), 'csrf' => csrf_token(), 'wid' => $wid, 't' => $T];
    if ($plan): $__lg = function_exists('kb_logo') ? kb_logo($wid) : null; [$__ar, $__ag, $__ab] = cpl_accent_rgb($plan); $cfg['plan'] = cpl_status($plan) + ['name' => (string)$plan['name'], 'has_logo' => $__lg ? 1 : 0, 'logo' => $__lg ? url('media', ['file' => $__lg['id']]) : '', 'accent' => sprintf('#%02x%02x%02x', $__ar, $__ag, $__ab), 'imgPrice' => cpl_img_unit_cost($plan)]; ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <a href="<?= e(url('content_plan')) ?>" class="btn-ghost !py-1.5">← <?= e(t('كل الخطط')) ?></a>
      <span class="text-sm text-ink-500 min-w-0 flex-1 truncate"><?= e(mb_substr((string)$plan['goal'], 0, 160)) ?></span>
      <button type="button" id="cp-cost-btn" class="btn-ghost !py-1.5" title="<?= e(t('تكلفة الذكاء لهذه الحملة')) ?>">💰 <?= e(t('التكلفة')) ?></button>
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
      <input id="cp-q" type="search" class="input !mt-0 !py-1 !w-44 text-sm" placeholder="<?= e(t('بحث في المنشورات…')) ?>">
      <div class="inline-flex rounded-lg border border-ink-200 overflow-hidden">
        <button type="button" id="cp-v-list" class="px-2.5 py-1 text-sm bg-brand-50 text-brand-700" title="<?= e(t('قائمة')) ?>">☰</button>
        <button type="button" id="cp-v-grid" class="px-2.5 py-1 text-sm" title="<?= e(t('شبكة')) ?>">▦</button>
      </div>
      <span class="flex-1"></span>

      <a href="<?= e(url('content_bank')) ?>" id="cp-tobank" class="btn-ghost !py-1.5 hidden"></a>
    </div>
    <div id="cp-list" class="space-y-3 pb-28"></div>
    <div id="cp-ilogo" class="hidden fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm grid place-items-center p-4" dir="rtl">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-auto">
        <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-ink-100 sticky top-0 bg-white z-10"><b class="text-ink-900"><?= e($T['ilTitle']) ?></b><button type="button" id="il-save" class="btn-primary !py-1.5"><?= e($T['ilSave']) ?></button></div>
        <div class="p-5 flex flex-wrap items-start gap-5">
          <div class="space-y-2">
            <div id="il-prev" class="relative w-64 aspect-[4/5] rounded-xl overflow-hidden border border-ink-200 bg-cover bg-center bg-ink-900 select-none touch-none">
              <div class="absolute inset-[7%] border border-dashed border-[#ffffff55] rounded pointer-events-none"></div>
              <div id="il-plate" class="absolute rounded-lg pointer-events-none"></div>
              <img id="il-logo" src="" alt="" draggable="false" class="absolute object-contain cursor-grab active:cursor-grabbing">
            </div>
            <p class="text-[11px] text-ink-400 w-64"><?= e($T['ilHint']) ?></p>
            <div id="il-imgs" class="flex flex-wrap gap-1 w-64"></div>
          </div>
          <div class="space-y-3 text-sm min-w-0 flex-1">
            <div><span class="text-xs text-ink-500 block mb-1"><?= e(t('أماكن جاهزة')) ?></span>
              <div class="grid grid-cols-3 gap-1.5 w-40" dir="ltr"><?php foreach ([[14, 9], [50, 9], [86, 9], [14, 50], [50, 50], [86, 50], [14, 91], [50, 91], [86, 91]] as [$px, $py]): ?><button type="button" data-ilp="<?= $px ?>,<?= $py ?>" class="h-8 rounded-lg border border-ink-200 relative hover:border-brand-300 [&.on]:border-brand-500 [&.on]:bg-brand-50"><span class="absolute w-3 h-2 rounded-sm bg-ink-400" style="left:calc(<?= $px ?>% - 6px);top:calc(<?= $py ?>% - 4px)"></span></button><?php endforeach; ?></div></div>
            <label class="block"><span class="text-xs text-ink-500 flex justify-between"><span><?= e($T['ilSize']) ?></span><b id="il-w-v" class="tabular-nums"></b></span><input type="range" min="8" max="45" value="18" class="w-full accent-brand-600" data-ilogo="logo_w"></label>
            <label class="block"><span class="text-xs text-ink-500 flex justify-between"><span><?= e($T['ilOp']) ?></span><b id="il-o-v" class="tabular-nums"></b></span><input type="range" min="30" max="100" value="100" class="w-full accent-brand-600" data-ilogo="logo_op"></label>
            <div><span class="text-xs text-ink-500 block mb-1"><?= e($T['ilColor']) ?></span><div class="flex flex-wrap gap-1.5"><?php foreach (['orig' => t('الأصلي'), 'white' => t('أبيض'), 'black' => t('أسود')] as $v => $l): ?><label class="cursor-pointer"><input type="radio" name="il_tint" value="<?= $v ?>" class="peer sr-only"<?= $v === 'orig' ? ' checked' : '' ?>><span class="inline-flex rounded-xl border border-ink-200 px-3 py-1.5 text-sm text-ink-700 hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-800"><?= e($l) ?></span></label><?php endforeach; ?></div></div>
            <div><span class="text-xs text-ink-500 block mb-1"><?= e($T['ilPlate']) ?></span><div class="flex flex-wrap gap-1.5"><?php foreach (['none' => t('بدون'), 'light' => t('فاتحة'), 'dark' => t('داكنة')] as $v => $l): ?><label class="cursor-pointer"><input type="radio" name="il_plate" value="<?= $v ?>" class="peer sr-only"<?= $v === 'none' ? ' checked' : '' ?>><span class="inline-flex rounded-xl border border-ink-200 px-3 py-1.5 text-sm text-ink-700 hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-800"><?= e($l) ?></span></label><?php endforeach; ?></div></div>
          </div>
        </div>
      </div>
    </div>
    <!-- محرّر بسيط: تحريك النص واللوجو فقط -->
    <div id="cp-itxt" class="hidden fixed inset-0 z-[70] bg-ink-900 flex flex-col sm:flex-row" dir="rtl">
      <div class="order-1 flex-1 min-h-0 grid place-items-center p-3 overflow-auto">
        <div id="it-cwrap" style="width:min(92%,440px);max-height:88vh">
          <canvas id="it-canvas" class="rounded-xl shadow-2xl touch-none"></canvas>
        </div>
      </div>
      <div class="order-2 w-full sm:w-80 shrink-0 bg-white sm:h-full overflow-auto flex flex-col">
        <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-ink-100">
          <b class="text-ink-900"><?= e(t('تحريك العناصر')) ?></b>
          <button type="button" data-tclose class="btn-ghost !py-1.5 !px-3 text-sm"><?= e(t('✕ إغلاق')) ?></button>
        </div>
        <div class="p-4 space-y-3 text-sm flex-1">
          <div class="rounded-xl bg-brand-50 border border-brand-200 p-3 text-brand-800 leading-relaxed">
            <b class="block mb-1">✋ <?= e(t('اسحب لتحريك المكان')) ?></b>
            <?= e(t('اسحب النص أو اللوجو بالماوس (أو بأسهم لوحة المفاتيح) لتغيير مكانه على الصورة. تحريك المكان فقط — بلا تغيير الحجم.')) ?>
          </div>
          <ul class="text-xs text-ink-500 space-y-1 list-disc ps-4">
            <li><?= e(t('اضغط على النص واسحبه لأي مكان.')) ?></li>
            <li><?= e(t('اضغط على اللوجو واسحبه لأي مكان.')) ?></li>
            <li><?= e(t('خطوط زرقاء تظهر للمحاذاة للمنتصف.')) ?></li>
          </ul>
          <div>
            <label class="block text-xs font-semibold text-ink-700 mb-1">✨ <?= e(t('تأثير العنوان')) ?></label>
            <select id="it-fx" class="input !py-2 text-sm">
              <?php foreach (['none' => t('بدون'), 'shadow' => t('ظل'), 'outline' => t('حدود'), 'sticker' => t('ستيكر'), '3d' => t('ثري دي'), 'gold' => t('ذهبي'), 'glow' => t('نيون')] as $fk => $fl): ?>
              <option value="<?= e($fk) ?>"><?= e($fl) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-[11px] text-ink-400 mt-1"><?= e(t('المعاينة فورية، ويتبيّت في الصورة عند الحفظ.')) ?></p>
          </div>
        </div>
        <div class="mt-auto p-3 border-t border-ink-100 bg-white sticky bottom-0">
          <button type="button" id="it-save" class="btn-primary w-full !py-2.5 text-sm"><?= e(t('حفظ')) ?></button>
        </div>
      </div>
    </div>
    <div id="cp-cost" class="hidden fixed inset-0 z-[72] bg-black/50 grid place-items-center p-4" dir="rtl">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[85vh] overflow-auto">
        <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100 sticky top-0 bg-white"><b class="text-ink-900">💰 <?= e(t('تكلفة الذكاء لهذه الحملة')) ?></b><button type="button" data-cost-x class="btn-ghost !py-1 !px-2">✕</button></div>
        <div id="cp-cost-body" class="p-4 text-sm text-ink-700"><?= e(t('جارٍ الحساب…')) ?></div>
        <p class="px-4 pb-4 text-[11px] text-ink-400"><?= e(t('الأرقام من بيانات الاستهلاك الفعلية (ai_usage). تكلفة الصور تقديرية حسب المزوّد، وبعض الموديلات قد لا توفّر سعرًا دقيقًا.')) ?></p>
      </div>
    </div>
    <div id="cp-float" class="hidden fixed bottom-4 inset-x-0 z-40 px-3 pointer-events-none">
      <div class="pointer-events-auto mx-auto w-fit max-w-full rounded-2xl border border-ink-200 bg-white shadow-2xl p-2 flex flex-wrap items-center justify-center gap-2 text-sm">
        <b id="cp-fn" class="px-2 text-ink-700"></b>
        <button type="button" data-gen="image" class="btn-ghost !py-2"><?= ui_icon('image', 'w-4 h-4') ?><?= e(t('توليد صورة')) ?></button>
        <button type="button" data-gen="video" class="btn-ghost !py-2"><?= ui_icon('play', 'w-4 h-4') ?><?= e(t('توليد فيديو')) ?></button>
        <button type="button" data-gen="carousel" class="btn-ghost !py-2"><?= ui_icon('layers', 'w-4 h-4') ?><?= e(t('توليد كاروسيل')) ?></button>
        <button type="button" data-bulk="approved" class="!py-2 px-4 rounded-xl font-semibold text-[#fff] inline-flex items-center gap-1.5" style="background:#16a34a">✓ <?= e(t('إرسال للمخزون')) ?></button>
        <button type="button" data-bulk="rejected" class="btn-ghost !py-2 text-red-600"><?= e(t('رفض')) ?></button>
        <button type="button" id="cp-fx" class="px-2 text-ink-400" title="<?= e(t('إلغاء التحديد')) ?>">✕</button>
      </div>
    </div>
    <?php else: $plans = cpl_plans(); $kb = kb_get($wid); $svc = array_values(array_filter(array_map(function ($r) { return trim((string)($r['name'] ?? '')); }, (array)($kb['services']['rows'] ?? []))));
      $hasLogo = function_exists('kb_logo') && kb_logo($wid); ?>
    <p class="text-sm text-ink-500 mb-4"><?= e(t('اكتب هدفك وعدد المنشورات وارفع تصميمات مرجعية. نكتب لك خطة كاملة من ملف شركتك: موضوع، نص لينكدإن، نص X، برومبت صورة، برومبت فيديو، وكاروسيل لكل منشور. لا نولّد صورًا هنا؛ أنت تعتمد ما يعجبك فيذهب إلى مخزون المحتوى للجدولة والنشر.')) ?></p>
    <form id="cp-wiz" class="card p-0 mb-6 overflow-hidden" novalidate>
      <ol id="cp-steps" class="flex border-b border-ink-100 bg-ink-50/60 text-sm overflow-x-auto">
        <li><button type="button" data-go="0" class="flex items-center gap-2 px-4 py-3 whitespace-nowrap text-ink-500 [&.on]:text-brand-700 [&.on]:font-semibold [&.done]:text-ink-800"><span class="w-6 h-6 rounded-full grid place-items-center text-xs bg-white border border-ink-200">1</span><?= e(t('الهدف')) ?></button></li>
        <li><button type="button" data-go="1" class="flex items-center gap-2 px-4 py-3 whitespace-nowrap text-ink-500 [&.on]:text-brand-700 [&.on]:font-semibold [&.done]:text-ink-800"><span class="w-6 h-6 rounded-full grid place-items-center text-xs bg-white border border-ink-200">2</span><?= e(t('الجمهور')) ?></button></li>
        <li><button type="button" data-go="2" class="flex items-center gap-2 px-4 py-3 whitespace-nowrap text-ink-500 [&.on]:text-brand-700 [&.on]:font-semibold [&.done]:text-ink-800"><span class="w-6 h-6 rounded-full grid place-items-center text-xs bg-white border border-ink-200">3</span><?= e(t('الأسلوب')) ?></button></li>
        <li><button type="button" data-go="3" class="flex items-center gap-2 px-4 py-3 whitespace-nowrap text-ink-500 [&.on]:text-brand-700 [&.on]:font-semibold [&.done]:text-ink-800"><span class="w-6 h-6 rounded-full grid place-items-center text-xs bg-white border border-ink-200">4</span><?= e(t('الشكل')) ?></button></li>
        <li><button type="button" data-go="4" class="flex items-center gap-2 px-4 py-3 whitespace-nowrap text-ink-500 [&.on]:text-brand-700 [&.on]:font-semibold [&.done]:text-ink-800"><span class="w-6 h-6 rounded-full grid place-items-center text-xs bg-white border border-ink-200">5</span><?= e(t('المراجعة')) ?></button></li>
      </ol>
      <div class="p-5 space-y-5">
      <?php
        $chip = function (string $name, string $val, string $label, bool $on = false, string $type = 'radio', string $sub = '') { return '<label class="cursor-pointer"><input type="' . $type . '" name="' . $name . '" value="' . e($val) . '" class="peer sr-only"' . ($on ? ' checked' : '') . '><span class="inline-flex flex-col rounded-xl border border-ink-200 px-3 py-1.5 text-sm text-ink-700 transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-800">' . e($label) . ($sub !== '' ? '<span class="text-[11px] text-ink-400">' . e($sub) . '</span>' : '') . '</span></label>'; };
        $kbd = function_exists('cpl_kb_defaults') ? cpl_kb_defaults($wid) : [];
        $opt = function (array $a, string $cur = '') { $h = ''; foreach ($a as $v => $l) $h .= '<option value="' . e($v) . '"' . ($v === $cur ? ' selected' : '') . '>' . e($l) . '</option>'; return $h; };
        $langs = ['ar' => t('عربي (تصميم من اليمين لليسار)'), 'en' => t('إنجليزي (تصميم من اليسار لليمين)'), 'ar_en' => t('عربي + إنجليزي (الإنجليزي أصغر وأخفت)'), 'en_ar' => t('إنجليزي + عربي (العربي أصغر وأخفت)')];
        $chanLangs = ['ar' => t('عربي'), 'en' => 'English', 'ar_en' => t('عربي ثم إنجليزي'), 'en_ar' => t('إنجليزي ثم عربي')];
        $dialects = ['' => t('— اختر —'), 'msa' => t('فصحى'), 'saudi' => t('سعودية'), 'gulf' => t('خليجية'), 'emirati' => t('إماراتية'), 'kuwaiti' => t('كويتية'), 'egyptian' => t('مصرية'), 'levantine' => t('شامية'), 'maghrebi' => t('مغاربية مبسطة'), 'white' => t('عربية بيضاء (مفهومة لكل العرب)'), 'other' => t('أخرى (اكتبها)…')];
        $tonePresets = [t('واثقة وعملية بلا مبالغة'), t('خبيرة وتعليمية'), t('ودودة وقريبة'), t('رسمية ومحترفة'), t('جريئة وملهمة'), t('مختصرة ومباشرة')];
        $ctaPresets = [t('اطلب عرض سعر'), t('احجز مكالمة استشارية'), t('احجز عرضًا تجريبيًا'), t('تواصل عبر واتساب'), t('زر الموقع'), t('حمّل الدليل'), t('سجّل في الفعالية'), t('اطلب زيارة ميدانية')];
      ?>
      <section data-step="0" class="space-y-5">
        <div><h2 class="text-lg font-bold text-ink-900"><?= e(t('ما هدف الحملة؟')) ?></h2><p class="text-sm text-ink-500"><?= e(t('اكتبه في جملة أو اثنتين: من تريد أن تصل إليه، وماذا تريده أن يفعل.')) ?></p></div>
        <label class="block"><span class="label"><?= e(t('هدف الحملة')) ?> *</span><textarea name="goal" rows="4" class="input" dir="auto" placeholder="<?= e(t('مثال: نريد طلبات عروض أسعار من مديري المشتريات في الشركات الكبيرة لخدمة تجهيز المطابخ التجارية خلال الربع القادم.')) ?>"></textarea></label>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><span class="label"><?= e(t('عدد المنشورات')) ?></span><input name="count" type="number" min="1" value="12" class="input !mt-0 !w-32" placeholder="12"></div>
          <label class="block"><span class="label"><?= e(t('اسم الخطة (اختياري)')) ?></span><input name="name" class="input" dir="auto"></label>
        </div>
      </section>
      <section data-step="1" class="space-y-5 hidden">
        <div><h2 class="text-lg font-bold text-ink-900"><?= e(t('من تخاطب؟')) ?></h2><p class="text-sm text-ink-500"><?= e(t('كلما كان الجمهور أدق كان المحتوى أقوى. يمكنك تركها فارغة لنعتمد على ملف الشركة.')) ?></p></div>
      <label class="block"><span class="label"><?= e(t('الجمهور المستهدف')) ?> <span class="text-xs text-ink-400"><?= e(t('(من ملف الشركة، عدّله لهذه الحملة)')) ?></span></span><textarea name="audience" rows="2" class="input" dir="auto" placeholder="<?= e(t('مثال: مديرو المشتريات والمرافق في الفنادق والمستشفيات بالسعودية')) ?>"><?= e((string)($kbd['audience'] ?? '')) ?></textarea></label>
      <label class="block"><span class="label"><?= e(t('نبذة عن الشركة')) ?> <span class="text-xs text-ink-400"><?= e(t('(من ملف الشركة، تُستخدم كسياق)')) ?></span></span><textarea name="about" rows="2" class="input" dir="auto"><?= e((string)($kbd['about'] ?? '')) ?></textarea></label>
      <?php if ($svc): ?><div id="cp-svc-wrap"><div class="flex items-center flex-wrap gap-2"><span class="label !mb-0"><?= e(t('ركّز على خدمات (من ملف الشركة)')) ?></span><span class="text-xs text-ink-400">·</span><button type="button" data-svc="all" class="text-xs font-semibold text-brand-700 hover:underline"><?= e(t('تحديد الكل')) ?></button><button type="button" data-svc="none" class="text-xs font-semibold text-ink-500 hover:underline"><?= e(t('إلغاء تحديد الكل')) ?></button></div><div class="flex flex-wrap gap-2 mt-1.5"><?php foreach (array_slice($svc, 0, 30) as $s): ?><label class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-3 py-1 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" name="services" value="<?= e($s) ?>" checked><?= e($s) ?></label><?php endforeach; ?></div>
      <script>(function(){var w=document.getElementById('cp-svc-wrap');if(!w)return;w.addEventListener('click',function(e){var b=e.target.closest('[data-svc]');if(!b)return;var on=b.getAttribute('data-svc')==='all';w.querySelectorAll('input[name=services]').forEach(function(c){c.checked=on;});});})();</script></div><?php endif; ?>
      </section>
      <section data-step="2" class="space-y-5 hidden">
        <div><h2 class="text-lg font-bold text-ink-900"><?= e(t('بأي لغة وأسلوب؟')) ?></h2><p class="text-sm text-ink-500"><?= e(t('الإعداد الافتراضي يناسب أغلب الحالات؛ غيّر ما تحتاجه فقط.')) ?></p></div>
        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block"><span class="label"><?= e(t('اللغة')) ?></span>
            <select name="lang_all" class="input"><?= $opt($langs, 'ar') ?></select></label>
          <label class="block"><span class="label"><?= e(t('اللهجة العربية')) ?></span>
            <select name="dialect_k" class="input"><?= $opt($dialects, $kbd['dialect_k'] ?? '') ?></select>
            <input name="dialect_other" class="input hidden mt-2" placeholder="<?= e(t('اكتب اللهجة أو الأسلوب (مثال: سعودية رسمية بمصطلحات تقنية إنجليزية)')) ?>"></label>
        </div>
        <p id="cp-dial-ex" class="rounded-xl bg-ink-50 px-3 py-2 text-sm text-ink-600 hidden" dir="rtl"></p>
        <details class="group rounded-xl border border-ink-200" id="cp-lang"><summary class="cursor-pointer text-sm font-medium text-ink-700 select-none px-3 py-2.5 flex items-center gap-2"><?= ui_icon('sliders', 'w-4 h-4 text-ink-400') ?><?= e(t('تخصيص لغة كل قناة')) ?><span class="text-xs text-ink-400 font-normal"><?= e(t('(اتركه ليتبع الاختيار الأساسي)')) ?></span></summary>
          <div class="border-t border-ink-100 divide-y divide-ink-50">
            <?php $imgLangs = ['ar' => t('عربي'), 'en' => 'English', 'ar_en' => t('عربي ثم إنجليزي (أصغر وأخفت)'), 'en_ar' => t('إنجليزي ثم عربي (أصغر وأخفت)'), 'none' => t('بدون نص')]; foreach (['lang_li' => [t('نص لينكدإن'), 'file'], 'lang_x' => [t('نص X'), 'hash'], 'lang_img' => [t('النص على الصور والشرائح'), 'image'], 'lang_vo' => [t('التعليق الصوتي في الفيديو'), 'mic']] as $nm => [$lab, $ic]): $o = $nm === 'lang_img' ? $imgLangs : $chanLangs; ?>
            <label class="flex items-center gap-3 px-3 py-2.5"><span class="flex items-center gap-2 text-sm text-ink-600 min-w-0 flex-1"><?= ui_icon($ic, 'w-4 h-4 text-ink-400 shrink-0') ?><span class="truncate"><?= e($lab) ?></span></span><select name="<?= $nm ?>" class="input !mt-0 !py-1.5 !w-auto min-w-[9rem]"><?= $opt($o, 'ar') ?></select></label>
            <?php endforeach; ?>
          </div>
        </details>
        <label class="block"><span class="label"><?= e(t('النبرة')) ?></span>
          <input name="tone" class="input" list="cp-tones" dir="auto" value="<?= e((string)($kbd['tone'] ?? '')) ?>" placeholder="<?= e(t('اكتب أو اختر من القائمة')) ?>">
          <datalist id="cp-tones"><?php foreach ($tonePresets as $tp) echo '<option value="' . e($tp) . '">'; ?></datalist></label>
        <label class="block"><span class="label"><?= e(t('الدعوة للإجراء')) ?></span>
          <input name="cta" class="input" list="cp-ctas" dir="auto" value="<?= e((string)($kbd['cta'] ?? '')) ?>" placeholder="<?= e(t('اكتب أو اختر من القائمة')) ?>">
          <datalist id="cp-ctas"><?php foreach ($ctaPresets as $cp) echo '<option value="' . e($cp) . '">'; ?></datalist></label>
        <label class="block"><span class="label"><?= e(t('تجنّب (اختياري)')) ?></span><input name="avoid" class="input" dir="auto" placeholder="<?= e(t('كلمات أو وعود أو أساليب لا تريدها')) ?>"></label>
      </section>
      <section data-step="3" class="space-y-5 hidden">
        <div><h2 class="text-lg font-bold text-ink-900"><?= e(t('شكل التصميمات')) ?></h2><p class="text-sm text-ink-500"><?= e(t('ارفع تصميمات تعجبك وحدد مكان الشعار. كل ذلك اختياري.')) ?></p></div>
      <div class="flex items-center gap-2 pt-1"><span class="text-base">🖼️</span><h3 class="text-base font-bold text-ink-900"><?= e(t('إعدادات الصور')) ?></h3><span class="h-px flex-1 bg-ink-100"></span></div>
      <div>
        <span class="label"><?= e(t('تصميمات مرجعية (اختياري، حتى 12 صورة أو صفحة PDF)')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('نحلل أسلوبها (الألوان، الخطوط، التكوين) ونطبقه في كل برومبت، وتُرسل لموديل الصور كمرجع للأسلوب أثناء التوليد، بدون نسخ محتواها.')) ?> <?= $hasLogo ? e(t('الشعار موجود في ملف الشركة وسيُذكر موضعه في البرومبتات.')) : '' ?></p>
        <div id="cp-refs" class="flex flex-wrap gap-2"></div>
        <div class="flex flex-wrap gap-2 mt-2"><label class="btn-ghost !py-1.5 cursor-pointer"><input id="cp-ref-in" type="file" accept="image/*,application/pdf,.pdf" multiple class="hidden"><?= ui_icon('upload', 'w-4 h-4') ?><?= e(t('أضف صورًا أو PDF')) ?></label><button type="button" id="cp-ref-lib" class="btn-ghost !py-1.5"><?= ui_icon('image', 'w-4 h-4') ?><?= e(t('اختر من مكتبة الوسائط')) ?></button></div>
        <div class="mt-3">
          <p class="text-xs text-ink-500 mb-1.5"><?= e(t('أو اختر من نماذج جاهزة احترافية (اضغط لإضافته كمرجع):')) ?></p>
          <div class="flex gap-2 overflow-x-auto pb-1">
            <?php foreach ([1 => t('إشراق المنتج'), 2 => t('نيون رياضي'), 3 => t('نظيف أزرق'), 4 => t('بنفسجي إبداعي'), 5 => t('كوربوريت راقٍ')] as $rk => $rl): ?>
            <button type="button" class="cp-ref-preset shrink-0 relative w-20 group" data-k="<?= $rk ?>" title="<?= e(t('أضِف «%s» كمرجع', $rl)) ?>">
              <img src="assets/ref-presets/ref<?= $rk ?>.webp?v=<?= e(APP_VERSION) ?>" alt="<?= e($rl) ?>" class="w-20 h-24 object-cover rounded-xl border border-ink-200 transition group-hover:border-brand-400 group-hover:shadow-lg" loading="lazy">
              <span data-ov class="absolute inset-0 grid place-items-center opacity-0 group-hover:opacity-100 transition bg-black/30 rounded-xl text-white text-xl font-bold" style="height:6rem">+</span>
              <span class="block text-[10px] text-ink-500 text-center mt-0.5 truncate"><?= e($rl) ?></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div>
        <span class="label"><?= e(t('طريقة إخراج التصميم')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('أهم اختيار: كيف يخرج شكل المنشور النهائي.')) ?></p>
        <?php $dmodes = [
          'layout' => ['🎨', t('قوالب تخطيط'), t('الذكاء يولّد الخلفية، ونظامنا يركّب التصميم والنص بهوية شركتك — نص عربي مضبوط دائمًا، والأقرب لشغل الوكالات.')],
          'ai' => ['✨', t('ذكاء اصطناعي'), t('الذكاء يولّد التصميم كاملًا بالنص داخله — أكثر تنوّعًا بصريًا، لكنه قد يكسر النص العربي أحيانًا (تجريبي).')],
        ]; ?>
        <div class="grid sm:grid-cols-2 gap-2" id="cp-dmode">
          <?php foreach ($dmodes as $mk => [$mi, $ml, $md]): ?>
          <label class="cursor-pointer"><input type="radio" name="design_mode" value="<?= e($mk) ?>" class="peer sr-only" <?= $mk === 'layout' ? 'checked' : '' ?>>
            <span class="block h-full rounded-xl border border-ink-200 p-3 transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
              <span class="text-xl"><?= $mi ?></span>
              <b class="block text-sm text-ink-900 mt-1"><?= e($ml) ?></b>
              <span class="block text-xs text-ink-500 mt-0.5 leading-snug"><?= e($md) ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <div id="cp-layout-wrap" class="mt-3">
          <span class="label !text-sm"><?= e(t('القالب')) ?></span>
          <div class="flex gap-2 overflow-x-auto pb-1" id="cp-layouts">
            <?php foreach (cpl_layouts() as $lk => $ll): ?>
            <label class="shrink-0 cursor-pointer"><input type="radio" name="layout" value="<?= e($lk) ?>" class="peer sr-only" <?= $lk === 'full' ? 'checked' : '' ?>>
              <span class="flex flex-col items-center gap-1 w-24 rounded-xl border border-ink-200 p-1.5 transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
                <span class="w-full h-14 rounded-lg relative overflow-hidden bg-ink-900 grid place-items-center text-[9px] text-white/70"><?= $lk === 'auto' ? '✨' : e($ll) ?></span>
                <span class="text-[11px] font-semibold text-center leading-tight"><?= e($ll) ?></span>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div>
        <span class="label"><?= e(t('نمط التصميم (ستايل الصورة)')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('اختر نمطًا جاهزًا، أو ارفع تصاميم مرجعية بالأعلى، أو اتركها تلقائية.')) ?></p>
        <?php
        $stylePal = [
          'auto'          => ['grad' => 'linear-gradient(135deg,#6366f1,#0ea5a4)', 'sub' => 'rgba(255,255,255,.4)',  'acc' => '#a3e635', 'dark' => 1, 'em' => '✨'],
          'cinematic'     => ['grad' => 'radial-gradient(circle at 68% 24%,#1f6f6a,#0b1220 72%)', 'sub' => 'rgba(34,211,238,.45)', 'acc' => '#22d3ee', 'dark' => 1, 'em' => ''],
          'neon_sport'    => ['grad' => 'linear-gradient(135deg,#020617,#06b6d4 130%)', 'sub' => 'rgba(6,182,212,.55)', 'acc' => '#22d3ee', 'dark' => 1, 'em' => ''],
          'luxury_gold'   => ['grad' => 'linear-gradient(135deg,#0b0b0b,#8a6a16)', 'sub' => 'rgba(212,175,55,.5)', 'acc' => '#d4af37', 'dark' => 1, 'em' => ''],
          'clean_product' => ['grad' => 'linear-gradient(135deg,#e0f2fe,#f8fafc)', 'sub' => 'rgba(37,99,235,.18)', 'acc' => '#2563eb', 'dark' => 0, 'em' => ''],
          'vibrant_pop'   => ['grad' => 'linear-gradient(135deg,#ec4899,#f97316)', 'sub' => 'rgba(255,255,255,.5)', 'acc' => '#ffffff', 'dark' => 1, 'em' => ''],
          'corporate_3d'  => ['grad' => 'linear-gradient(135deg,#1e3a8a,#38bdf8)', 'sub' => 'rgba(255,255,255,.42)', 'acc' => '#7dd3fc', 'dark' => 1, 'em' => ''],
          'nature_fresh'  => ['grad' => 'linear-gradient(135deg,#065f46,#34d399)', 'sub' => 'rgba(255,255,255,.42)', 'acc' => '#ecfccb', 'dark' => 1, 'em' => ''],
        ];
        $styleDesc = [
          'auto' => t('نختار النمط الأنسب تلقائيًا، أو من تصاميمك المرجعية.'),
          'cinematic' => t('مشهد سينمائي داكن فخم، إضاءة درامية وتوهّج بلون الهوية — إحساس إعلانات راقية.'),
          'neon_sport' => t('طاقة وحركة، خطوط نيون متوهّجة بلون الهوية، تباين عالٍ وجرأة.'),
          'luxury_gold' => t('فخامة بخلفية داكنة ولمسات ذهبية مع لون الهوية، وانعكاسات ناعمة.'),
          'clean_product' => t('منتج على خلفية فاتحة نظيفة، ظلال ناعمة، تكوين مينمال احترافي.'),
          'vibrant_pop' => t('ألوان زاهية مبهجة وأشكال هندسية جريئة — يوقف التمرير.'),
          'corporate_3d' => t('مشهد كوربوريت حديث بعناصر ثري دي وتدرّجات ناعمة بلون الهوية.'),
          'nature_fresh' => t('إضاءة نهار طبيعية مع ماء وخُضرة، تكوين منعش وحيوي.'),
        ];
        // معاينة بوستر مصغّرة توضّح شكل التصميم لكل نمط
        $poster = function ($sk) use ($stylePal) {
          $p = $stylePal[$sk] ?? $stylePal['auto'];
          $txtbar = $p['dark'] ? 'rgba(255,255,255,.55)' : 'rgba(15,23,42,.5)';
          $light = $p['dark'] ? '.22' : '.55';
          $logo = $p['dark'] ? 'rgba(255,255,255,.9)' : 'rgba(37,99,235,.9)';
          $h = '<span class="cp-pmock" style="position:relative;display:block;width:100%;aspect-ratio:4/5;border-radius:10px;overflow:hidden;background:' . $p['grad'] . '">';
          $h .= '<span style="position:absolute;inset:0;background:radial-gradient(60% 50% at 68% 24%,rgba(255,255,255,' . $light . '),transparent 70%)"></span>';
          $h .= '<span style="position:absolute;width:56%;height:40%;border-radius:9999px;filter:blur(8px);background:' . $p['sub'] . ';top:16%;inset-inline-end:8%"></span>';
          $h .= '<span style="position:absolute;inset-inline:9%;bottom:9%;display:block">';
          $h .= '<span style="display:block;height:10%;min-height:5px;width:72%;border-radius:3px;background:' . $p['acc'] . ';margin-bottom:7%"></span>';
          $h .= '<span style="display:block;height:7%;min-height:4px;width:46%;border-radius:3px;background:' . $txtbar . '"></span>';
          $h .= '</span>';
          $h .= '<span style="position:absolute;top:8%;inset-inline-start:8%;width:15%;aspect-ratio:1;border-radius:4px;background:' . $logo . '"></span>';
          if ($p['em']) $h .= '<span style="position:absolute;inset:0;display:grid;place-items:center;font-size:150%">' . $p['em'] . '</span>';
          return $h . '</span>';
        }; ?>
        <div id="cp-styles" class="flex gap-2 overflow-x-auto pb-1">
          <?php foreach (cpl_styles() as $sk => [$sl, $sd]): ?>
          <label class="shrink-0 cursor-pointer relative"><input type="radio" name="style" value="<?= e($sk) ?>" class="peer sr-only" <?= $sk === 'auto' ? 'checked' : '' ?>>
            <span class="flex flex-col items-center gap-1.5 w-24 rounded-xl border border-ink-200 p-1.5 transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
              <?= $poster($sk) ?>
              <span class="text-xs font-semibold text-center leading-tight"><?= e($sl) ?></span>
            </span>
            <button type="button" class="cp-prev-btn absolute top-2.5 end-2.5 w-6 h-6 rounded-lg bg-black/45 text-white grid place-items-center text-[12px] leading-none hover:bg-black/70" data-name="<?= e($sl) ?>" data-desc="<?= e($styleDesc[$sk] ?? '') ?>" title="<?= e(t('معاينة')) ?>" aria-label="<?= e(t('معاينة')) ?>">⤢</button>
          </label>
          <?php endforeach; ?>
        </div>
        <div id="cp-style-modal" class="hidden fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm grid place-items-center p-4" dir="rtl">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-ink-100"><b id="cp-style-name" class="text-ink-900"></b><button type="button" id="cp-style-x" class="text-ink-400 hover:text-ink-700 text-2xl leading-none">&times;</button></div>
            <div class="p-4">
              <div id="cp-style-big" class="mx-auto" style="width:250px"></div>
              <p id="cp-style-desc" class="mt-3 text-sm text-ink-600 text-center leading-relaxed"></p>
              <p class="mt-2 text-[11px] text-ink-400 text-center"><?= e(t('معاينة توضيحية للإحساس العام — النتيجة الفعلية تُولَّد بالذكاء بألوان هويتك.')) ?></p>
            </div>
          </div>
        </div>
        <script>
        (function () {
          var m = document.getElementById('cp-style-modal'); if (!m) return;
          var big = document.getElementById('cp-style-big'), nm = document.getElementById('cp-style-name'), ds = document.getElementById('cp-style-desc');
          function close() { m.classList.add('hidden'); document.body.style.overflow = ''; }
          Array.prototype.forEach.call(document.querySelectorAll('.cp-prev-btn'), function (b) {
            b.addEventListener('click', function (e) {
              e.preventDefault(); e.stopPropagation();
              var lbl = b.closest('label'), mock = lbl && lbl.querySelector('.cp-pmock'); if (!mock) return;
              big.innerHTML = ''; var c = mock.cloneNode(true); c.style.width = '100%'; big.appendChild(c);
              nm.textContent = b.getAttribute('data-name') || ''; ds.textContent = b.getAttribute('data-desc') || '';
              m.classList.remove('hidden'); document.body.style.overflow = 'hidden';
            });
          });
          document.getElementById('cp-style-x').addEventListener('click', close);
          m.addEventListener('click', function (e) { if (e.target === m) close(); });
          document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !m.classList.contains('hidden')) close(); });
        })();
        </script>
      </div>
      <div>
        <span class="label"><?= e(t('صور المنتج (اختياري، حتى 6 صور)')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('إن كان لديك منتج (علبة، عبوة، جهاز…)، فارفع صوره ليظهر داخل كل تصميم بشكله الحقيقي.')) ?></p>
        <div id="cp-prods" class="flex flex-wrap gap-2"></div>
        <div class="flex flex-wrap gap-2 mt-2"><label class="btn-ghost !py-1.5 cursor-pointer"><input id="cp-prod-in" type="file" accept="image/*" multiple class="hidden"><?= ui_icon('upload', 'w-4 h-4') ?><?= e(t('أضف صور المنتج')) ?></label><button type="button" id="cp-prod-lib" class="btn-ghost !py-1.5"><?= ui_icon('image', 'w-4 h-4') ?><?= e(t('اختر من مكتبة الوسائط')) ?></button></div>
      </div>
      <div>
        <span class="label"><?= e(t('جودة الصور')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('«الأعلى» ينتج تصاميم بمستوى الوكالات بتفاصيل وإضاءة أغنى (تكلفة أعلى). «جيدة» أسرع وأقل تكلفة.')) ?></p>
        <div class="flex gap-2">
          <?php foreach (['high' => [t('الأعلى (موصى به)'), t('مستوى وكالات')], 'medium' => [t('جيدة'), t('تكلفة أقل')]] as $qk => [$ql, $qd]): ?>
          <label class="shrink-0 cursor-pointer"><input type="radio" name="img_q" value="<?= e($qk) ?>" class="peer sr-only" <?= $qk === 'high' ? 'checked' : '' ?>>
            <span class="flex flex-col items-center justify-center gap-0.5 w-32 h-16 rounded-xl border border-ink-200 px-2 text-center transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
              <span class="text-sm font-semibold"><?= e($ql) ?></span>
              <span class="text-[10px] text-ink-500"><?= e($qd) ?></span>
            </span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <span class="label"><?= e(t('حجم الصور')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('نسبة أبعاد التصميمات المولّدة.')) ?></p>
        <div class="flex gap-2 flex-wrap">
          <?php foreach (['4:5' => [t('طولي'), '4:5', 'w-10 h-12'], '1:1' => [t('مربع'), '1:1', 'w-11 h-11'], '9:16' => [t('ستوري'), '9:16', 'w-8 h-14'], '16:9' => [t('عرضي'), '16:9', 'w-14 h-8']] as $ak => [$al, $ar, $box]): ?>
          <label class="shrink-0 cursor-pointer"><input type="radio" name="aspect" value="<?= e($ak) ?>" class="peer sr-only" <?= $ak === '4:5' ? 'checked' : '' ?>>
            <span class="flex flex-col items-center justify-center gap-1 w-20 h-20 rounded-xl border border-ink-200 px-2 text-center transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-50">
              <span class="<?= $box ?> rounded border-2 border-ink-300 bg-ink-100"></span>
              <span class="text-xs font-semibold"><?= e($al) ?> <span class="text-ink-400" dir="ltr"><?= e($ar) ?></span></span>
            </span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php $lgRow = function_exists('kb_logo') ? kb_logo($wid) : null; ?>
      <div class="rounded-2xl border border-ink-200 p-4">
        <span class="label"><?= e(t('اللوجو على التصميم')) ?></span>
        <?php if (!$lgRow): ?><p class="text-sm text-amber-700"><?= e(t('لا يوجد لوجو في ملف الشركة. أضفه من قاعدة المعرفة ليُركَّب على الصور تلقائيًا.')) ?> <a class="link" href="<?= e(url('knowledge')) ?>"><?= e(t('قاعدة المعرفة')) ?></a></p>
        <?php else: ?>
        <p class="text-xs text-ink-500 mb-3"><?= e(t('السيستم يركّب لوجو شركتك الحقيقي على كل صورة وشريحة (الذكاء الاصطناعي لا يرسمه).')) ?></p>
        <div class="flex items-center gap-3 flex-wrap">
          <div id="cp-lthumb" class="relative w-16 h-20 rounded-lg border border-ink-200 bg-cover bg-center overflow-hidden shrink-0" style="background-color:#0b1f3a;background-image:linear-gradient(135deg,#0b1f3a,#155e75)"><img id="cp-lthumb-logo" src="<?= e(url('media', ['file' => $lgRow['id']])) ?>" alt="" class="absolute object-contain"></div>
          <button type="button" id="cp-logo-open" class="btn-ghost"><?= ui_icon('image', 'w-4 h-4') ?><?= e(t('اضبط اللوجو ومكانه')) ?></button>
          <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="logo_on" checked><?= e(t('ركّب اللوجو على الصور')) ?></label>
        </div>
        <input type="hidden" name="logo_x" value="86"><input type="hidden" name="logo_y" value="9">
        <input type="hidden" name="logo_w" value="18"><input type="hidden" name="logo_op" value="100">

        <div id="cp-logo-modal" class="hidden fixed inset-0 z-[60] bg-black/60 backdrop-blur-sm grid place-items-center p-4" dir="rtl">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-auto">
            <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-ink-100 sticky top-0 bg-white z-10"><b class="text-ink-900"><?= e(t('ضبط اللوجو')) ?></b><button type="button" id="cp-logo-done" class="btn-primary !py-1.5"><?= e(t('تم')) ?></button></div>
            <div class="p-5 flex flex-wrap items-start gap-5">
              <div class="space-y-2">
                <div id="cp-lprev" class="relative w-64 aspect-[4/5] rounded-xl overflow-hidden border border-ink-200 bg-cover bg-center select-none touch-none" style="background-color:#0b1f3a;background-image:linear-gradient(135deg,#0b1f3a,#155e75)">
                  <div class="absolute inset-[7%] border border-dashed border-[#ffffff55] rounded pointer-events-none"></div>
                  <div id="cp-lplate" class="absolute rounded-lg pointer-events-none"></div>
                  <img id="cp-lprev-logo" src="<?= e(url('media', ['file' => $lgRow['id']])) ?>" alt="" draggable="false" class="absolute object-contain cursor-grab active:cursor-grabbing" title="<?= e(t('اسحب اللوجو لأي مكان، حتى خارج الإطار')) ?>">
                </div>
                <p class="text-[11px] text-ink-400 w-64"><?= e(t('اسحب اللوجو لأي مكان — حتى خارج حدود الصورة. الخط المتقطع = المنطقة الآمنة.')) ?></p>
                <div id="cp-lbgs" class="flex flex-wrap gap-1 w-64"></div>
              </div>
              <div class="space-y-3 text-sm min-w-0 flex-1">
                <div><span class="text-xs text-ink-500 block mb-1"><?= e(t('أماكن جاهزة')) ?></span>
                  <div class="grid grid-cols-3 gap-1.5 w-40" dir="ltr"><?php foreach (['top-left' => [14, 9], 'top-center' => [50, 9], 'top-right' => [86, 9], 'middle-left' => [14, 50], 'center' => [50, 50], 'middle-right' => [86, 50], 'bottom-left' => [14, 91], 'bottom-center' => [50, 91], 'bottom-right' => [86, 91]] as $pos => [$px, $py]): ?><button type="button" data-lp="<?= $px ?>,<?= $py ?>" class="h-8 rounded-lg border border-ink-200 relative hover:border-brand-300 [&.on]:border-brand-500 [&.on]:bg-brand-50"><span class="absolute w-3 h-2 rounded-sm bg-ink-400" style="left:calc(<?= $px ?>% - 6px);top:calc(<?= $py ?>% - 4px)"></span></button><?php endforeach; ?></div></div>
                <label class="block"><span class="text-xs text-ink-500 flex justify-between"><span><?= e(t('الحجم')) ?></span><b id="cp-lw-v" class="tabular-nums"></b></span><input type="range" min="8" max="45" value="18" class="w-full accent-brand-600" data-logo="logo_w"></label>
                <label class="block"><span class="text-xs text-ink-500 flex justify-between"><span><?= e(t('الشفافية')) ?></span><b id="cp-lo-v" class="tabular-nums"></b></span><input type="range" min="30" max="100" value="100" class="w-full accent-brand-600" data-logo="logo_op"></label>
                <div><span class="text-xs text-ink-500 block mb-1"><?= e(t('لون اللوجو')) ?></span><div class="flex flex-wrap gap-1.5"><?php foreach (['orig' => t('الأصلي'), 'white' => t('أبيض'), 'black' => t('أسود')] as $v => $l) echo $chip('logo_tint', $v, $l, $v === 'orig'); ?></div></div>
                <div><span class="text-xs text-ink-500 block mb-1"><?= e(t('خلفية خلف اللوجو')) ?></span><div class="flex flex-wrap gap-1.5"><?php foreach (['none' => t('بدون'), 'light' => t('فاتحة'), 'dark' => t('داكنة')] as $v => $l) echo $chip('logo_plate', $v, $l, $v === 'none'); ?></div></div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div id="cp-text-group" class="flex items-center gap-2 pt-2"><span class="text-base">🔤</span><h3 class="text-base font-bold text-ink-900"><?= e(t('إعدادات النص على التصميم')) ?></h3><span class="h-px flex-1 bg-ink-100"></span></div>
      <?php $cpFonts = ['auto' => [t('تلقائي (من الريفرنس/الهوية)'), ''], 'Cairo' => ['Cairo', 'geometric humanist sans, clean and modern'], 'Alexandria' => ['Alexandria', 'clean modern geometric sans'], 'Tajawal' => ['Tajawal', 'modern geometric sans, friendly'], 'Almarai' => ['Almarai', 'neutral rounded sans, highly legible'], 'Changa' => ['Changa', 'bold condensed display'], 'El Messiri' => ['El Messiri', 'elegant contemporary with calligraphic touch'], 'Reem Kufi' => ['Reem Kufi', 'kufic geometric display'], 'Amiri' => ['Amiri', 'classic Naskh serif, editorial'], 'Lateef' => ['Lateef', 'traditional Arabic'], 'IBM Plex Sans Arabic' => ['IBM Plex Sans Arabic', 'technical corporate sans']]; ?>
      <div id="cp-font-wrap">
        <span class="label"><?= e(t('خط العناوين')) ?></span>
        <p class="text-xs text-ink-500 mb-2"><?= e(t('يُستخدم كأسلوب للنص الذي نطبعه على الصور والشرائح (في وضع «قوالب تخطيط»).')) ?></p>
        <div class="grid sm:grid-cols-2 gap-3 items-stretch">
          <div class="space-y-2">
            <select name="font" id="cp-font-sel" class="input !py-2.5">
              <?php foreach ($cpFonts as $fk => [$fl, $fd]): ?>
              <option value="<?= e($fk) ?>" data-fam="<?= $fk === 'auto' ? '' : e($fk) ?>" <?= $fk === 'auto' ? 'selected' : '' ?>><?= e($fl) ?><?= $fd ? ' — ' . e($fd) : '' ?></option>
              <?php endforeach; ?>
            </select>
            <select name="txt_effect" id="cp-fx-sel" class="input !py-2.5">
              <?php foreach (['none' => t('بدون تأثير'), 'shadow' => t('ظل'), 'outline' => t('حدود (خط حول الحروف)'), 'sticker' => t('ستيكر (حدود بيضاء سميكة)'), '3d' => t('ثري دي (بروز ملوّن)'), 'gold' => t('ذهبي معدني'), 'glow' => t('توهّج (نيون)')] as $fxk => $fxl): ?>
              <option value="<?= e($fxk) ?>" <?= $fxk === 'none' ? 'selected' : '' ?>><?= e($fxl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="rounded-xl border border-ink-200 grid place-items-center px-4 py-4 overflow-hidden" style="background:linear-gradient(135deg,#0b1430,#1d4ed8)">
            <div id="cp-font-prev" class="text-center leading-tight text-white">
              <div class="text-3xl font-extrabold"><?= e(t('عنوان تجريبي')) ?></div>
              <div class="text-base mt-1.5 opacity-90" dir="ltr">Sample Heading</div>
            </div>
          </div>
        </div>
      </div>
      </section>
      <section data-step="4" class="space-y-5 hidden">
        <div><h2 class="text-lg font-bold text-ink-900"><?= e(t('مراجعة قبل الكتابة')) ?></h2><p class="text-sm text-ink-500"><?= e(t('راجع اختياراتك. يمكنك العودة إلى أي خطوة من الأعلى.')) ?></p></div>
        <dl id="cp-sum" class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm"></dl>
      <div class="rounded-2xl border border-ink-200 bg-ink-50/60 p-4">
        <div class="flex items-center gap-2 mb-2"><span class="text-base">👁️</span><b class="text-sm text-ink-900"><?= e(t('معاينة تقريبية للشكل')) ?></b><span class="text-[11px] text-ink-400"><?= e(t('(مجّانية — قبل صرف أي كريدِت)')) ?></span></div>
        <div id="cp-preview" class="grid place-items-center"></div>
      </div>
      <div class="flex flex-wrap gap-4 text-sm">
        <label class="flex items-center gap-1.5"><input type="checkbox" name="video" checked><?= e(t('برومبت فيديو لكل منشور')) ?></label>
        <label class="flex items-center gap-1.5"><input type="checkbox" name="carousel" checked><?= e(t('كاروسيل 5 شرائح')) ?></label>
        <label class="flex items-center gap-1.5" title="<?= e(t('مهارة Humanizer كاملة تدخل في برومبت الكتابة نفسه، فيُكتب المحتوى بأسلوب بشري من البداية دون تغيير المعلومات.')) ?>"><input type="checkbox" name="humanize" checked><?= e(t('الكتابة بمهارة Humanizer')) ?></label>
        <label class="flex items-center gap-1.5" title="<?= e(t('فحص كل منشور بعد كتابته وتصحيحه تلقائيًا إن وُجدت مشكلة. إيقافه أسرع ويوفّر الاستهلاك، لكن بدون تصحيح تلقائي.')) ?>"><input type="checkbox" name="qc" checked><?= e(t('فحص الجودة والتصحيح التلقائي')) ?></label>
      </div>
      </section>
      </div>
      <div class="flex items-center gap-3 border-t border-ink-100 px-5 py-3 bg-white">
        <button type="button" id="cp-back" class="btn-ghost invisible"><?= e(t('السابق')) ?></button>
        <span class="text-xs text-ink-400 flex-1"><?= e(t('المسودة تُحفظ على جهازك أثناء الكتابة.')) ?></span>
        <button type="button" id="cp-next" class="btn-primary"><?= e(t('التالي')) ?></button>
        <button type="submit" id="cp-go" class="btn-primary hidden"><?= e(t('اكتب الخطة')) ?></button>
      </div>
    </form>
    <?php if ($plans): ?>
    <h2 class="font-bold mb-2"><?= e(t('خططك')) ?></h2>
    <div class="card divide-y divide-ink-100">
      <?php foreach ($plans as $p): $st = ['generating' => [t('جارٍ التوليد'), 'badge-amber'], 'paused' => [t('متوقفة'), 'badge-amber'], 'review' => [t('للمراجعة'), 'badge-green'], 'done' => [t('مكتملة'), '']][$p['status']] ?? [$p['status'], '']; ?>
      <div class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-ink-50" data-plan="<?= (int)$p['id'] ?>">
        <a href="<?= e(url('content_plan', ['plan' => $p['id']])) ?>" class="min-w-0 flex-1"><b class="block truncate text-ink-900" data-pname><?= e($p['name']) ?></b><span class="text-xs text-ink-500"><?= e(fmt_date((string)$p['created_at'], 'd/m/Y')) ?></span></a>
        <span class="text-xs text-ink-500"><?= e(t('%s من %s جاهز', (int)$p['ready'], (int)$p['cnt'])) ?> · <?= e(t('%s معتمد', (int)$p['approved'])) ?></span>
        <span class="badge <?= $st[1] ?>"><?= e($st[0]) ?></span>
        <div class="flex items-center gap-1 shrink-0">
          <button type="button" data-prename class="p-1.5 rounded-lg text-ink-400 hover:bg-ink-100 hover:text-ink-700" title="<?= e(t('تعديل الاسم')) ?>" aria-label="<?= e(t('تعديل الاسم')) ?>"><?= ui_icon('pencil', 'w-4 h-4') ?></button>
          <button type="button" data-predel class="p-1.5 rounded-lg text-ink-400 hover:bg-rose-50 hover:text-rose-600" title="<?= e(t('حذف الخطة')) ?>" aria-label="<?= e(t('حذف الخطة')) ?>"><?= ui_icon('trash', 'w-4 h-4') ?></button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php $cfg['media'] = url('media'); ?><script>window.CP = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script>(function(){
      var box=document.querySelector('.card.divide-y'); if(!box||!window.CP)return; var C=window.CP;
      function post(d){var fd=new FormData();Object.keys(d).forEach(function(k){fd.append(k,d[k]);});fd.append('_csrf',C.csrf);fd.append('_ws',C.wid);return fetch(C.url,{method:'POST',body:fd,headers:{'X-WS-Id':C.wid}}).then(function(r){return r.json();});}
      box.addEventListener('click',function(e){
        var row=e.target.closest('[data-plan]'); if(!row)return; var id=row.getAttribute('data-plan');
        if(e.target.closest('[data-prename]')){ e.preventDefault(); var el=row.querySelector('[data-pname]'); var msg=<?= json_encode(t('اسم الخطة الجديد:'), JSON_UNESCAPED_UNICODE) ?>; (window.fxAsk?fxAsk(msg,{input:el.textContent.trim()}):Promise.resolve(prompt(msg,el.textContent.trim()))).then(function(nm){ nm=(nm||'').trim(); if(!nm)return; post({action:'rename',plan:id,name:nm}).then(function(j){ if(j&&j.ok)el.textContent=j.name; }); }); }
        else if(e.target.closest('[data-predel]')){ e.preventDefault(); var msg=<?= json_encode(t('حذف الخطة؟ المنشورات المجدولة أو المنشورة تبقى في المخزون.'), JSON_UNESCAPED_UNICODE) ?>; (window.fxAsk?fxAsk(msg):Promise.resolve(confirm(msg))).then(function(ok){ if(!ok)return; post({action:'delete',plan:id}).then(function(j){ if(j&&j.ok){ row.remove(); if(!box.querySelector('[data-plan]')) location.reload(); } }); }); }
      });
    })();</script>
    <?php endif; endif; ?>
    <?php $cfg['media'] = url('media'); ?><script>window.CP = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <?php if (!$plan): ?><script src="assets/extract.js?v=<?= e(APP_VERSION) ?>"></script><?php endif; ?>
    <?php if (!$plan): ?><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&family=Alexandria:wght@400;700&family=Tajawal:wght@400;700&family=Almarai:wght@400;700&family=Changa:wght@400;700&family=El+Messiri:wght@400;700&family=Reem+Kufi:wght@400;700&family=Amiri:wght@400;700&family=Lateef&family=IBM+Plex+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet"><?php endif; ?>
    <?php if ($plan): ?><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&family=Alexandria:wght@400;700;800&family=Tajawal:wght@400;700&display=swap" rel="stylesheet"><script src="assets/vendor/fabric-5.3.0.min.js"></script><?php endif; ?>
    <script src="assets/content-plan.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
