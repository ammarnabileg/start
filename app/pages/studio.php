<?php
/** استوديو المحتوى: الكتابة بالذكاء الاصطناعي. بيكتب منشورات/ستوري/ريلز/كاروسيل/أفكار من ملف الشركة، وكل نتيجة تتنشر أو تتصمم بضغطة */
$wid = (int)ws('id');
$kb = function_exists('kb_get') ? kb_get($wid) : [];
$svcs = array_values(array_filter(array_map(function ($r) { return trim((string)($r['name'] ?? '')); }, (array)($kb['services']['rows'] ?? [])), 'strlen'));
$kinds = ['post' => [t('منشور'), 'file', t('نص جاهز للنشر')], 'story' => [t('ستوري'), 'clock', t('جمل قصيرة على الصورة')], 'reel' => [t('سكريبت ريلز'), 'film', t('مشاهد وكلام')], 'carousel' => [t('كاروسيل'), 'layers', t('شرائح متتالية')], 'ideas' => [t('أفكار'), 'bulb', t('أفكار لمحتوى الشهر')]];
$plats = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'twitter' => 'X', 'tiktok' => 'TikTok'];
$goals = ['sales' => t('مبيعات'), 'engage' => t('تفاعل'), 'aware' => t('انتشار'), 'trust' => t('ثقة')];
$tones = ['pro' => t('احترافية'), 'friendly' => t('ودودة'), 'bold' => t('حماسية'), 'fun' => t('مرحة')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    if (!can('posts.create') || !can('ai.use')) $J(['error' => t('مالكش صلاحية.')], 403);
    $kind = isset($kinds[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'post';
    $pl = isset($plats[$_POST['plat'] ?? '']) ? $_POST['plat'] : 'instagram';
    $goal = isset($goals[$_POST['goal'] ?? '']) ? $_POST['goal'] : 'sales';
    $tone = isset($tones[$_POST['tone'] ?? '']) ? $_POST['tone'] : 'friendly';
    $topic = mb_substr(trim((string)($_POST['topic'] ?? '')), 0, 300);
    $lang = ($_POST['lang'] ?? 'ar') === 'en' ? 'English' : 'Arabic (same dialect as the company knowledge base)';
    $n = max(1, min(6, (int)($_POST['n'] ?? 3)));
    $shape = [
        'post' => 'Each item is one complete social post (hook first line, value, clear call to action).',
        'story' => 'Each item is a 3-frame story: put each frame on its own line starting with "1)", "2)", "3)". Very short lines that fit on an image.',
        'reel' => 'Each item is a 15-30 second reel script: lines like "[0-3s] scene — spoken line". Strong hook in the first 3 seconds.',
        'carousel' => 'Each item is a 5-7 slide carousel: each slide on its own line starting with "Slide 1:", "Slide 2:"... Last slide is the call to action.',
        'ideas' => 'Each item is a content idea for this month: title = idea name, text = what to show and why it works, in 2-3 lines.',
    ][$kind];
    $sys = "You are a senior social media copywriter. STUDIO_JSON\nWrite for $plats[$pl]. Goal: {$goals[$goal]}. Tone: {$tones[$tone]}. Language: $lang.\n$shape\n"
        . "Use only facts from the company knowledge base (prices, services, offers); never invent numbers or claims.\n"
        . 'Return ONLY JSON: {"items":[{"title":"short label","text":"the content","hashtags":["#tag"],"visual":"one line describing the image or video to use"}]} with exactly ' . $n . ' items, each different in angle.';
    $user = $topic !== '' ? t('الموضوع: %s', $topic) : t('اختار أنسب موضوع من خدمات الشركة.');
    // إعادة صياغة نسخة واحدة (أقصر/أطول/أقوى في البيع/عاطفية) بنفس الشكل
    $hows = ['short' => 'Make it noticeably shorter and punchier (about half the length).', 'long' => 'Make it richer and longer with more useful detail.', 'sell' => 'Make it sell harder: stronger hook, clear offer and urgency, direct call to action.', 'emo' => 'Make it more emotional and story-driven, speaking to the reader\'s feelings.'];
    $base = mb_substr(trim((string)($_POST['base'] ?? '')), 0, 4000);
    if ($base !== '' && isset($hows[$_POST['how'] ?? ''])) { $n = 1; $user = "Rewrite this content. {$hows[$_POST['how']]} Keep facts unchanged.\n\n" . $base;
        $sys = str_replace('with exactly ' . max(1, min(6, (int)($_POST['n'] ?? 3))) . ' items', 'with exactly 1 items', $sys); }
    try { $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], 2200, 'compose'); } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
    $txt = (string)($r['text'] ?? ''); if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
    $d = json_decode($txt, true); $items = [];
    foreach ((array)($d['items'] ?? []) as $it) {
        if (!is_array($it) || trim((string)($it['text'] ?? '')) === '') continue;
        $tags = array_values(array_filter(array_map(function ($h) { $h = trim((string)$h); return $h === '' ? '' : ($h[0] === '#' ? $h : '#' . $h); }, (array)($it['hashtags'] ?? []))));
        $items[] = ['title' => mb_substr(trim((string)($it['title'] ?? '')), 0, 120), 'text' => mb_substr(trim((string)$it['text']), 0, 4000), 'tags' => array_slice($tags, 0, 12), 'visual' => mb_substr(trim((string)($it['visual'] ?? '')), 0, 300)];
    }
    if (!$items) $J(['error' => t('الذكاء الاصطناعي مارجعش نتيجة مفهومة. جرّب تاني.')], 502);
    log_activity('studio.write', $kind . ' ' . count($items));
    $J(['ok' => true, 'items' => $items, 'kind' => $kind, 'plat' => $pl, 'cost' => $r['cost'] ?? null]);
}

