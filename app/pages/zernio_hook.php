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
save_setting('ai_hook_last_' . $wid, json_encode(['t' => time(), 'event' => $type]));
if ($dup || !$incoming) exit;
// رسالة صادرة من التطبيق نفسه بتيجي message.sent: مش بنرد عليها (الإيقاف بعد الرد البشري بيتحسب من سجل المحادثة)
if (!function_exists('ai_auto_enabled') || !ai_auto_enabled($wid, 'reply') || !ai_feature_on('auto_reply', $wid)) exit;

// قفل: تشغيلة واحدة في نفس الوقت؛ لو جت رسالة وهي شغالة بنعلّم إنها تعيد مرة بعد ما تخلص
$lock = (int)setting('ai_hook_lock_' . $wid, '0');
if ($lock > time() - 90) { save_setting('ai_hook_again_' . $wid, '1'); exit; }
for ($round = 0; $round < 3; $round++) {
    save_setting('ai_hook_lock_' . $wid, (string)time()); save_setting('ai_hook_again_' . $wid, '0');
    try { ai_as_workspace($wid, function () use ($wid) { return ai_auto_reply($wid); }); } catch (Throwable $e) {}
    if (setting('ai_hook_again_' . $wid, '0') !== '1') break;
}
save_setting('ai_hook_lock_' . $wid, '0');
exit;
