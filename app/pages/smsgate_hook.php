<?php
/* استقبال الويبهوك من تطبيق SMS Gateway على الموبايل (رسالة جاية + حالة الرسايل اللي اتبعتت).
   الحماية: توكن سري في الرابط لكل شركة + توقيع HMAC لو مفتاح التوقيع متسجل. */
header('Content-Type: application/json; charset=utf-8');
$deny = function (int $code, string $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; };
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $deny(405, 'POST only');
$wid = (int)($_GET['w'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
$c = $wid ? (connections('sms_gate', $wid)[0] ?? null) : null;
if (!$c || $tok === '' || !hash_equals((string)($c['settings']['hook_token'] ?? ''), $tok)) $deny(403, 'bad token');

$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 512 * 1024) $deny(413, 'too large');
$key = (string)($c['credentials']['signing'] ?? '');
if ($key !== '') {
    $sig = (string)($_SERVER['HTTP_X_SIGNATURE'] ?? '');
    $ts = (string)($_SERVER['HTTP_X_TIMESTAMP'] ?? '');
    if ($sig === '' || !ctype_digit($ts) || abs(time() - (int)$ts) > 300 || !hash_equals(hash_hmac('sha256', $raw . $ts, $key), strtolower($sig))) $deny(401, 'bad signature');
}
$j = json_decode($raw, true);
if (!is_array($j)) $deny(400, 'bad json');
$ev = (string)($j['event'] ?? '');
$p = (array)($j['payload'] ?? []);
$mid = mb_substr((string)($p['messageId'] ?? $p['id'] ?? ''), 0, 64);

if ($ev === 'sms:received') {
    $from = (string)($p['sender'] ?? $p['phoneNumber'] ?? '');
    $text = (string)($p['message'] ?? $p['text'] ?? '');
    if ($from !== '' && $text !== '') {
        $dup = false;
        if ($mid !== '') { $st = db()->prepare('SELECT 1 FROM sms_gate_messages WHERE workspace_id = ? AND ext_id = ? AND direction = \'in\' LIMIT 1'); $st->execute([$wid, $mid]); $dup = (bool)$st->fetchColumn(); }
        if (!$dup) {
            $at = !empty($p['receivedAt']) && strtotime((string)$p['receivedAt']) ? date('Y-m-d H:i:s', strtotime((string)$p['receivedAt'])) : null;
            smsgate_log($wid, 'in', $from, $text, $mid, 'received', isset($p['simNumber']) ? (int)$p['simNumber'] : null, null, $at);
        }
    }
} elseif (in_array($ev, ['sms:sent', 'sms:delivered', 'sms:failed'], true) && $mid !== '') {
    $state = substr($ev, 4);
    $err = $ev === 'sms:failed' ? mb_substr((string)($p['reason'] ?? $p['error'] ?? ''), 0, 250) : null;
    // "delivered" مايرجعش لـ "sent" لو الترتيب اتلخبط
    db()->prepare("UPDATE sms_gate_messages SET state = ?, error = COALESCE(?, error) WHERE workspace_id = ? AND ext_id = ? AND direction = 'out' AND NOT (state = 'delivered' AND ? = 'sent')")
        ->execute([$state, $err, $wid, $mid, $state]);
}
echo json_encode(['ok' => true]);
exit;