$aiOn = function_exists('ai_connection') && ai_connection() && can('ai.use');
$wsName = (string)(ws('name') ?? '');
render(t('استوديو المحتوى'), 'studio', function () use ($kinds, $plats, $goals, $tones, $svcs, $aiOn, $wsName) {
    $seg = function (string $name, array $opts, string $on) { $h = '<div class="flex flex-wrap gap-1.5">';
        foreach ($opts as $k => $l) $h .= '<label class="cursor-pointer"><input type="radio" name="' . $name . '" value="' . e($k) . '" class="peer sr-only"' . ($k === $on ? ' checked' : '') . '><span class="inline-flex rounded-full border border-ink-200 bg-white px-3 py-1.5 text-xs font-medium text-ink-600 transition hover:border-brand-300 peer-checked:border-brand-500 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-4 peer-focus-visible:ring-brand-500/20">' . e($l) . '</span></label>';
        return $h . '</div>'; };
    ?>
  <?php if (!$aiOn): ?>
    <div class="card p-8 text-center"><div class="mx-auto mb-3 w-12 h-12 rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><?= ui_icon('spark', 'w-6 h-6') ?></div>
      <b class="block text-ink-900"><?= e(t('اربط مزود ذكاء اصطناعي الأول')) ?></b><p class="text-sm text-ink-500 mt-1"><?= e(t('الاستوديو بيكتب من ملف شركتك باستخدام مزود الذكاء الاصطناعي المربوط في التكاملات.')) ?></p>
      <a href="<?= e(url('connectors')) ?>" class="btn-primary mt-4 inline-flex"><?= e(t('فتح التكاملات')) ?></a></div>
  <?php else: ?>
  <div class="grid xl:grid-cols-[400px_minmax(0,1fr)] gap-5 items-start">
    <form id="st-f" class="card p-5 space-y-5 xl:sticky xl:top-4" data-no-ajax>
      <div><span class="cp-sec block mb-2"><?= e(t('عاوز تكتب إيه؟')) ?></span>
        <div class="grid grid-cols-3 sm:grid-cols-5 xl:grid-cols-3 gap-2">
        <?php foreach ($kinds as $k => [$l, $ic, $sub]): ?>
          <label class="cp-mode !py-3" title="<?= e($sub) ?>"><input type="radio" name="kind" value="<?= $k ?>" class="sr-only" <?= $k === 'post' ? 'checked' : '' ?>><?= ui_icon($ic, 'w-5 h-5') ?><span><?= e($l) ?></span></label>
        <?php endforeach; ?></div></div>
      <div><span class="cp-sec block mb-2"><?= e(t('الموضوع')) ?></span>
        <input name="topic" id="st-topic" class="input !mt-0" dir="auto" maxlength="300" placeholder="<?= e(t('مثلًا: عرض الويك إند، أو سيبه فاضي ونختار من خدماتك')) ?>">
        <?php if ($svcs): ?><div class="flex flex-wrap gap-1.5 mt-2"><?php foreach (array_slice($svcs, 0, 8) as $s): ?><button type="button" class="cmp-chip text-[11px]" data-topic="<?= e($s) ?>"><?= e($s) ?></button><?php endforeach; ?></div><?php endif; ?></div>
      <div><span class="cp-sec block mb-2"><?= e(t('المنصة')) ?></span><?= $seg('plat', $plats, 'instagram') ?></div>
      <div class="grid grid-cols-2 gap-4"><div><span class="cp-sec block mb-2"><?= e(t('الهدف')) ?></span><?= $seg('goal', $goals, 'sales') ?></div><div><span class="cp-sec block mb-2"><?= e(t('النبرة')) ?></span><?= $seg('tone', $tones, 'friendly') ?></div></div>
      <div class="flex flex-wrap items-center gap-4"><div><span class="cp-sec block mb-2"><?= e(t('اللغة')) ?></span><?= $seg('lang', ['ar' => t('عربي'), 'en' => 'English'], 'ar') ?></div>
        <div><span class="cp-sec block mb-2"><?= e(t('كام نسخة')) ?></span><div class="inline-flex items-center rounded-xl border border-ink-200"><button type="button" class="w-9 h-9 text-ink-600" data-n="-1">−</button><input name="n" id="st-n" value="3" readonly class="w-8 text-center text-sm font-semibold bg-transparent"><button type="button" class="w-9 h-9 text-ink-600" data-n="1">+</button></div></div></div>
      <button id="st-go" class="btn-primary w-full !py-3 text-base"><?= ui_icon('spark', 'w-5 h-5') ?><span><?= e(t('اكتب')) ?></span></button>
    </form>
    <div class="min-w-0">
      <div id="st-empty" class="card p-10 text-center"><div class="mx-auto mb-3 w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-600 to-fuchsia-500 text-white grid place-items-center"><?= ui_icon('spark', 'w-7 h-7') ?></div>
        <b class="block text-ink-900 text-lg"><?= e(t('اختار نوع المحتوى ودوس اكتب')) ?></b><p class="text-sm text-ink-500 mt-1"><?= e(t('بنكتب من ملف شركتك: خدماتك وأسعارك ونبرة كلامك. كل نتيجة تقدر تعدّلها وتنشرها أو تصممها.')) ?></p></div>
      <div id="st-res"></div>
    </div>
  </div>
  <?php endif; ?>
  <script>window.ST = <?= json_encode(['csrf' => csrf_token(), 'url' => url('studio'), 'compose' => url('compose'), 'designer' => url('designer'),
    'L' => ['busy' => t('بنكتب…'), 'err' => t('حصلت مشكلة.'), 'copy' => t('نسخ'), 'copied' => t('اتنسخ ✓'), 'post' => t('انشره'), 'design' => t('صمّمه'), 'edit' => t('عدّل'), 'save' => t('حفظ'), 'visual' => t('الصورة المقترحة'), 'more' => t('اكتب نسخ تانية'), 'chars' => t('حرف'),
      'vText' => t('نص'), 'vPrev' => t('معاينة'), 'variant' => t('نوّع'), 'short' => t('أقصر'), 'long' => t('أطول'), 'sell' => t('أقوى في البيع'), 'emo' => t('عاطفية أكتر'), 'rewriting' => t('بنعيد الصياغة…'),
      'fav' => t('احفظ'), 'faved' => t('اتحفظت ⭐'), 'saved' => t('المحفوظ'), 'hist' => t('آخر كتابات'), 'restore' => t('رجّعها'), 'noHist' => t('لسه مفيش. اللي هتكتبه هيتحفظ هنا على الجهاز ده.'), 'clear' => t('امسح'),
      'over' => t('أطول من حد %s (%s حرف)'), 'frame' => t('إطار %s'), 'slide' => t('شريحة %s'), 'now' => t('دلوقتي'), 'likes' => t('إعجاب'), 'editHint' => t('اضغط على النص عشان تعدّله')], 'ws' => $wsName], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="assets/studio.js?v=<?= e(APP_VERSION) ?>"></script>
<?php });
