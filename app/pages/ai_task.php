<?php
/** مهام الذكاء الاصطناعي السريعة (كتابة منشور، اقتراح رد). بترجع JSON. */
$task = (string)($_POST['task'] ?? '');
// عرض صورة من مكتبة الشركة (المجلد مقفول للعامة)
if (($_GET['img'] ?? '') !== '') {
    $f = (string)$_GET['img'];
    $path = hf_lib_dir((int)ws('id')) . '/' . $f;
    if (!hf_lib_valid($f) || !is_file($path) || !can('posts.create')) { http_response_code(404); exit; }
    header('Content-Type: ' . mime_content_type($path));
    header('Cache-Control: private, max-age=86400');
    readfile($path);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
$out = function (array $d, int $code = 200) { http_response_code($code); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
// توليد الصور عن طريق Higgsfield: طلب، وبعدين متابعة الحالة لحد ما تخلص
if (in_array($task, ['image', 'image_status'], true)) {
    if (!ai_feature_on('image')) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
    if (!can('posts.create')) $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    $hf = connector('higgsfield');
    if (!$hf) $out(['error' => t('اربط Higgsfield أولًا من صفحة التكاملات.')], 422);
    $c = $hf['credentials'];
    try {
        if ($task === 'image') {
            $prompt = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 1500);
            if ($prompt === '') $out(['error' => t('اكتب وصف الصورة أولًا.')], 422);
            $video = ($_POST['kind'] ?? '') === 'video';
            if ($video && !ai_feature_on('video')) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
            $path = '/' . ltrim(trim((string)($video ? ($c['video_model'] ?? '') : ($c['model'] ?? ''))) ?: ($video ? '/higgsfield/cinema-studio/4.0' : '/higgsfield-ai/soul/v2/standard'), '/');
            if (!preg_match('#^/[\w./-]+$#', $path)) $out(['error' => t('مسار الموديل غير صحيح.')], 422);
            $style = trim((string)($c['style'] ?? ''));
            $body = ['prompt' => $prompt . ($style !== '' ? '. Style: ' . $style : '')];
            if ($video && ($src = (string)($_POST['src'] ?? '')) !== '') {
                $meta = hf_lib_valid($src) ? json_decode((string)@file_get_contents(hf_lib_dir((int)ws('id')) . '/' . $src . '.json'), true) : null;
                if (!$meta || time() - (int)$meta['t'] > 6.5 * 86400) $out(['error' => t('يمكن تحويل الصور المولّدة خلال آخر 7 أيام فقط.')], 422);
                $body['image_urls'] = [$meta['src']];
            }
            $ar = (string)($_POST['aspect'] ?? '');
            if (in_array($ar, ['1:1', '4:5', '9:16', '16:9'], true)) $body['aspect_ratio'] = $ar;
            $ids = [];
            for ($k = 0; $k < ((int)($_POST['count'] ?? 1) === 2 ? 2 : 1); $k++) {
                $r = hf_request($c, 'POST', $path, $body);
                $id = (string)($r['request_id'] ?? '');
                if ($id === '') throw new Exception(t('لم يرجع Higgsfield رقم الطلب.'));
                $_SESSION['hf_req'][$id] = 1;
                $ids[] = $id;
            }
            log_activity('ai.image', $path . ' x' . count($ids));
            $out(['id' => $ids[0], 'ids' => $ids, 'status' => 'queued']);
        }
        $id = (string)($_POST['id'] ?? '');
        if (!preg_match('/^[\w-]{8,80}$/', $id) || empty($_SESSION['hf_req'][$id])) $out(['error' => t('طلب غير صحيح.')], 400);
        $r = hf_request($c, 'GET', '/requests/' . $id . '/status');
        $st = (string)($r['status'] ?? '');
        if ($st === 'completed') {
            $urls = array_values(array_filter(array_map(function ($i) { return (string)($i['url'] ?? ''); }, $r['images'] ?? [])));
            if (!empty($r['video']['url'])) $urls[] = (string)$r['video']['url'];
            unset($_SESSION['hf_req'][$id]);
            // بنحفظها في مكتبة الشركة على طول، لأن روابط Higgsfield بتنتهي بعد 7 أيام
            $lib = [];
            foreach ($urls as $u) { $n = hf_lib_save((int)ws('id'), $u); if ($n) $lib[] = $n; }
            $out(['status' => 'completed', 'images' => array_map(function ($n) { return ['name' => $n, 'src' => url('ai_task', ['img' => $n]), 'video' => substr($n, -4) === '.mp4']; }, $lib)]);
        }
        if (in_array($st, ['failed', 'nsfw', 'canceled'], true)) {
            unset($_SESSION['hf_req'][$id]);
            $out(['status' => $st, 'error' => $st === 'nsfw' ? t('رفض Higgsfield الوصف لأنه مخالف لسياسة المحتوى.') : t('فشل توليد الصورة. جرّب وصفًا مختلفًا.')]);
        }
        $out(['status' => $st ?: 'queued']);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

// المكالمات: ملخص ومهام، أو تقييم جودة (بموافقة الموظفين)
if ($task === 'call') {
    if (!ai_feature_on('calls')) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
    if (!can('phone.view')) $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    $mode = ($_POST['mode'] ?? '') === 'score' ? 'score' : 'summary';
    if ($mode === 'score' && setting('ai_call_consent_' . (int)ws('id'), '0') !== '1') $out(['error' => t('تقييم المكالمات يحتاج تأكيد مالك الشركة بموافقة الموظفين (من إعدادات الذكاء الاصطناعي).')], 403);
    $z = zernio_or_null($err);
    if (!$z) $out(['error' => $err ?: t('طلب غير صحيح.')], 422);
    try {
        $tr = ai_call_transcript($z, (string)($_POST['id'] ?? ''));
        $lang = lang() === 'ar' ? 'Arabic' : 'English';
        $sys = $mode === 'summary'
            ? "Summarize this phone call in $lang: 3-5 bullet summary, then 'Action items' with who should do what and when (e.g. send a quote tomorrow), then customer sentiment in one word. Only from the transcript."
            : "Evaluate the agent's handling of this call in $lang, fairly and constructively: score out of 10 for greeting, understanding the need, accuracy, politeness, and closing; then 2 strengths and 2 concrete improvements. Base it only on the transcript; note if it is too short to judge.";
        $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => mb_substr($tr, 0, 30000)]], 900, 'analyze');
        $out(['html' => ai_render($r['text']), 'text' => $r['text'], 'model' => $r['model'], 'cost' => $r['cost'] ?? null]);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

// تصنيف المحادثات دلوقتي (بدل ما تستنى التشغيل الدوري)
if ($task === 'classify_now') {
    if (!ai_feature_on('classify_btn')) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
    if (!can('inbox.reply') && !can('inbox.manage')) $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    try {
        $out(['ok' => true, 'count' => ai_classify_conversations((int)ws('id'))]);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

// لوحة ✨ الجانبية: محادثة واحدة على مستوى المنصة، فاكرة الصفحة اللي المستخدم فيها
if (in_array($task, ['chat', 'chat_clear', 'chat_log'], true)) {
    $wid = (int)ws('id');
    $hist = &$_SESSION['copilot'][$wid];
    if (!is_array($hist)) $hist = [];
    if ($task === 'chat_clear') { $hist = []; unset($_SESSION['ai_ctx'][$wid], $_SESSION['ai_ctx'][$wid . 'x']); $out(['ok' => true]); }
    if ($task === 'chat_log') {
        $out(['items' => array_map(function ($h) { return ['role' => $h['role'], 'html' => $h['role'] === 'assistant' ? ai_render($h['content']) : nl2br(e($h['content'])), 'meta' => $h['meta'] ?? '', 'label' => $h['label'] ?? '']; }, $hist)]);
    }
    if (!ai_feature_on('assistant')) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
    $q = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 2000);
    if ($q === '') $out(['error' => t('اكتب سؤالك الأول.')], 422);
    $pageName = mb_substr(trim((string)($_POST['page_title'] ?? '')), 0, 80);
    $pageText = mb_substr(trim((string)($_POST['page'] ?? '')), 0, 6000);
    $lang = lang() === 'ar' ? 'Arabic (Modern Standard Arabic)' : 'English';
    $system = "You are the AI copilot inside a social media management app for the company \"" . ws('name') . "\". "
        . "You can answer about the company's data (DATA), about what is on the user's current page (PAGE, if provided), and how to use the app (MENU). "
        . "Never invent numbers; if something isn't in the data, say so briefly and suggest where to look. "
        . "Be concise and practical: short paragraphs or bullets, concrete numbers, 1-3 actionable recommendations when useful. "
        . "When the user asks you to write something (post, reply, message), write it ready to copy. "
        . "Best-time hours are UTC; convert to " . site_timezone() . ". Reply in $lang."
        . "\n\nCURRENT PAGE: " . ($pageName ?: '-')
        . "\n\nMENU:\n" . implode("\n", array_map(function ($g) { return '- ' . ($g['section'] ? $g['section'] . ' › ' : '') . $g['label'] . (!empty($g['tabs']) ? ': ' . implode(', ', array_values($g['tabs'])) : ''); }, nav_items()))
        . "\n\nDATA:\n" . assistant_context() . ($pageText !== '' ? "\n\nPAGE:\n" . $pageText : '');
    $msgs = [['role' => 'system', 'content' => $system]];
    foreach (array_slice($hist, -8) as $h) $msgs[] = ['role' => $h['role'], 'content' => $h['content']];
    $msgs[] = ['role' => 'user', 'content' => $q];
    try {
        $r = ai_chat($msgs, 1000, 'assistant');
        $meta = $r['model'] . (isset($r['cost']) && $r['cost'] !== null ? ' · $' . rtrim(rtrim(number_format((float)$r['cost'], 5), '0'), '.') : '');
        $hist[] = ['role' => 'user', 'content' => $q, 't' => time()];
        $hist[] = ['role' => 'assistant', 'content' => $r['text'], 'meta' => $meta, 't' => time()];
        $hist = array_slice($hist, -30);
        $out(['html' => ai_render($r['text']), 'text' => $r['text'], 'meta' => $meta]);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

// تحليلات من بيانات الشركة
if ($task === 'insight') {
    $all = ai_insights();
    $key = (string)($_POST['insight'] ?? '');
    if (!isset($all[$key])) $out(['error' => t('طلب غير صحيح.')], 400);
    if (!ai_feature_on($key)) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
    $in = $all[$key];
    $extra = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 6000);
    if (!empty($in['input']) && $extra === '' && empty($in['optional'])) $out(['error' => t('اكتب المطلوب أولًا.')], 422);
    if (!can('analytics.view') && $in['group'] === 'analyze') $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    $sys = strtr($in['sys'], ['{lang}' => lang() === 'ar' ? 'Arabic (Modern Standard Arabic)' : 'English', '{tz}' => site_timezone()]) . ' The business is "' . ws('name') . '".';
    if (!empty($in['admin']) && !is_admin()) $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    $user = empty($in['nodata']) ? "DATA:\n" . assistant_context(true) : '';
    if (!empty($in['page'])) $user .= "\n\nPAGE:\n" . mb_substr(trim((string)($_POST['page'] ?? '')), 0, 8000);
    if (!empty($in['perms'])) { $pl = []; foreach (permission_catalog() as $g => $items) foreach ($items as $k => $l) $pl[] = "$g / $l"; $user .= "\n\nPERMISSIONS:\n" . implode("\n", $pl); }
    if (!empty($in['nav'])) { $nl = []; foreach (nav_items() as $g) { $nl[] = '- ' . ($g['section'] ? $g['section'] . ' › ' : '') . ($g['label'] ?? '') . (!empty($g['tabs']) ? ': ' . implode(', ', array_values($g['tabs'])) : ''); } $user .= "\n\nMENU:\n" . implode("\n", $nl); }
    if (!empty($in['admin'])) $user .= "\n\nPLATFORM:\n" . ai_admin_context();
    $user .= $extra !== '' ? "\n\nUSER REQUEST:\n" . $extra : '';
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], !empty($in['csv']) ? 2500 : 1200, $in['group']);
        $text = trim($r['text']);
        if (!empty($in['csv'])) {
            $text = trim(preg_replace('/^```\w*\s*|```\s*$/m', '', $text));
            $out(['csv' => $text, 'rows' => max(0, count(array_filter(explode("\n", $text))) - 1), 'model' => $r['model'], 'cost' => $r['cost'] ?? null]);
        }
        $meta = $r['model'] . (isset($r['cost']) && $r['cost'] !== null ? ' · $' . rtrim(rtrim(number_format((float)$r['cost'], 5), '0'), '.') : '');
        $h = &$_SESSION['copilot'][(int)ws('id')]; if (!is_array($h)) $h = [];
        $h[] = ['role' => 'user', 'content' => ($in['icon'] ?? '') . ' ' . $in['label'] . ($extra !== '' ? "\n" . mb_substr($extra, 0, 300) : ''), 'label' => 1, 't' => time()];
        $h[] = ['role' => 'assistant', 'content' => $text, 'meta' => $meta, 't' => time()];
        $h = array_slice($h, -30);
        $out(['html' => ai_render($text), 'text' => $text, 'model' => $r['model'], 'cost' => $r['cost'] ?? null, 'meta' => $meta]);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

// أوامر جاهزة (✨): تحسين، هاشتاجات، نبرة، تلخيص، تصنيف...
if ($task === 'preset') {
    $presets = ai_presets();
    $key = (string)($_POST['preset'] ?? '');
    if (!isset($presets[$key])) $out(['error' => t('طلب غير صحيح.')], 400);
    if (!ai_feature_on($key)) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
    $pr = $presets[$key];
    $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 4000);
    $ctx = mb_substr(trim((string)($_POST['context'] ?? '')), -5000);
    if ($pr['needs'] === 'text' && $text === '') $out(['error' => t('اكتب النص أولًا.')], 422);
    if ($pr['needs'] === 'context' && $ctx === '') $out(['error' => t('لا يوجد محتوى لتحليله.')], 422);
    $img = (string)($_POST['image'] ?? '');
    if ($pr['needs'] === 'image') {
        if (!ai_feature_on('vision')) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $img) || strlen($img) > 3 * 1048576) $out(['error' => t('اختر صورة أولًا (أقل من 2 ميجا).')], 422);
    }
    $sys = str_replace('{lang}', lang() === 'ar' ? 'Arabic' : 'English', $pr['sys']) . ' The business is "' . ws('name') . '".';
    $user = $pr['needs'] === 'context' ? "Content:\n" . $ctx . ($text !== '' ? "\n\nCurrent draft:\n" . $text : '') : ($text !== '' ? $text : $ctx);
    try {
        $userMsg = $pr['needs'] === 'image' ? ['role' => 'user', 'content' => [['type' => 'text', 'text' => $text !== '' ? "Draft text:\n" . $text : 'Image:'], ['type' => 'image_url', 'image_url' => ['url' => $img]]]] : ['role' => 'user', 'content' => $user];
        $r = ai_chat([['role' => 'system', 'content' => str_replace('{lang}', lang() === 'ar' ? 'Arabic' : 'English', $sys)], $userMsg], (int)(ai_tasks()[$pr['group']]['max'] ?? 700), $pr['group']);
        $out(['text' => $pr['mode'] === 'show' ? trim($r['text']) : trim($r['text'], " \n\"'«»"), 'mode' => $pr['mode'], 'model' => $r['model'], 'cost' => $r['cost'] ?? null]);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

if ($task === 'reply' && !ai_feature_on('reply_btn')) $out(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($task, ['compose', 'reply'], true)) $out(['error' => t('طلب غير صحيح.')], 400);
if ($task === 'compose' && !can('posts.create')) $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
if ($task === 'reply' && !can('inbox.reply') && !can('comments.reply')) $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);

$text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 3000);
$ctx = mb_substr(trim((string)($_POST['context'] ?? '')), -3000);
$lang = lang() === 'ar' ? 'Arabic' : 'English';
$brand = (string)ws('name');

