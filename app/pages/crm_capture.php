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
    if ($a === 'ref_cfg') { $mgr = can('crm.manage') || is_ws_owner(); if (!$mgr) $J(['ok' => false, 'error' => t('مالكش صلاحية.')]);
        save_setting('crm_ref_cfg_' . $wid, json_encode(['on' => !empty($_POST['on']) ? 1 : 0, 'give' => mb_substr(trim((string)($_POST['give'] ?? '')), 0, 120), 'get' => mb_substr(trim((string)($_POST['get'] ?? '')), 0, 120),
            'when' => ($_POST['when'] ?? '') === 'lead' ? 'lead' : 'won', 'msg' => mb_substr(trim((string)($_POST['msg'] ?? '')), 0, 1000)], JSON_UNESCAPED_UNICODE)); $J(['ok' => true]); }
    if ($a === 'ref_paid') { $id = (int)($_POST['lead'] ?? 0); $l = crm_lead($id); if (!$l) $J(['ok' => false, 'error' => t('بيانات ناقصة')]);
        $p = cap_ref_paid($wid); $p[(string)$id] = max(0, (int)($_POST['n'] ?? 0)); save_setting('crm_ref_paid_' . $wid, json_encode($p)); crm_act_add($id, 'note', '🎁 ' . t('اتسلّمت مكافأة الإحالة (%s)', (int)$p[(string)$id])); $J(['ok' => true, 'n' => (int)$p[(string)$id]]); }
    if ($a === 'ref_link') { $l = crm_lead((int)($_POST['lead'] ?? 0)); $l ? $J(['ok' => true, 'url' => cap_ref_link((int)$l['id']), 'name' => $l['name']]) : $J(['error' => t('بيانات ناقصة')], 422); }
    if ($a === 'card_parse') {
        $txt = mb_substr((string)($_POST['text'] ?? ''), 0, 4000); $c = crm_extract_contacts($txt);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n/', $txt)), function ($x) { return mb_strlen($x) >= 2; }));
        $name = $company = $title = $site = '';
        foreach ($lines as $ln) {
            if (preg_match('~(https?://|www\.)\S+~i', $ln, $m)) { $site = $site ?: $m[0]; continue; }
            if (preg_match('/@|\d{5,}/', $ln)) continue;
            if ($title === '' && preg_match('/manager|director|ceo|founder|owner|sales|engineer|consultant|head|مدير|مؤسس|مهندس|مستشار|مسؤول|رئيس|مندوب/iu', $ln)) { $title = $ln; continue; }
            if ($company === '' && preg_match('/\b(co|company|ltd|llc|inc|group|est)\b|شركة|مؤسسة|مجموعة|للتجارة|للمقاولات/iu', $ln)) { $company = trim(preg_replace('/[+\d][\d\s()-]{6,}/', '', $ln)); continue; }
            if ($name === '' && mb_strlen($ln) <= 40 && preg_match('/^[\p{L}\s.\'-]+$/u', $ln)) { $name = $ln; continue; }
        }
        $J(['ok' => true, 'lines' => array_slice($lines, 0, 30), 'name' => $name, 'phone' => $c['phone'], 'email' => $c['email'], 'company' => $company, 'title' => $title, 'site' => $site]);
    }
    if ($a === 'card_save') {
        $l = crm_lead_upsert(['source' => 'manual', 'name' => mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255) ?: t('كارت بزنس'), 'phone' => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 40), 'email' => mb_substr(trim((string)($_POST['email'] ?? '')), 0, 255),
            'company' => mb_substr(trim((string)($_POST['company'] ?? '')), 0, 255), 'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255), 'owner_id' => (int)(current_user()['id'] ?? 0)]);
        if (!$l) $J(['error' => t('اكتب رقم أو إيميل على الأقل.')], 422);
        crm_act_add((int)$l['id'], 'note', '🪪 ' . t('اتضاف من كارت بزنس') . (trim((string)($_POST['event'] ?? '')) !== '' ? ' — ' . mb_substr(trim((string)$_POST['event']), 0, 120) : '') . (trim((string)($_POST['site'] ?? '')) !== '' ? ' · ' . mb_substr(trim((string)$_POST['site']), 0, 200) : ''));
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

// برنامج الإحالة: السفراء (العملاء الحاليين + أي حد جاب حد) وأرقامهم
$st = db()->prepare("SELECT l.id, l.name, l.company, l.phone, l.stage, COUNT(x.id) n, COALESCE(SUM(x.stage = 'won'), 0) w, COALESCE(SUM(CASE WHEN x.stage = 'won' THEN x.deal_value END), 0) v
    FROM crm_leads l LEFT JOIN crm_leads x ON x.referred_by = l.id AND x.workspace_id = l.workspace_id WHERE l.workspace_id = ? AND (l.stage IN ('won','proposal','qualified') OR EXISTS (SELECT 1 FROM crm_leads y WHERE y.referred_by = l.id))
    GROUP BY l.id ORDER BY n DESC, l.stage = 'won' DESC, l.id DESC LIMIT 300"); $st->execute([$wid]);
$refAmb = array_map(function ($r) { return ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'co' => (string)$r['company'], 'wa' => crm_wa_digits((string)$r['phone']), 'stage' => (string)$r['stage'], 'n' => (int)$r['n'], 'w' => (int)$r['w'], 'v' => (float)$r['v'], 'link' => cap_ref_link((int)$r['id'])]; }, $st->fetchAll());
$st = db()->prepare("SELECT COUNT(*) a, COALESCE(SUM(stage = 'won'), 0) w FROM crm_leads WHERE workspace_id = ? AND referred_by IS NOT NULL"); $st->execute([$wid]); $refAll = $st->fetch();
$st = db()->prepare("SELECT COUNT(*) a, COALESCE(SUM(stage = 'won'), 0) w FROM crm_leads WHERE workspace_id = ? AND referred_by IS NULL"); $st->execute([$wid]); $refOth = $st->fetch();
$st = db()->prepare("SELECT x.name, x.stage, x.created_at, r.name rn FROM crm_leads x JOIN crm_leads r ON r.id = x.referred_by WHERE x.workspace_id = ? ORDER BY x.id DESC LIMIT 8"); $st->execute([$wid]); $refFeed = $st->fetchAll();
$refData = ['amb' => $refAmb, 'cfg' => cap_ref_cfg($wid), 'paid' => cap_ref_paid($wid), 'all' => ['a' => (int)$refAll['a'], 'w' => (int)$refAll['w']], 'oth' => ['a' => (int)$refOth['a'], 'w' => (int)$refOth['w']],
    'T' => ['tSet' => t('الإعدادات والمكافآت'), 'tAmb' => t('السفراء'), 'on' => t('البرنامج شغال'), 'onS' => t('اللي يدخل من لينك إحالة يشوف اسم اللي رشّحه والهدية'),
        'give' => t('مكافأة صاحب الإحالة'), 'giveP' => t('مثلًا: خصم 10% أو 500 جنيه'), 'get' => t('هدية الشخص الجديد'), 'getP' => t('مثلًا: استشارة مجانية'), 'when' => t('المكافأة تستحق إمتى؟'), 'wLead' => t('أول ما يسجّل'), 'wWon' => t('لما يبقى عميل'),
        'msg' => t('رسالة المشاركة'), 'save' => t('حفظ'), 'k1' => t('جم بالإحالة'), 'k2' => t('تحويل الإحالات'), 'k3' => t('تحويل باقي المصادر'), 'k4' => t('إيراد من الإحالات'), 'due' => t('مستحق'), 'gave' => t('سلّمت المكافأة'),
        'send' => t('ابعت واتساب'), 'copy' => t('نسخ اللينك'), 'copied' => t('اتنسخ'), 'noWa' => t('مفيش رقم'), 'find' => t('دوّر على سفير…'), 'fAll' => t('الكل'), 'fDue' => t('ليهم مكافأة'), 'fNew' => t('لسه مابعتلهمش'), 'queue' => t('ابعت للكل واحد ورا التاني'), 'next' => t('التالي'), 'qDone' => t('خلصت الطابور'),
        'feed' => t('آخر الإحالات'), 'none' => t('لسه مفيش سفراء. أي عميل حالته "تم الاتفاق" أو "عرض سعر" أو "مؤهل" هيظهر هنا تلقائيًا.'), 'by' => t('من طرف %s'), 'ref' => t('%s إحالة'), 'won' => t('%s اتقفل'), 'better' => t('الإحالات بتتحوّل %sx أحسن من باقي المصادر'), 'prev' => t('معاينة'), 'ok' => t('اتحفظ'), 'err' => t('حصل خطأ')],
    'feed' => array_map(function ($r) { return ['n' => (string)$r['name'], 'by' => (string)$r['rn'], 'st' => (string)$r['stage'], 'at' => fmt_date($r['created_at'], 'd/m')]; }, $refFeed), 'stages' => crm_stages()];
// مصادر العملاء آخر 30 يوم مقارنة بالـ 30 اللي قبلهم
$st = db()->prepare("SELECT source, SUM(created_at >= NOW() - INTERVAL 30 DAY) a, SUM(created_at < NOW() - INTERVAL 30 DAY) b FROM crm_leads WHERE workspace_id = ? AND created_at >= NOW() - INTERVAL 60 DAY GROUP BY source"); $st->execute([$wid]);
$srcStat = []; $srcL = crm_sources(); foreach ($st->fetchAll() as $r) $srcStat[] = ['k' => (string)$r['source'], 'l' => (string)($srcL[$r['source']] ?? $r['source']), 'a' => (int)$r['a'], 'b' => (int)$r['b']];
$st = db()->prepare("SELECT COUNT(*) FROM crm_leads WHERE workspace_id = ? AND referred_by IS NOT NULL AND created_at >= NOW() - INTERVAL 30 DAY"); $st->execute([$wid]); $refN = (int)$st->fetchColumn();
render(t('المصادر والإعدادات'), 'crm_capture', function () use ($wid, $cfg, $formUrl, $bookUrl, $callUrl, $lps, $sheet, $refLeads, $refTop, $waLinks, $srcStat, $refN, $refData) {
    $qrBtn = function (string $u, string $name) { return '<button type="button" class="btn-ghost !py-1 !text-xs" data-qr="' . e($u) . '" data-qrn="' . e($name) . '">▦ QR</button>'; };
    $copy = function (string $u) { return '<button type="button" class="btn-ghost !py-1 !text-xs" data-copy="' . e($u) . '">' . e(t('نسخ')) . '</button>'; };
    $sec = 'card p-5 scroll-mt-24';
    ?>
  <?php $setU = function (string $h) { return url('crm_settings', ['embed' => 1]) . '#' . $h; };
    $tiles = [
      ['g' => 'cap', 'id' => 'links', 'i' => 'file', 'c' => '#2563eb', 'n' => t('الفورم والحجز'), 'd' => t('الروابط وQR، إعدادات الفورم، أسئلة التأهيل، وصفحة الحجز'), 'm' => setting('crm_book_on_' . $wid, '0') === '1' ? t('الحجز شغال') : t('الحجز مقفول')],
      ['g' => 'cap', 'id' => 'lp', 'i' => 'file', 'c' => '#0891b2', 'n' => t('صفحات هبوط'), 'd' => t('صفحة لكل حملة من غير موقع'), 'm' => t('%s صفحة', count($lps))],
      ['g' => 'cap', 'id' => 'wa', 'i' => 'chat', 'c' => '#16a34a', 'n' => t('واتساب متتبع'), 'd' => t('رابط بيعرف العميل جه منين'), 'm' => t('%s رابط', count($waLinks))],
      ['g' => 'cap', 'x' => 1, 'id' => 'ref', 'i' => 'heart', 'c' => '#db2777', 'n' => t('برنامج الإحالة'), 'd' => t('عملاءك يجيبوا عملاء مقابل مكافأة'), 'm' => t('%s سفير', count($refData['amb']))],
      ['g' => 'cap', 'id' => 'sheet', 'i' => 'grid', 'c' => '#65a30d', 'n' => 'Google Sheets', 'd' => t('كل صف جديد = عميل'), 'm' => !empty($sheet['url']) ? t('متوصل') : t('مش متوصل')],
      ['g' => 'cap', 'x' => 1, 'id' => 'auto', 'i' => 'spark', 'c' => '#a855f7', 'n' => t('التقاط من الرسائل'), 'd' => t('أي حد يكتب "عاوز/السعر" يتسجّل لوحده'), 'm' => crm_auto_capture_on($wid) ? t('مفعّل') : t('مقفول')],
      ['g' => 'cap', 'x' => 1, 'id' => 'track', 'i' => 'globe', 'c' => '#0ea5e9', 'n' => t('تتبع الموقع'), 'd' => t('اعرف مين زار صفحة الأسعار'), 'm' => setting('crm_site_url_' . $wid, '') !== '' ? t('متوصل') : t('مش متوصل')],
      ['g' => 'ads', 'u' => url('leads', ['embed' => 1]), 'id' => 'adforms', 'i' => 'megaphone', 'c' => '#f59e0b', 'n' => t('فورمات الإعلانات'), 'd' => t('فورمات Lead Ads وتنزيلها CSV'), 'm' => ''],
      ['g' => 'set', 'u' => $setU('stages'), 'id' => 's-stages', 'i' => 'layers', 'c' => '#2563eb', 'n' => t('المراحل'), 'd' => t('أسماء وألوان مراحل العميل'), 'm' => t('%s مرحلة', count(crm_stages()))],
      ['g' => 'set', 'u' => $setU('pipes'), 'id' => 's-pipes', 'i' => 'route', 'c' => '#7c3aed', 'n' => 'Pipelines', 'd' => t('أكتر من مسار مبيعات'), 'm' => (string)count(crm_pipelines())],
      ['g' => 'set', 'u' => $setU('fields'), 'id' => 's-fields', 'i' => 'puzzle', 'c' => '#0891b2', 'n' => t('حقول مخصصة'), 'd' => t('بيانات زيادة لكل عميل'), 'm' => t('%s حقل', count(crm_custom_fields()))],
      ['g' => 'set', 'u' => $setU('tags'), 'id' => 's-tags', 'i' => 'tag', 'c' => '#db2777', 'n' => t('تاجات أوتوماتيك'), 'd' => t('تاج بيتحط لوحده بقواعد'), 'm' => t('%s قاعدة', count(crm_tag_rules()))],
      ['g' => 'set', 'u' => $setU('mail'), 'id' => 's-mail', 'i' => 'mail', 'c' => '#ea580c', 'n' => t('الإيميل'), 'd' => t('الإرسال والتتبع والتذكير اليومي'), 'm' => ''],
      ['g' => 'set', 'x' => 1, 'id' => 'plans', 'i' => 'refresh', 'c' => '#16a34a', 'n' => t('خطط المتابعة'), 'd' => t('خطوات متابعة أوتوماتيك بالأيام'), 'm' => t('%s خطة', count(crm_plans()))],
      ['g' => 'set', 'x' => 1, 'id' => 'tpl', 'i' => 'chat', 'c' => '#22c55e', 'n' => t('رسائل واتساب الجاهزة'), 'd' => t('القوالب اللي بتظهر في نافذة العميل'), 'm' => t('%s قالب', count(crm_templates()))],
      ['g' => 'set', 'x' => 1, 'id' => 'sla', 'i' => 'clock', 'c' => '#ef4444', 'n' => t('السرعة والتوزيع'), 'd' => t('وقت الرد، توزيع العملاء، واحتمالات الإغلاق'), 'm' => t('%s دقيقة', (int)crm_sla_cfg()['mins'])],
    ]; ?>
  <div id="cap-ov"></div>
  <div id="hub" class="hub"></div>
  <aside id="hub-dr" class="hub-dr" aria-hidden="true"><div class="hub-bg" data-hx></div><div class="hub-pn"><header><span class="hub-ic"></span><b></b><button type="button" class="hub-x" data-hx aria-label="✕">✕</button></header><div class="hub-body"></div></div></aside>
  <?php $members = []; foreach (crm_members() as $m) $members[(int)$m['id']] = $m['name']; $fcfg = json_decode((string)setting('crm_form_cfg_' . $wid, ''), true) ?: []; ?>
  <script>window.HUBX = <?= json_encode(['api' => url('crm_leads'), 'self' => url('crm_capture'), 'csrf' => csrf_token(), 'manage' => can('crm.manage') || is_ws_owner(),
    'plans' => crm_plans(), 'chans' => crm_plan_channels(), 'tpl' => crm_templates(), 'sla' => crm_sla_cfg(), 'routes' => crm_route_rules(), 'probs' => crm_stage_probs(), 'stages' => crm_stages(), 'members' => $members,
    'auto' => crm_auto_capture_on($wid), 'cta' => crm_cta_words(), 'track' => crm_track_cfg($wid), 'site' => (string)setting('crm_site_url_' . $wid, ''), 'trackJs' => crm_site_script(),
    'book' => crm_book_cfg(), 'bookOn' => setting('crm_book_on_' . $wid, '0') === '1', 'form' => $fcfg, 'formOn' => setting('crm_form_on_' . $wid, '1') === '1', 'links' => ['form' => $formUrl, 'call' => $callUrl, 'book' => $bookUrl],
    'budgets' => cap_budgets($fcfg), 'sheet' => !empty($sheet['url']), 'setUrl' => url('crm_settings'), 'ref' => $refData, 'rules' => crm_plan_rules(),
    'T' => ['err' => t('حصلت مشكلة.'), 'saved' => t('اتحفظ ✓'), 'save' => t('حفظ'), 'copy' => t('نسخ'), 'copied' => t('اتنسخ ✓'), 'share' => t('ابعته واتساب'), 'preview' => t('معاينة'), 'addEnter' => t('اكتب واضغط Enter'), 'color' => t('اللون'), 'untitled' => t('من غير اسم'),
      'autoT' => t('سجّل العملاء من الرسائل والتعليقات لوحده'), 'autoD' => t('أي حد يكتب كلمة من دول في رسالة أو تعليق بيتضاف عميل محتمل.'), 'ctaT' => t('الكلمات اللي تدل على اهتمام'), 'ctaD' => t('زي: السعر، بكام، عاوز، احجز، التفاصيل'),
      'demo' => t('مثال'), 'demoMsg' => t('"السلام عليكم، {w} الباقة؟"'), 'captured' => t('اتسجّل عميل'), 'autoOn' => t('اتفعّل ✓ واتضاف %s عميل من الرسائل الموجودة'),
      'tr1' => t('حط رابط موقعك'), 'tr2' => t('حط الكود في الموقع'), 'tr3' => t('تابع الزيارات في نافذة العميل'), 'site' => t('رابط موقعك'), 'snippet' => t('كود التتبع'), 'snipD' => t('حطه قبل </body> في كل صفحات الموقع. بيعرف العميل لما يرجع من رابط اتبعتله.'),
      'hot' => t('الصفحات السخنة'), 'hotD' => t('أي زيارة لصفحة فيها الكلمات دي بتعمل متابعة "كلّمه دلوقتي".'), 'waBtn' => t('زرار واتساب عائم في الموقع'), 'waBtnD' => t('بيظهر لكل الزوار وبيتسجل مين ضغط.'), 'waNum' => t('رقم واتساب'), 'btnLabel' => t('نص الزرار'), 'yourSite' => t('موقعك'), 'chatUs' => t('كلّمنا'),
      'newPlan' => t('خطة جديدة'), 'planName' => t('اسم الخطة (مثلًا: متابعة عميل جديد)'), 'dayN' => t('يوم'), 'subject' => t('عنوان الإيميل'), 'stepText' => t('نص الخطوة أو المهمة…'), 'addStep' => t('ضيف خطوة'), 'vars' => t('متغيرات:'), 'delPlan' => t('احذف الخطة'), 'delPlanQ' => t('تحذف الخطة؟ العملاء اللي ماشيين عليها هتقف.'), 'noPlans' => t('لسه مفيش خطط. اعمل أول خطة: واتساب يوم 0، مكالمة يوم 2، إيميل يوم 5.'),
      's1' => t('أهلًا {name}، شكرًا لاهتمامك! تحب أبعتلك التفاصيل؟'), 's2' => t('كلّم {name} واعرف احتياجه'), 's3s' => t('عرض خاص ليك يا {name}'), 's3' => t('أهلًا {name}، ده ملخص عرضنا. تحب نحدد ميعاد؟ {book}'),
      'tplName' => t('اسم القالب (مثلًا: متابعة)'), 'tplNew' => t('أهلًا {name}، معاك {me}. '), 'noTpl' => t('مفيش قوالب. ضيف أول قالب.'), 'sampleName' => t('أحمد'), 'sampleCo' => t('شركة النور'), 'sampleMe' => (string)(current_user()['name'] ?? ''),
      'slaT' => t('لازم نرد على العميل الجديد خلال'), 'slaD' => t('لو عدّى الوقت ومحدش كلّمه بيوصل تنبيه للمسؤول.'), 'hrs' => t('%s ساعة'), 'mins' => t('%s دقيقة'), 'moveT' => t('انقله لحد تاني لو محدش كلّمه بعد (ساعات)'), 'moveD' => t('0 = ماتنقلوش'),
      'routeT' => t('توزيع العملاء الجداد'), 'routeD' => t('مين ياخد العميل أول ما يتضاف حسب مصدره أو بلده.'), 'ifW' => t('لو'), 'addRule' => t('ضيف قاعدة'), 'routeF' => ['source' => t('المصدر'), 'country' => t('الدولة'), 'phone' => t('الرقم يبدأ بـ'), 'keyword' => t('كلمة في الرسالة')],
      'probT' => t('احتمال الإغلاق لكل مرحلة'), 'probD' => t('بيتحسب منه توقع الإيراد في تقارير المبيعات.'),
      'formT' => t('إعدادات الفورم'), 'formOn' => t('الفورم شغال'), 'fTitle' => t('العنوان'), 'thanks' => t('رسالة الشكر بعد الإرسال'), 'askCo' => t('اسأل عن الشركة'), 'askMsg' => t('خانة رسالة'),
      'bookT' => t('صفحة الحجز'), 'bookOn' => t('الحجز شغال'), 'bookOff' => t('الحجز مقفول، شغّله'), 'from' => t('من'), 'to' => t('لحد'), 'len' => t('مدة الميعاد (دقيقة)'), 'ahead' => t('متاح لكام يوم قدام'), 'notice' => t('أقل وقت قبل الميعاد (ساعات)'), 'slotsP' => t('المواعيد اللي هتظهر:'),
      'days' => [t('أحد'), t('اتنين'), t('تلات'), t('أربع'), t('خميس'), t('جمعة'), t('سبت')],
      'qOn' => t('اسأل أسئلة التأهيل'), 'qOnD' => t('اللي عاوز يبدأ دلوقتي بيطلع فوق في العملاء.'), 'addBudget' => t('ضيف اختيار ميزانية'), 'qBudget' => t('ميزانيتك تقريبًا؟'), 'qWhen' => t('محتاج تبدأ إمتى؟'), 'whens' => [t('دلوقتي'), t('خلال شهر'), t('بعدين')],
      'lpTitle' => t('عنوان الصفحة'), 'lpCta' => t('سجّل اهتمامك'), 'dropT' => t('اسحب صورة الكارت هنا أو اضغط'), 'dropD' => t('أو صوّره بالموبايل'), 'sh1' => t('افتح الشيت وانشره CSV'), 'sh2' => t('الصق الرابط هنا'), 'sh3' => t('كل ساعة بيتسحب الجديد'), 'findLead' => t('ابحث عن عميل…'), 'tLinks' => t('الروابط وQR'), 'tForm' => t('إعدادات الفورم'), 'tQual' => t('أسئلة التأهيل'), 'tBook' => t('الحجز'), 'tPlans' => t('الخطط'), 'tRules' => t('القواعد'),
      'rHours' => t('ابعت في ساعات العمل بس'), 'rHoursD' => t('الإيميلات والمهام بتستنى لأول ساعة شغل.'), 'rStop' => t('وقّف الخطة لو العميل رد'), 'rStopD' => t('بيتعمل له مهمة "رد عليه" بدل باقي الخطوات.'), 'rNa' => t('لو مردّش كذا مرة انقله لخطة تانية'), 'rTimes' => t('مرات ←'), 'none' => t('من غير')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script>window.HUB = <?= json_encode(['tiles' => $tiles, 'G' => ['cap' => t('أدوات جمع العملاء'), 'ads' => t('الإعلانات'), 'set' => t('الإعدادات')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
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
  <script src="assets/hub-ui.js?v=<?= e(APP_VERSION) ?>"></script>
  <script src="assets/hub.js?v=<?= e(APP_VERSION) ?>"></script>
<?php });
