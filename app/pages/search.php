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
    $rows = $st->fetchAll(); $seen = array_column($rows, 'id');
    // #86 كمان في الملاحظات وسجل التواصل
    $sq = 'SELECT l.id, l.name, l.company, l.phone, l.email, l.stage, a.body hit FROM crm_acts a JOIN crm_leads l ON l.id = a.lead_id WHERE a.workspace_id = ? AND a.body LIKE ?' . (!can('crm.manage') ? ' AND (l.owner_id = ? OR l.owner_id IS NULL)' : '') . ' ORDER BY a.id DESC LIMIT 20';
    $sa = [$wid, $like]; if (!can('crm.manage')) $sa[] = $uid; $st2 = db()->prepare($sq); $st2->execute($sa);
    foreach ($st2->fetchAll() as $r) { if (in_array($r['id'], $seen)) continue; $seen[] = $r['id']; $r['sub2'] = $r['hit']; $rows[] = $r; if (count($rows) >= 12) break; }
    $st3 = db()->prepare('SELECT id, name, company, phone, email, stage, note hit FROM crm_leads WHERE workspace_id = ? AND note LIKE ?' . (!can('crm.manage') ? ' AND (owner_id = ? OR owner_id IS NULL)' : '') . ' LIMIT 8'); $st3->execute($sa);
    foreach ($st3->fetchAll() as $r) { if (in_array($r['id'], $seen)) continue; $seen[] = $r['id']; $r['sub2'] = $r['hit']; $rows[] = $r; }
    foreach ($rows as $r) if (!empty($r['sub2'])) { $pos = mb_stripos($r['sub2'], $q); $r2 = '“…' . mb_substr($r['sub2'], max(0, (int)$pos - 25), 70) . '…”'; $out['leads'][] = ['id' => (int)$r['id'], 'name' => $r['name'], 'sub' => $r2]; }
    $rows = array_filter($rows, function ($r) { return empty($r['sub2']); });
    foreach ($rows as $r) $out['leads'][] = ['id' => (int)$r['id'], 'name' => $r['name'], 'sub' => trim(implode(' · ', array_filter([(string)$r['company'], $stg[$r['stage']] ?? '', (string)($r['phone'] ?: $r['email'])])))];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