if ($task === 'compose') {
    if ($text === '') $out(['error' => t('اكتب فكرة المنشور أو مسودة الأول.')], 422);
    $sys = "You are a social media copywriter for \"$brand\". Write in the same language as the user's text (default $lang). "
        . "If the text is a short idea, write a ready-to-publish post from it. If it's a draft, improve it while keeping its meaning and facts. "
        . "Keep it concise, natural, with at most 3 relevant hashtags and no more than 2 emojis. Return ONLY the post text, no quotes or explanations.";
    $msgs = [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $text]];
} else {
    if ($ctx === '') $out(['error' => t('مفيش محادثة نقترح رد عليها.')], 422);
    $sys = "You are a customer support agent for \"$brand\". Read the conversation and write ONE short, polite, helpful reply to the customer's latest message, "
        . "in the same language and dialect the customer uses. Never invent prices, policies or promises; if information is missing, ask a brief follow-up question. "
        . "Return ONLY the reply text.";
    $msgs = [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => "Conversation:\n" . $ctx . ($text !== '' ? "\n\nDraft to improve:\n" . $text : '')]];
}
try {
    $r = ai_chat($msgs, ai_tasks()[$task]['max'], $task);
    log_activity('ai.' . $task, $r['model'] . ' · ' . (int)($r['usage']['total_tokens'] ?? 0) . ' tokens');
    $out(['text' => trim($r['text'], " \n\"'«»"), 'model' => $r['model']]);
} catch (Throwable $e) {
    $out(['error' => $e->getMessage()], 502);
}
