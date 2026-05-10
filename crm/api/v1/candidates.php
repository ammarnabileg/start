<?php
require_once __DIR__ . '/_bootstrap.php';
api_authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') api_error(405, 'method_not_allowed');
if (!has_any_perm(['candidates.view.own','candidates.view.all','candidates.manage']))
    api_error(403, 'candidates_view_denied');

[$ownerSql, $ownerParams] = scope_owned('candidates');
$rows = db_all('
    SELECT c.*, u.name AS owner_name
    FROM ' . tbl('candidates') . ' c
    LEFT JOIN ' . tbl('users') . ' u ON u.id = c.owner_id
    WHERE 1 ' . $ownerSql . '
    ORDER BY c.created_at DESC LIMIT 100
', $ownerParams);
api_ok(['data' => $rows, 'count' => count($rows)]);
