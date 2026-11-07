<?php
/** أدوات جمع العملاء (#51 #52 #53 #54 #56 #57 #58 #60) — كلها مجانية ومن غير ذكاء اصطناعي */
$wid = (int)ws('id');
$mgr = can('crm.manage') || is_ws_owner();
$J = function (array $d, int $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
$cfg = json_decode((string)setting('crm_form_cfg_' . $wid, ''), true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    // إعدادات الشركة (الفورم والصفحات والشيت) لمدير العملاء بس؛ الباقي (كارت، لينكات) لأي حد بيشوف العملاء
    if (in_array($a, ['form_cfg', 'lp_save', 'lp_del', 'sheet_save', 'sheet_run'], true) && !$mgr) { flash('error', t('مالكش صلاحية.')); redirect(url('crm_capture')); }
    if ($a === 'form_cfg') {
        $cfg['qualify'] = !empty($_POST['qualify']) ? 1 : 0; $cfg['budgets'] = mb_substr(trim((string)($_POST['budgets'] ?? '')), 0, 400);
        save_setting('crm_form_cfg_' . $wid, json_encode($cfg, JSON_UNESCAPED_UNICODE)); flash('success', t('اتحفظ ✓')); redirect(url('crm_capture') . '#qualify');
    }
    if ($a === 'lp_save') {
        $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower((string)($_POST['slug'] ?? ''))), '-') ?: 'p' . substr(bin2hex(random_bytes(3)), 0, 5);
        $slug = substr($slug, 0, 40);
        $row = ['slug' => $slug, 'on' => !empty($_POST['on']) ? 1 : 0, 'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 140), 'sub' => mb_substr(trim((string)($_POST['sub'] ?? '')), 0, 240),
            'body' => mb_substr(trim((string)($_POST['body'] ?? '')), 0, 3000), 'img' => preg_match('~^https://~i', trim((string)($_POST['img'] ?? ''))) ? mb_substr(trim((string)$_POST['img']), 0, 600) : '',
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#4f46e5', 'wa' => preg_replace('/\D/', '', (string)($_POST['wa'] ?? '')), 'cta' => mb_substr(trim((string)($_POST['cta'] ?? '')), 0, 60)];
        if ($row['title'] === '') { flash('error', t('اكتب عنوان للصفحة.')); redirect(url('crm_capture') . '#lp'); }
        $all = lp_list(); $old = (string)($_POST['old'] ?? ''); $found = false;
        foreach ($all as &$x) if ($x['slug'] === ($old ?: $slug)) { $row['views'] = (int)($x['views'] ?? 0); $x = $row; $found = true; } unset($x);
        if (!$found) { foreach ($all as $x) if ($x['slug'] === $slug) { flash('error', t('الرابط ده مستخدم. اختار اسم تاني.')); redirect(url('crm_capture') . '#lp'); } $all[] = $row; }
        save_setting('crm_lp_' . $wid, json_encode(array_slice($all, 0, 30), JSON_UNESCAPED_UNICODE)); log_activity('crm.lp_save', $slug);
        flash('success', t('اتحفظت الصفحة ✓')); redirect(url('crm_capture') . '#lp');
    }
    if ($a === 'lp_del') {
        $s = (string)($_POST['slug'] ?? ''); save_setting('crm_lp_' . $wid, json_encode(array_values(array_filter(lp_list(), function ($x) use ($s) { return $x['slug'] !== $s; })), JSON_UNESCAPED_UNICODE));
        redirect(url('crm_capture') . '#lp');
    }
    if ($a === 'wa_make') { $r = cap_wa_link((string)($_POST['phone'] ?? ''), (string)($_POST['msg'] ?? ''), mb_substr(trim((string)($_POST['label'] ?? '')), 0, 160)); $r ? $J(['ok' => true] + $r) : $J(['error' => t('اكتب رقم واتساب صحيح بكود الدولة.')], 422); }
    if ($a === 'ref_link') { $l = crm_lead((int)($_POST['lead'] ?? 0)); $l ? $J(['ok' => true, 'url' => cap_ref_link((int)$l['id']), 'name' => $l['name']]) : $J(['error' => t('بيانات ناقصة')], 422); }
    if ($a === 'card_parse') {
        $txt = mb_substr((string)($_POST['text'] ?? ''), 0, 4000); $c = crm_extract_contacts($txt);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', $txt)), function ($x) { return mb_strlen($x) >= 2; }));
        $name = $company = $title = $site = '';
        foreach ($lines as $ln) {
            if (preg_match('~(https?://|www\.)\S+~i', $ln, $m)) { $site = $site ?: $m[0]; continue; }
            if (preg_match('/@|\d{5,}/', $ln)) continue;
            if ($title === '' && preg_match('/manager|director|ceo|founder|owner|sales|engineer|consultant|head|مدير|مؤسس|مهندس|مستشار|مسؤول|رئيس|مندوب/iu', $ln)) { $title = $ln; continue; }
            if ($company === '' && preg_match('/\b(co|company|ltd|llc|inc|group|est)\b|شركة|مؤسسة|مجموعة|للتجارة|للمقاولات/iu', $ln)) { $company = $ln; continue; }
            if ($name === '' && mb_strlen($ln) <= 40 && preg_match('/^[\p{L}\s.\'-]+$/u', $ln)) { $name = $ln; continue; }
        }
        $J(['ok' => true, 'name' => $name, 'phone' => $c['phone'], 'email' => $c['email'], 'company' => $company, 'title' => $title, 'site' => $site]);
    }
    if ($a === 'card_save') {
        $l = crm_lead_upsert(['source' => 'manual', 'name' => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255) ?: t('كارت بزنس'), 'phone' => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 40), 'email' => mb_substr(trim((string)($_POST['email'] ?? '')), 0, 255),
            'company' => mb_substr(trim((string)($_POST['company'] ?? '')), 0, 255), 'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255), 'owner_id' => (int)(current_user()['id'] ?? 0)]);
        if (!$l) $J(['error' => t('اكتب رقم أو إيميل على الأقل.')], 422);
        crm_act_add((int)$l['id'], 'note', '🪪 ' . t('اتضاف من كارت بزنس') . (trim((string)($_POST['event'] ?? '')) !== '' ? ' — ' . mb_substr(trim((string)$_POST['event']), 0, 120) : ''));
        $J(['ok' => true, 'id' => (int)$l['id'], 'merged' => !empty($l['merged'])]);
    }
    if ($a === 'sheet_save') {
        $u = trim((string)($_POST['url'] ?? ''));
        if ($u !== '' && !preg_match('~^https://docs\.google\.com/spreadsheets/~', $u)) { flash('error', t('حط رابط Google Sheets بس.')); redirect(url('crm_capture') . '#sheet'); }
        $c = cap_sheet_cfg(); if ($c['url'] !== $u) $c = ['url' => $u, 'seen' => !empty($_POST['skip_old']) ? PHP_INT_MAX : 0, 'last' => '', 'err' => ''];
        save_setting('crm_sheet_' . $wid, json_encode($c, JSON_UNESCAPED_UNICODE));
        if ($u !== '') { $n = cap_sheet_tick(); $c = cap_sheet_cfg(); $c['err'] ? flash('error', $c['err']) : flash('success', t('اتربط ✓ واتضاف %s عميل جديد.', $n)); } else flash('success', t('اتفصل الربط.'));
        redirect(url('crm_capture') . '#sheet');
    }
    if ($a === 'sheet_run') { $n = cap_sheet_tick(); $c = cap_sheet_cfg(); $c['err'] ? flash('error', $c['err']) : flash('success', t('اتضاف %s عميل جديد.', $n)); redirect(url('crm_capture') . '#sheet'); }
}

