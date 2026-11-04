<?php
/** مهام الذكاء الاصطناعي السريعة (كتابة منشور، اقتراح رد). بترجع JSON. */
header('Content-Type: application/json; charset=utf-8');
$task = (string)($_POST['task'] ?? '');
$out = function (array $d, int $code = 200) { http_response_code($code); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
// توليد الصور عن طريق Higgsfield: طلب، وبعدين متابعة الحالة لحد ما تخلص
if (in_array($task, ['image', 'image_status'], true)) {
    if (!can('posts.create')) $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    $hf = connector('higgsfield');
    if (!$hf) $out(['error' => t('اربط Higgsfield أولًا من صفحة التكاملات.')], 422);
    $c = $hf['credentials'];
    try {
        if ($task === 'image') {
            $prompt = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 1500);
            if ($prompt === '') $out(['error' => t('اكتب وصف الصورة أولًا.')], 422);
            $path = '/' . ltrim(trim((string)($c['model'] ?? '')) ?: '/higgsfield-ai/soul/v2/standard', '/');
            if (!preg_match('#^/[\w./-]+$#', $path)) $out(['error' => t('مسار الموديل غير صحيح.')], 422);
            $r = hf_request($c, 'POST', $path, ['prompt' => $prompt]);
            $id = (string)($r['request_id'] ?? '');
            if ($id === '') throw new Exception(t('لم يرجع Higgsfield رقم الطلب.'));
            $_SESSION['hf_req'][$id] = 1;
            log_activity('ai.image', $path);
            $out(['id' => $id, 'status' => $r['status'] ?? 'queued']);
        }
        $id = (string)($_POST['id'] ?? '');
        if (!preg_match('/^[\w-]{8,80}$/', $id) || empty($_SESSION['hf_req'][$id])) $out(['error' => t('طلب غير صحيح.')], 400);
        $r = hf_request($c, 'GET', '/requests/' . $id . '/status');
        $st = (string)($r['status'] ?? '');
        if ($st === 'completed') {
            $urls = array_values(array_filter(array_map(function ($i) { return (string)($i['url'] ?? ''); }, $r['images'] ?? [])));
            foreach ($urls as $u) $_SESSION['hf_urls'][$u] = 1; // بس الروابط دي اللي هنقبلها في المنشور
            unset($_SESSION['hf_req'][$id]);
            $out(['status' => 'completed', 'images' => $urls]);
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

// تحليلات من بيانات الشركة
if ($task === 'insight') {
    $all = ai_insights();
    $key = (string)($_POST['insight'] ?? '');
    if (!isset($all[$key])) $out(['error' => t('طلب غير صحيح.')], 400);
    $in = $all[$key];
    $extra = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 6000);
    if (!empty($in['input']) && $extra === '' && empty($in['optional'])) $out(['error' => t('اكتب المطلوب أولًا.')], 422);
    if (!can('analytics.view') && $in['group'] === 'analyze') $out(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    $sys = strtr($in['sys'], ['{lang}' => lang() === 'ar' ? 'Arabic (Modern Standard Arabic)' : 'English', '{tz}' => site_timezone()]) . ' The business is "' . ws('name') . '".';
    $user = "DATA:\n" . assistant_context(true) . ($extra !== '' ? "\n\nUSER REQUEST:\n" . $extra : '');
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], !empty($in['csv']) ? 2500 : 1200, $in['group']);
        $text = trim($r['text']);
        if (!empty($in['csv'])) {
            $text = trim(preg_replace('/^```\w*\s*|```\s*$/m', '', $text));
            $out(['csv' => $text, 'rows' => max(0, count(array_filter(explode("\n", $text))) - 1), 'model' => $r['model'], 'cost' => $r['cost'] ?? null]);
        }
        $out(['html' => ai_render($text), 'text' => $text, 'model' => $r['model'], 'cost' => $r['cost'] ?? null]);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

// أوامر جاهزة (✨): تحسين، هاشتاجات، نبرة، تلخيص، تصنيف...
if ($task === 'preset') {
    $presets = ai_presets();
    $key = (string)($_POST['preset'] ?? '');
    if (!isset($presets[$key])) $out(['error' => t('طلب غير صحيح.')], 400);
    $pr = $presets[$key];
    $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 4000);
    $ctx = mb_substr(trim((string)($_POST['context'] ?? '')), -5000);
    if ($pr['needs'] === 'text' && $text === '') $out(['error' => t('اكتب النص أولًا.')], 422);
    if ($pr['needs'] === 'context' && $ctx === '') $out(['error' => t('لا يوجد محتوى لتحليله.')], 422);
    $sys = str_replace('{lang}', lang() === 'ar' ? 'Arabic' : 'English', $pr['sys']) . ' The business is "' . ws('name') . '".';
    $user = $pr['needs'] === 'context' ? "Content:\n" . $ctx . ($text !== '' ? "\n\nCurrent draft:\n" . $text : '') : ($text !== '' ? $text : $ctx);
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], (int)(ai_tasks()[$pr['group']]['max'] ?? 700), $pr['group']);
        $out(['text' => $pr['mode'] === 'show' ? trim($r['text']) : trim($r['text'], " \n\"'«»"), 'mode' => $pr['mode'], 'model' => $r['model'], 'cost' => $r['cost'] ?? null]);
    } catch (Throwable $e) {
        $out(['error' => $e->getMessage()], 502);
    }
}

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
