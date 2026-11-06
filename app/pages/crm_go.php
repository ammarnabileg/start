<?php
/* رابط متتبع: بيسجّل إن العميل فتحه، وبيحوّله لموقعك ومعاه ?ap= عشان كود الموقع يتابع باقي الصفحات */
$wid = (int)($_GET['w'] ?? 0); $k = (string)($_GET['k'] ?? ''); $u = (string)($_GET['u'] ?? '');
if (!preg_match('~^https?://~i', $u)) { http_response_code(400); exit('bad link'); }
$lead = $wid ? crm_lead_from_token($wid, $k) : 0;
if ($lead) {
    $st = db()->prepare('SELECT id FROM crm_leads WHERE id = ? AND workspace_id = ?'); $st->execute([$lead, $wid]);
    if ($st->fetchColumn()) {
        $first = !(int)db()->query('SELECT COUNT(*) FROM crm_visits WHERE lead_id = ' . (int)$lead)->fetchColumn();
        crm_track_visit($wid, $lead, $u);
        if ($first) ai_as_workspace($wid, function () use ($lead, $u) { crm_act_add($lead, 'note', '🔗 ' . t('فتح الرابط اللي بعتهوله') . "\n" . $u); });
        $u .= (strpos($u, '?') === false ? '?' : '&') . 'ap=' . rawurlencode($k);
    }
}
header('Location: ' . $u, true, 302); exit;
