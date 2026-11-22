<?php
/* مخزون المحتوى: المنشورات المعتمدة من خطط المحتوى — أرفق صورة، انسخ البرومبتات، جدول فردي أو جماعي، طابور، أو نشر الآن */
$wid = (int)ws('id');
$J = function (array $d, int $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); exit(json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); };
$zerr = null; $z = zernio_or_null($zerr);
function cb_thumbs(array $ms): array { $wid = (int)ws('id'); return array_map(function ($m) use ($wid) { $md = media_get($wid, (int)$m); $kind = $md ? media_kind((string)($md['mime'] ?? '')) : 'image'; return ['id' => $m, 'thumb' => url('media', ['file' => $m, 'thumb' => 1]), 'url' => url('media', ['file' => $m]), 'kind' => $kind]; }, $ms); }
$fxd = getenv('UI_FIXTURES'); if ($fxd && is_file($fxd . '/cb_fake_z.php')) $z = require $fxd . '/cb_fake_z.php'; // اختبارات بس: مفيش نشر حقيقي

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    try {
        $ids = array_map('intval', (array)json_decode((string)($_POST['ids'] ?? '[]'), true));
        if ($a === 'media') { // يضيف ملف (أو يشيل واحد) — لحد 10 لكل منشور عشان الكاروسيل
            $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 404);
            $ms = $it['media_ids'];
            if (isset($_POST['remove'])) $ms = array_values(array_diff($ms, [(int)$_POST['remove']]));
            if (!empty($_FILES['file']['name'])) { if (count($ms) >= 10) $J(['ok' => false, 'error' => t('حتى 10 ملفات لكل منشور.')]); $row = media_store_upload($wid, $_FILES['file'], 'Content bank'); $ms[] = (int)$row['id']; }
            cpl_save_item($it['id'], ['media_ids' => $ms]);
            $J(['ok' => true, 'media' => cb_thumbs($ms)]);
        }
        if ($a === 'edit') { // تعديل نصوص المنشور inline من الكارت
            $it = cpl_item((int)($_POST['id'] ?? 0)); if (!$it) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 404);
            if (($it['status'] ?? '') !== 'approved') $J(['ok' => false, 'error' => t('مينفعش تعدّل منشور اتنشر أو اتجدول.')], 403);
            $f = []; foreach (['linkedin', 'x', 'headline', 'image_prompt', 'video_prompt'] as $k) if (array_key_exists($k, $_POST)) $f[$k] = (string)$_POST[$k];
            if (isset($_POST['carousel'])) { $car = json_decode((string)$_POST['carousel'], true); if (is_array($car)) $f['carousel'] = $car; }
            if ($f) cpl_save_item((int)$it['id'], $f);
            $J(['ok' => true]);
        }
        if ($a === 'back') { $J(['ok' => true, 'n' => cpl_set_status($ids, 'draft')]); }
        if ($a === 'spread') { $J(['ok' => true, 'times' => cpl_spread((string)($_POST['start'] ?? ''), (int)($_POST['every'] ?? 24), count($ids), (int)($_POST['from'] ?? 9), (int)($_POST['to'] ?? 21))]); }
        if ($a === 'publish') {
            if (!$z) $J(['ok' => false, 'error' => $zerr ?: t('اربط حساب النشر أولًا.')]);
            if (setting('post_approval_' . $wid, '0') === '1' && !can('posts.manage')) $J(['ok' => false, 'error' => t('المنشورات تحتاج موافقة المدير؛ النشر من المخزون متاح للمدير فقط.')], 403);
            $type = (string)($_POST['type'] ?? ''); if (!in_array($type, ['now', 'queue', 'schedule'], true)) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
            $accs = array_values(array_filter(array_map('strval', (array)json_decode((string)($_POST['accounts'] ?? '[]'), true))));
            $ids = array_slice($ids, 0, 60); session_write_close(); ignore_user_abort(true); /* لو النت فصل بعد الضغط، النشر يكمل والنتيجة تظهر بعد التحديث */ @set_time_limit(60 + 10 * count($ids));
            $times = (array)json_decode((string)($_POST['times'] ?? '[]'), true);
            if ($type === 'schedule') { $tz = new DateTimeZone(site_timezone()); foreach ($times as $tm) { $d = DateTime::createFromFormat('Y-m-d\TH:i:s', (string)$tm, $tz); if (!$d || $d < new DateTime('now', $tz)) $J(['ok' => false, 'error' => t('اختر موعدًا في المستقبل.')]); } }
            save_setting('cb_accounts_' . $wid . '_' . (int)(current_user()['id'] ?? 0), json_encode($accs));
            $J(['ok' => true, 'results' => cpl_publish($z, $ids, $accs, $type, array_values($times))]);
        }
    } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
    $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
}

