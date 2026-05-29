<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Authentication required']); exit;
}

$current_user = get_current_user();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'mark_all_read') {
    db_execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$current_user['id']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_read') {
    $notif_id = (int)($data['id'] ?? 0);
    if ($notif_id) {
        db_execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$notif_id, $current_user['id']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'list') {
    $limit = (int)($_GET['limit'] ?? 20);
    $notifications = db_fetch_all(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
        [$current_user['id'], $limit]
    );
    $unread = get_unread_notification_count($current_user['id']);
    echo json_encode(['success' => true, 'notifications' => $notifications, 'unread' => $unread]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
