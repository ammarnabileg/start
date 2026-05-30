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
$action = $data['action'] ?? 'update_status';

// Handle promote/demote admin actions
if ($action === 'promote_admin' || $action === 'demote_admin') {
    $user_id = (int)($data['user_id'] ?? 0);
    $community_id = (int)($data['community_id'] ?? 0);
    if (!$user_id || !$community_id) { echo json_encode(['error'=>'Missing params']); exit; }

    // Only owner can promote/demote
    $caller = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $community_id]);
    if (!$caller || $caller['role'] !== 'owner') { echo json_encode(['error'=>'Only owner can manage admins']); exit; }

    $new_role = $action === 'promote_admin' ? 'admin' : 'member';
    db_execute('UPDATE memberships SET role=? WHERE user_id=? AND community_id=? AND status="approved"', [$new_role, $user_id, $community_id]);
    echo json_encode(['success'=>true, 'role'=>$new_role]); exit;
}

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
} elseif (in_array($status, ['rejected', 'banned']) && $old_status === 'approved') {
    // Decrement member count when removing an approved member
    db_execute('UPDATE communities SET member_count = GREATEST(member_count - 1, 0) WHERE id = ?', [$membership['community_id']]);
}

if ($status === 'approved' && $old_status !== 'approved') {
    $community = db_fetch('SELECT * FROM communities WHERE id = ?', [$membership['community_id']]);
    $community_name = $community ? $community['name'] : 'the community';

    // Notify new member
    create_notification($membership['user_id'], 'membership_approved', 'Membership Approved!',
        "You've been approved to join {$community_name}",
        '/community.php?slug=' . ($community ? $community['slug'] : '')
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
