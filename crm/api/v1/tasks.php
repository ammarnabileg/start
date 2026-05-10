<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/events.php';
api_authenticate();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
preg_match('#/tasks(?:/(\d+))?(?:/(complete))?#', $path, $m);
$id = $m[1] ?? null;
$action = $m[2] ?? null;

// POST /tasks/:id/complete
if ($method === 'POST' && $id && $action === 'complete') {
    if (!has_perm('tasks.manage')) api_error(403, 'tasks_manage_denied');
    $task = db_one('SELECT * FROM ' . tbl('tasks') . ' WHERE id = :id', ['id' => $id]);
    if (!$task) api_error(404, 'not_found');
    db_update(tbl('tasks'),
        ['status' => 'done', 'completed_at' => date('Y-m-d H:i:s')],
        'id = :id', ['id' => $id]
    );
    event_fire('task.completed', 'task', (int)$id, ['priority' => $task['priority']], (int)$task['assignee_id']);
    api_ok(['message' => 'completed']);
}

if ($method === 'GET') {
    if (!has_any_perm(['tasks.view.own','tasks.view.all','tasks.manage']))
        api_error(403, 'tasks_view_denied');
    [$ownerSql, $ownerParams] = scope_owned('tasks', 't.assignee_id');
    $rows = db_all('
        SELECT t.*, u.name AS assignee_name
        FROM ' . tbl('tasks') . ' t
        LEFT JOIN ' . tbl('users') . ' u ON u.id = t.assignee_id
        WHERE 1 ' . $ownerSql . '
        ORDER BY t.created_at DESC LIMIT 100
    ', $ownerParams);
    api_ok(['data' => $rows, 'count' => count($rows)]);
}

if ($method === 'POST') {
    if (!has_perm('tasks.manage')) api_error(403, 'tasks_manage_denied');
    $body = api_input();
    $title = trim($body['title'] ?? '');
    if ($title === '') api_error(422, 'title_required');
    $newId = db_insert(tbl('tasks'), [
        'title'        => $title,
        'description'  => $body['description'] ?? null,
        'assignee_id'  => (int)($body['assignee_id'] ?? auth_id()),
        'related_type' => $body['related_type'] ?? 'none',
        'related_id'   => $body['related_id'] ?? null,
        'priority'     => $body['priority'] ?? 'medium',
        'status'       => $body['status'] ?? 'open',
        'due_at'       => $body['due_at'] ?? null,
        'created_by'   => auth_id(),
    ]);
    api_ok(['id' => (int)$newId, 'message' => 'created'], 201);
}

api_error(405, 'method_not_allowed');
