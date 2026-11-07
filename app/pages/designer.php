<?php
/** استوديو المحتوى: المصمم (زي كانفا). مقاسات جاهزة، قوالب بألوان الشركة، نصوص وأشكال وصور، وحفظ في مكتبة الوسائط أو نشر على طول */
$wid = (int)ws('id');
$fontDir = ROOT_DIR . '/uploads/brandfonts/' . $wid;
$fontTypes = ['ttf' => 'font/ttf', 'otf' => 'font/otf', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];
// خطوط الشركة المرفوعة (المجلد مقفول للعامة، بتتقري من هنا بس)
if (isset($_GET['font'])) {
    $fn = basename((string)$_GET['font']); $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if (!preg_match('/^[\w-]{1,60}\.(ttf|otf|woff2?)$/', $fn) || !is_file($fontDir . '/' . $fn)) { http_response_code(404); exit; }
    header('Content-Type: ' . $fontTypes[$ext]); header('Cache-Control: private, max-age=604800'); readfile($fontDir . '/' . $fn); exit;
}
$kit = json_decode((string)setting('dz_kit_' . $wid, ''), true) ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    if (!can('posts.create')) $J(['error' => t('مالكش صلاحية.')], 403);
    // حفظ التصميم كصورة PNG في مكتبة الوسائط (فولدر التصميمات)
    if (($_POST['action'] ?? '') === 'save') {
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['size'] ?? 0) > 15 * 1048576) $J(['error' => t('الصورة كبيرة أو مارفعتش.')], 422);
        $f['name'] = preg_replace('/[^\p{L}\p{N} _-]+/u', '', mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80)) ?: t('تصميم');
        $f['name'] .= (($_POST['fmt'] ?? '') === 'jpg' ? '.jpg' : '.png');
        try { $row = media_store_upload($wid, $f, t('التصميمات')); } catch (Throwable $e) { $J(['error' => $e->getMessage()], 422); }
        log_activity('designer.save', (string)$row['id']);
        $J(['ok' => true, 'id' => $row['id'], 'url' => $row['url'], 'compose' => url('compose', ['lib' => $row['id']])]);
    }
    $act = (string)($_POST['action'] ?? '');
    $uid = (int)(current_user()['id'] ?? 0);
    // هوية الشركة: ألوان، خط العناوين والنص، اللوجو، وحطه تلقائي
    if ($act === 'kit_save') {
        $cols = array_values(array_slice(array_unique(array_filter(array_map(function ($c) { $c = strtolower(trim((string)$c)); return preg_match('/^#[0-9a-f]{6}$/', $c) ? $c : ''; }, (array)json_decode((string)($_POST['colors'] ?? '[]'), true)))), 0, 10));
        $logo = (string)($_POST['logo'] ?? ''); if (!preg_match('~^index\.php\?p=(media&file=\d+|ai_task&img=[\w.-]+)$~', $logo)) $logo = '';
        $font = function ($f) { $f = trim((string)$f); return preg_match('/^[\p{L}\p{N} _-]{1,60}$/u', $f) ? $f : ''; };
        $new = ['colors' => $cols, 'head' => $font($_POST['head'] ?? ''), 'body' => $font($_POST['body'] ?? ''), 'logo' => $logo, 'auto' => !empty($_POST['auto']), 'fonts' => array_values(array_filter((array)($kit['fonts'] ?? []), 'is_array'))];
        save_setting('dz_kit_' . $wid, json_encode($new, JSON_UNESCAPED_UNICODE)); log_activity('designer.kit', '');
        $J(['ok' => true, 'kit' => $new]);
    }
    if ($act === 'kit_font') {
        $f = $_FILES['file'] ?? null; $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !isset($fontTypes[$ext]) || $f['size'] > 5 * 1048576) $J(['error' => t('ارفع ملف خط (TTF أو OTF أو WOFF) لحد 5 ميجا.')], 422);
        $head = (string)@file_get_contents($f['tmp_name'], false, null, 0, 4); // التأكد إنه خط فعلًا من أول بايتات الملف
        if (!in_array($head, ["\x00\x01\x00\x00", 'OTTO', 'true', 'wOFF', 'wOF2'], true)) $J(['error' => t('الملف ده مش خط صالح.')], 422);
        $name = trim(preg_replace('/[^\p{L}\p{N} _-]+/u', '', (string)($_POST['name'] ?? pathinfo($f['name'], PATHINFO_FILENAME)))) ?: 'Brand Font';
        $name = mb_substr($name, 0, 40); $fn = substr(preg_replace('/[^a-z0-9]+/', '-', strtolower((function_exists('transliterator_transliterate') ? transliterator_transliterate('Any-Latin; Latin-ASCII', $name) : '') ?: 'font')), 0, 30) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!is_dir($fontDir)) @mkdir($fontDir, 0750, true);
        if (!move_uploaded_file($f['tmp_name'], $fontDir . '/' . $fn)) $J(['error' => t('ما قدرناش نحفظ الملف على السيرفر.')], 500);
        $kit['fonts'] = array_values(array_slice(array_merge((array)($kit['fonts'] ?? []), [['name' => $name, 'file' => $fn]]), -12));
        save_setting('dz_kit_' . $wid, json_encode($kit, JSON_UNESCAPED_UNICODE));
        $J(['ok' => true, 'font' => ['name' => $name, 'url' => url('designer', ['font' => $fn])]]);
    }
    // صور مجانية من Pexels: البحث، والصورة المختارة بتتحفظ في المكتبة (عشان التصدير يشتغل)
    if ($act === 'stock' || $act === 'stock_add') {
        $px = connector('pexels'); if (!$px) $J(['error' => t('اربط Pexels من التكاملات الأول.'), 'connect' => url('connectors')], 422);
        try {
            if ($act === 'stock') {
                $q = mb_substr(trim((string)($_POST['q'] ?? '')), 0, 80);
                $r = $q !== '' ? pexels_request($px['credentials'], '/v1/search', ['query' => $q, 'per_page' => 24, 'page' => max(1, (int)($_POST['page'] ?? 1))]) : pexels_request($px['credentials'], '/v1/curated', ['per_page' => 24, 'page' => max(1, (int)($_POST['page'] ?? 1))]);
                $J(['ok' => true, 'more' => !empty($r['next_page']), 'items' => array_map(function ($ph) { return ['id' => (int)$ph['id'], 'thumb' => (string)($ph['src']['medium'] ?? ''), 'w' => (int)$ph['width'], 'h' => (int)$ph['height'], 'by' => (string)($ph['photographer'] ?? ''), 'alt' => (string)($ph['alt'] ?? '')]; }, (array)($r['photos'] ?? []))]);
            }
            $ph = pexels_request($px['credentials'], '/v1/photos/' . (int)($_POST['id'] ?? 0));
            $src = (string)($ph['src']['large2x'] ?? $ph['src']['large'] ?? ''); if ($src === '') $J(['error' => t('الصورة مش موجودة.')], 404);
            $r = safe_fetch($src, ['file' => true, 'max' => 15 * 1048576, 'timeout' => 40, 'headers' => []]);
            if (!empty($r['err'])) $J(['error' => $r['err']], 502);
            $mime = (string)mime_content_type($r['file']); if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { @unlink($r['file']); $J(['error' => t('نوع الملف ده مش مسموح: %s', $mime)], 422); }
            $row = media_store_path($wid, $r['file'], 'pexels-' . (int)$ph['id'] . '.' . media_types()[$mime], $mime, t('صور مجانية')); @unlink($r['file']);
            log_activity('designer.stock', (string)(int)$ph['id']);
            $J(['ok' => true, 'url' => $row['url'], 'by' => (string)($ph['photographer'] ?? '')]);
        } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
    }
    // تصميماتي: حفظ التصميم نفسه (كل الصفحات) عشان يتفتح ويتعدل تاني
    if ($act === 'dsave') {
        $data = (string)($_POST['data'] ?? ''); $thumb = (string)($_POST['thumb'] ?? '');
        if (strlen($data) > 12 * 1048576 || !is_array(json_decode($data, true))) $J(['error' => t('التصميم كبير أو مش سليم.')], 422);
        if ($thumb !== '' && (strlen($thumb) > 400000 || strpos($thumb, 'data:image/jpeg;base64,') !== 0)) $thumb = '';
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120) ?: t('تصميم بدون اسم');
        $w = max(50, min(5000, (int)($_POST['w'] ?? 1080))); $h = max(50, min(5000, (int)($_POST['h'] ?? 1080))); $pg = max(1, min(30, (int)($_POST['pages'] ?? 1)));
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $st = db()->prepare('UPDATE designs SET name = ?, w = ?, h = ?, pages = ?, data = ?, thumb = ? WHERE id = ? AND workspace_id = ?'); $st->execute([$name, $w, $h, $pg, $data, $thumb ?: null, $id, $wid]); if (!$st->rowCount()) { $c = db()->prepare('SELECT 1 FROM designs WHERE id = ? AND workspace_id = ?'); $c->execute([$id, $wid]); if (!$c->fetchColumn()) $id = 0; } }
        if (!$id) { db()->prepare('INSERT INTO designs (workspace_id, user_id, name, w, h, pages, data, thumb) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$wid, $uid ?: null, $name, $w, $h, $pg, $data, $thumb ?: null]); $id = (int)db()->lastInsertId(); }
        $J(['ok' => true, 'id' => $id]);
    }
    if ($act === 'dlist') {
        $st = db()->prepare('SELECT id, name, w, h, pages, thumb, updated_at FROM designs WHERE workspace_id = ? ORDER BY updated_at DESC LIMIT 60'); $st->execute([$wid]);
        $J(['ok' => true, 'items' => array_map(function ($r) { return ['id' => (int)$r['id'], 'name' => $r['name'], 'w' => (int)$r['w'], 'h' => (int)$r['h'], 'pages' => (int)$r['pages'], 'thumb' => (string)$r['thumb'], 'at' => fmt_date($r['updated_at'], 'd/m H:i')]; }, $st->fetchAll())]);
    }
    if ($act === 'dget' || $act === 'ddel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($act === 'ddel') { db()->prepare('DELETE FROM designs WHERE id = ? AND workspace_id = ?')->execute([$id, $wid]); $J(['ok' => true]); }
        $st = db()->prepare('SELECT id, name, data FROM designs WHERE id = ? AND workspace_id = ?'); $st->execute([$id, $wid]); $r = $st->fetch();
        if (!$r) $J(['error' => t('التصميم مش موجود.')], 404);
        $J(['ok' => true, 'id' => (int)$r['id'], 'name' => $r['name'], 'data' => $r['data']]);
    }
    // الذكاء الاصطناعي جوه المصمم: عناوين لنص محدد، أو نصوص تصميم كامل لخدمة
    if ($act === 'ai') {
        if (!can('ai.use') || !ai_connection()) $J(['error' => t('اربط مزود ذكاء اصطناعي الأول من التكاملات.')], 422);
        $mode = in_array($_POST['mode'] ?? '', ['design', 'layout', 'carousel'], true) ? $_POST['mode'] : 'headline';
        $topic = mb_substr(trim((string)($_POST['topic'] ?? '')), 0, 300); $cur = mb_substr(trim((string)($_POST['current'] ?? '')), 0, 300);
        $facts = "Use only facts from the company knowledge base (names, prices, offers, contact). Same language/dialect as the knowledge base. Never invent prices or claims.\n";
        $sys = [
            'design' => "You write the text for one social media graphic. DESIGN_JSON\n" . $facts . "Very short, punchy.\n"
                . 'Return ONLY JSON: {"title":"max 5 words","sub":"max 12 words","cta":"max 3 words","kind":"offer|product|quote|edu|event|story"}',
            // تصميم كامل من جملة: التخطيط والألوان والنصوص ووصف الصورة
            'layout' => "You are an art director designing one social media graphic from a one-line brief. LAYOUT_JSON\n" . $facts
                . "Pick the layout that fits: hero (big photo + headline), split (photo half, text half), center (bold centered type, no photo), card (photo card on colored background), badge (offer with price badge), minimal (lots of space, elegant).\n"
                . "Pick a mood: bold, elegant, fresh, warm, dark. image_prompt is an English photo description for an image generator (no text in the image); empty for center/minimal.\n"
                . 'Return ONLY JSON: {"layout":"hero|split|center|card|badge|minimal","mood":"bold|elegant|fresh|warm|dark","title":"max 6 words","sub":"max 14 words","cta":"max 3 words","badge":"short price or % or empty","image_prompt":"..."}',
            // كاروسيل كامل من موضوع
            'carousel' => "You write an educational/sales carousel for Instagram/LinkedIn. CAROUSEL_JSON\n" . $facts
                . "5 to 7 slides: slide 1 is a strong hook, middle slides each give one clear point (title max 6 words, body max 25 words), last slide is a call to action using the company contact.\n"
                . 'Return ONLY JSON: {"slides":[{"title":"...","body":"..."}]}',
            'headline' => "You write headlines for a social media graphic. HEADLINE_JSON\n" . $facts . "Max 7 words each, different angles.\n"
                . 'Return ONLY JSON: {"items":["headline 1","headline 2","headline 3","headline 4"]}',
        ][$mode];
        $u = ($topic !== '' ? t('الموضوع: %s', $topic) : t('اختار أنسب موضوع من خدمات الشركة.')) . ($cur !== '' ? "\n" . t('النص الحالي: %s', $cur) : '');
        try { $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $u]], $mode === 'carousel' ? 1800 : 800, 'compose'); } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
        $txt = (string)($r['text'] ?? ''); if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $d = json_decode($txt, true); if (!is_array($d)) $J(['error' => t('الذكاء الاصطناعي مارجعش نتيجة مفهومة. جرّب تاني.')], 502);
        $cut = function ($v, $n) { return mb_substr(trim((string)$v), 0, $n); };
        if ($mode === 'headline') $d = ['items' => array_values(array_filter(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 120); }, (array)($d['items'] ?? [])), 'strlen'))];
        elseif ($mode === 'layout') $d = ['layout' => in_array($d['layout'] ?? '', ['hero', 'split', 'center', 'card', 'badge', 'minimal'], true) ? $d['layout'] : 'hero', 'mood' => in_array($d['mood'] ?? '', ['bold', 'elegant', 'fresh', 'warm', 'dark'], true) ? $d['mood'] : 'bold',
            'title' => $cut($d['title'] ?? '', 90), 'sub' => $cut($d['sub'] ?? '', 180), 'cta' => $cut($d['cta'] ?? '', 40), 'badge' => $cut($d['badge'] ?? '', 20), 'image_prompt' => $cut($d['image_prompt'] ?? '', 600)];
        elseif ($mode === 'carousel') { $sl = []; foreach (array_slice((array)($d['slides'] ?? []), 0, 8) as $x) if (is_array($x) && trim((string)($x['title'] ?? '')) !== '') $sl[] = ['title' => $cut($x['title'], 90), 'body' => $cut($x['body'] ?? '', 260)];
            if (count($sl) < 2) $J(['error' => t('الذكاء الاصطناعي مارجعش نتيجة مفهومة. جرّب تاني.')], 502); $d = ['slides' => $sl]; }
        else $d =['title' => mb_substr(trim((string)($d['title'] ?? '')), 0, 80), 'sub' => mb_substr(trim((string)($d['sub'] ?? '')), 0, 160), 'cta' => mb_substr(trim((string)($d['cta'] ?? '')), 0, 40), 'kind' => preg_replace('/[^a-z]/', '', (string)($d['kind'] ?? 'offer'))];
        log_activity('designer.ai', $mode);
        $J(['ok' => true] + $d);
    }
    $J(['error' => t('طلب غير صحيح.')], 400);
}
$kb = function_exists('kb_get') ? kb_get($wid) : [];
$co = (array)($kb['company'] ?? []);
preg_match_all('/#[0-9a-f]{6}\b|#[0-9a-f]{3}\b/i', (string)($kb['visual_style']['colors'] ?? ''), $m);
$brand = array_values(array_unique(array_map('strtolower', $m[0])));
$ct = (array)($kb['contact'] ?? []); $hrs = (array)($kb['hours'] ?? []);
// المواعيد: الميعاد الأكتر تكرار "يوميًا"، والأيام المختلفة باسمها
$dn = ['sat' => t('السبت'), 'sun' => t('الأحد'), 'mon' => t('الاثنين'), 'tue' => t('الثلاثاء'), 'wed' => t('الأربعاء'), 'thu' => t('الخميس'), 'fri' => t('الجمعة')];
$hrs = array_filter(array_map(function ($v) { return trim((string)$v); }, array_intersect_key($hrs, $dn)), 'strlen');
$hoursTxt = '';
if ($hrs) { $cnt = array_count_values($hrs); arsort($cnt); $main = (string)array_key_first($cnt);
    // "12:00 - 23:00" بتتقلب جوه النص العربي، فبنكتبها "من 12:00 لـ 23:00"
    $iso = function ($v) { $p = array_map('trim', explode('-', $v)); return count($p) === 2 ? t('من %s لـ %s', $p[0], $p[1]) : $v; };
    $lines = [t('يوميًا %s', $iso($main))]; foreach ($hrs as $d => $v) if ($v !== $main) $lines[] = $dn[$d] . ' ' . $iso($v);
    $hoursTxt = implode("\n", array_slice($lines, 0, 4)); }
