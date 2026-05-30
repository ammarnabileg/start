<?php
// lesson.php - Full lesson viewer page
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$lesson_id = (int)($_GET['id'] ?? 0);
if (!$lesson_id) { header('Location: /index.php'); exit; }

// Fetch lesson with section and course info
$lesson = db_fetch(
 'SELECT l.*, cs.course_id, cs.title as section_title, c.title as course_title, c.community_id,
 c.is_published, co.slug as community_slug, co.name as community_name
 FROM lessons l
 JOIN course_sections cs ON cs.id = l.section_id
 JOIN courses c ON c.id = cs.course_id
 JOIN communities co ON co.id = c.community_id
 WHERE l.id = ?',
 [$lesson_id]
);

if (!$lesson) {
 http_response_code(404);
 die('<!DOCTYPE html><html><head><title>Not Found</title></head><body style="font-family:sans-serif;text-align:center;padding:50px"><h1>Lesson Not Found</h1><p><a href="/index.php">Go Home</a></p></body></html>');
}

$course_id = (int)$lesson['course_id'];
$community_id = (int)$lesson['community_id'];

$current_user = get_auth_user();

// Check membership
$membership = null;
$is_member = false;
if ($current_user) {
 $membership = db_fetch('SELECT status, role FROM memberships WHERE user_id = ? AND community_id = ?', [$current_user['id'], $community_id]);
 $is_member = $membership && $membership['status'] === 'approved';
}

if (!$is_member) {
 header('Location: /course.php?id=' . $course_id);
 exit;
}

// All lessons for the course (for sidebar + prev/next)
$all_sections = db_fetch_all('SELECT * FROM course_sections WHERE course_id = ? ORDER BY sort_order', [$course_id]);
$all_lessons = [];
$lesson_by_section = [];
foreach ($all_sections as $sec) {
 $sec_lessons = db_fetch_all('SELECT * FROM lessons WHERE section_id = ? ORDER BY sort_order', [$sec['id']]);
 $lesson_by_section[$sec['id']] = $sec_lessons;
 foreach ($sec_lessons as $l) $all_lessons[] = $l;
}

// Prev / Next
$prev_lesson = null;
$next_lesson = null;
$found = false;
foreach ($all_lessons as $i => $l) {
 if ((int)$l['id'] === $lesson_id) {
 $found = true;
 if ($i > 0) $prev_lesson = $all_lessons[$i - 1];
 if ($i < count($all_lessons) - 1) $next_lesson = $all_lessons[$i + 1];
 break;
 }
}

// Progress
$completed_lessons = [];
if ($current_user) {
 $prog = db_fetch_all('SELECT lesson_id FROM lesson_progress WHERE user_id = ?', [$current_user['id']]);
 $completed_lessons = array_column($prog, 'lesson_id');
}
$progress = get_course_progress($current_user ? $current_user['id'] : 0, $course_id);
$is_completed = in_array($lesson_id, $completed_lessons);

