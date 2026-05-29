<?php
// Redirect to course.php with lesson param
$lesson_id = (int)($_GET['id'] ?? 0);
if (!$lesson_id) { header('Location: /platform/index.php'); exit; }

require_once __DIR__ . '/includes/db.php';
$lesson = db_fetch(
    'SELECT l.*, cs.course_id FROM lessons l JOIN course_sections cs ON cs.id = l.section_id WHERE l.id = ?',
    [$lesson_id]
);
if (!$lesson) { http_response_code(404); die('Lesson not found'); }
header('Location: /platform/course.php?id=' . $lesson['course_id'] . '&lesson=' . $lesson_id);
exit;
