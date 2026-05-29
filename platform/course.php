<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$course_id = (int)($_GET['id'] ?? 0);
if (!$course_id) { header('Location: /index.php'); exit; }

$course = db_fetch(
    'SELECT c.*, co.slug as community_slug, co.name as community_name, co.id as community_id FROM courses c JOIN communities co ON co.id = c.community_id WHERE c.id = ? AND c.is_published = 1',
    [$course_id]
);
if (!$course) { http_response_code(404); die('<h1>Course not found</h1>'); }

$current_user = get_auth_user();
$community_id = $course['community_id'];

// Get sections with lessons
$sections = db_fetch_all('SELECT * FROM course_sections WHERE course_id = ? ORDER BY sort_order', [$course_id]);
$all_lessons = [];
$lesson_by_section = [];
foreach ($sections as $sec) {
    $lessons = db_fetch_all('SELECT * FROM lessons WHERE section_id = ? ORDER BY sort_order', [$sec['id']]);
    $lesson_by_section[$sec['id']] = $lessons;
    foreach ($lessons as $l) $all_lessons[$l['id']] = $l;
}

// Current lesson
$lesson_id = (int)($_GET['lesson'] ?? 0);
$current_lesson = null;
if ($lesson_id && isset($all_lessons[$lesson_id])) {
    $current_lesson = $all_lessons[$lesson_id];
} elseif (!empty($all_lessons)) {
    $current_lesson = reset($all_lessons);
    $lesson_id = $current_lesson['id'];
}

// Progress tracking
$completed_lessons = [];
if ($current_user) {
    $prog = db_fetch_all(
        'SELECT lesson_id FROM lesson_progress WHERE user_id = ?',
        [$current_user['id']]
    );
    $completed_lessons = array_column($prog, 'lesson_id');
}

$progress = get_course_progress($current_user ? $current_user['id'] : 0, $course_id);
$is_completed_lesson = $current_lesson && in_array($current_lesson['id'], $completed_lessons);

// Find next lesson
$next_lesson = null;
$found_current = false;
foreach ($all_lessons as $l) {
    if ($found_current) { $next_lesson = $l; break; }
    if ($l['id'] === $lesson_id) $found_current = true;
}

