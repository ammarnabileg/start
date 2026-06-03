<?php
require_once '../includes/config.php';

$cat_id  = (int)($_GET['cat'] ?? 0);
$offset  = (int)($_GET['offset'] ?? 0);
$type    = ($_GET['type'] ?? 'personalities') === 'institutions' ? 'institutions' : 'personalities';
$limit   = 10;

if (!$cat_id) { echo json_encode(['items'=>[],'has_more'=>false]); exit; }

$items = [];
if ($type === 'personalities') {
    $r = $mysqli->query("SELECT p_id,p_name_ar,p_title,p_photo,p_verified,p_membership_type
                         FROM pi_personalities p
                         JOIN pi_personality_categories pc ON p.p_id=pc.p_id
                         WHERE pc.cat_id=$cat_id AND p.p_active=1
                         ORDER BY p.p_views DESC
                         LIMIT $limit OFFSET $offset");
    if ($r) while ($row=$r->fetch_assoc()) $items[] = $row;
} else {
    $r = $mysqli->query("SELECT i.inst_id,i.inst_name_ar,i.inst_name_en,i.inst_logo,i.inst_verified
                         FROM pi_institutions i
                         JOIN pi_institution_categories ic ON i.inst_id=ic.inst_id
                         WHERE ic.cat_id=$cat_id AND i.inst_active=1
                         ORDER BY i.inst_views DESC
                         LIMIT $limit OFFSET $offset");
    if ($r) while ($row=$r->fetch_assoc()) $items[] = $row;
}

echo json_encode(['items' => $items, 'has_more' => count($items) === $limit]);