preg_match('~instagram\.com/([\w.]+)~i', (string)($ct['social'] ?? ''), $ig);
$branch = trim((string)($co['branches'] ?? '')); $city = trim((string)(preg_split('/[،,]/u', $branch)[1] ?? ''));
$kbd = ['name' => (string)($co['name'] ?? ''), 'tagline' => (string)($co['tagline'] ?? ''), 'about' => mb_substr(trim((string)($co['about'] ?? '')), 0, 220), 'branch' => preg_replace('/[,،]\s*[A-Z]{2}$/u', '', $branch), 'city' => $city,
    'whatsapp' => (string)($ct['whatsapp'] ?? ''), 'phone' => (string)($ct['phone'] ?? ''), 'handle' => (string)($ig[1] ?? ''), 'hours' => $hoursTxt,
    'focus' => mb_substr(trim((string)($kb['focus']['text'] ?? '')), 0, 120), 'pod' => mb_substr(trim((string)($kb['pod']['text'] ?? $kb['pod']['statement'] ?? '')), 0, 200), 'rtb' => mb_substr(trim((string)($kb['rtb']['text'] ?? '')), 0, 200),
    'svcs' => array_values(array_map(function ($r) { return ['name' => trim((string)($r['name'] ?? '')), 'price' => trim((string)($r['price'] ?? ''))]; }, array_filter((array)($kb['services']['rows'] ?? []), function ($r) { return trim((string)($r['name'] ?? '')) !== ''; }))),
    'faq' => array_values(array_map(function ($r) { return ['q' => trim((string)$r['q']), 'a' => mb_substr(trim((string)$r['a']), 0, 140)]; }, array_filter((array)($kb['faq']['rows'] ?? []), function ($r) { return trim((string)($r['q'] ?? '')) !== '' && trim((string)($r['a'] ?? '')) !== ''; })))];
