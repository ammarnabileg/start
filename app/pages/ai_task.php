<?php
/** مهام الذكاء الاصطناعي السريعة (كتابة منشور، اقتراح رد). بترجع JSON. */
header('Content-Type: application/json; charset=utf-8');
$task = (string)($_POST['task'] ?? '');
$out = function (array $d, int $code = 200) { http_response_code($code); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
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
