<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/events.php';
api_authenticate();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!has_any_perm(['clients.view.own','clients.view.all','clients.manage']))
        api_error(403, 'clients_view_denied');
    [$ownerSql, $ownerParams] = scope_owned('clients');
    $rows = db_all('
        SELECT c.*, u.name AS owner_name
        FROM ' . tbl('clients') . ' c
        LEFT JOIN ' . tbl('users') . ' u ON u.id = c.owner_id
        WHERE 1 ' . $ownerSql . '
        ORDER BY c.created_at DESC LIMIT 100
    ', $ownerParams);
    api_ok(['data' => $rows, 'count' => count($rows)]);
}

if ($method === 'POST') {
    if (!has_perm('clients.manage')) api_error(403, 'clients_manage_denied');
    $body = api_input();
    $name = trim($body['name'] ?? '');
    if ($name === '') api_error(422, 'name_required');
    $id = db_insert(tbl('clients'), [
        'name'     => $name,
        'type'     => $body['type'] ?? 'company',
        'industry' => $body['industry'] ?? null,
        'phone'    => $body['phone'] ?? null,
        'email'    => $body['email'] ?? null,
        'owner_id' => (int)($body['owner_id'] ?? auth_id()),
        'stage'    => $body['stage'] ?? 'lead',
        'value'    => (float)($body['value'] ?? 0),
        'notes'    => $body['notes'] ?? null,
    ]);
    event_fire('client.created', 'client', (int)$id, [], (int)($body['owner_id'] ?? auth_id()));
    api_ok(['id' => (int)$id, 'message' => 'created'], 201);
}

api_error(405, 'method_not_allowed');