$page_title = $lesson['title'] . ' — ' . $lesson['course_title'];
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
 <!-- Breadcrumb -->
 <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-4 flex-wrap">
 <a href="/community.php?slug=<?= e($lesson['community_slug']) ?>" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors"><?= e($lesson['community_name']) ?></a>
 <span>›</span>
 <a href="/course.php?id=<?= $course_id ?>" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors"><?= e($lesson['course_title']) ?></a>
 <span>›</span>
 <span class="text-gray-700 dark:text-gray-300 font-medium truncate max-w-xs"><?= e($lesson['title']) ?></span>
 </nav>

 <!-- Progress bar -->
 <div class="bg-white dark:bg-[#1a1a1a] rounded-xl border border-gray-200 dark:border-white/10 p-4 mb-5">
 <div class="flex items-center justify-between mb-2">
 <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?= e($lesson['course_title']) ?></span>
 <span class="text-sm font-bold text-primary-600 dark:text-primary-400"><?= $progress['percent'] ?>% Complete</span>
 </div>
 <div class="w-full bg-gray-200 dark:bg-[#2a2a2a] rounded-full h-2">
 <div class="bg-gradient-to-r from-primary-600 to-accent-500 h-2 rounded-full transition-all duration-500" style="width:<?= $progress['percent'] ?>%"></div>
 </div>
 <div class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= $progress['completed'] ?> / <?= $progress['total'] ?> lessons completed</div>
 </div>

 <div class="flex gap-5 flex-col lg:flex-row">
 <!-- Sidebar: Course Outline -->
 <div class="lg:w-72 flex-shrink-0 order-2 lg:order-1">
 <div class="sidebar-sticky bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden">
 <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10 bg-gradient-to-r from-primary-600 to-accent-500">
 <h3 class="font-bold text-sm text-white">Course Content</h3>
 </div>
 <div class="overflow-y-auto max-h-[calc(100vh-250px)]">
 <?php foreach ($all_sections as $sec): ?>
 <?php $sec_lessons = $lesson_by_section[$sec['id']] ?? []; ?>
 <div class="border-b border-gray-100 dark:border-white/10 last:border-0">
 <button onclick="toggleSection(<?= $sec['id'] ?>)"
 class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/5 transition-all text-left">
 <span class="text-xs font-bold text-gray-700 dark:text-gray-300 leading-tight"><?= e($sec['title']) ?></span>
 <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" id="sec-arrow-<?= $sec['id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
 </svg>
 </button>
 <?php
 $contains_current = false;
 foreach ($sec_lessons as $sl) {
 if ((int)$sl['id'] === $lesson_id) { $contains_current = true; break; }
 }
 ?>
 <div id="section-<?= $sec['id'] ?>" class="<?= $contains_current ? '' : 'hidden' ?>">
 <?php foreach ($sec_lessons as $l): ?>
 <?php $done = in_array($l['id'], $completed_lessons); ?>
 <a href="/lesson.php?id=<?= $l['id'] ?>"
 class="flex items-center gap-3 px-4 py-2.5 text-xs transition-all hover:bg-gray-50 dark:hover:bg-white/5 border-l-2 <?= (int)$l['id'] === $lesson_id ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-900/20' : 'border-transparent' ?>">
 <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 <?= $done ? 'bg-green-500' : ((int)$l['id'] === $lesson_id ? 'bg-primary-500' : 'bg-gray-200 dark:bg-white/10') ?>">
 <?php if ($done): ?>
 <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
 <?php elseif ((int)$l['id'] === $lesson_id): ?>
 <div class="w-2 h-2 bg-white rounded-full"></div>
 <?php else: ?>
 <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
 <?php endif; ?>
 </div>
 <span class="flex-1 leading-tight text-gray-700 dark:text-gray-300 <?= (int)$l['id'] === $lesson_id ? 'font-semibold text-primary-700 dark:text-primary-400' : '' ?>"><?= e($l['title']) ?></span>
 <?php if ((int)$l['duration_minutes'] > 0): ?>
 <span class="text-gray-400 dark:text-gray-500 text-xs flex-shrink-0"><?= $l['duration_minutes'] ?>m</span>
 <?php endif; ?>
 </a>
 <?php endforeach; ?>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
 </div>
 </div>

 <!-- Main Lesson Area -->
 <div class="flex-1 min-w-0 order-1 lg:order-2">
 <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden">

 <!-- Video embed -->
 <?php if (!empty($lesson['video_url']) || !empty($lesson['video_embed'])): ?>
 <div class="w-full bg-black">
 <?php
 $embed_code = $lesson['video_embed'] ?: get_video_embed($lesson['video_url'] ?? '');
 if ($embed_code) {
 echo $embed_code;
 } else {
 // Fallback: try to render as iframe for youtube/vimeo URLs
 $vurl = $lesson['video_url'] ?? '';
 if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $vurl, $m) || preg_match('/youtu\.be\/([^?]+)/', $vurl, $m)) {
 echo '<div class="w-full aspect-video"><iframe class="w-full h-full" src="https://www.youtube.com/embed/' . htmlspecialchars($m[1]) . '" frameborder="0" allowfullscreen></iframe></div>';
 } elseif (preg_match('/vimeo\.com\/(\d+)/', $vurl, $m)) {
 echo '<div class="w-full aspect-video"><iframe class="w-full h-full" src="https://player.vimeo.com/video/' . htmlspecialchars($m[1]) . '" frameborder="0" allowfullscreen></iframe></div>';
 } else {
 echo '<div class="w-full aspect-video flex items-center justify-center bg-gray-900 text-gray-400 text-sm">Video not available</div>';
 }
 }
 ?>
 </div>
 <?php endif; ?>

 <div class="p-6">
 <!-- Lesson header -->
 <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
 <div>
 <p class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wider mb-1"><?= e($lesson['section_title']) ?></p>
 <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= e($lesson['title']) ?></h1>
 <div class="flex items-center gap-3 mt-2">
 <?php if ((int)$lesson['duration_minutes'] > 0): ?>
 <span class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
 <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
 <?= $lesson['duration_minutes'] ?> min
 </span>
 <?php endif; ?>
 <span class="text-xs bg-gray-100 dark:bg-[#2a2a2a] text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full capitalize"><?= e($lesson['lesson_type']) ?></span>
 <?php if ($is_completed): ?>
 <span class="flex items-center gap-1 text-xs bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 px-2 py-0.5 rounded-full font-medium">
 <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
 Completed
 </span>
 <?php endif; ?>
 </div>
 </div>

 <!-- Mark complete + navigation -->
 <div class="flex items-center gap-2 flex-wrap">
 <?php if (!$is_completed): ?>
 <button onclick="markComplete()"
 class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover: transition-all hover:-translate-y-0.5">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
 Mark Complete
 </button>
 <?php endif; ?>
 <?php if ($prev_lesson): ?>
 <a href="/lesson.php?id=<?= $prev_lesson['id'] ?>"
 class="flex items-center gap-1.5 px-3 py-2 bg-gray-100 dark:bg-[#2a2a2a] text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-white/10 transition-all">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
 Prev
 </a>
 <?php endif; ?>
 <?php if ($next_lesson): ?>
 <a href="/lesson.php?id=<?= $next_lesson['id'] ?>"
 class="flex items-center gap-1.5 px-3 py-2 bg-gray-100 dark:bg-[#2a2a2a] text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-white/10 transition-all">
 Next
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
 </a>
 <?php endif; ?>
 </div>
 </div>

 <!-- Lesson content -->
 <?php if (!empty($lesson['content'])): ?>
 <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 leading-relaxed border-t border-gray-100 dark:border-white/10 pt-5">
 <?= nl2br(e($lesson['content'])) ?>
 </div>
 <?php endif; ?>

 <!-- Prev/Next navigation footer -->
 <div class="flex items-center justify-between mt-8 pt-5 border-t border-gray-100 dark:border-white/10 gap-4">
 <div class="flex-1">
 <?php if ($prev_lesson): ?>
 <a href="/lesson.php?id=<?= $prev_lesson['id'] ?>"
 class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group">
 <svg class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
 <div>
 <div class="text-xs text-gray-400 dark:text-gray-500">Previous</div>
 <div class="font-medium text-gray-700 dark:text-gray-300 line-clamp-1"><?= e($prev_lesson['title']) ?></div>
 </div>
 </a>
 <?php endif; ?>
 </div>
 <div class="flex-1 text-right">
 <?php if ($next_lesson): ?>
 <a href="/lesson.php?id=<?= $next_lesson['id'] ?>"
 class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover: transition-all hover:-translate-y-0.5 group">
 <div class="text-right">
 <div class="text-xs opacity-75">Next Lesson</div>
 <div class="font-semibold line-clamp-1"><?= e($next_lesson['title']) ?></div>
 </div>
 <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
 </a>
 <?php else: ?>
 <a href="/course.php?id=<?= $course_id ?>"
 class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-xl text-sm font-semibold transition-all">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
 Finish Course
 </a>
 <?php endif; ?>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
const LESSON_ID = <?= (int)$lesson_id ?>;
const COURSE_ID = <?= (int)$course_id ?>;
const COMMUNITY_ID = <?= (int)$community_id ?>;

function toggleSection(id) {
 const el = document.getElementById('section-' + id);
 const arrow = document.getElementById('sec-arrow-' + id);
 if (el) el.classList.toggle('hidden');
 if (arrow) arrow.style.transform = el && !el.classList.contains('hidden') ? 'rotate(180deg)' : '';
}

function markComplete() {
 fetch('/api/complete_lesson.php', {
 method: 'POST',
 headers: {'Content-Type': 'application/json'},
 body: JSON.stringify({lesson_id: LESSON_ID, course_id: COURSE_ID, community_id: COMMUNITY_ID, csrf_token: CSRF_TOKEN})
 })
 .then(r => r.json())
 .then(data => {
 if (data.success) {
 showToast('+10 XP earned!');
 setTimeout(() => {
 <?php if ($next_lesson): ?>
 window.location.href = '/lesson.php?id=<?= $next_lesson['id'] ?>';
 <?php else: ?>
 window.location.reload();
 <?php endif; ?>
 }, 600);
 } else {
 showToast(data.error || 'Error', 'error');
 }
 });
}
</script>