// المخزون = المنشورات الجاهزة بس. أول ما يتجدول أو يتحط في الطابور أو يتنشر، يسيب المخزون ويتعامل معاملة المنشور العادي (في صفحة المنشورات/التقويم).
$items = cpl_items(0, ['approved']);
$plans = []; foreach (cpl_plans() as $p) $plans[(int)$p['id']] = (string)$p['name'];
$accounts = [];
if ($z) { try { foreach (acc_filter($z->accounts(zernio_profile())) as $a) $accounts[] = ['id' => (string)$a['_id'], 'platform' => (string)$a['platform'], 'name' => (string)($a['displayName'] ?? $a['username'] ?? $a['platform'])]; } catch (Throwable $e) { $zerr = $e->getMessage(); } }
render(t('مخزون المحتوى'), 'content_bank', function () use ($items, $plans, $accounts, $zerr, $wid) {
    foreach ($items as &$it) $it['media'] = cb_thumbs($it['media_ids']); unset($it);
    
    $saved = json_decode((string)setting('cb_accounts_' . $wid . '_' . (int)(current_user()['id'] ?? 0), '[]'), true) ?: [];
    $T = [
        'all' => t('تحديد الكل'), 'clearSel' => t('إلغاء التحديد'), 'sel' => t('%s محدد'), 'now' => t('انشر الآن'), 'queue' => t('أضف للطابور'), 'sched' => t('جدولة'), 'back' => t('إرجاع للمراجعة'), 'attach' => t('أرفق صورة/فيديو'), 'more' => t('+ ملف'), 'bulkAttach' => t('أرفق صورًا للمحدد بالترتيب'), 'bulkDone' => t('تم إرفاق %s ملف.'), 'bulkNone' => t('حدد العناصر الأول اللي عايز ترفعلها.'), 'bulkExtra' => t('اخترت %s ملف بس المحدد %s عنصر. الملفات الزيادة مش هتترفع. تكمل؟'), 'carNote' => t('للكاروسيل: أرفق الصور الخمس بالترتيب.'), 'change' => t('غيّر'), 'remove' => t('إزالة'),
        'copy' => t('نسخ'), 'copied' => t('تم النسخ'), 'img' => t('برومبت الصورة'), 'vid' => t('برومبت الفيديو'), 'car' => t('الكاروسيل'), 'li' => 'LinkedIn', 'x' => 'X',
        'hl' => t('العنوان'), 'saved' => t('تم الحفظ ✓'), 'editBtn' => t('تعديل'), 'viewBtn' => t('عرض'), 'close' => t('إغلاق'), 'cancel' => t('إلغاء'), 'save2' => t('حفظ التعديلات'),
        's_approved' => t('جاهز'), 's_scheduled' => t('مجدول %s'), 's_queued' => t('في الطابور'), 's_published' => t('تم النشر'),
        'accounts' => t('الحسابات'), 'noAcc' => t('اختر حسابًا واحدًا على الأقل.'), 'askNow' => t('نشر %s منشور الآن على %s حساب؟'), 'done' => t('تم: %s، فشل: %s'), 'err' => t('حصلت مشكلة. حاول مرة أخرى.'),
        'mOne' => t('موعد واحد لكل المحدد'), 'mSpread' => t('وزّع على مواعيد'), 'start' => t('البداية'), 'needStart' => t('اختر موعد البداية الأول.'), 'every' => t('كل'), 'hours' => t('ساعة'), 'days' => t('يوم'), 'window' => t('بين الساعة'), 'and' => t('و'), 'preview' => t('المواعيد المقترحة'), 'confirm' => t('جدول %s منشور'),
        'noImg' => t('بدون صورة: سيُنشر نصًا فقط. انسخ برومبت الصورة وولّدها ثم أرفقها.'), 'xNote' => t('المنصات ذات الحد القصير (X وما شابهها) تأخذ النص القصير، وباقي المنصات تأخذ النص الطويل (لينكدإن).'), 'offline' => t('انقطع الاتصال. لم يتغير شيء؛ حاول مرة أخرى.'), 'empty' => t('لا شيء في هذا العرض.'),
    ];
    ?>
    <p class="text-sm text-ink-500 mb-4"><?= e(t('المنشورات التي اعتمدتها من خطط المحتوى. ولّد الصورة من البرومبت بأي أداة ثم أرفقها، واختر: نشر الآن، أو طابور، أو جدولة فردية أو موزعة.')) ?> <a class="link" href="<?= e(url('content_plan')) ?>"><?= e(t('محتوى جديد')) ?></a></p>
    <?php if ($zerr): ?><div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 mb-4"><?= e($zerr) ?></div><?php endif; ?>
    <?php if (!$items): ?><div class="card"><div class="ds-state"><div class="ds-state-ico"><?= ui_icon('layers', 'w-7 h-7') ?></div><div class="ds-state-title"><?= e(t('المخزون فارغ')) ?></div><div class="ds-state-text"><?= e(t('اكتب خطة محتوى واعتمد منها منشورات، وهتلاقيها هنا جاهزة للنشر والجدولة.')) ?></div><div class="ds-state-actions"><a class="btn-primary" href="<?= e(url('content_plan')) ?>"><?= e(t('إنشاء المحتوى')) ?></a></div></div></div>
    <?php else: ?>
    <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
      <?php if (count($plans) > 1): ?><select id="cb-plan" class="input !mt-0 !py-2 !w-auto"><option value=""><?= e(t('كل الخطط')) ?></option><?php foreach ($plans as $id => $n): ?><option value="<?= $id ?>"><?= e($n) ?></option><?php endforeach; ?></select><?php endif; ?>
      <span class="relative flex-1 min-w-[12rem]"><span class="absolute inset-y-0 start-3 flex items-center text-ink-400"><?= ui_icon('search', 'w-4 h-4') ?></span><input id="cb-q" type="search" class="input !mt-0 !py-2 ps-10 w-full" placeholder="<?= e(t('ابحث في النصوص…')) ?>"></span>
      <select id="cb-type" class="input !mt-0 !py-2 !w-auto" title="<?= e(t('فلترة حسب النوع')) ?>">
        <option value=""><?= e(t('كل الأنواع')) ?></option>
        <option value="img"><?= e(t('فيه صورة مرفقة')) ?></option>
        <option value="noimg"><?= e(t('بدون صورة')) ?></option>
        <option value="vid"><?= e(t('فيه فيديو')) ?></option>
        <option value="car"><?= e(t('فيه كاروسيل')) ?></option>
        <option value="text"><?= e(t('نص فقط')) ?></option>
      </select>
      <button type="button" id="cb-all" class="btn-ghost !py-2 text-sm whitespace-nowrap"><?= e(t('تحديد الكل')) ?></button>
    </div>
    <div id="cb-bar" class="hidden sticky top-2 z-10 card p-3 mb-3 space-y-2 text-sm">
      <div class="flex flex-wrap items-center gap-2"><b id="cb-n"></b><span class="flex-1"></span>
        <button type="button" data-do="now" class="btn-ghost !py-1.5"><?= e($T['now']) ?></button><button type="button" data-do="queue" class="btn-ghost !py-1.5"><?= e($T['queue']) ?></button><button type="button" data-do="sched" class="btn-primary !py-1.5"><?= e($T['sched']) ?></button><button type="button" data-do="back" class="btn-ghost !py-1.5"><?= e($T['back']) ?></button><label class="btn-ghost !py-1.5 cursor-pointer" title="<?= e(t('اختر صورًا كثيرة مرة واحدة: الأولى للمنشور الأول المحدد، والثانية للثاني، وهكذا.')) ?>"><input id="cb-bulk" type="file" accept="image/*,video/*" multiple class="hidden"><?= e($T['bulkAttach']) ?></label></div>
      <div class="flex flex-wrap items-center gap-2"><span class="text-ink-500"><?= e($T['accounts']) ?>:</span>
        <?php foreach ($accounts as $a): ?><label class="cb-acc-lbl inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-3 py-1.5"><input type="checkbox" class="cb-acc sr-only" value="<?= e($a['id']) ?>" data-pf="<?= e($a['platform']) ?>" <?= in_array($a['id'], $saved, true) ? 'checked' : '' ?>><?= platform_icon($a['platform'], 'w-3.5 h-3.5') ?><?= e($a['name']) ?></label><?php endforeach; ?>
        <span class="text-xs text-ink-400"><?= e($T['xNote']) ?></span></div>
      <div id="cb-sch" class="hidden rounded-xl border border-ink-200 p-3 space-y-2">
        <div class="flex flex-wrap gap-4"><label class="flex items-center gap-1.5"><input type="radio" name="cbm" value="one" checked><?= e($T['mOne']) ?></label><label class="flex items-center gap-1.5"><input type="radio" name="cbm" value="spread"><?= e($T['mSpread']) ?></label></div>
        <div class="flex flex-wrap items-end gap-2">
          <label class="block"><span class="label"><?= e($T['start']) ?></span><input id="cb-start" type="datetime-local" class="input !mt-0"></label>
          <span class="cb-spread hidden contents"><label class="block"><span class="label"><?= e($T['every']) ?></span><span class="flex gap-1"><input id="cb-every" type="number" min="1" value="1" class="input !mt-0 !w-20"><select id="cb-unit" class="input !mt-0 !w-auto"><option value="24"><?= e($T['days']) ?></option><option value="1"><?= e($T['hours']) ?></option></select></span></label>
          <label class="block"><span class="label"><?= e($T['window']) ?></span><span class="flex items-center gap-1"><input id="cb-from" type="number" min="0" max="23" value="9" class="input !mt-0 !w-16"><?= e($T['and']) ?><input id="cb-to" type="number" min="1" max="24" value="21" class="input !mt-0 !w-16"></span></label></span>
          <button type="button" id="cb-go" class="btn-primary"></button>
        </div>
        <div id="cb-prev" class="text-xs text-ink-500"></div>
      </div>
    </div>
    <div id="cb-list" class="grid md:grid-cols-2 xl:grid-cols-3 gap-3"></div>
    <?php endif; ?>
    <script>window.CB = <?= json_encode(['url' => url('content_bank'), 'csrf' => csrf_token(), 'items' => $items, 'plans' => $plans, 't' => $T, 'tz' => site_timezone()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="assets/content-bank.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
