<?php
require_once __DIR__ . '/_bootstrap.php';
api_authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') api_error(405, 'method_not_allowed');
if (!has_any_perm(['deals.view.own','deals.view.all','deals.manage']))
    api_error(403, 'deals_view_denied');

[$ownerSql, $ownerParams] = scope_owned('deals');
$rows = db_all('
    SELECT d.*, c.name AS client_name, u.name AS owner_name
    FROM ' . tbl('deals') . ' d
    LEFT JOIN ' . tbl('clients') . ' c ON c.id = d.client_id
    LEFT JOIN ' . tbl('users') . ' u ON u.id = d.owner_id
    WHERE 1 ' . $ownerSql . '
    ORDER BY d.created_at DESC LIMIT 100
', $ownerParams);
api_ok(['data' => $rows, 'count' => count($rows)]);
