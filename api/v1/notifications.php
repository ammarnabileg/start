<?php
Auth::requireAuth();
$db  = Database::getInstance();
$uid = Auth::id();

// GET /api/v1/notifications
if ($method === 'GET' && !$id) {
    $unreadOnly = $req->get('unread') === '1';
    $page       = max(1,(int)$req->get('page',1));
    $where      = "user_id=?";
    $params     = [$uid];
    if ($unreadOnly) { $where .= " AND read_at IS NULL"; }

    $result = $db->paginate(
        "SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC",
        $params, $page, 30
    );
    Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
}

// PATCH /api/v1/notifications/read-all or POST
if (in_array($method,['PATCH','POST']) && $id === 'read-all') {
    $db->query("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL", [$uid]);
    Response::success(null, 'All marked as read');
}

// PATCH /api/v1/notifications/{id}/read
if (in_array($method,['PATCH','POST']) && $id && $sub === 'read') {
    $db->query("UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=?", [$id,$uid]);
    Response::success(null, 'Marked as read');
}

Response::notFound();
