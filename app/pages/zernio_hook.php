<?php
/**
 * إشعار فوري من Zernio (message.received / comment.received): الرد الذكي بيرد على طول من غير cron.
 * الحماية: رقم الشركة + توكن في الرابط + توقيع HMAC-SHA256 للجسم (X-Zernio-Signature).
 * لازم نرد 2xx خلال 5 ثواني، فبنقفل الاتصال الأول وبعدين نشغّل الرد.
 */
$wid = (int)($_GET['w'] ?? 0);
$raw = (string)file_get_contents('php://input');
$cfg = json_decode((string)setting('ai_hook_' . $wid, ''), true) ?: [];
$ok = $wid && !empty($cfg['token']) && hash_equals((string)$cfg['token'], (string)($_GET['k'] ?? ''));
$sig = (string)($_SERVER['HTTP_X_ZERNIO_SIGNATURE'] ?? $_SERVER['HTTP_X_LATE_SIGNATURE'] ?? '');
if ($ok && !empty($cfg['secret']) && $sig !== '') $ok = hash_equals(hash_hmac('sha256', $raw, (string)$cfg['secret']), strtolower($sig));
elseif ($ok && !empty($cfg['secret'])) $ok = false;
if (!$ok) { http_response_code(401); exit('no'); }

$ev = json_decode($raw, true) ?: [];
$type = (string)($ev['event'] ?? ($_SERVER['HTTP_X_ZERNIO_EVENT'] ?? ''));
// تكرار نفس الحدث (Zernio بيبعت مرة أو أكتر): نتجاهله
$eid = (string)($ev['id'] ?? ($_SERVER['HTTP_X_ZERNIO_EVENT_ID'] ?? ''));
$seen = json_decode((string)setting('ai_hook_seen_' . $wid, ''), true) ?: [];
$dup = $eid !== '' && isset($seen[$eid]);
if ($eid !== '' && !$dup) { $seen[$eid] = time(); save_setting('ai_hook_seen_' . $wid, json_encode(array_slice($seen, -300, null, true))); }

// رد سريع وقفل الاتصال
http_response_code(200); header('Content-Type: text/plain'); header('Connection: close'); $body = 'ok'; header('Content-Length: ' . strlen($body)); echo $body;
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else { @ob_end_flush(); @flush(); }
ignore_user_abort(true); @set_time_limit(120);

$incoming = ($type === 'message.received' && ($ev['message']['direction'] ?? 'incoming') === 'incoming')
    || ($type === 'comment.received' && empty($ev['comment']['author']['isOwnAccount']));
save_setting('ai_hook_last_' . $wid, json_encode(['t' => time(), 'event' => $type, 'raw' => mb_substr($raw, 0, 3000)], JSON_UNESCAPED_UNICODE));
if ($dup || !$incoming) exit;
// رسالة صادرة من التطبيق نفسه بتيجي message.sent: مش بنرد عليها (الإيقاف بعد الرد البشري بيتحسب من سجل المحادثة)
if (!function_exists('ai_auto_enabled') || !ai_auto_enabled($wid, 'reply') || !ai_feature_on('auto_reply', $wid)) exit;

// كل إشعار بيتعالج من بياناته هو (قائمة Zernio بتتأخر). القفل لكل محادثة/تعليق بس، فكذا رسالة تترد في نفس الوقت
$handle = function (string $type, array $ev) use ($wid) {
    $acc = (string)($ev['account']['accountId'] ?? $ev['account']['id'] ?? $ev['conversation']['accountId'] ?? '');
    if ($type === 'message.received') {
        $m = (array)($ev['message'] ?? []);
        $c = ['id' => (string)($m['conversationId'] ?? $ev['conversation']['id'] ?? ''), 'accountId' => $acc, 'lastMessage' => (string)($m['text'] ?? ''), 'platform' => (string)($m['platform'] ?? $ev['account']['platform'] ?? ''),
            'participantName' => (string)($m['sender']['name'] ?? $m['sender']['username'] ?? $ev['conversation']['participantName'] ?? ''), 'unreadCount' => 1, 'lastMessageAt' => (string)($m['sentAt'] ?? $ev['timestamp'] ?? '')];
        return ai_auto_reply($wid, false, $c);
    }
    $cm = (array)($ev['comment'] ?? []);
    return ai_auto_reply($wid, false, null, ['id' => (string)($cm['id'] ?? ''), 'postId' => (string)($cm['postId'] ?? $ev['post']['id'] ?? $cm['platformPostId'] ?? $ev['post']['platformPostId'] ?? ''), 'accountId' => $acc,
        'text' => (string)($cm['text'] ?? ''), 'platform' => (string)($cm['platform'] ?? $ev['account']['platform'] ?? ''), 'name' => (string)($cm['author']['name'] ?? $cm['author']['username'] ?? '')]);
};
$key = $type === 'message.received' ? 'c:' . (string)($ev['message']['conversationId'] ?? $ev['conversation']['id'] ?? '') : 'cm:' . (string)($ev['comment']['id'] ?? '');
$lk = 'ai_hook_lock_' . $wid . '_' . md5($key);
if ((int)setting($lk, '0') > time() - 60) { usleep(1500000); } // رسالتين ورا بعض في نفس المحادثة: نستنى الأولى تخلص
save_setting($lk, (string)time());
try { ai_as_workspace($wid, function () use ($handle, $type, $ev) { return $handle($type, $ev); }); } catch (Throwable $e) {}
save_setting($lk, '0');
exit;