$formUrl = crm_form_url(); $bookUrl = crm_book_url(); $callUrl = $formUrl . '&m=call';
$lps = lp_list(); $sheet = cap_sheet_cfg();
// "Infinity" بعد json لو اخترت تتخطى القديم: نعرضه كأنه صفر
if ((int)$sheet['seen'] > 1e9) $sheet['seen'] = 0;
$st = db()->prepare("SELECT id, name, company FROM crm_leads WHERE workspace_id = ? AND stage IN ('won','proposal','qualified') ORDER BY stage = 'won' DESC, id DESC LIMIT 300"); $st->execute([$wid]); $refLeads = $st->fetchAll();
$st = db()->prepare("SELECT r.id, r.name, COUNT(x.id) n, SUM(x.stage = 'won') w FROM crm_leads x JOIN crm_leads r ON r.id = x.referred_by WHERE x.workspace_id = ? GROUP BY r.id, r.name ORDER BY n DESC LIMIT 10"); $st->execute([$wid]); $refTop = $st->fetchAll();
$st = db()->prepare("SELECT l.label, l.code, l.clicks, (SELECT COUNT(*) FROM crm_leads x WHERE x.workspace_id = l.workspace_id AND x.link_code = l.code) leads FROM crm_links l WHERE l.workspace_id = ? AND l.url LIKE 'https://wa.me/%' ORDER BY l.id DESC LIMIT 20"); $st->execute([$wid]); $waLinks = $st->fetchAll();

