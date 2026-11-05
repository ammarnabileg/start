<?php
/* Apollo بيبعت أرقام التليفون هنا بعد الكشف (ممكن ياخد دقايق). الحماية: توكن سري لكل شركة في الرابط. */
header('Content-Type: application/json; charset=utf-8');
$deny = function (int $code, string $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; };
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $deny(405, 'POST only');
$wid = (int)($_GET['w'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
$st = db()->prepare('SELECT hook_token FROM crm_prefs WHERE workspace_id = ?');
$st->execute([$wid]);
$real = (string)$st->fetchColumn();
if ($real === '' || $tok === '' || !hash_equals($real, $tok)) $deny(403, 'bad token');
$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 512 * 1024) $deny(413, 'too large');
$j = json_decode($raw, true);
if (!is_array($j)) $deny(400, 'bad json');

// شكل الرد مش ثابت: بندور على أي شخص فيه id و phone_numbers
$people = [];
$walk = function ($n) use (&$walk, &$people) {
    if (!is_array($n)) return;
    if (isset($n['id']) && is_string($n['id']) && isset($n['phone_numbers'])) { $people[] = $n; return; }
    foreach ($n as $v) $walk($v);
};
$walk($j);
$n = 0;
foreach ($people as $p) {
    $phone = crm_intl_phone(crm_pick_phone($p), ws_country($wid));
    $q = db()->prepare('UPDATE crm_leads SET phone = COALESCE(phone, ?), phone_pending = 0 WHERE workspace_id = ? AND ext_id = ?');
    $q->execute([$phone ?: null, $wid, mb_substr($p['id'], 0, 64)]);
    $n += $q->rowCount();
}
echo json_encode(['ok' => true, 'updated' => $n]);
exit;
