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
$target_user_id = (int)($data['user_id'] ?? 0);
$community_id = (int)($data['community_id'] ?? 0);
$points = (int)($data['points'] ?? 0);
$reason = trim($data['reason'] ?? 'Manual award by owner');

if (!$target_user_id || !$community_id || $points <= 0 || $points > 10000) {
 echo json_encode(['error' => 'Invalid parameters']); exit;
}

// Verify caller is admin or owner of the community
$community = db_fetch('SELECT * FROM communities WHERE id = ?', [$community_id]);
if (!$community) { echo json_encode(['error' => 'Community not found']); exit; }
$caller_mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $community_id]);
if (!$caller_mem || !in_array($caller_mem['role'], ['admin', 'owner'])) {
 echo json_encode(['error' => 'Only admins can award points']); exit;
}

// Verify target is a member
$membership = db_fetch('SELECT 1 FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$target_user_id, $community_id]);
if (!$membership) {
 echo json_encode(['error' => 'User is not a member of this community']); exit;
}

award_points($target_user_id, $community_id, $points, $reason);

$target_user = db_fetch('SELECT first_name, username FROM users WHERE id = ?', [$target_user_id]);
$name = $target_user ? ($target_user['first_name'] ?: $target_user['username']) : 'User';

create_notification($target_user_id, 'points_awarded', 'Points Awarded!',
 "You received {$points} XP in {$community['name']}" . ($reason ? ": {$reason}" : ''),
 '/community.php?slug=' . urlencode($community['slug']) . '&tab=leaderboard'
);

echo json_encode(['success' => true, 'points' => $points]);
