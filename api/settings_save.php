<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
 // Check if it's a GET request for logout_all
 if (isset($_GET['action']) && $_GET['action'] === 'logout_all') {
 header('Location: /login.php');
 exit;
 }
 echo json_encode(['error' => 'Authentication required']); exit;
}

$current_user = get_auth_user();

// Handle GET actions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
 $action = $_GET['action'] ?? '';
 if ($action === 'logout_all') {
 logout_all_devices($current_user['id']);
 header('Location: /login.php');
 exit;
 }
 if ($action === 'remove_payment') {
 $id = (int)($_GET['id'] ?? 0);
 $csrf = $_GET['csrf_token'] ?? '';
 if (!verify_csrf($csrf)) { header('Location: /settings.php?tab=payment&error=csrf'); exit; }
 db_execute('DELETE FROM payment_methods WHERE id = ? AND user_id = ?', [$id, $current_user['id']]);
 header('Location: /settings.php?tab=payment&saved=1');
 exit;
 }
 echo json_encode(['error' => 'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!verify_csrf($data['csrf_token'] ?? '')) {
 echo json_encode(['error' => 'Invalid CSRF token']); exit;
}

$action = $data['action'] ?? '';

if ($action === 'toggle_follow') {
 $target_id = (int)($data['user_id'] ?? 0);
 if (!$target_id || $target_id === $current_user['id']) {
 echo json_encode(['error' => 'Invalid user']); exit;
 }

 $target = db_fetch('SELECT id, first_name, username FROM users WHERE id = ?', [$target_id]);
 if (!$target) { echo json_encode(['error' => 'User not found']); exit; }

 $existing = db_fetch('SELECT 1 FROM follows WHERE follower_id=? AND following_id=?', [$current_user['id'], $target_id]);
 if ($existing) {
 db_execute('DELETE FROM follows WHERE follower_id=? AND following_id=?', [$current_user['id'], $target_id]);
 echo json_encode(['success' => true, 'following' => false, 'message' => 'Unfollowed ' . ($target['first_name'] ?: $target['username'])]);
 } else {
 db_insert('INSERT INTO follows (follower_id, following_id) VALUES (?,?)', [$current_user['id'], $target_id]);

 // Notify
 $ns = db_fetch('SELECT new_follower FROM notification_settings WHERE user_id = ?', [$target_id]);
 if (!$ns || $ns['new_follower']) {
 $my_name = $current_user['first_name'] ?: $current_user['username'];
 create_notification($target_id, 'new_follower', 'New Follower',
 "{$my_name} started following you",
 '/profile.php?username=' . $current_user['username']
 );
 }

 echo json_encode(['success' => true, 'following' => true, 'message' => 'Following ' . ($target['first_name'] ?: $target['username'])]);
 }
 exit;
}

echo json_encode(['error' => 'Unknown action']);
