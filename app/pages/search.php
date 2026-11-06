<?php
/** بحث سريع (Ctrl+K): العملاء بالاسم أو الرقم أو الإيميل أو الشركة — من غير ذكاء اصطناعي */
header('Content-Type: application/json; charset=utf-8');
$q = trim((string)($_GET['q'] ?? ''));
$out = ['leads' => []];
if (mb_strlen($q) >= 2 && module_enabled('crm') && can('crm.view')) {
    $wid = (int)ws('id'); $uid = (int)(current_user()['id'] ?? 0); $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    $digits = preg_replace('/\D/', '', $q);
    $sql = 'SELECT id, name, company, phone, email, stage FROM crm_leads WHERE workspace_id = ? AND (name LIKE ? OR company LIKE ? OR email LIKE ?' . (strlen($digits) >= 4 ? ' OR phone LIKE ?' : '') . ')';
    $args = [$wid, $like, $like, $like]; if (strlen($digits) >= 4) $args[] = '%' . $digits . '%';
    if (!can('crm.manage')) { $sql .= ' AND (owner_id = ? OR owner_id IS NULL)'; $args[] = $uid; }
    $st = db()->prepare($sql . ' ORDER BY (name LIKE ?) DESC, id DESC LIMIT 8'); $args[] = $q . '%'; $st->execute($args);
    $stg = crm_stages();
    foreach ($st->fetchAll() as $r) $out['leads'][] = ['id' => (int)$r['id'], 'name' => $r['name'], 'sub' => trim(implode(' · ', array_filter([(string)$r['company'], $stg[$r['stage']] ?? '', (string)($r['phone'] ?: $r['email'])])))];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
