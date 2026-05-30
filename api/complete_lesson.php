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
$lesson_id = (int)($data['lesson_id'] ?? 0);
$course_id = (int)($data['course_id'] ?? 0);
$community_id = (int)($data['community_id'] ?? 0);

if (!$lesson_id) {
 echo json_encode(['error' => 'lesson_id required']); exit;
}

$lesson = db_fetch('SELECT l.*, cs.course_id FROM lessons l JOIN course_sections cs ON cs.id = l.section_id WHERE l.id = ?', [$lesson_id]);
if (!$lesson) {
 echo json_encode(['error' => 'Lesson not found']); exit;
}

// Check if already completed
$existing = db_fetch('SELECT 1 FROM lesson_progress WHERE user_id=? AND lesson_id=?', [$current_user['id'], $lesson_id]);
if ($existing) {
 echo json_encode(['success' => true, 'already_completed' => true]); exit;
}

// Mark complete
db_insert('INSERT INTO lesson_progress (user_id, lesson_id) VALUES (?,?)', [$current_user['id'], $lesson_id]);

// Award XP
if ($community_id) {
 award_points($current_user['id'], $community_id, 10, 'Completed lesson: ' . ($lesson['title'] ?? ''));
}

// Check course completion
$cid = $course_id ?: $lesson['course_id'];
$course_complete = false;
$badge_awarded = false;

if ($cid) {
 $progress = get_course_progress($current_user['id'], $cid);
 if ($progress['total'] > 0 && $progress['completed'] >= $progress['total']) {
 $course_complete = true;

 // Award bonus XP
 if ($community_id) {
 award_points($current_user['id'], $community_id, 100, 'Completed course!');
 }

 // Award Scholar badge (first course)
 $scholar_badge = db_fetch('SELECT id FROM badges WHERE name = "Scholar" AND community_id IS NULL');
 if ($scholar_badge) {
 $already = db_fetch('SELECT 1 FROM user_badges WHERE user_id=? AND badge_id=?', [$current_user['id'], $scholar_badge['id']]);
 if (!$already && $community_id) {
 db_insert('INSERT INTO user_badges (user_id, badge_id, community_id) VALUES (?,?,?)',
 [$current_user['id'], $scholar_badge['id'], $community_id]);
 $badge_awarded = true;
 }
 }

 // Check Master badge (5 courses)
 if ($community_id) {
 $completed_courses = db_fetch(
 'SELECT COUNT(DISTINCT cs2.course_id) as cnt
 FROM lesson_progress lp2
 JOIN lessons l2 ON l2.id = lp2.lesson_id
 JOIN course_sections cs2 ON cs2.id = l2.section_id
 JOIN courses c2 ON c2.id = cs2.course_id
 WHERE lp2.user_id = ? AND c2.community_id = ?',
 [$current_user['id'], $community_id]
 );
 if ((int)($completed_courses['cnt'] ?? 0) >= 5) {
 $master_badge = db_fetch('SELECT id FROM badges WHERE name = "Master" AND community_id IS NULL');
 if ($master_badge) {
 $already2 = db_fetch('SELECT 1 FROM user_badges WHERE user_id=? AND badge_id=?', [$current_user['id'], $master_badge['id']]);
 if (!$already2) {
 db_insert('INSERT INTO user_badges (user_id, badge_id, community_id) VALUES (?,?,?)',
 [$current_user['id'], $master_badge['id'], $community_id]);
 }
 }
 }
 }

 if ($community_id) {
 create_notification($current_user['id'], 'course_complete', 'Course Complete! 🎉',
 'Congratulations! You completed a course and earned 100 XP + Scholar badge!',
 '/course.php?id=' . $cid
 );
 }
 }
}

echo json_encode([
 'success' => true,
 'xp_earned' => 10,
 'course_complete' => $course_complete,
 'badge_awarded' => $badge_awarded,
]);