if (!empty($kit['colors'])) $brand = $kit['colors'];
if (!$brand) $brand = ['#2563eb', '#0f172a', '#f59e0b'];
// صورة جاية من استوديو الصور أو من المكتبة (نفس الموقع بس، عشان التصدير يشتغل)
$img = (string)($_GET['img'] ?? ''); if (!preg_match('~^index\.php\?p=(ai_task&img=[\w.-]+|media&file=\d+)$~', $img)) $img = '';

$kitOut = $kit + ['colors' => [], 'head' => '', 'body' => '', 'logo' => '', 'auto' => false, 'fonts' => []];
$kitOut['fonts'] = array_map(function ($f) { return ['name' => (string)$f['name'], 'url' => url('designer', ['font' => (string)$f['file']])]; }, array_filter((array)$kitOut['fonts'], function ($f) { return is_array($f) && !empty($f['file']); }));
render(t('استوديو المحتوى'), 'designer', function () use ($brand, $co, $img, $kbd, $kitOut) {
    $fonts = ['Cairo', 'Tajawal', 'Almarai', 'Alexandria', 'Changa', 'El Messiri', 'Lalezar', 'Amiri', 'Reem Kufi', 'Inter', 'Poppins', 'Playfair Display'];
    ?>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&family=Tajawal:wght@400;700;900&family=Almarai:wght@400;700;800&family=Changa:wght@400;700&family=El+Messiri:wght@400;700&family=Lalezar&family=Amiri:wght@400;700&family=Reem+Kufi:wght@400;700&family=Inter:wght@400;700;900&family=Poppins:wght@400;700;900&family=Playfair+Display:wght@400;700&display=swap">
  <div id="dz" class="dz-shell">
    <!-- شريط أعلى -->
    <div class="dz-top">
      <input id="dz-name" class="dz-name" dir="auto" maxlength="80" value="<?= e(t('تصميم بدون اسم')) ?>">
      <select id="dz-size" class="dz-sel" title="<?= e(t('المقاس')) ?>">
        <?php foreach (['1080x1080' => t('بوست مربع 1080×1080'), '1080x1350' => t('بوست طولي 1080×1350'), '1080x1920' => t('ستوري / ريلز 1080×1920'), '1200x628' => t('لينك / فيسبوك 1200×628'), '1500x500' => t('غلاف X ‏1500×500'), '1584x396' => t('غلاف لينكدإن 1584×396')] as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?>
        <option value="custom"><?= e(t('مقاس مخصص…')) ?></option>
      </select>
      <span id="dz-custom" class="hidden items-center gap-1 text-xs"><input id="dz-cw" type="number" min="50" max="5000" value="1080" class="dz-sel !w-20"> × <input id="dz-ch" type="number" min="50" max="5000" value="1080" class="dz-sel !w-20"><button type="button" class="btn-primary !py-1 !px-2 !text-xs" data-cmd="custom"><?= e(t('تطبيق')) ?></button></span>
      <span class="dz-div"></span>
      <button type="button" class="dz-ib" data-cmd="new" title="<?= e(t('تصميم جديد')) ?>"><?= ui_icon('plus', 'w-4 h-4') ?></button>
      <button type="button" class="dz-ib" data-cmd="undo" title="<?= e(t('تراجع')) ?> (Ctrl+Z)"><?= ui_icon('undo', 'w-4 h-4') ?></button>
      <button type="button" class="dz-ib" data-cmd="redo" title="<?= e(t('إعادة')) ?> (Ctrl+Y)"><?= ui_icon('redo', 'w-4 h-4') ?></button>
      <span class="dz-div"></span>
      <button type="button" class="dz-ib" data-cmd="zoom-" title="<?= e(t('تصغير')) ?>">−</button><span id="dz-zoom" class="text-xs tabular-nums text-ink-600 w-10 text-center">100%</span><button type="button" class="dz-ib" data-cmd="zoom+" title="<?= e(t('تكبير')) ?>">+</button>
      <span class="ms-auto"></span>
      <span id="dz-saved" class="text-[11px] text-ink-400"></span>
      <button type="button" class="btn-ghost !py-1.5 !text-sm" data-cmd="dsave" title="Ctrl+S"><?= ui_icon('check', 'w-4 h-4') ?><?= e(t('حفظ التصميم')) ?></button>
      <div class="relative"><button type="button" class="btn-ghost !py-1.5 !text-sm" id="dz-exp-b"><?= ui_icon('download', 'w-4 h-4') ?><?= e(t('تحميل')) ?> ▾</button>
        <div id="dz-exp" class="hidden absolute z-40 top-full mt-1 end-0 w-56 rounded-xl border border-ink-200 bg-white shadow-xl p-1 text-sm">
          <button type="button" class="dz-mi" data-exp="png">PNG <small><?= e(t('الصفحة الحالية، أعلى جودة')) ?></small></button>
          <button type="button" class="dz-mi" data-exp="jpg">JPG <small><?= e(t('حجم أصغر')) ?></small></button>
          <button type="button" class="dz-mi" data-exp="all">PNG × <?= e(t('كل الصفحات')) ?></button>
          <button type="button" class="dz-mi" data-exp="pdf">PDF <small><?= e(t('كل الصفحات في ملف واحد')) ?></small></button></div></div>
      <button type="button" class="btn-ghost !py-1.5 !text-sm" data-cmd="save"><?= ui_icon('folder', 'w-4 h-4') ?><?= e(t('حفظ في المكتبة')) ?></button>
      <button type="button" class="btn-primary !py-1.5 !text-sm" data-cmd="post"><?= ui_icon('megaphone', 'w-4 h-4') ?><?= e(t('انشره')) ?></button>
    </div>
    <div class="dz-body">
      <!-- لوحة الأدوات -->
      <aside class="dz-side">
        <nav class="dz-rail">
          <?php foreach (['mine' => [t('تصميماتي'), 'folder'], 'ai' => [t('ذكاء'), 'spark'], 'tpl' => [t('قوالب'), 'layers'], 'el' => [t('عناصر'), 'shapes'], 'text' => [t('نص'), 'type'], 'shape' => [t('أشكال'), 'shapes'], 'img' => [t('صور'), 'image'], 'bg' => [t('خلفية'), 'palette'], 'brand' => [t('هويتك'), 'star']] as $k => [$l, $ic]): ?>
            <button type="button" data-tab="<?= $k ?>" class="<?= $k === 'tpl' ? 'on' : '' ?>"><?= ui_icon($ic, 'w-5 h-5') ?><span><?= e($l) ?></span></button>
          <?php endforeach; ?>
        </nav>
        <div class="dz-panel">
          <section data-pane="mine" hidden><h3><?= e(t('تصميماتك المحفوظة')) ?></h3><div id="dz-mine" class="grid grid-cols-2 gap-2"></div></section>
          <section data-pane="ai" hidden>
            <h3><?= e(t('صمّم من جملة')) ?></h3>
            <textarea id="dz-ai-brief" rows="3" class="input !mt-0 text-sm" dir="auto" maxlength="300" placeholder="<?= e(t('مثلًا: بوست لعرض المشويات في الويك إند بشكل فخم')) ?>"></textarea>
            <label class="flex items-center gap-2 text-xs text-ink-600 mt-2"><input type="checkbox" id="dz-ai-withimg" class="rounded" <?= connector('higgsfield') ? 'checked' : 'disabled' ?>> <?= e(connector('higgsfield') ? t('ولّد الصورة كمان (Higgsfield)') : t('اربط Higgsfield عشان الصورة تتولد لوحدها')) ?></label>
            <button type="button" class="btn-primary w-full mt-2" data-cmd="ailayout"><?= ui_icon('spark', 'w-4 h-4') ?><span><?= e(t('صمّم')) ?></span></button>
            <h3 class="!mt-5"><?= e(t('كاروسيل كامل من موضوع')) ?></h3>
            <input id="dz-ai-car" class="input !mt-0 text-sm" dir="auto" maxlength="300" placeholder="<?= e(t('مثلًا: 5 أسباب تطلب من عندنا')) ?>">
            <button type="button" class="btn-ghost w-full mt-2" data-cmd="aicarousel"><?= ui_icon('layers', 'w-4 h-4') ?><span><?= e(t('اعمل الكاروسيل')) ?></span></button>
            <h3 class="!mt-5"><?= e(t('مقاسات لكل المنصات')) ?></h3>
            <p class="text-[11px] text-ink-500 mb-2"><?= e(t('بيعمل نسخة من التصميم الحالي بمقاس البوست والطولي والستوري والعرضي، وبيحفظهم في تصميماتي.')) ?></p>
            <button type="button" class="btn-ghost w-full" data-cmd="resizeall"><?= ui_icon('copy', 'w-4 h-4') ?><span><?= e(t('اعمل كل المقاسات')) ?></span></button>
            <h3 class="!mt-5"><?= e(t('صمّم لي بالذكاء الاصطناعي')) ?></h3>
            <input id="dz-ai-topic" class="input !mt-0 text-sm" dir="auto" maxlength="300" placeholder="<?= e(t('الموضوع أو الخدمة (اختياري)')) ?>">
            <button type="button" class="btn-primary w-full mt-2" data-cmd="aidesign"><?= ui_icon('spark', 'w-4 h-4') ?><span><?= e(t('اعمل تصميم')) ?></span></button>
            <h3 class="!mt-5"><?= e(t('عناوين للنص المحدد')) ?></h3>
            <button type="button" class="btn-ghost w-full" data-cmd="aihead"><?= ui_icon('type', 'w-4 h-4') ?><span><?= e(t('اقترح عناوين')) ?></span></button><div id="dz-heads" class="space-y-1.5 mt-2"></div>
            <h3 class="!mt-5"><?= e(t('ولّد صورة')) ?></h3>
            <textarea id="dz-ai-img" rows="3" class="input !mt-0 text-sm" dir="auto" maxlength="1200" placeholder="<?= e(t('صِف الصورة، وهتتحط في مكان الصورة المحدد أو على اللوحة')) ?>"></textarea>
            <button type="button" class="btn-ghost w-full mt-2" data-cmd="aiimg"><?= ui_icon('image', 'w-4 h-4') ?><span><?= e(t('ولّد')) ?></span></button><p id="dz-ai-st" class="text-[11px] text-ink-500 mt-2"></p></section>
          <section data-pane="tpl"><h3><?= e(t('قوالب من قاعدة المعرفة بألوانك')) ?></h3><div id="dz-tcat" class="flex flex-wrap gap-1 mb-3"></div><div id="dz-tpls" class="grid grid-cols-2 gap-2 items-start"></div></section>
          <section data-pane="el" hidden>
            <label class="mk-search !w-full mb-3"><?= ui_icon('search') ?><input type="search" id="dz-el-q" placeholder="<?= e(t('ابحث في العناصر…')) ?>"></label>
            <h3><?= e(t('ملصقات')) ?></h3><div id="dz-stk" class="grid grid-cols-2 gap-2 mb-4"></div>
            <h3><?= e(t('إطارات صور')) ?></h3><div id="dz-frm" class="grid grid-cols-3 gap-2 mb-4"></div>
            <h3><?= e(t('فواصل وأسهم')) ?></h3><div id="dz-lns" class="grid grid-cols-3 gap-2 mb-4"></div>
            <h3><?= e(t('خلفيات بنقوش')) ?></h3><div id="dz-pat" class="grid grid-cols-4 gap-1.5 mb-4"></div>
            <h3><?= e(t('إيموجي ملونة')) ?></h3><div id="dz-emo" class="grid grid-cols-6 gap-1 mb-4"></div>
            <h3 class="flex items-center gap-2"><?= e(t('أيقونات')) ?><span id="dz-ico-st" class="ms-auto inline-flex rounded-lg bg-ink-100 p-0.5 text-[10px] font-normal"><button type="button" data-ist="line" class="on"><?= e(t('خطوط')) ?></button><button type="button" data-ist="duo"><?= e(t('ملونة')) ?></button><button type="button" data-ist="solid"><?= e(t('مصمتة')) ?></button></span></h3><div id="dz-ico" class="grid grid-cols-5 gap-1.5"></div></section>
          <section data-pane="text" hidden><h3><?= e(t('أضف نص')) ?></h3>
            <button type="button" class="dz-add text-2xl font-black" data-add="h1"><?= e(t('عنوان كبير')) ?></button>
            <button type="button" class="dz-add text-lg font-bold" data-add="h2"><?= e(t('عنوان فرعي')) ?></button>
            <button type="button" class="dz-add text-sm" data-add="p"><?= e(t('نص عادي')) ?></button>
            <h3 class="!mt-5"><?= e(t('أنماط جاهزة')) ?></h3><div id="dz-tsty" class="space-y-2 mb-2"></div>
            <h3 class="!mt-5"><?= e(t('تركيبات')) ?></h3>
            <button type="button" class="dz-add" data-add="badge"><span class="inline-block rounded-full bg-red-600 text-white px-3 py-1 text-sm font-bold">-30%</span></button>
            <button type="button" class="dz-add" data-add="cta"><span class="inline-block rounded-xl bg-ink-900 text-white px-4 py-2 text-sm font-bold"><?= e(t('اطلب الآن')) ?></span></button>
            <button type="button" class="dz-add" data-add="quote"><span class="text-3xl leading-none text-brand-600">“</span> <?= e(t('اقتباس عميل')) ?></button>
          </section>
          <section data-pane="shape" hidden><h3><?= e(t('أشكال')) ?></h3>
            <div class="grid grid-cols-3 gap-2">
              <?php foreach (['rect' => '<rect x="4" y="6" width="16" height="12" rx="1"/>', 'round' => '<rect x="4" y="6" width="16" height="12" rx="5"/>', 'circle' => '<circle cx="12" cy="12" r="8"/>', 'tri' => '<path d="M12 4l9 16H3z"/>', 'line' => '<path d="M3 12h18"/>', 'star' => '<path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.5 6.7 19.4l1.2-6L3.4 9.3l6-.7z"/>', 'arrow' => '<path d="M4 12h14M13 6l6 6-6 6"/>', 'blob' => '<path d="M6 7c3-4 10-3 12 1s1 9-4 10-11 0-11-5 1-3 3-6z"/>', 'frame' => '<rect x="4" y="4" width="16" height="16" rx="2" fill="none"/>'] as $k => $svg): ?>
                <button type="button" class="dz-shape" data-add="<?= $k ?>"><svg viewBox="0 0 24 24" class="w-8 h-8" fill="currentColor" stroke="currentColor" stroke-width="<?= in_array($k, ['line', 'arrow', 'frame'], true) ? 2 : 0 ?>"><?= $svg ?></svg></button>
              <?php endforeach; ?></div></section>
          <section data-pane="img" hidden><h3><?= e(t('صور')) ?></h3>
            <button type="button" class="btn-primary w-full" data-cmd="lib"><?= ui_icon('folder', 'w-4 h-4') ?><?= e(t('من مكتبة الوسائط')) ?></button>
            <label class="btn-ghost w-full mt-2 cursor-pointer"><?= ui_icon('upload', 'w-4 h-4') ?><?= e(t('ارفع من جهازك')) ?><input type="file" id="dz-up" accept="image/*" class="hidden"></label>
            <a class="btn-ghost w-full mt-2" href="<?= e(url('studio_media')) ?>"><?= ui_icon('spark', 'w-4 h-4') ?><?= e(t('ولّد صورة بالذكاء الاصطناعي')) ?></a>
            <p class="text-[11px] text-ink-400 mt-3"><?= e(t('اسحب أي صورة من جهازك على اللوحة مباشرة.')) ?></p>
            <h3 class="!mt-5 flex items-center gap-1"><?= e(t('صور مجانية')) ?> <span class="text-[10px] font-normal text-ink-400">Pexels</span></h3>
            <div id="dz-stock-box"><form id="dz-stock-f" class="flex gap-1.5" data-no-ajax><input id="dz-stock-q" class="input !mt-0 !py-1.5 text-sm flex-1" dir="auto" placeholder="<?= e(t('ابحث بالإنجليزي أفضل: food, coffee…')) ?>"><button class="btn-primary !py-1.5 !px-2.5"><?= ui_icon('search', 'w-4 h-4') ?></button></form>
              <div id="dz-stock" class="grid grid-cols-2 gap-1.5 mt-2"></div><button type="button" id="dz-stock-more" class="btn-ghost w-full !py-1 !text-xs mt-2 hidden"><?= e(t('صور كمان')) ?></button></div></section>
          <section data-pane="bg" hidden><h3><?= e(t('لون الخلفية')) ?></h3><div id="dz-bgc" class="grid grid-cols-6 gap-1.5"></div>
            <h3 class="!mt-5"><?= e(t('تدرجات')) ?></h3><div id="dz-bgg" class="grid grid-cols-4 gap-1.5"></div>
            <label class="flex items-center gap-2 mt-4 text-xs text-ink-600"><?= e(t('لون مخصص')) ?><input type="color" id="dz-bgx" class="h-8 w-12 rounded border border-ink-200"></label></section>
          <section data-pane="brand" hidden>
            <div class="rounded-2xl border border-ink-200 p-3 mb-4 space-y-3" id="dz-kit">
              <b class="block text-sm text-ink-900"><?= e(t('هوية الشركة')) ?></b>
              <div><h3><?= e(t('اللوجو')) ?></h3><div class="flex items-center gap-2"><div id="kit-logo" class="w-16 h-16 rounded-xl border border-dashed border-ink-300 bg-[repeating-conic-gradient(rgb(var(--ink-100))_0_25%,rgb(var(--ink-50))_0_50%)] bg-[length:12px_12px] grid place-items-center overflow-hidden text-[10px] text-ink-400"></div>
                <div class="flex-1 space-y-1"><button type="button" class="btn-ghost w-full !py-1 !text-xs" data-cmd="kitlogo"><?= e(t('اختار اللوجو')) ?></button><button type="button" class="btn-ghost w-full !py-1 !text-xs" data-cmd="addlogo"><?= e(t('حطه في التصميم')) ?></button></div></div></div>
              <div><h3><?= e(t('ألوان الهوية')) ?></h3><div id="kit-cols" class="flex flex-wrap gap-1.5"></div></div>
              <div class="grid grid-cols-2 gap-2"><label class="block text-[11px] text-ink-500"><?= e(t('خط العناوين')) ?><select id="kit-head" class="dz-sel w-full mt-1"></select></label><label class="block text-[11px] text-ink-500"><?= e(t('خط النص')) ?><select id="kit-body" class="dz-sel w-full mt-1"></select></label></div>
              <label class="btn-ghost w-full !py-1 !text-xs cursor-pointer"><?= ui_icon('upload', 'w-4 h-4') ?><?= e(t('ارفع خط الشركة')) ?><input type="file" id="kit-font" accept=".ttf,.otf,.woff,.woff2" class="hidden"></label>
              <label class="flex items-center gap-2 text-xs text-ink-700"><input type="checkbox" id="kit-auto" class="rounded"> <?= e(t('حط اللوجو تلقائيًا في كل قالب')) ?></label>
              <div class="grid grid-cols-2 gap-2"><button type="button" class="btn-primary !py-1.5 !text-xs" data-cmd="kitsave"><?= e(t('حفظ الهوية')) ?></button><button type="button" class="btn-ghost !py-1.5 !text-xs" data-cmd="kitapply"><?= e(t('طبّق على التصميم')) ?></button></div>
              <p class="text-[10px] text-ink-400"><?= e(t('الهوية بتتحفظ للشركة كلها، والقوالب والتصميمات بالذكاء الاصطناعي بتستخدمها.')) ?></p>
            </div>
            <h3><?= e(t('ألوان شركتك')) ?></h3><div id="dz-brand" class="flex flex-wrap gap-2"></div>
            <p class="text-[11px] text-ink-400 mt-2"><?= e(t('من الهوية البصرية في قاعدة المعرفة. دوس على لون يتطبق على العنصر المحدد.')) ?></p>
            <h3 class="!mt-5"><?= e(t('اسم الشركة')) ?></h3><button type="button" class="dz-add font-bold" data-add="brandname"><?= e((string)($co['name'] ?? t('اسم الشركة'))) ?></button>
            <?php if (!empty($co['tagline'])): ?><button type="button" class="dz-add text-sm" data-add="tagline"><?= e((string)$co['tagline']) ?></button><?php endif; ?></section>
        </div>
      </aside>
      <!-- اللوحة -->
      <div class="dz-center"><div class="dz-stage" id="dz-stage"><div id="dz-wrap" class="dz-wrap"><canvas id="dz-c"></canvas></div><div id="dz-drop" class="dz-drop hidden"><?= e(t('سيب الصورة هنا')) ?></div></div>
        <div class="dz-pages"><div id="dz-pages" class="flex items-center gap-2 overflow-x-auto"></div><button type="button" class="dz-padd" data-cmd="addpage" title="<?= e(t('صفحة جديدة')) ?>">+</button><span class="text-[11px] text-ink-400 whitespace-nowrap"><?= e(t('صفحات للكاروسيل')) ?></span></div></div>
      <!-- الخصائص -->
      <aside class="dz-props" id="dz-props">
        <div id="dz-none" class="text-center text-sm text-ink-400 py-10 px-4"><?= ui_icon('pointer', 'w-6 h-6 mx-auto mb-2') ?><?= e(t('اختار عنصر على اللوحة عشان تعدّله')) ?></div>
        <div id="dz-multi" hidden class="p-3"><b class="block text-xs text-ink-500 mb-2"><span id="dz-mn"></span> <?= e(t('عناصر محددة')) ?></b>
          <div class="grid grid-cols-6 gap-1 mb-2"><?php foreach (['left' => '⇤', 'hcenter' => '↔', 'right' => '⇥', 'top' => '⤒', 'vcenter' => '↕', 'bottom' => '⤓'] as $k => $g): ?><button type="button" class="dz-tg" data-align="<?= $k ?>"><?= $g ?></button><?php endforeach; ?></div>
          <div class="grid grid-cols-2 gap-1 mb-2"><button type="button" class="dz-ib2" data-align="dh"><?= e(t('توزيع أفقي')) ?></button><button type="button" class="dz-ib2" data-align="dv"><?= e(t('توزيع رأسي')) ?></button></div>
          <button type="button" class="dz-ib2 w-full" data-cmd="group"><?= ui_icon('layers', 'w-4 h-4') ?><?= e(t('اجمعهم')) ?></button></div>
        <div id="dz-p" hidden>
          <div class="grid grid-cols-6 gap-1 mb-3"><?php foreach (['left' => '⇤', 'hcenter' => '↔', 'right' => '⇥', 'top' => '⤒', 'vcenter' => '↕', 'bottom' => '⤓'] as $k => $g): ?><button type="button" class="dz-tg" data-align="<?= $k ?>" title="<?= e(t('محاذاة على اللوحة')) ?>"><?= $g ?></button><?php endforeach; ?></div>
          <button type="button" class="dz-ib2 w-full mb-3 hidden" data-cmd="ungroup" id="dz-ungroup"><?= e(t('فك التجميع')) ?></button>
          <div id="dz-phbox" hidden class="mb-3 rounded-xl bg-brand-50 p-3 text-xs text-brand-800"><p class="mb-2"><?= e(t('ده مكان صورة. بدّله بصورتك وهتتقص على نفس الشكل.')) ?></p>
            <button type="button" class="btn-primary w-full !py-1.5 !text-xs" data-cmd="phlib"><?= ui_icon('image', 'w-4 h-4') ?><?= e(t('بدّل بصورة')) ?></button>
            <label class="btn-ghost w-full !py-1.5 !text-xs mt-1.5 cursor-pointer"><?= ui_icon('upload', 'w-4 h-4') ?><?= e(t('من جهازك')) ?><input type="file" accept="image/*" class="hidden" onchange="document.getElementById('dz-up').files=this.files;document.getElementById('dz-up').dispatchEvent(new Event('change'))"></label></div>
          <div class="dz-row" data-for="text"><label><?= e(t('الخط')) ?></label><select id="pp-font" class="dz-sel w-full"><?php foreach ($fonts as $f): ?><option value="<?= e($f) ?>" style="font-family:'<?= e($f) ?>'"><?= e($f) ?></option><?php endforeach; ?></select></div>
          <div class="dz-row" data-for="text"><label><?= e(t('الحجم')) ?></label><input type="range" id="pp-size" min="12" max="260"><span id="pp-size-v" class="w-9 text-end tabular-nums text-xs"></span></div>
          <div class="dz-row flex gap-1" data-for="text">
            <button type="button" class="dz-tg" data-tg="bold" title="<?= e(t('عريض')) ?>"><b>B</b></button><button type="button" class="dz-tg" data-tg="italic" title="<?= e(t('مائل')) ?>"><i>I</i></button><button type="button" class="dz-tg" data-tg="underline"><u>U</u></button>
            <span class="dz-div"></span><button type="button" class="dz-tg" data-al="right">⇥</button><button type="button" class="dz-tg" data-al="center">≡</button><button type="button" class="dz-tg" data-al="left">⇤</button></div>
          <div class="dz-row" data-for="text"><label><?= e(t('تباعد الأسطر')) ?></label><input type="range" id="pp-lh" min="0.8" max="2.4" step="0.05"></div>
          <div class="dz-row" data-for="text"><label><?= e(t('تأثيرات النص')) ?></label>
            <div class="grid grid-cols-4 gap-1 w-full"><?php foreach (['none' => t('بدون'), 'outline' => t('حدود'), 'box' => t('خلفية'), 'grad' => t('تدرج')] as $k => $l): ?><button type="button" class="dz-ib2" data-fx="<?= $k ?>"><?= e($l) ?></button><?php endforeach; ?></div></div>
          <div class="dz-row" data-for="text" id="dz-ls"><label><?= e(t('تباعد الحروف (إنجليزي)')) ?></label><input type="range" id="pp-cs" min="-100" max="800" step="10"></div>
          <div class="dz-row" data-for="image"><label><?= e(t('شكل الصورة')) ?></label><div class="grid grid-cols-3 gap-1 w-full"><button type="button" class="dz-ib2" data-mask="none">▭</button><button type="button" class="dz-ib2" data-mask="round">▢</button><button type="button" class="dz-ib2" data-mask="circle">◯</button></div></div>
          <div class="dz-row" data-for="image"><label><?= e(t('قلب')) ?></label><div class="grid grid-cols-2 gap-1 w-full"><button type="button" class="dz-ib2" data-cmd="flipx">⇋ <?= e(t('أفقي')) ?></button><button type="button" class="dz-ib2" data-cmd="flipy">⇵ <?= e(t('رأسي')) ?></button></div></div>
          <?php foreach (['br' => [t('السطوع'), -0.6, 0.6, 0.02], 'ct' => [t('التباين'), -0.6, 0.6, 0.02], 'sa' => [t('التشبع'), -1, 1, 0.05], 'bl' => [t('التغبيش'), 0, 0.5, 0.01]] as $k => [$l, $mn, $mx, $st]): ?>
            <div class="dz-row" data-for="image"><label><?= e($l) ?></label><input type="range" data-flt="<?= $k ?>" min="<?= $mn ?>" max="<?= $mx ?>" step="<?= $st ?>" value="0"></div>
          <?php endforeach; ?>
          <div class="dz-row" data-for="image"><label><?= e(t('فلاتر جاهزة')) ?></label><div class="grid grid-cols-3 gap-1 w-full"><?php foreach (['none' => t('أصلي'), 'gray' => t('أبيض وأسود'), 'sepia' => t('كلاسيك'), 'warm' => t('دافي'), 'cool' => t('بارد'), 'vivid' => t('زاهي')] as $k => $l): ?><button type="button" class="dz-ib2" data-preset="<?= $k ?>"><?= e($l) ?></button><?php endforeach; ?></div></div>
          <div class="dz-row" data-for="image"><button type="button" class="dz-ib2 w-full" data-cmd="replaceimg"><?= ui_icon('image', 'w-4 h-4') ?><?= e(t('بدّل الصورة')) ?></button></div>
          <div class="dz-row" data-for="fill"><label><?= e(t('اللون')) ?></label><input type="color" id="pp-fill" class="h-8 w-12 rounded border border-ink-200"><div id="pp-sw" class="flex flex-wrap gap-1"></div></div>
          <div class="dz-row" data-for="shape"><label><?= e(t('الحدود')) ?></label><input type="color" id="pp-stroke" class="h-8 w-12 rounded border border-ink-200"><input type="range" id="pp-sw2" min="0" max="40"></div>
          <div class="dz-row" data-for="round"><label><?= e(t('استدارة الأركان')) ?></label><input type="range" id="pp-rx" min="0" max="300"></div>
          <div class="dz-row" data-for="any"><label><?= e(t('الشفافية')) ?></label><input type="range" id="pp-op" min="0.05" max="1" step="0.05"></div>
          <div class="dz-row" data-for="any"><label><?= e(t('ظل')) ?></label><input type="checkbox" id="pp-shadow" class="rounded"></div>
          <div class="grid grid-cols-4 gap-1 mt-3" data-for="any">
            <button type="button" class="dz-ib2" data-cmd="front" title="<?= e(t('لقدام')) ?>"><?= ui_icon('up', 'w-4 h-4') ?></button><button type="button" class="dz-ib2" data-cmd="back" title="<?= e(t('لورا')) ?>"><?= ui_icon('down', 'w-4 h-4') ?></button>
            <button type="button" class="dz-ib2" data-cmd="center" title="<?= e(t('في النص')) ?>">⊕</button><button type="button" class="dz-ib2" data-cmd="lock" title="<?= e(t('قفل')) ?>"><?= ui_icon('lock', 'w-4 h-4') ?></button>
            <button type="button" class="dz-ib2 col-span-2" data-cmd="dup"><?= ui_icon('copy', 'w-4 h-4') ?><?= e(t('نسخة')) ?></button><button type="button" class="dz-ib2 col-span-2 hover:!text-red-600" data-cmd="del"><?= ui_icon('trash', 'w-4 h-4') ?><?= e(t('حذف')) ?></button></div>
        </div>
        <div class="mt-auto border-t border-ink-100 p-3"><b class="block text-xs text-ink-500 mb-2"><?= e(t('الطبقات')) ?></b><ol id="dz-layers" class="space-y-1 max-h-60 overflow-y-auto text-xs"></ol></div>
      </aside>
    </div>
  </div>
  <script>window.DZ = <?= json_encode(['kit' => $kitOut, 'pexels' => (bool)connector('pexels'), 'connectUrl' => url('connectors'), 'icons' => ui_icon_paths(), 'hf' => (bool)connector('higgsfield'), 'kb' => $kbd, 'csrf' => csrf_token(), 'url' => url('designer'), 'brand' => $brand, 'company' => (string)($co['name'] ?? ''), 'tagline' => (string)($co['tagline'] ?? ''), 'img' => $img,
    'title' => mb_substr((string)($_GET['title'] ?? ''), 0, 120), 'text' => mb_substr((string)($_GET['text'] ?? ''), 0, 160), 'fonts' => $fonts,
    'L' => ['h1' => t('عنوان كبير'), 'h2' => t('عنوان فرعي'), 'p' => t('اكتب النص هنا'), 'cta' => t('اطلب الآن'), 'quote' => t('«خدمة ممتازة وسرعة في التنفيذ»'), 'quoteBy' => t('— عميل سعيد'), 'company' => t('اسم الشركة'),
      'saved' => t('اتحفظ في المكتبة ✓'), 'saving' => t('بنحفظ…'), 'err' => t('حصلت مشكلة.'), 'draft' => t('مسودة محفوظة'), 'restore' => t('عندك تصميم لسه شغال عليه. نكمّله؟'), 'replace' => t('القالب هيبدل التصميم الحالي. تكمل؟'),
      'tpl' => [t('عرض وخصم'), t('خدمة جديدة'), t('نصيحة'), t('رأي عميل'), t('إعلان حدث'), t('ستوري عرض')], 'offer' => t('عرض خاص'), 'off' => t('خصم 30%'), 'limited' => t('لفترة محدودة'), 'newSvc' => t('خدمة جديدة'), 'tip' => t('نصيحة اليوم'), 'tipTxt' => t('اكتب النصيحة هنا في سطرين'), 'event' => t('انضم لنا'), 'date' => t('الخميس 8 م'), 'swipe' => t('اسحب لفوق'),
      'ph' => t('حط صورتك'), 'sub' => t('اكتب التفاصيل هنا'), 's1' => t('السؤال الأول'), 's2' => t('السؤال التاني'), 's3' => t('السؤال التالت'), 'faqT' => t('أسئلة بتوصلنا كتير'), 'why' => t('ليه تختارنا؟'), 'hoursT' => t('مواعيدنا'),
      'before' => t('قبل'), 'after' => t('بعد'), 'month' => t('الشهر'), 'evTitle' => t('اسم الفعالية'), 'place' => t('المكان'), 'register' => t('سجّل دلوقتي'), 'hiring' => t('بنوظّف!'), 'role' => t('اسم الوظيفة'), 'full' => t('دوام كامل'), 'city' => t('المدينة'), 'apply' => t('قدّم الآن'),
      'new' => t('جديد'), 'from' => t('من'), 'news' => t('خبر جديد'), 'qday' => t('سؤال اليوم'), 'question' => t('رأيك يهمنا'), 'which' => t('أنهي تختار؟'), 'yes' => t('أيوه'), 'no' => t('لأ'), 'hook' => t('اكتب جملة تشد هنا'), 'whySvc' => t('ليه %s؟'), 'swipeSide' => t('اسحب للآخر'),
      'greet' => t('كل سنة وانتم طيبين'), 'greetSub' => t('من كل فريقنا'), 'menu' => t('قائمة الأسعار'), 'item' => t('صنف'), 'orderWa' => t('اطلب على واتساب'),
      'delPage' => t('حذف الصفحة'), 'dupPage' => t('نسخة من الصفحة'), 'delPageQ' => t('تحذف الصفحة دي؟'), 'dsaved' => t('اتحفظ التصميم ✓'), 'pagesN' => t('صفحات'), 'noMine' => t('لسه مفيش تصميمات محفوظة. دوس «حفظ التصميم».'),
      'del' => t('حذف'), 'delQ' => t('متأكد إنك عاوز تحذفه؟'), 'newQ' => t('تبدأ تصميم جديد؟ اللي مش محفوظ هيتمسح.'), 'untitled' => t('تصميم بدون اسم'), 'aiDone' => t('اتعمل التصميم ✓ عدّل اللي انت عاوزه'), 'pickText' => t('اختار نص على اللوحة الأول.'),
      'genBusy' => t('بنولّد الصورة… من 10 لـ 60 ثانية'), 'genDone' => t('اتولدت ✓'), 'watch' => t('شاهد'),
      'ramadan' => t('رمضان كريم'), 'ramadanSub' => t('كل عام وأنتم بخير'), 'eid' => t('عيد مبارك'), 'national' => t('اليوم الوطني'), 'nationalSub' => t('دام عزك يا وطن'), 'weekend' => t('عرض الويك إند'), 'fri' => t('الخميس والجمعة بس'),
      'delivery' => t('التوصيل لحد بابك'), 'combo' => t('الوجبة الكاملة'), 'together' => t('الثلاثة مع بعض'), 'special' => t('بسعر خاص'), 'review' => t('رأيك يفرق معانا'), 'reviewSub' => t('لو عجبتك التجربة قيّمنا على جوجل وقولنا رأيك'), 'reviewCta' => t('قيّمنا الآن'),
      'opening' => t('افتتاح فرعنا'), 'endsIn' => t('العرض بيخلص خلال'), 'days' => t('يوم'), 'hours' => t('ساعة'), 'mins' => t('دقيقة'), 'coupon' => t('كود الخصم'), 'couponSub' => t('استخدمه عند الطلب'), 'thanks' => t('شكرًا لكم'), 'thanksSub' => t('على ثقتكم ودعمكم الدايم'),
      'x1' => t('غلاف ريلز'), 'x2' => t('إعلان عرضي'), 'x3' => t('رمضان'), 'x4' => t('عيد'), 'x5' => t('اليوم الوطني'), 'x6' => t('عرض الويك إند'), 'x7' => t('التوصيل'), 'x8' => t('وجبة / باقة'), 'x9' => t('طلب تقييم'), 'x10' => t('كارت التواصل'), 'x11' => t('افتتاح'), 'x12' => t('عد تنازلي'), 'x13' => t('كوبون خصم'), 'x14' => t('شكر'),
      'copy' => t('نسخ'), 'paste' => t('لصق'), 'dup' => t('نسخة'), 'del2' => t('حذف'), 'front' => t('لقدام'), 'back' => t('لورا'), 'group' => t('اجمعهم'), 'ungroup' => t('فك التجميع'), 'lock' => t('قفل'), 'unlock' => t('فك القفل'), 'center' => t('في النص'),
      'show' => t('إظهار'), 'hide' => t('إخفاء'), 'more' => t('المزيد'), 'font' => t('الخط'),
      'stk' => [t('جديد'), t('خصم'), t('الأكثر مبيعًا'), t('توصيل مجاني'), t('عرض محدود'), t('حصري'), t('مجانًا'), t('اطلب الآن')],
      'tsty' => [[t('عنوان فخم'), t('وصف قصير أنيق')], [t('عنوان قوي'), t('سطر يشرح الفكرة')], [t('عرض اليوم'), t('لحد نص الليل')], ['NEW COLLECTION', 'Limited edition']],
      'aiBusy' => t('بنصمّم…'), 'carBusy' => t('بنكتب الكاروسيل…'), 'carDone' => t('اتعمل كاروسيل من %s صفحات ✓'), 'imgWait' => t('التصميم جاهز، وبنولّد الصورة…'), 'resizeDone' => t('اتعملت %s نسخ واتحفظت في تصميماتي ✓'),
      'sizes' => ['1080x1080' => t('بوست'), '1080x1350' => t('طولي'), '1080x1920' => t('ستوري'), '1200x628' => t('عرضي')], 'nothing' => t('مفيش نتايج.'), 'kitSaved' => t('اتحفظت الهوية ✓'), 'kitApplied' => t('اتطبقت الهوية على التصميم ✓'), 'noLogo' => t('اختار اللوجو الأول.'), 'fontUp' => t('اترفع الخط ✓'), 'defFont' => t('افتراضي'),
      'stockConnect' => t('اربط Pexels من التكاملات عشان تدوّر في صور مجانية.'), 'stockOpen' => t('فتح التكاملات'), 'stockAdding' => t('بنحفظ الصورة في المكتبة…'), 'by' => t('تصوير'), 'addColor' => t('لون'),
      't1' => t('عرض'), 't2' => t('منتج'), 't3' => t('عن الشركة'), 't4' => t('ليه تختارنا'), 't5' => t('أسئلة شائعة'), 't6' => t('المواعيد والتواصل'), 't7' => t('قبل وبعد'), 't8' => t('فعالية'), 't9' => t('توظيف'), 't10' => t('جديد عندنا'), 't11' => t('خبر'), 't12' => t('ستوري عرض'), 't13' => t('ستوري تصويت'), 't14' => t('غلاف كاروسيل'), 't15' => t('تهنئة'), 't16' => t('قائمة الأسعار'),
      'cAll' => t('الكل'), 'cOffer' => t('عروض'), 'cProduct' => t('منتجات'), 'cQuote' => t('الشركة'), 'cEdu' => t('معلومات'), 'cEvent' => t('فعاليات'), 'cStory' => t('ستوري'), 'cCarousel' => t('كاروسيل'), 'cSeason' => t('مناسبات')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="assets/vendor/fabric-5.3.0.min.js"></script>
  <script src="assets/designer.js?v=<?= e(APP_VERSION) ?>"></script>
  <script src="assets/vendor/jspdf-4.2.1.umd.min.js" defer></script>
  <script src="assets/designer-pro.js?v=<?= e(APP_VERSION) ?>"></script>
  <script src="assets/designer-plus.js?v=<?= e(APP_VERSION) ?>"></script>
  <script src="assets/designer-kit.js?v=<?= e(APP_VERSION) ?>"></script>
<?php });
