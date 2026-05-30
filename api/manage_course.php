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

if ($action === 'delete_course') {
 $course_id = (int)($data['course_id'] ?? 0);
 if (!$course_id) { echo json_encode(['error' => 'course_id required']); exit; }

 $course = db_fetch('SELECT * FROM courses WHERE id = ?', [$course_id]);
 if (!$course) { echo json_encode(['error' => 'Course not found']); exit; }

 // Check admin/owner
 $mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $course['community_id']]);
 if (!$mem || !in_array($mem['role'], ['admin', 'owner'])) {
 echo json_encode(['error' => 'Permission denied']); exit;
 }

 // Cascade delete
 $sections = db_fetch_all('SELECT id FROM course_sections WHERE course_id = ?', [$course_id]);
 foreach ($sections as $sec) {
 $lessons = db_fetch_all('SELECT id FROM lessons WHERE section_id = ?', [$sec['id']]);
 foreach ($lessons as $les) {
 db_execute('DELETE FROM lesson_progress WHERE lesson_id = ?', [$les['id']]);
 }
 db_execute('DELETE FROM lessons WHERE section_id = ?', [$sec['id']]);
 }
 db_execute('DELETE FROM course_sections WHERE course_id = ?', [$course_id]);
 db_execute('DELETE FROM courses WHERE id = ?', [$course_id]);

 echo json_encode(['success' => true]);
 exit;
}

if ($action === 'reorder') {
 $items = $data['items'] ?? [];
 $type = $data['type'] ?? 'section'; // 'section' or 'lesson'

 foreach ($items as $item) {
 $id = (int)($item['id'] ?? 0);
 $order = (int)($item['sort_order'] ?? 0);
 if (!$id) continue;
 if ($type === 'section') {
 db_execute('UPDATE course_sections SET sort_order = ? WHERE id = ?', [$order, $id]);
 } else {
 db_execute('UPDATE lessons SET sort_order = ? WHERE id = ?', [$order, $id]);
 }
 }
 echo json_encode(['success' => true]);
 exit;
}

echo json_encode(['error' => 'Unknown action']);
