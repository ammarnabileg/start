<?php
/* زيارة صفحة من كود الموقع (crm_js). بيتقبل بس لو توكن العميل صحيح */
header('Access-Control-Allow-Origin: *'); header('Cache-Control: no-store');
$wid = (int)($_GET['w'] ?? 0); $k = (string)($_GET['k'] ?? ''); $u = (string)($_GET['u'] ?? '');
$lead = $wid && preg_match('~^https?://~i', $u) ? crm_lead_from_token($wid, $k) : 0;
if ($lead) { $st = db()->prepare('SELECT id FROM crm_leads WHERE id = ? AND workspace_id = ?'); $st->execute([$lead, $wid]); if ($st->fetchColumn()) crm_track_visit($wid, $lead, $u); }
header('Content-Type: image/gif'); echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); exit;
