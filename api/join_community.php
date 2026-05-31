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
 echo json_encode(['error' => 'Login required']); exit;
}

$current_user = get_auth_user();
$community_id = (int)($data['community_id'] ?? 0);

if (!$community_id) {
 echo json_encode(['error' => 'community_id required']); exit;
}

$community = db_fetch('SELECT * FROM communities WHERE id = ? AND is_active = 1', [$community_id]);
if (!$community) {
 echo json_encode(['error' => 'Community not found']); exit;
}

// Check if already member
$existing = db_fetch('SELECT * FROM memberships WHERE user_id=? AND community_id=?', [$current_user['id'], $community_id]);
if ($existing) {
 if ($existing['status'] === 'approved') {
 echo json_encode(['error' => 'Already a member']); exit;
 } elseif ($existing['status'] === 'pending') {
 echo json_encode(['error' => 'Request already pending']); exit;
 } elseif ($existing['status'] === 'banned') {
 echo json_encode(['error' => 'You are banned from this community']); exit;
 }
 // Rejected - allow re-request; auto-approve for public communities
 $re_status = $community['type'] === 'private' ? 'pending' : 'approved';
 db_execute('UPDATE memberships SET status=?, joined_at=NOW() WHERE user_id=? AND community_id=?', [$re_status, $current_user['id'], $community_id]);
 if ($re_status === 'approved') {
 db_execute('UPDATE communities SET member_count = member_count + 1 WHERE id = ?', [$community_id]);
 }
} else {
 $status = $community['type'] === 'private' ? 'pending' : 'approved';

 // For paid communities, handle wallet deduction
 if ($community['pricing'] === 'paid' && (float)$community['price'] > 0 && $status === 'approved') {
 $join_price = (float)$community['price'];
 try {
 $pdo = get_pdo();
 $pdo->beginTransaction();

 $urow = $pdo->query("SELECT wallet_balance FROM users WHERE id = {$current_user['id']} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
 $wallet = (float)($urow['wallet_balance'] ?? 0);

 if ($wallet < $join_price) {
 $pdo->rollBack();
 echo json_encode([
 'error' => 'Insufficient wallet balance. Please top up.',
 'balance' => number_format($wallet, 2, '.', ''),
 'required' => number_format($join_price, 2, '.', ''),
 ]); exit;
 }

 $newBal = $wallet - $join_price;
 $pdo->prepare('UPDATE users SET wallet_balance = ? WHERE id = ?')->execute([$newBal, $current_user['id']]);

 $mem_id = db_insert('INSERT INTO memberships (user_id, community_id, role, status) VALUES (?,?,?,?)',
 [$current_user['id'], $community_id, 'member', $status]);

 $pdo->prepare(
 "INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id, balance_after) VALUES (?,?,?,?,?,?)"
 )->execute([$current_user['id'], -$join_price, 'community_join', 'Joined community: ' . $community['name'], $community_id, $newBal]);

 db_execute('UPDATE communities SET member_count = member_count + 1 WHERE id = ?', [$community_id]);

 $pdo->commit();
 } catch (Exception $e) {
 try { get_pdo()->rollBack(); } catch (Exception $e2) {}
 echo json_encode(['error' => 'Payment failed. Please try again.']); exit;
 }
 // Skip the normal flow below — already done inside transaction
 echo json_encode(['success' => true, 'message' => 'Welcome! You\'ve joined the community.']);
 exit;
 }

 db_insert('INSERT INTO memberships (user_id, community_id, role, status) VALUES (?,?,?,?)',
 [$current_user['id'], $community_id, 'member', $status]);

 if ($status === 'approved') {
 db_execute('UPDATE communities SET member_count = member_count + 1 WHERE id = ?', [$community_id]);

 // Auto-follow community owner
 $owner_id = $community['owner_id'];
 if ($owner_id !== $current_user['id']) {
 $follow_exists = db_fetch('SELECT 1 FROM follows WHERE follower_id=? AND following_id=?', [$current_user['id'], $owner_id]);
 if (!$follow_exists) {
 db_insert('INSERT INTO follows (follower_id, following_id) VALUES (?,?)', [$current_user['id'], $owner_id]);
 }
 }

 // Award "First Steps" badge
 $badge = db_fetch('SELECT id FROM badges WHERE name = "First Steps" AND community_id IS NULL');
 if ($badge) {
 $badge_exists = db_fetch('SELECT 1 FROM user_badges WHERE user_id=? AND badge_id=?', [$current_user['id'], $badge['id']]);
 if (!$badge_exists) {
 db_insert('INSERT INTO user_badges (user_id, badge_id, community_id) VALUES (?,?,?)',
 [$current_user['id'], $badge['id'], $community_id]);
 create_notification($current_user['id'], 'badge_awarded', 'Badge Earned!',
 'You earned the "First Steps" badge for joining your first community!', '/profile.php?username=' . $current_user['username']);
 }
 }
 }
}

$msg = $community['type'] === 'private' ? 'Join request submitted! Awaiting approval.' : 'Welcome! You\'ve joined the community.';
echo json_encode(['success' => true, 'message' => $msg]);