$page_title = $course['title'];
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <!-- Breadcrumb -->
  <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-4">
    <a href="/community.php?slug=<?= e($course['community_slug']) ?>" class="hover:text-primary-600 dark:hover:text-primary-400"><?= e($course['community_name']) ?></a>
    <span>›</span>
    <a href="/community.php?slug=<?= e($course['community_slug']) ?>&tab=classroom" class="hover:text-primary-600 dark:hover:text-primary-400">Classroom</a>
    <span>›</span>
    <span class="text-gray-700 dark:text-gray-300 font-medium truncate"><?= e($course['title']) ?></span>
  </nav>

  <!-- Progress bar -->
  <div class="bg-white dark:bg-gray-800 rounded-xl p-4 mb-4 border border-gray-100 dark:border-gray-700 shadow-sm">
    <div class="flex items-center justify-between mb-2">
      <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?= e($course['title']) ?></span>
      <span class="text-sm font-bold text-primary-600 dark:text-primary-400"><?= $progress['percent'] ?>% Complete</span>
    </div>
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
      <div class="bg-gradient-to-r from-primary-500 to-accent-500 h-2 rounded-full transition-all duration-500" style="width:<?= $progress['percent'] ?>%"></div>
    </div>
    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= $progress['completed'] ?> / <?= $progress['total'] ?> lessons completed</div>
  </div>

  <div class="flex gap-5 flex-col lg:flex-row">
    <!-- Left Sidebar: Course Navigation -->
    <div class="lg:w-72 flex-shrink-0">
      <div class="sidebar-sticky bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
          <h3 class="font-bold text-sm text-gray-700 dark:text-gray-300">Course Content</h3>
        </div>
        <div class="overflow-y-auto max-h-[calc(100vh-200px)]">
          <?php foreach ($sections as $sec): ?>
            <?php $sec_lessons = $lesson_by_section[$sec['id']] ?? []; ?>
            <div class="border-b border-gray-50 dark:border-gray-700/50 last:border-0">
              <button onclick="toggleSection(<?= $sec['id'] ?>)"
                class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all text-left">
                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 leading-tight"><?= e($sec['title']) ?></span>
                <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" id="sec-arrow-<?= $sec['id'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <div id="section-<?= $sec['id'] ?>" class="<?= ($current_lesson && in_array($current_lesson['id'], array_column($sec_lessons, 'id'))) ? '' : 'hidden' ?>">
                <?php foreach ($sec_lessons as $lesson): ?>
                  <?php $done = in_array($lesson['id'], $completed_lessons); ?>
                  <a href="?id=<?= $course_id ?>&lesson=<?= $lesson['id'] ?>"
                    class="flex items-center gap-3 px-4 py-2.5 text-xs transition-all hover:bg-gray-50 dark:hover:bg-gray-700/50 border-l-2 <?= $lesson['id'] === $lesson_id ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-900/20' : 'border-transparent' ?>">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 <?= $done ? 'bg-green-500' : ($lesson['id'] === $lesson_id ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-600') ?>">
                      <?php if ($done): ?>
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                      <?php elseif ($lesson['id'] === $lesson_id): ?>
                        <div class="w-2 h-2 bg-white rounded-full"></div>
                      <?php else: ?>
                        <div class="w-2 h-2 bg-gray-400 dark:bg-gray-400 rounded-full"></div>
                      <?php endif; ?>
                    </div>
                    <span class="flex-1 leading-tight text-gray-700 dark:text-gray-300 <?= $lesson['id'] === $lesson_id ? 'font-semibold text-primary-700 dark:text-primary-400' : '' ?>"><?= e($lesson['title']) ?></span>
                    <?php if ($lesson['duration_minutes'] > 0): ?>
                      <span class="text-gray-400 dark:text-gray-500 text-xs flex-shrink-0"><?= $lesson['duration_minutes'] ?>m</span>
                    <?php endif; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Main Lesson Content -->
    <div class="flex-1 min-w-0">
      <?php if ($current_lesson): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden shadow-sm">
          <!-- Video or Content -->
          <?php if ($current_lesson['video_url'] || $current_lesson['video_embed']): ?>
            <div class="w-full bg-black">
              <?php
              $embed_code = $current_lesson['video_embed'] ?: get_video_embed($current_lesson['video_url'] ?? '');
              echo $embed_code ?: '<div class="w-full aspect-video flex items-center justify-center text-white text-sm">Video not available</div>';
              ?>
            </div>
          <?php endif; ?>

          <div class="p-6">
            <!-- Lesson Header -->
            <div class="flex items-start justify-between gap-4 mb-4 flex-wrap">
              <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white"><?= e($current_lesson['title']) ?></h1>
                <div class="flex items-center gap-3 mt-1">
                  <?php if ($current_lesson['duration_minutes'] > 0): ?>
                    <span class="text-xs text-gray-500 dark:text-gray-400">⏱ <?= $current_lesson['duration_minutes'] ?> min</span>
                  <?php endif; ?>
                  <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full capitalize"><?= $current_lesson['lesson_type'] ?></span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <?php if ($current_user && !$is_completed_lesson): ?>
                  <button onclick="completeLesson(<?= $lesson_id ?>, <?= $course_id ?>, <?= $community_id ?>)"
                    class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all hover:-translate-y-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Mark Complete
                  </button>
                <?php elseif ($is_completed_lesson): ?>
                  <span class="flex items-center gap-2 px-4 py-2 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-xl text-sm font-semibold">
                    ✓ Completed
                  </span>
                <?php endif; ?>
                <?php if ($next_lesson): ?>
                  <a href="?id=<?= $course_id ?>&lesson=<?= $next_lesson['id'] ?>"
                    class="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
                    Next
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  </a>
                <?php endif; ?>
              </div>
            </div>

            <!-- Lesson Content -->
            <?php if ($current_lesson['content']): ?>
              <div class="prose dark:prose-invert text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                <?= nl2br(e($current_lesson['content'])) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-12 text-center shadow-sm">
          <div class="text-5xl mb-4">📚</div>
          <h3 class="font-bold text-xl text-gray-700 dark:text-gray-300 mb-2">No lessons yet</h3>
          <p class="text-gray-500 dark:text-gray-400">This course doesn't have any lessons yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Course Completion Modal -->
<div id="completion-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
  <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl w-full max-w-md p-8 text-center">
    <div class="text-6xl mb-4">🎉</div>
    <h2 class="text-2xl font-black text-gray-900 dark:text-white mb-2">Course Complete!</h2>
    <p class="text-gray-600 dark:text-gray-400 mb-4">Congratulations! You've completed <strong><?= e($course['title']) ?></strong></p>

    <!-- Certificate Preview -->
    <div class="bg-gradient-to-br from-primary-600 to-accent-500 rounded-2xl p-6 mb-6 text-white">
      <div class="text-sm opacity-80 mb-1">Certificate of Completion</div>
      <div class="text-lg font-black mb-1"><?= e($course['title']) ?></div>
      <div class="text-sm opacity-80"><?= e($course['community_name']) ?> • <?= date('F Y') ?></div>
      <?php if ($current_user): ?>
        <div class="mt-3 font-bold"><?= e(trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')) ?: $current_user['username']) ?></div>
      <?php endif; ?>
    </div>

    <div class="flex items-center justify-center gap-2 mb-6 text-sm text-gray-600 dark:text-gray-400">
      <span class="bg-yellow-50 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 px-3 py-1.5 rounded-xl font-semibold">+100 XP Bonus!</span>
      <span class="bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 px-3 py-1.5 rounded-xl font-semibold">📚 Scholar Badge</span>
    </div>

    <div class="flex justify-center gap-3">
      <a href="/community.php?slug=<?= e($course['community_slug']) ?>&tab=classroom"
        class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">
        Back to Classroom
      </a>
      <button onclick="document.getElementById('completion-modal').classList.add('hidden')"
        class="px-5 py-2.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">
        Continue Learning
      </button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

function toggleSection(sectionId) {
  const content = document.getElementById('section-' + sectionId);
  const arrow = document.getElementById('sec-arrow-' + sectionId);
  if (content) content.classList.toggle('hidden');
  if (arrow) arrow.style.transform = content.classList.contains('hidden') ? '' : 'rotate(180deg)';
}

function completeLesson(lessonId, courseId, communityId) {
  fetch('/api/complete_lesson.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({lesson_id: lessonId, course_id: courseId, community_id: communityId, csrf_token: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showToast('+10 XP earned!');
      if (data.course_complete) {
        setTimeout(() => {
          document.getElementById('completion-modal').classList.remove('hidden');
        }, 500);
      } else {
        setTimeout(() => location.reload(), 500);
      }
    } else {
      showToast(data.error || 'Error', 'error');
    }
  });
}
</script>
