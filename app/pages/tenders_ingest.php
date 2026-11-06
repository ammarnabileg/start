<?php
/**
 * استقبال المنافسات من السحّاب (أو أي مصدر تاني) — مش محتاج تسجيل دخول.
 * الحماية: توكن سري واحد على مستوى المنصة (Authorization: Bearer ...)، بيظهر لمدير المنصة في صفحة المنافسات.
 * الجسم: {"src":"etimad","items":[ {tenderId, referenceNumber, tenderName, ...كما يرجعها اعتماد} ]} — لحد 500 في المرة.
 */
header('Content-Type: application/json; charset=utf-8');
$deny = function (int $code, string $msg) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; };
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $deny(405, 'POST only');
$real = (string)setting('tenders_ingest_token', '');
$tok = preg_match('/^Bearer\s+(\S+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''), $m) ? $m[1] : (string)($_GET['t'] ?? '');
if (strlen($real) < 32 || $tok === '' || !hash_equals($real, $tok)) $deny(403, 'bad token');
$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 4 * 1024 * 1024) $deny(413, 'too large');
$j = json_decode($raw, true);
if (!is_array($j) || !isset($j['items']) || !is_array($j['items'])) $deny(400, 'bad json');
if (count($j['items']) > 500) $deny(413, 'max 500 items');
if (count($j['items']) === 0 && empty($j['note'])) $deny(400, 'no items');
$src = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($j['src'] ?? 'etimad'))) ?: 'etimad';
$r = tnd_ingest($src, $j['items']);
if ($r['new'] + $r['updated'] > 0) save_setting('tenders_last_ingest', gmdate('Y-m-d H:i:s')); // دفعة فاضية (السحّاب اتمنع مثلًا) ماتخبّيش إن البيانات متأخرة
save_setting('tenders_last_stats', json_encode(['at' => gmdate('c'), 'src' => $src] + $r + ['note' => mb_substr((string)($j['note'] ?? ''), 0, 200)]));
echo json_encode(['ok' => true] + $r);
