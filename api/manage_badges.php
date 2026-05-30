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
$action = $data['action'] ?? '';
$community_id = (int)($data['community_id'] ?? 0);

if (!$community_id) { echo json_encode(['error' => 'community_id required']); exit; }

// Verify admin/owner
$mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $community_id]);
if (!$mem || !in_array($mem['role'], ['admin', 'owner'])) {
 echo json_encode(['error' => 'Permission denied']); exit;
}

if ($action === 'create' || $action === 'create_badge') {
 $name = trim($data['name'] ?? '');
 $description = trim($data['description'] ?? '');
 $icon = trim($data['icon'] ?? '🏅');
 $points_req = (int)($data['points_required'] ?? 0);
 $badge_type = $data['badge_type'] ?? 'achievement';

 if (!$name) { echo json_encode(['error' => 'Badge name required']); exit; }

 $badge_id = db_insert(
 'INSERT INTO badges (community_id, name, description, icon, points_required, badge_type) VALUES (?,?,?,?,?,?)',
 [$community_id, $name, $description, $icon, $points_req, $badge_type]
 );

 echo json_encode(['success' => true, 'badge_id' => $badge_id]);
 exit;
}

if ($action === 'award' || $action === 'award_badge') {
 $badge_id = (int)($data['badge_id'] ?? 0);
 $target_user = (int)($data['user_id'] ?? 0);

 if (!$badge_id || !$target_user) { echo json_encode(['error' => 'badge_id and user_id required']); exit; }

 $badge = db_fetch('SELECT * FROM badges WHERE id = ?', [$badge_id]);
 if (!$badge) { echo json_encode(['error' => 'Badge not found']); exit; }

 // Check if already awarded
 $existing = db_fetch('SELECT 1 FROM user_badges WHERE user_id=? AND badge_id=? AND community_id=?', [$target_user, $badge_id, $community_id]);
 if ($existing) { echo json_encode(['error' => 'Badge already awarded to this user']); exit; }

 db_insert('INSERT INTO user_badges (user_id, badge_id, community_id) VALUES (?,?,?)', [$target_user, $badge_id, $community_id]);

 $community = db_fetch('SELECT name, slug FROM communities WHERE id = ?', [$community_id]);
 create_notification($target_user, 'badge_awarded', 'Badge Earned!',
 "You earned the {$badge['name']} badge in {$community['name']}",
 '/community.php?slug=' . urlencode($community['slug'] ?? '') . '&tab=leaderboard'
 );

 echo json_encode(['success' => true]);
 exit;
}

if ($action === 'delete' || $action === 'delete_badge') {
 $badge_id = (int)($data['badge_id'] ?? 0);
 if (!$badge_id) { echo json_encode(['error' => 'badge_id required']); exit; }

 $badge = db_fetch('SELECT * FROM badges WHERE id = ? AND community_id = ?', [$badge_id, $community_id]);
 if (!$badge) { echo json_encode(['error' => 'Badge not found']); exit; }

 db_execute('DELETE FROM user_badges WHERE badge_id = ?', [$badge_id]);
 db_execute('DELETE FROM badges WHERE id = ?', [$badge_id]);

 echo json_encode(['success' => true]);
 exit;
}

if ($action === 'list_badges') {
 $badges = db_fetch_all('SELECT * FROM badges WHERE community_id = ? ORDER BY created_at DESC', [$community_id]);
 echo json_encode(['success' => true, 'badges' => $badges]);
 exit;
}

echo json_encode(['error' => 'Unknown action']);
