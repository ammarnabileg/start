<?php
// manage-course.php - Add or Edit a course
// GET params: community_id=X, course_id=X (optional, for edit)
// Must be owner or admin of the community
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$current_user = get_auth_user();
$community_id = (int)($_GET['community_id'] ?? 0);
$course_id    = (int)($_GET['course_id'] ?? 0);

if (!$community_id) { header('Location: /index.php'); exit; }

$community = db_fetch('SELECT * FROM communities WHERE id = ? AND is_active = 1', [$community_id]);
if (!$community) { http_response_code(404); die('Community not found.'); }

// Must be admin/owner
$mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $community_id]);
if (!$mem || !in_array($mem['role'], ['admin', 'owner'])) {
    header('Location: /community.php?slug=' . urlencode($community['slug'])); exit;
}

$course = null;
$sections_data = [];
if ($course_id) {
    $course = db_fetch('SELECT * FROM courses WHERE id = ? AND community_id = ?', [$course_id, $community_id]);
    if (!$course) { header('Location: /community.php?slug=' . urlencode($community['slug']) . '&tab=classroom'); exit; }
    $sections_raw = db_fetch_all('SELECT * FROM course_sections WHERE course_id = ? ORDER BY sort_order', [$course_id]);
    foreach ($sections_raw as $sec) {
        $lessons = db_fetch_all('SELECT * FROM lessons WHERE section_id = ? ORDER BY sort_order', [$sec['id']]);
        $sections_data[] = ['section' => $sec, 'lessons' => $lessons];
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $thumbnail   = trim($_POST['thumbnail'] ?? '');
        $pricing     = $_POST['pricing'] ?? 'free';
        $price       = (float)($_POST['price'] ?? 0);
        $is_published = (int)($_POST['is_published'] ?? 1);

        if (!$title) {
            $error = 'Course title is required.';
        } else {
            if ($course_id && $course) {
                // Update course
                db_execute(
                    'UPDATE courses SET title=?, description=?, thumbnail=?, pricing=?, price=?, is_published=? WHERE id=?',
                    [$title, $description, $thumbnail, $pricing, $price, $is_published, $course_id]
                );
                $cid = $course_id;
            } else {
                // Insert course
                $max_order = db_fetch('SELECT COALESCE(MAX(sort_order),0) as m FROM courses WHERE community_id=?', [$community_id]);
                $cid = db_insert(
                    'INSERT INTO courses (community_id, title, description, thumbnail, pricing, price, is_published, sort_order) VALUES (?,?,?,?,?,?,?,?)',
                    [$community_id, $title, $description, $thumbnail, $pricing, $price, $is_published, (int)($max_order['m']??0)+1]
                );
            }

            // Delete existing sections/lessons and re-insert
            $old_sections = db_fetch_all('SELECT id FROM course_sections WHERE course_id=?', [$cid]);
            foreach ($old_sections as $os) {
                $old_lessons = db_fetch_all('SELECT id FROM lessons WHERE section_id=?', [$os['id']]);
                foreach ($old_lessons as $ol) {
                    db_execute('DELETE FROM lesson_progress WHERE lesson_id=?', [$ol['id']]);
                }
                db_execute('DELETE FROM lessons WHERE section_id=?', [$os['id']]);
            }
            db_execute('DELETE FROM course_sections WHERE course_id=?', [$cid]);

            // Re-insert sections and lessons from POST
            $section_titles  = $_POST['section_title'] ?? [];
            $section_lessons = $_POST['lessons'] ?? [];

            foreach ($section_titles as $s_idx => $s_title) {
                $s_title = trim($s_title);
                if (!$s_title) continue;
                $sec_id = db_insert(
                    'INSERT INTO course_sections (course_id, title, sort_order) VALUES (?,?,?)',
                    [$cid, $s_title, $s_idx + 1]
                );
                $lessons_for_section = $section_lessons[$s_idx] ?? [];
                $l_titles = $lessons_for_section['title'] ?? [];
                foreach ($l_titles as $l_idx => $l_title) {
                    $l_title = trim($l_title);
                    if (!$l_title) continue;
                    $l_type     = $lessons_for_section['type'][$l_idx] ?? 'text';
                    $l_video    = trim($lessons_for_section['video_url'][$l_idx] ?? '');
                    $l_embed    = trim($lessons_for_section['video_embed'][$l_idx] ?? '');
                    $l_content  = trim($lessons_for_section['content'][$l_idx] ?? '');
                    $l_duration = (int)($lessons_for_section['duration'][$l_idx] ?? 0);
                    db_insert(
                        'INSERT INTO lessons (section_id, title, lesson_type, video_url, video_embed, content, duration_minutes, sort_order) VALUES (?,?,?,?,?,?,?,?)',
                        [$sec_id, $l_title, $l_type, $l_video, $l_embed, $l_content, $l_duration, $l_idx + 1]
                    );
                }
            }

            header('Location: /community.php?slug=' . urlencode($community['slug']) . '&tab=classroom');
            exit;
        }
    }
}

