<?php
/* لينك قصير متتبع للمنشورات: بيعد الضغطة ويحوّل للرابط الأصلي ومعاه utm عشان الفورم/الحجز يعرف العميل جه منين */
$c = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? ''));
$st = $c !== '' ? db()->prepare('SELECT * FROM crm_links WHERE code = ?') : null;
$l = null; if ($st) { $st->execute([$c]); $l = $st->fetch(); }
if (!$l) { http_response_code(404); exit('Link not found'); }
db()->prepare('UPDATE crm_links SET clicks = clicks + 1, last_click = NOW() WHERE id = ?')->execute([(int)$l['id']]);
$u = (string)$l['url'];
$q = 'utm_source=aipush&utm_medium=post&utm_campaign=' . rawurlencode($c);
if (strpos($u, 'wa.me/') === false) $u .= (strpos($u, '?') === false ? '?' : '&') . $q;
header('Location: ' . $u, true, 302); exit;
