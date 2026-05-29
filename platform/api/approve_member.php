<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!verify_csrf($data['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']); exit;
}
if (!is_logged_in()) {
    echo json_encode(['error' => 'Authentication required']); exit;
}

$current_user = get_auth_user();
$membership_id = (int)($data['membership_id'] ?? 0);
$status = $data['status'] ?? '';

if (!$membership_id || !in_array($status, ['approved', 'rejected', 'banned'])) {
    echo json_encode(['error' => 'Invalid parameters']); exit;
}

$membership = db_fetch('SELECT * FROM memberships WHERE id = ?', [$membership_id]);
if (!$membership) {
    echo json_encode(['error' => 'Membership not found']); exit;
}

// Verify caller is admin/owner of community
$caller_mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $membership['community_id']]);
if (!$caller_mem || !in_array($caller_mem['role'], ['admin', 'owner'])) {
    echo json_encode(['error' => 'Permission denied']); exit;
}

$old_status = $membership['status'];
db_execute('UPDATE memberships SET status=? WHERE id=?', [$status, $membership_id]);

if ($status === 'approved' && $old_status !== 'approved') {
    // Increment member count
    db_execute('UPDATE communities SET member_count = member_count + 1 WHERE id = ?', [$membership['community_id']]);

    $community = db_fetch('SELECT * FROM communities WHERE id = ?', [$membership['community_id']]);
    $community_name = $community ? $community['name'] : 'the community';

    // Notify new member
    create_notification($membership['user_id'], 'membership_approved', 'Membership Approved!',
        "You've been approved to join {$community_name}",
        '/platform/community.php?slug=' . ($community ? $community['slug'] : '')
    );

    // Auto-follow owner
    $owner_id = $community['owner_id'] ?? 0;
    if ($owner_id && $owner_id !== $membership['user_id']) {
        $follow_exists = db_fetch('SELECT 1 FROM follows WHERE follower_id=? AND following_id=?', [$membership['user_id'], $owner_id]);
        if (!$follow_exists) {
            db_insert('INSERT INTO follows (follower_id, following_id) VALUES (?,?)', [$membership['user_id'], $owner_id]);
        }
    }
}

echo json_encode(['success' => true, 'status' => $status]);