$page_title = $course ? 'Edit Course' : 'Add Course';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <!-- Breadcrumb -->
  <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
    <a href="/community.php?slug=<?= e($community['slug']) ?>" class="hover:text-primary-600 dark:hover:text-primary-400"><?= e($community['name']) ?></a>
    <span>›</span>
    <a href="/community.php?slug=<?= e($community['slug']) ?>&tab=classroom" class="hover:text-primary-600 dark:hover:text-primary-400">Classroom</a>
    <span>›</span>
    <span class="text-gray-700 dark:text-gray-300 font-medium"><?= $course ? 'Edit Course' : 'New Course' ?></span>
  </nav>

  <div class="mb-8">
    <h1 class="text-3xl font-black text-gray-900 dark:text-white mb-1"><?= $course ? 'Edit Course' : 'Create New Course' ?></h1>
    <p class="text-gray-500 dark:text-gray-400">for <?= e($community['name']) ?></p>
  </div>

  <?php if ($error): ?>
    <div class="mb-5 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-600 dark:text-red-400 text-sm">❌ <?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" id="course-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- Course Info Card -->
    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-100 dark:border-white/10 shadow-airbnb p-6 mb-6">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Course Details</h2>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Course Title <span class="text-red-500">*</span></label>
          <input type="text" name="title" required value="<?= e($course['title'] ?? '') ?>"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 shadow-airbnb"
            placeholder="e.g. Introduction to Marketing">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
          <textarea name="description" rows="4"
            class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none dark:text-gray-200 shadow-airbnb"
            placeholder="What will students learn in this course?"><?= e($course['description'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Thumbnail</label>
          <div class="flex items-center gap-4">
            <div id="thumbnail-preview-wrap" class="<?= empty($course['thumbnail']) ? 'hidden' : '' ?>">
              <img id="thumbnail-preview" src="<?= e($course['thumbnail'] ?? '') ?>" class="w-32 h-20 object-cover rounded-xl border border-gray-200 dark:border-white/10">
            </div>
            <div>
              <input type="file" id="thumbnail-upload" accept="image/*" class="hidden" onchange="uploadThumbnail(this)">
              <label for="thumbnail-upload" class="cursor-pointer px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition-all">
                <?= $course ? 'Change Thumbnail' : 'Upload Thumbnail' ?>
              </label>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Recommended: 1280×720px</p>
            </div>
          </div>
          <input type="hidden" name="thumbnail" id="thumbnail-url" value="<?= e($course['thumbnail'] ?? '') ?>">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Pricing</label>
            <select name="pricing" onchange="togglePriceField()" id="course-pricing"
              class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 shadow-airbnb">
              <option value="free" <?= (($course['pricing'] ?? 'free') === 'free') ? 'selected' : '' ?>>Free</option>
              <option value="paid" <?= (($course['pricing'] ?? '') === 'paid') ? 'selected' : '' ?>>Paid</option>
            </select>
          </div>
          <div id="price-field" class="<?= (($course['pricing'] ?? 'free') === 'paid') ? '' : 'hidden' ?>">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Price ($)</label>
            <input type="number" name="price" value="<?= e($course['price'] ?? '0') ?>" step="0.01" min="0"
              class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 shadow-airbnb"
              placeholder="9.99">
          </div>
        </div>
        <div>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_published" value="1" <?= (($course['is_published'] ?? 1) == 1) ? 'checked' : '' ?>
              class="w-4 h-4 text-primary-600 rounded focus:ring-primary-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Publish course (visible to members)</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Sections & Lessons -->
    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-100 dark:border-white/10 shadow-airbnb p-6 mb-6">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Course Content</h2>
        <button type="button" onclick="addSection()" class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl text-sm font-semibold hover:shadow-md transition-all">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add Section
        </button>
      </div>
      <div id="sections-container" class="space-y-4">
        <?php foreach ($sections_data as $s_idx => $sd): ?>
          <div class="section-block border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden" data-section-idx="<?= $s_idx ?>">
            <div class="flex items-center gap-3 bg-gray-50 dark:bg-white/5 px-4 py-3">
              <div class="cursor-grab text-gray-400 dark:text-gray-500">⠿</div>
              <input type="text" name="section_title[]" value="<?= e($sd['section']['title']) ?>" required
                placeholder="Section title..."
                class="flex-1 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1a1a1a] text-sm focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200 font-semibold">
              <button type="button" onclick="this.closest('.section-block').remove()" class="text-red-400 hover:text-red-600 text-sm px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all">Remove</button>
            </div>
            <div class="lessons-container p-3 space-y-2">
              <?php foreach ($sd['lessons'] as $l_idx => $lesson): ?>
                <div class="lesson-block bg-white dark:bg-[#1a1a1a] border border-gray-100 dark:border-white/10 rounded-xl p-4">
                  <div class="flex items-center gap-2 mb-3">
                    <div class="cursor-grab text-gray-400 text-xs">⠿</div>
                    <input type="text" name="lessons[<?= $s_idx ?>][title][]" value="<?= e($lesson['title']) ?>" required
                      placeholder="Lesson title..."
                      class="flex-1 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-sm focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200 font-medium">
                    <button type="button" onclick="this.closest('.lesson-block').remove()" class="text-red-400 hover:text-red-600 text-xs px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all flex-shrink-0">✕</button>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-2">
                    <select name="lessons[<?= $s_idx ?>][type][]" onchange="toggleVideoFields(this)"
                      class="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
                      <option value="video" <?= $lesson['lesson_type'] === 'video' ? 'selected' : '' ?>>Video</option>
                      <option value="text" <?= $lesson['lesson_type'] === 'text' ? 'selected' : '' ?>>Text</option>
                      <option value="mixed" <?= $lesson['lesson_type'] === 'mixed' ? 'selected' : '' ?>>Mixed</option>
                    </select>
                    <input type="number" name="lessons[<?= $s_idx ?>][duration][]" value="<?= (int)$lesson['duration_minutes'] ?>" min="0" placeholder="Duration (min)"
                      class="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
                  </div>
                  <div class="video-fields <?= in_array($lesson['lesson_type'], ['video','mixed']) ? '' : 'hidden' ?> space-y-2 mb-2">
                    <input type="url" name="lessons[<?= $s_idx ?>][video_url][]" value="<?= e($lesson['video_url'] ?? '') ?>"
                      placeholder="YouTube/Vimeo URL..."
                      class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
                    <textarea name="lessons[<?= $s_idx ?>][video_embed][]" rows="2" placeholder="Or paste embed code (iframe)..."
                      class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 resize-none dark:text-gray-200"><?= e($lesson['video_embed'] ?? '') ?></textarea>
                  </div>
                  <textarea name="lessons[<?= $s_idx ?>][content][]" rows="3" placeholder="Lesson content (supports markdown)..."
                    class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 resize-none dark:text-gray-200"><?= e($lesson['content'] ?? '') ?></textarea>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="px-4 pb-3">
              <button type="button" onclick="addLesson(this, <?= $s_idx ?>)"
                class="text-sm text-primary-600 dark:text-primary-400 hover:underline font-medium flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Lesson
              </button>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($sections_data)): ?>
          <div class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm" id="no-sections-msg">
            Click "Add Section" to start building your course content.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Submit -->
    <div class="flex items-center justify-between">
      <a href="/community.php?slug=<?= e($community['slug']) ?>&tab=classroom"
        class="px-5 py-3 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-400 rounded-xl font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
        Cancel
      </a>
      <button type="submit"
        class="px-8 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-bold hover:shadow-xl hover:shadow-primary-500/30 transition-all hover:-translate-y-0.5 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Save Course
      </button>
    </div>
  </form>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
let sectionCount = <?= count($sections_data) ?>;

function addSection() {
  const noMsg = document.getElementById('no-sections-msg');
  if (noMsg) noMsg.remove();
  const container = document.getElementById('sections-container');
  const sIdx = sectionCount++;
  const div = document.createElement('div');
  div.className = 'section-block border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden';
  div.dataset.sectionIdx = sIdx;
  div.innerHTML = `
    <div class="flex items-center gap-3 bg-gray-50 dark:bg-white/5 px-4 py-3">
      <div class="cursor-grab text-gray-400 dark:text-gray-500">⠿</div>
      <input type="text" name="section_title[]" required placeholder="Section title..."
        class="flex-1 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#1a1a1a] text-sm focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200 font-semibold">
      <button type="button" onclick="this.closest('.section-block').remove()" class="text-red-400 hover:text-red-600 text-sm px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all">Remove</button>
    </div>
    <div class="lessons-container p-3 space-y-2"></div>
    <div class="px-4 pb-3">
      <button type="button" onclick="addLesson(this, ${sIdx})"
        class="text-sm text-primary-600 dark:text-primary-400 hover:underline font-medium flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Lesson
      </button>
    </div>`;
  container.appendChild(div);
}

function addLesson(btn, sectionIdx) {
  const section = btn.closest('.section-block');
  const container = section.querySelector('.lessons-container');
  const div = document.createElement('div');
  div.className = 'lesson-block bg-white dark:bg-[#1a1a1a] border border-gray-100 dark:border-white/10 rounded-xl p-4';
  div.innerHTML = `
    <div class="flex items-center gap-2 mb-3">
      <div class="cursor-grab text-gray-400 text-xs">⠿</div>
      <input type="text" name="lessons[${sectionIdx}][title][]" required placeholder="Lesson title..."
        class="flex-1 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-sm focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200 font-medium">
      <button type="button" onclick="this.closest('.lesson-block').remove()" class="text-red-400 hover:text-red-600 text-xs px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-all flex-shrink-0">✕</button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-2">
      <select name="lessons[${sectionIdx}][type][]" onchange="toggleVideoFields(this)"
        class="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
        <option value="video">Video</option>
        <option value="text" selected>Text</option>
        <option value="mixed">Mixed</option>
      </select>
      <input type="number" name="lessons[${sectionIdx}][duration][]" min="0" placeholder="Duration (min)"
        class="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
    </div>
    <div class="video-fields hidden space-y-2 mb-2">
      <input type="url" name="lessons[${sectionIdx}][video_url][]" placeholder="YouTube/Vimeo URL..."
        class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200">
      <textarea name="lessons[${sectionIdx}][video_embed][]" rows="2" placeholder="Or paste embed code (iframe)..."
        class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 resize-none dark:text-gray-200"></textarea>
    </div>
    <textarea name="lessons[${sectionIdx}][content][]" rows="3" placeholder="Lesson content (supports markdown)..."
      class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 resize-none dark:text-gray-200"></textarea>`;
  container.appendChild(div);
}

function toggleVideoFields(select) {
  const lesson = select.closest('.lesson-block');
  const vf = lesson.querySelector('.video-fields');
  if (select.value === 'video' || select.value === 'mixed') {
    vf.classList.remove('hidden');
  } else {
    vf.classList.add('hidden');
  }
}

function togglePriceField() {
  const pricing = document.getElementById('course-pricing').value;
  const priceField = document.getElementById('price-field');
  if (pricing === 'paid') priceField.classList.remove('hidden');
  else priceField.classList.add('hidden');
}

async function uploadThumbnail(input) {
  const file = input.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', 'post_image');
  try {
    const res = await fetch('/api/upload.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.url) {
      document.getElementById('thumbnail-url').value = data.url;
      const prev = document.getElementById('thumbnail-preview');
      prev.src = data.url;
      document.getElementById('thumbnail-preview-wrap').classList.remove('hidden');
      showToast('Thumbnail uploaded!');
    } else {
      showToast(data.error || 'Upload failed', 'error');
    }
  } catch(e) {
    showToast('Upload failed', 'error');
  }
}
</script>
