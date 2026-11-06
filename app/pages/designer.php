<?php
/** استوديو المحتوى: المصمم (زي كانفا). مقاسات جاهزة، قوالب بألوان الشركة، نصوص وأشكال وصور، وحفظ في مكتبة الوسائط أو نشر على طول */
$wid = (int)ws('id');
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
        $mode = ($_POST['mode'] ?? '') === 'design' ? 'design' : 'headline';
        $topic = mb_substr(trim((string)($_POST['topic'] ?? '')), 0, 300); $cur = mb_substr(trim((string)($_POST['current'] ?? '')), 0, 300);
        $sys = $mode === 'design'
            ? "You write the text for one social media graphic. DESIGN_JSON\nUse only facts from the company knowledge base (names, prices, offers). Same language/dialect as the knowledge base. Very short, punchy.\n"
              . 'Return ONLY JSON: {"title":"max 5 words","sub":"max 12 words","cta":"max 3 words","kind":"offer|product|quote|edu|event|story"}'
            : "You write headlines for a social media graphic. HEADLINE_JSON\nUse only facts from the company knowledge base. Same language/dialect as the knowledge base. Max 7 words each, different angles.\n"
              . 'Return ONLY JSON: {"items":["headline 1","headline 2","headline 3","headline 4"]}';
        $u = ($topic !== '' ? t('الموضوع: %s', $topic) : t('اختار أنسب موضوع من خدمات الشركة.')) . ($cur !== '' ? "\n" . t('النص الحالي: %s', $cur) : '');
        try { $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $u]], 600, 'compose'); } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
        $txt = (string)($r['text'] ?? ''); if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $d = json_decode($txt, true); if (!is_array($d)) $J(['error' => t('الذكاء الاصطناعي مارجعش نتيجة مفهومة. جرّب تاني.')], 502);
        if ($mode === 'headline') $d = ['items' => array_values(array_filter(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 120); }, (array)($d['items'] ?? [])), 'strlen'))];
        else $d = ['title' => mb_substr(trim((string)($d['title'] ?? '')), 0, 80), 'sub' => mb_substr(trim((string)($d['sub'] ?? '')), 0, 160), 'cta' => mb_substr(trim((string)($d['cta'] ?? '')), 0, 40), 'kind' => preg_replace('/[^a-z]/', '', (string)($d['kind'] ?? 'offer'))];
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
if (!$brand) $brand = ['#2563eb', '#0f172a', '#f59e0b'];
// صورة جاية من استوديو الصور أو من المكتبة (نفس الموقع بس، عشان التصدير يشتغل)
$img = (string)($_GET['img'] ?? ''); if (!preg_match('~^index\.php\?p=(ai_task&img=[\w.-]+|media&file=\d+)$~', $img)) $img = '';

