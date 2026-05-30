<?php
// Redirect to course.php with lesson param
$lesson_id = (int)($_GET['id'] ?? 0);
if (!$lesson_id) { header('Location: /index.php'); exit; }

require_once __DIR__ . '/includes/db.php';
$lesson = db_fetch(
    'SELECT l.*, cs.course_id FROM lessons l JOIN course_sections cs ON cs.id = l.section_id WHERE l.id = ?',
    [$lesson_id]
);
if (!$lesson) { http_response_code(404); die('<!DOCTYPE html><html><head><title>Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:50px"><h1>Lesson Not Found</h1><p><a href="/index.php">Go Home</a></p></body></html>'); }
header('Location: /course.php?id=' . $lesson['course_id'] . '&lesson=' . $lesson_id);
exit;
