<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/events.php';
api_authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') api_error(405, 'method_not_allowed');

$uid = auth_id();
[$ownerSqlClients, $opc] = scope_owned('clients');
[$ownerSqlDeals, $opd] = scope_owned('deals');
[$ownerSqlTasks, $opt] = scope_owned('tasks', 'assignee_id');

$data = [
    'clients_total'  => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('clients') . " WHERE 1 $ownerSqlClients", $opc),
    'deals_open'     => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('deals') . " WHERE stage NOT IN ('won','lost') $ownerSqlDeals", $opd),
    'tasks_open'     => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('tasks') . " WHERE status IN ('open','in_progress') $ownerSqlTasks", $opt),
    'tasks_overdue'  => (int)db_scalar("SELECT COUNT(*) FROM " . tbl('tasks') . " WHERE status IN ('open','in_progress') AND due_at < NOW() $ownerSqlTasks", $opt),
    'performance'    => compute_performance($uid),
];
api_ok($data);