render(t('استوديو المحتوى'), 'designer', function () use ($brand, $co, $img, $kbd) {
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
          <?php foreach (['mine' => [t('تصميماتي'), 'folder'], 'ai' => [t('ذكاء'), 'spark'], 'tpl' => [t('قوالب'), 'layers'], 'text' => [t('نص'), 'type'], 'shape' => [t('أشكال'), 'shapes'], 'img' => [t('صور'), 'image'], 'bg' => [t('خلفية'), 'palette'], 'brand' => [t('هويتك'), 'star']] as $k => [$l, $ic]): ?>
            <button type="button" data-tab="<?= $k ?>" class="<?= $k === 'tpl' ? 'on' : '' ?>"><?= ui_icon($ic, 'w-5 h-5') ?><span><?= e($l) ?></span></button>
          <?php endforeach; ?>
        </nav>
        <div class="dz-panel">
          <section data-pane="mine" hidden><h3><?= e(t('تصميماتك المحفوظة')) ?></h3><div id="dz-mine" class="grid grid-cols-2 gap-2"></div></section>
          <section data-pane="ai" hidden>
            <h3><?= e(t('صمّم لي بالذكاء الاصطناعي')) ?></h3>
            <input id="dz-ai-topic" class="input !mt-0 text-sm" dir="auto" maxlength="300" placeholder="<?= e(t('الموضوع أو الخدمة (اختياري)')) ?>">
            <button type="button" class="btn-primary w-full mt-2" data-cmd="aidesign"><?= ui_icon('spark', 'w-4 h-4') ?><span><?= e(t('اعمل تصميم')) ?></span></button>
            <h3 class="!mt-5"><?= e(t('عناوين للنص المحدد')) ?></h3>
            <button type="button" class="btn-ghost w-full" data-cmd="aihead"><?= ui_icon('type', 'w-4 h-4') ?><span><?= e(t('اقترح عناوين')) ?></span></button><div id="dz-heads" class="space-y-1.5 mt-2"></div>
            <h3 class="!mt-5"><?= e(t('ولّد صورة')) ?></h3>
            <textarea id="dz-ai-img" rows="3" class="input !mt-0 text-sm" dir="auto" maxlength="1200" placeholder="<?= e(t('صِف الصورة، وهتتحط في مكان الصورة المحدد أو على اللوحة')) ?>"></textarea>
            <button type="button" class="btn-ghost w-full mt-2" data-cmd="aiimg"><?= ui_icon('image', 'w-4 h-4') ?><span><?= e(t('ولّد')) ?></span></button><p id="dz-ai-st" class="text-[11px] text-ink-500 mt-2"></p></section>
          <section data-pane="tpl"><h3><?= e(t('قوالب من قاعدة المعرفة بألوانك')) ?></h3><div id="dz-tcat" class="flex flex-wrap gap-1 mb-3"></div><div id="dz-tpls" class="grid grid-cols-2 gap-2 items-start"></div></section>
          <section data-pane="text" hidden><h3><?= e(t('أضف نص')) ?></h3>
            <button type="button" class="dz-add text-2xl font-black" data-add="h1"><?= e(t('عنوان كبير')) ?></button>
            <button type="button" class="dz-add text-lg font-bold" data-add="h2"><?= e(t('عنوان فرعي')) ?></button>
            <button type="button" class="dz-add text-sm" data-add="p"><?= e(t('نص عادي')) ?></button>
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
            <p class="text-[11px] text-ink-400 mt-3"><?= e(t('اسحب أي صورة من جهازك على اللوحة مباشرة.')) ?></p></section>
          <section data-pane="bg" hidden><h3><?= e(t('لون الخلفية')) ?></h3><div id="dz-bgc" class="grid grid-cols-6 gap-1.5"></div>
            <h3 class="!mt-5"><?= e(t('تدرجات')) ?></h3><div id="dz-bgg" class="grid grid-cols-4 gap-1.5"></div>
            <label class="flex items-center gap-2 mt-4 text-xs text-ink-600"><?= e(t('لون مخصص')) ?><input type="color" id="dz-bgx" class="h-8 w-12 rounded border border-ink-200"></label></section>
          <section data-pane="brand" hidden><h3><?= e(t('ألوان شركتك')) ?></h3><div id="dz-brand" class="flex flex-wrap gap-2"></div>
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
  <script>window.DZ = <?= json_encode(['kb' => $kbd, 'csrf' => csrf_token(), 'url' => url('designer'), 'brand' => $brand, 'company' => (string)($co['name'] ?? ''), 'tagline' => (string)($co['tagline'] ?? ''), 'img' => $img,
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
      't1' => t('عرض'), 't2' => t('منتج'), 't3' => t('عن الشركة'), 't4' => t('ليه تختارنا'), 't5' => t('أسئلة شائعة'), 't6' => t('المواعيد والتواصل'), 't7' => t('قبل وبعد'), 't8' => t('فعالية'), 't9' => t('توظيف'), 't10' => t('جديد عندنا'), 't11' => t('خبر'), 't12' => t('ستوري عرض'), 't13' => t('ستوري تصويت'), 't14' => t('غلاف كاروسيل'), 't15' => t('تهنئة'), 't16' => t('قائمة الأسعار'),
      'cAll' => t('الكل'), 'cOffer' => t('عروض'), 'cProduct' => t('منتجات'), 'cQuote' => t('الشركة'), 'cEdu' => t('معلومات'), 'cEvent' => t('فعاليات'), 'cStory' => t('ستوري'), 'cCarousel' => t('كاروسيل'), 'cSeason' => t('مناسبات')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="assets/vendor/fabric-5.3.0.min.js"></script>
  <script src="assets/designer.js?v=<?= e(APP_VERSION) ?>"></script>
  <script src="assets/vendor/jspdf-4.2.1.umd.min.js" defer></script>
  <script src="assets/designer-pro.js?v=<?= e(APP_VERSION) ?>"></script>
<?php });