// مصادر العملاء آخر 30 يوم مقارنة بالـ 30 اللي قبلهم
$st = db()->prepare("SELECT source, SUM(created_at >= NOW() - INTERVAL 30 DAY) a, SUM(created_at < NOW() - INTERVAL 30 DAY) b FROM crm_leads WHERE workspace_id = ? AND created_at >= NOW() - INTERVAL 60 DAY GROUP BY source"); $st->execute([$wid]);
$srcStat = []; $srcL = crm_sources(); foreach ($st->fetchAll() as $r) $srcStat[] = ['k' => (string)$r['source'], 'l' => (string)($srcL[$r['source']] ?? $r['source']), 'a' => (int)$r['a'], 'b' => (int)$r['b']];
$st = db()->prepare("SELECT COUNT(*) FROM crm_leads WHERE workspace_id = ? AND referred_by IS NOT NULL AND created_at >= NOW() - INTERVAL 30 DAY"); $st->execute([$wid]); $refN = (int)$st->fetchColumn();
render(t('جمع العملاء'), 'crm_capture', function () use ($cfg, $formUrl, $bookUrl, $callUrl, $lps, $sheet, $refLeads, $refTop, $waLinks, $srcStat, $refN) {
    $qrBtn = function (string $u, string $name) { return '<button type="button" class="btn-ghost !py-1 !text-xs" data-qr="' . e($u) . '" data-qrn="' . e($name) . '">▦ QR</button>'; };
    $copy = function (string $u) { return '<button type="button" class="btn-ghost !py-1 !text-xs" data-copy="' . e($u) . '">' . e(t('نسخ')) . '</button>'; };
    $sec = 'card p-5 scroll-mt-24';
    ?>
  <div id="cap-ov"></div>
  <script>window.CAPU = <?= json_encode(['src' => $srcStat, 'ref' => $refN, 'lpViews' => array_sum(array_map(function ($x) { return (int)($x['views'] ?? 0); }, $lps)), 'lpN' => count($lps), 'waClicks' => array_sum(array_column($waLinks, 'clicks')), 'waLeads' => array_sum(array_column($waLinks, 'leads')), 'prospects' => url('prospects'),
    'T' => ['title' => t('مصادر العملاء آخر 30 يوم'), 'new' => t('عميل جديد'), 'vs' => t('%s عن الـ 30 يوم اللي قبلها'), 'lp' => t('زيارة لصفحات الهبوط'), 'wa' => t('ضغطة واتساب'), 'waConv' => t('%s اتحولوا لعملاء (%s)'), 'ref' => t('عميل بالإحالة'),
      'none' => t('لسه مفيش عملاء في آخر 60 يوم. ابدأ بفورم أو رابط واتساب متتبع.'), 'best' => t('أقوى مصدر: %s'), 'all' => t('عرض الكل (%s)'), 'less' => t('عرض أقل'), 'find' => t('ابحث…'), 'open' => t('افتح العملاء'), 'fresh' => t('جديد')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <div class="mb-4 flex flex-wrap gap-1.5 text-xs" id="cap-nav">
    <?php foreach (['links' => ['link', t('روابط وQR')], 'qualify' => ['target', t('فورم ذكي')], 'lp' => ['file', t('صفحات هبوط')], 'wa' => ['chat', t('واتساب متتبع')], 'ref' => ['users', t('الإحالة')], 'card' => ['id', t('كارت بزنس')], 'sheet' => ['chart', 'Google Sheets']] as $k => [$ic, $v]): ?><a href="#<?= $k ?>" class="chip inline-flex items-center gap-1.5"><?= ui_icon($ic, 'w-4 h-4') ?><?= e($v) ?></a><?php endforeach; ?>
  </div>
  <div class="grid xl:grid-cols-2 gap-4">
    <!-- #53 #58 -->
    <section id="links" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg> <?= e(t('روابطك الجاهزة + QR')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('اطبع الـ QR وحطه في المحل أو المعرض أو على الكروت. اللي يمسحه بيوصل للفورم ويتسجل Lead على طول.')) ?></p>
      <div class="space-y-2 text-sm">
        <?php foreach ([[t('فورم التواصل'), $formUrl], [t('فورم "اتصل بيا" (رقم بس، زرار واحد)'), $callUrl], [t('صفحة الحجز'), $bookUrl]] as [$n, $u]): ?>
          <div class="flex flex-wrap items-center gap-2 rounded-xl border border-ink-200 p-2.5"><span class="flex-1 min-w-[160px] font-medium text-ink-800"><?= e($n) ?></span><?= $copy($u) ?><a href="<?= e($u) ?>" target="_blank" class="btn-ghost !py-1 !text-xs"><?= e(t('افتح')) ?></a><?= $qrBtn($u, $n) ?></div>
        <?php endforeach; ?>
      </div></section>

    <!-- #51 -->
    <section id="qualify" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z"/></svg> <?= e(t('فورم ذكي بأسئلة تأهيل')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('بيسأل عن الميزانية وإمتى محتاج. اللي عاوز يبدأ دلوقتي بياخد نقط أعلى ويطلع فوق في العملاء.')) ?></p>
      <form method="post" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="form_cfg">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="qualify" value="1" <?= !empty($cfg['qualify']) ? 'checked' : '' ?>> <b><?= e(t('شغّل أسئلة التأهيل في الفورم')) ?></b></label>
        <label class="block text-xs text-ink-600"><?= e(t('اختيارات الميزانية (مفصولة بفاصلة)')) ?><input name="budgets" class="input text-sm" dir="auto" value="<?= e((string)($cfg['budgets'] ?? '')) ?>" placeholder="<?= e(implode('، ', cap_budgets([]))) ?>"></label>
        <button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button></form></section>

    <!-- #52 -->
    <section id="lp" class="<?= $sec ?> xl:col-span-2"><div class="flex items-center gap-2 mb-1"><h2 class="font-semibold text-ink-900 flex-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6"/></svg> <?= e(t('صفحات هبوط للحملات')) ?></h2><button type="button" class="btn-primary !py-1.5 !text-xs" data-lp-new>+ <?= e(t('صفحة جديدة')) ?></button></div>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('صفحة لكل حملة من غير موقع: عنوان، وصف، صورة، زرار واتساب، وفورم. حط لينكها في الإعلان أو البايو، وكل اللي يسجّل بيتعلّم باسم الحملة.')) ?></p>
      <?php if (!$lps): ?><p class="text-sm text-ink-400 py-4 text-center"><?= e(t('لسه مفيش صفحات.')) ?></p><?php endif; ?>
      <div class="divide-y divide-ink-100">
        <?php foreach ($lps as $x): $u = lp_url($x['slug']); ?>
          <div class="cap-row flex flex-wrap items-center gap-2 py-2.5 text-sm" data-v="<?= (int)($x['views'] ?? 0) ?>" data-q="<?= e(mb_strtolower($x['title'] . ' ' . $x['slug'])) ?>"><span class="w-2 h-2 rounded-full <?= !empty($x['on']) ? 'bg-lime-500' : 'bg-ink-300' ?>"></span>
            <span class="flex-1 min-w-[180px]"><b class="text-ink-900"><?= e($x['title']) ?></b> <span class="text-[11px] text-ink-400" dir="ltr">/<?= e($x['slug']) ?></span> <span class="text-[11px] text-ink-500"> · <?= e(t('%s زيارة', (int)($x['views'] ?? 0))) ?></span></span>
            <?= $copy($u) ?><a href="<?= e($u) ?>" target="_blank" class="btn-ghost !py-1 !text-xs"><?= e(t('افتح')) ?></a><?= $qrBtn($u, $x['title']) ?>
            <button type="button" class="btn-ghost !py-1 !text-xs" data-lp-edit='<?= e(json_encode($x, JSON_UNESCAPED_UNICODE)) ?>'><?= e(t('تعديل')) ?></button>
            <form method="post" onsubmit="return confirm('<?= tjs('تمسح الصفحة؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="lp_del"><input type="hidden" name="slug" value="<?= e($x['slug']) ?>"><button class="text-xs text-red-600 px-2"><?= e(t('حذف')) ?></button></form></div>
        <?php endforeach; ?>
      </div>
      <form method="post" id="lp-form" class="hidden mt-3 grid md:grid-cols-2 gap-2 rounded-2xl border border-ink-200 bg-ink-50/50 p-3 text-sm"><?= csrf_field() ?><input type="hidden" name="action" value="lp_save"><input type="hidden" name="old">
        <?php $kbS = function_exists('kb_get') ? (array)(kb_get((int)ws('id'))['services']['rows'] ?? []) : []; if ($kbS): $kbAll = kb_get((int)ws('id')); ?>
        <label class="text-xs text-ink-600 md:col-span-2 rounded-xl border border-lime-200 bg-lime-50/60 p-2"><span class="inline-flex items-center gap-1 font-semibold text-lime-800"><?= ui_icon('spark', 'w-3.5 h-3.5') ?><?= e(t('املأ الصفحة من خدمة في ملف الشركة')) ?></span>
          <select id="lp-from" class="input !mt-1 text-sm"><option value=""><?= e(t('اختار خدمة…')) ?></option><?php foreach ($kbS as $i => $sv): if (trim((string)($sv['name'] ?? '')) === '') continue; ?><option value="<?= $i ?>"><?= e($sv['name'] . (!empty($sv['price']) ? ' · ' . $sv['price'] : '')) ?></option><?php endforeach; ?></select></label>
        <script>window.LPKB = <?= json_encode(['svc' => array_values($kbS), 'tagline' => (string)($kbAll['company']['tagline'] ?? ''), 'about' => (string)($kbAll['company']['about'] ?? ''), 'delivery' => (string)($kbAll['company']['delivery'] ?? ''), 'area' => (string)($kbAll['company']['service_area'] ?? ''), 'wa' => preg_replace('/\D/', '', (string)($kbAll['contact']['whatsapp'] ?? $kbAll['contact']['phone'] ?? '')), 'price' => t('السعر: %s'), 'cta' => t('اطلب دلوقتي'), 'area2' => t('بنوصّل: %s')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
        <?php endif; ?>
        <label class="text-xs text-ink-600"><?= e(t('العنوان')) ?><input name="title" required maxlength="140" class="input text-sm" dir="auto"></label>
        <label class="text-xs text-ink-600"><?= e(t('اسم الرابط (حروف إنجليزي)')) ?><input name="slug" maxlength="40" class="input text-sm" dir="ltr" placeholder="ramadan-offer"></label>
        <label class="text-xs text-ink-600 md:col-span-2"><?= e(t('سطر تحت العنوان')) ?><input name="sub" maxlength="240" class="input text-sm" dir="auto"></label>
        <label class="text-xs text-ink-600 md:col-span-2"><?= e(t('التفاصيل')) ?><textarea name="body" rows="4" maxlength="3000" class="input text-sm" dir="auto"></textarea></label>
        <label class="text-xs text-ink-600"><?= e(t('رابط الصورة (https)')) ?><input name="img" class="input text-sm" dir="ltr"></label>
        <label class="text-xs text-ink-600"><?= e(t('رقم واتساب بكود الدولة (اختياري)')) ?><input name="wa" class="input text-sm" dir="ltr" placeholder="9665…"></label>
        <label class="text-xs text-ink-600"><?= e(t('نص الزرار')) ?><input name="cta" maxlength="60" class="input text-sm" dir="auto" placeholder="<?= e(t('سجّل اهتمامك')) ?>"></label>
        <label class="text-xs text-ink-600"><?= e(t('اللون')) ?><input type="color" name="color" value="#4f46e5" class="input !h-10 !p-1"></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="on" value="1" checked> <?= e(t('الصفحة شغالة')) ?></label>
        <div class="flex gap-2 md:justify-end"><button type="button" class="btn-ghost !py-1.5 !text-xs" data-lp-cancel><?= e(t('إلغاء')) ?></button><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button></div>
      </form></section>

    <!-- #57 -->
    <section id="wa" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg> <?= e(t('لينك واتساب متتبع')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('لينك لكل إعلان أو منشور. الرسالة بتتفتح فيها كود صغير زي #Ab12Cd3، ولما العميل يبعتها ويتضاف Lead بنعرف جه منين. والضغطات بتتعد.')) ?></p>
      <div class="grid sm:grid-cols-2 gap-2 text-sm"><input id="wa-ph" class="input !mt-0 text-sm" dir="ltr" placeholder="<?= e(t('رقم واتساب بكود الدولة')) ?>"><input id="wa-lb" class="input !mt-0 text-sm" dir="auto" placeholder="<?= e(t('اسمه (مثلًا: إعلان العيد)')) ?>">
        <input id="wa-msg" class="input !mt-0 text-sm sm:col-span-2" dir="auto" value="<?= e(t('السلام عليكم، عاوز أعرف تفاصيل أكتر')) ?>"><button type="button" id="wa-go" class="btn-primary !py-1.5 !text-xs sm:col-span-2"><?= e(t('اعمل اللينك')) ?></button></div>
      <div id="wa-out" class="mt-2"></div>
      <?php if ($waLinks): ?><div class="mt-3 divide-y divide-ink-100 text-xs"><?php foreach ($waLinks as $w): ?><div class="cap-row flex items-center gap-2 py-1.5" data-v="<?= (int)$w['clicks'] ?>" data-l="<?= (int)$w['leads'] ?>" data-q="<?= e(mb_strtolower((string)$w['label'])) ?>"><span class="flex-1 truncate"><?= e($w['label'] ?: '#' . $w['code']) ?></span><span><?= e(t('%s ضغطة', (int)$w['clicks'])) ?></span><span class="text-lime-700 font-semibold"><?= e(t('%s عميل', (int)$w['leads'])) ?></span><?= $qrBtn(crm_link_short($w['code']), (string)($w['label'] ?: $w['code'])) ?></div><?php endforeach; ?></div><?php endif; ?>
    </section>

    <!-- #54 -->
    <section id="ref" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a6 6 0 0112 0v1M16 3.5a4 4 0 010 7.5M22 21v-1a6 6 0 00-4-5.6"/></svg> <?= e(t('برنامج الإحالة')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('ابعت لكل عميل لينك خاص بيه. أي حد يسجّل منه بيتكتب إنه جه من العميل ده، وبياخد نقط أعلى، والعميل الأصلي بيتسجل عنده إنه جاب حد.')) ?></p>
      <div class="flex gap-2"><select id="ref-l" class="input !mt-0 text-sm flex-1"><option value=""><?= e(t('اختار عميل')) ?></option><?php foreach ($refLeads as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['name'] . ($l['company'] ? ' · ' . $l['company'] : '')) ?></option><?php endforeach; ?></select><button type="button" id="ref-go" class="btn-primary !py-1.5 !text-xs"><?= e(t('اللينك')) ?></button></div>
      <div id="ref-out" class="mt-2"></div>
      <?php if ($refTop): ?><div class="mt-3 text-xs"><div class="font-semibold text-ink-700 mb-1"><?= e(t('أكتر ناس جابوا عملاء')) ?></div><?php foreach ($refTop as $r): ?><div class="flex gap-2 py-1"><span class="flex-1"><?= e($r['name']) ?></span><span><?= e(t('%s عميل', (int)$r['n'])) ?></span><span class="text-lime-700"><?= (int)$r['w'] ?> ✓</span></div><?php endforeach; ?></div><?php endif; ?>
    </section>

    <!-- #56 -->
    <section id="card" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zM8 12a2 2 0 100-4 2 2 0 000 4zM5 16a3 3 0 016 0M14 9h4M14 13h4"/></svg> <?= e(t('كارت بزنس بالكاميرا')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('صوّر الكارت من الموبايل. النص بيتقري في المتصفح (من غير ذكاء اصطناعي)، وانت تراجع البيانات قبل الحفظ.')) ?></p>
      <label class="btn-ghost !text-xs cursor-pointer"><input type="file" id="card-f" accept="image/*" capture="environment" class="hidden"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4zM12 17a4 4 0 100-8 4 4 0 000 8z"/></svg> <?= e(t('صوّر أو ارفع كارت')) ?></label>
      <span id="card-st" class="text-xs text-ink-500 ms-2"></span>
      <form id="card-form" class="hidden mt-3 grid sm:grid-cols-2 gap-2 text-sm">
        <?php foreach (['name' => t('الاسم'), 'phone' => t('الموبايل'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى'), 'event' => t('اتقابلنا فين؟ (اختياري)')] as $k => $v): ?><label class="text-xs text-ink-600"><?= e($v) ?><input name="<?= $k ?>" class="input text-sm" dir="auto"></label><?php endforeach; ?>
        <div class="sm:col-span-2 flex gap-2 justify-end"><button class="btn-primary !py-1.5 !text-xs"><?= e(t('ضيفه Lead')) ?></button></div></form></section>

    <!-- #60 -->
    <section id="sheet" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-8M21 20H3"/></svg> <?= e(t('ربط Google Sheets')) ?></h2>
      <p class="text-xs text-ink-500 mb-2"><?= e(t('أي صف جديد في الشيت بيبقى Lead (كل ساعة). مناسب لفورمات Google Forms ولأي حد بيجمع أرقام في شيت.')) ?></p>
      <ol class="text-[11px] text-ink-500 list-decimal ps-4 mb-3 space-y-0.5"><li><?= e(t('في الشيت: File ← Share ← Publish to web، واختار CSV.')) ?></li><li><?= e(t('الصف الأول لازم يكون أسماء الأعمدة (الاسم، الموبايل، الإيميل…).')) ?></li><li><?= e(t('انسخ اللينك وحطه هنا.')) ?></li></ol>
      <form method="post" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="sheet_save">
        <input name="url" class="input text-sm" dir="ltr" value="<?= e($sheet['url']) ?>" placeholder="https://docs.google.com/spreadsheets/d/e/…/pub?output=csv">
        <?php if ($sheet['url'] === ''): ?><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="skip_old" value="1"> <?= e(t('تجاهل الصفوف القديمة وابدأ من الجديد')) ?></label><?php endif; ?>
        <button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button></form>
      <?php if ($sheet['url'] !== ''): ?><div class="flex flex-wrap items-center gap-2 mt-2"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sheet_run"><button class="btn-ghost !py-1.5 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('اسحب دلوقتي')) ?></button></form>
        <span class="text-[11px] <?= $sheet['err'] ? 'text-red-600' : 'text-ink-500' ?>"><?= e($sheet['err'] ?: ($sheet['last'] ? t('آخر سحب: %s · %s صف', fmt_date($sheet['last'], 'd/m h:i A'), (int)$sheet['seen']) : '')) ?></span></div><?php endif; ?>
    </section>
  </div>

  <dialog id="qr-dlg" class="p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40"><div class="p-5 text-center space-y-3"><b id="qr-n" class="block text-ink-900"></b><img id="qr-img" class="mx-auto w-64 h-64" alt="QR"><div class="flex gap-2 justify-center"><a id="qr-dl" class="btn-primary !py-1.5 !text-xs" download="qr.png"><?= e(t('تحميل PNG')) ?></a><button type="button" class="btn-ghost !py-1.5 !text-xs" onclick="this.closest('dialog').close()"><?= e(t('إغلاق')) ?></button></div></div></dialog>
  <script>window.CAP = <?= json_encode(['copied' => t('اتنسخ'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'), 'reading' => t('بيقرا الكارت…'), 'none' => t('مالقيناش كلام واضح. جرّب صورة أوضح.'), 'review' => t('راجع البيانات واحفظ'), 'saved' => t('اتضاف ✓'), 'merged' => t('كان موجود، اتحدّث ✓'), 'open' => t('افتح العميل'), 'leads' => url('crm_leads'), 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
  <script src="assets/extract.js?v=<?= e(APP_VERSION) ?>"></script>
  <script src="assets/crm-capture.js?v=<?= e(APP_VERSION) ?>"></script>
  <script src="assets/cap-ux.js?v=<?= e(APP_VERSION) ?>"></script>
<?php });
