<?php
/**
 * Create / Edit Job View
 * Layout: app
 * $job is set when editing an existing job
 */
$isEdit = isset($job) && !empty($job['id']);
$pageTitle = $isEdit ? 'Edit Job' : 'Post New Job';
$submitUrl = $isEdit ? '/api/v1/jobs/' . $job['id'] : '/api/v1/jobs';
?>
<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="min-h-screen bg-gray-50">
  <!-- Page Header -->
  <div class="bg-white border-b border-gray-200 px-6 py-4">
    <div class="flex items-center gap-4">
      <a href="/jobs" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= $pageTitle ?></h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= $isEdit ? 'Update job details, criteria and screening questions' : 'Fill out the details to create a new job posting' ?></p>
      </div>
    </div>
  </div>

  <div class="px-6 py-6 max-w-4xl mx-auto">

    <!-- Alert area -->
    <div id="form-alert" class="hidden mb-5 p-4 rounded-xl border text-sm font-medium"></div>

    <form id="job-form" class="space-y-6" onsubmit="return false;">

      <!-- Basic Info -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
          <h2 class="text-base font-semibold text-gray-900">Job Information</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

          <!-- Title (full width) -->
          <div class="md:col-span-2">
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">
              Job Title <span class="text-red-500">*</span>
            </label>
            <input
              type="text" id="title" name="title" required
              placeholder="e.g. Senior Software Engineer"
              value="<?= $isEdit ? htmlspecialchars($job['title'] ?? '') : '' ?>"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>

          <!-- Department -->
          <div>
            <label for="department" class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
            <input
              type="text" id="department" name="department"
              placeholder="e.g. Engineering, Sales, HR"
              value="<?= $isEdit ? htmlspecialchars($job['department'] ?? '') : '' ?>"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>

          <!-- Location -->
          <div>
            <label for="location" class="block text-sm font-medium text-gray-700 mb-1.5">Location</label>
            <input
              type="text" id="location" name="location"
              placeholder="e.g. New York, NY or Remote"
              value="<?= $isEdit ? htmlspecialchars($job['location'] ?? '') : '' ?>"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>

          <!-- Employment Type -->
          <div>
            <label for="type" class="block text-sm font-medium text-gray-700 mb-1.5">Employment Type</label>
            <select id="type" name="type" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
              <option value="">Select type</option>
              <option value="full_time"  <?= ($isEdit && ($job['type'] ?? '') === 'full_time')  ? 'selected' : '' ?>>Full Time</option>
              <option value="part_time"  <?= ($isEdit && ($job['type'] ?? '') === 'part_time')  ? 'selected' : '' ?>>Part Time</option>
              <option value="contract"   <?= ($isEdit && ($job['type'] ?? '') === 'contract')   ? 'selected' : '' ?>>Contract</option>
              <option value="internship" <?= ($isEdit && ($job['type'] ?? '') === 'internship') ? 'selected' : '' ?>>Internship</option>
            </select>
          </div>

          <!-- Work Mode -->
          <div>
            <label for="work_mode" class="block text-sm font-medium text-gray-700 mb-1.5">Work Mode</label>
            <select id="work_mode" name="work_mode" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
              <option value="">Select mode</option>
              <option value="remote" <?= ($isEdit && ($job['work_mode'] ?? '') === 'remote') ? 'selected' : '' ?>>Remote</option>
              <option value="onsite" <?= ($isEdit && ($job['work_mode'] ?? '') === 'onsite') ? 'selected' : '' ?>>On-site</option>
              <option value="hybrid" <?= ($isEdit && ($job['work_mode'] ?? '') === 'hybrid') ? 'selected' : '' ?>>Hybrid</option>
            </select>
          </div>

          <!-- Experience Level -->
          <div>
            <label for="experience_level" class="block text-sm font-medium text-gray-700 mb-1.5">Experience Level</label>
            <select id="experience_level" name="experience_level" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
              <option value="">Select level</option>
              <option value="entry"    <?= ($isEdit && ($job['experience_level'] ?? '') === 'entry')    ? 'selected' : '' ?>>Entry Level</option>
              <option value="mid"      <?= ($isEdit && ($job['experience_level'] ?? '') === 'mid')      ? 'selected' : '' ?>>Mid Level</option>
              <option value="senior"   <?= ($isEdit && ($job['experience_level'] ?? '') === 'senior')   ? 'selected' : '' ?>>Senior</option>
              <option value="lead"     <?= ($isEdit && ($job['experience_level'] ?? '') === 'lead')     ? 'selected' : '' ?>>Lead / Principal</option>
              <option value="manager"  <?= ($isEdit && ($job['experience_level'] ?? '') === 'manager')  ? 'selected' : '' ?>>Manager</option>
              <option value="director" <?= ($isEdit && ($job['experience_level'] ?? '') === 'director') ? 'selected' : '' ?>>Director+</option>
            </select>
          </div>

          <!-- Salary Min -->
          <div>
            <label for="salary_min" class="block text-sm font-medium text-gray-700 mb-1.5">Salary Min</label>
            <input
              type="number" id="salary_min" name="salary_min" min="0" step="500"
              placeholder="e.g. 50000"
              value="<?= $isEdit ? htmlspecialchars($job['salary_min'] ?? '') : '' ?>"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>

          <!-- Salary Max -->
          <div>
            <label for="salary_max" class="block text-sm font-medium text-gray-700 mb-1.5">Salary Max</label>
            <input
              type="number" id="salary_max" name="salary_max" min="0" step="500"
              placeholder="e.g. 90000"
              value="<?= $isEdit ? htmlspecialchars($job['salary_max'] ?? '') : '' ?>"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
          </div>

          <!-- Currency -->
          <div>
            <label for="currency" class="block text-sm font-medium text-gray-700 mb-1.5">Currency</label>
            <select id="currency" name="currency" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
              <option value="USD" <?= ($isEdit && ($job['currency'] ?? 'USD') === 'USD') ? 'selected' : '' ?>>USD — US Dollar</option>
              <option value="EUR" <?= ($isEdit && ($job['currency'] ?? '') === 'EUR') ? 'selected' : '' ?>>EUR — Euro</option>
              <option value="GBP" <?= ($isEdit && ($job['currency'] ?? '') === 'GBP') ? 'selected' : '' ?>>GBP — British Pound</option>
              <option value="SAR" <?= ($isEdit && ($job['currency'] ?? '') === 'SAR') ? 'selected' : '' ?>>SAR — Saudi Riyal</option>
              <option value="AED" <?= ($isEdit && ($job['currency'] ?? '') === 'AED') ? 'selected' : '' ?>>AED — UAE Dirham</option>
              <option value="EGP" <?= ($isEdit && ($job['currency'] ?? '') === 'EGP') ? 'selected' : '' ?>>EGP — Egyptian Pound</option>
            </select>
          </div>

          <!-- Status -->
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
            <select id="status" name="status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
              <option value="draft"  <?= ($isEdit && ($job['status'] ?? 'draft') === 'draft')  ? 'selected' : '' ?>>Draft</option>
              <option value="active" <?= ($isEdit && ($job['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active (Published)</option>
            </select>
          </div>

          <!-- Description (full width) -->
          <div class="md:col-span-2">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">Job Description</label>
            <textarea
              id="description" name="description" rows="6"
              placeholder="Describe the role, responsibilities, and what success looks like..."
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y"
            ><?= $isEdit ? htmlspecialchars($job['description'] ?? '') : '' ?></textarea>
          </div>

          <!-- Requirements (full width) -->
          <div class="md:col-span-2">
            <label for="requirements" class="block text-sm font-medium text-gray-700 mb-1.5">Requirements</label>
            <textarea
              id="requirements" name="requirements" rows="5"
              placeholder="List required skills, experience, education, and qualifications..."
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y"
            ><?= $isEdit ? htmlspecialchars($job['requirements'] ?? '') : '' ?></textarea>
          </div>
        </div>
      </div>

      <!-- AI Screening Criteria -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <div>
            <h2 class="text-base font-semibold text-gray-900">AI Screening Criteria</h2>
            <p class="text-xs text-gray-500 mt-0.5">Define criteria with weights for automatic candidate evaluation</p>
          </div>
          <button type="button" onclick="addCriterion()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Criterion
          </button>
        </div>
        <div class="p-6">
          <div id="criteria-container" class="space-y-3">
            <!-- Criterion rows injected here -->
          </div>
          <p id="criteria-empty" class="text-sm text-gray-400 text-center py-4 hidden">No criteria added yet. Click "Add Criterion" to begin.</p>
        </div>
      </div>

      <!-- Screening Questions -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <div>
            <h2 class="text-base font-semibold text-gray-900">Screening Questions</h2>
            <p class="text-xs text-gray-500 mt-0.5">Questions shown to candidates during the AI screening interview</p>
          </div>
          <button type="button" onclick="addQuestion()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Question
          </button>
        </div>
        <div class="p-6">
          <div id="questions-container" class="space-y-3">
            <!-- Question rows injected here -->
          </div>
          <p id="questions-empty" class="text-sm text-gray-400 text-center py-4 hidden">No questions added yet. Click "Add Question" to begin.</p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-between bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-4">
        <a href="/jobs" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Cancel
        </a>
        <div class="flex items-center gap-3">
          <button type="button" onclick="submitJob('draft')" id="btn-draft" class="px-5 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Save as Draft
          </button>
          <button type="button" onclick="submitJob('active')" id="btn-publish" class="px-5 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors">
            <?= $isEdit ? 'Update Job' : 'Publish Job' ?>
          </button>
        </div>
      </div>

    </form>
  </div>
</div>

<script>
(function () {
  const CSRF = document.querySelector('meta[name=csrf]').content;
  const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
  const JOB_ID  = <?= $isEdit ? json_encode($job['id']) : 'null' ?>;

  let criterionCount = 0;
  let questionCount  = 0;

  // ---- Criteria ----
  window.addCriterion = function (data) {
    criterionCount++;
    const idx = criterionCount;
    const container = document.getElementById('criteria-container');
    document.getElementById('criteria-empty').classList.add('hidden');

    const row = document.createElement('div');
    row.className = 'criterion-row bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3';
    row.dataset.idx = idx;
    row.innerHTML = `
      <div class="flex items-start gap-3">
        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Criterion Name <span class="text-red-500">*</span></label>
            <input type="text" name="criteria[${idx}][name]" placeholder="e.g. Python experience"
              value="${escAttr(data?.name || '')}"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Weight (1–10)</label>
            <input type="number" name="criteria[${idx}][weight]" min="1" max="10" placeholder="5"
              value="${escAttr(data?.weight || '5')}"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
          </div>
        </div>
        <button type="button" onclick="removeCriterion(this)" class="mt-5 p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors flex-shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Description <span class="text-gray-400">(optional)</span></label>
        <textarea name="criteria[${idx}][description]" rows="2" placeholder="Describe what this criterion evaluates..."
          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white resize-none">${escHtml(data?.description || '')}</textarea>
      </div>
    `;
    container.appendChild(row);
  };

  window.removeCriterion = function (btn) {
    btn.closest('.criterion-row').remove();
    if (!document.querySelectorAll('.criterion-row').length) {
      document.getElementById('criteria-empty').classList.remove('hidden');
    }
  };

  // ---- Questions ----
  window.addQuestion = function (data) {
    questionCount++;
    const idx = questionCount;
    const container = document.getElementById('questions-container');
    document.getElementById('questions-empty').classList.add('hidden');

    const row = document.createElement('div');
    row.className = 'question-row bg-gray-50 border border-gray-200 rounded-xl p-4';
    row.dataset.idx = idx;
    row.innerHTML = `
      <div class="flex items-start gap-3">
        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Question <span class="text-red-500">*</span></label>
            <input type="text" name="questions[${idx}][text]" placeholder="e.g. Describe your experience with React"
              value="${escAttr(data?.question_text || '')}"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
            <select name="questions[${idx}][type]" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
              <option value="open"            ${(data?.question_type||'open')==='open'            ? 'selected':''}>Open Ended</option>
              <option value="multiple_choice" ${(data?.question_type||'')==='multiple_choice' ? 'selected':''}>Multiple Choice</option>
              <option value="yes_no"          ${(data?.question_type||'')==='yes_no'          ? 'selected':''}>Yes / No</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-3 mt-5 flex-shrink-0">
          <label class="flex items-center gap-1.5 cursor-pointer select-none">
            <input type="checkbox" name="questions[${idx}][required]" value="1" ${data?.is_required ? 'checked' : ''} class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="text-xs text-gray-600 whitespace-nowrap">Required</span>
          </label>
          <button type="button" onclick="removeQuestion(this)" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
    `;
    container.appendChild(row);
  };

  window.removeQuestion = function (btn) {
    btn.closest('.question-row').remove();
    if (!document.querySelectorAll('.question-row').length) {
      document.getElementById('questions-empty').classList.remove('hidden');
    }
  };

  // ---- Submit ----
  window.submitJob = async function (overrideStatus) {
    const btnDraft   = document.getElementById('btn-draft');
    const btnPublish = document.getElementById('btn-publish');
    const alertEl    = document.getElementById('form-alert');

    // Gather basic fields
    const payload = {
      title:            document.getElementById('title').value.trim(),
      department:       document.getElementById('department').value.trim(),
      location:         document.getElementById('location').value.trim(),
      type:             document.getElementById('type').value,
      work_mode:        document.getElementById('work_mode').value,
      experience_level: document.getElementById('experience_level').value,
      salary_min:       document.getElementById('salary_min').value ? Number(document.getElementById('salary_min').value) : null,
      salary_max:       document.getElementById('salary_max').value ? Number(document.getElementById('salary_max').value) : null,
      currency:         document.getElementById('currency').value,
      description:      document.getElementById('description').value.trim(),
      requirements:     document.getElementById('requirements').value.trim(),
      status:           overrideStatus || document.getElementById('status').value,
    };

    if (!payload.title) {
      showAlert('Job title is required.', 'error');
      document.getElementById('title').focus();
      return;
    }

    // Gather criteria
    payload.criteria = [];
    document.querySelectorAll('.criterion-row').forEach(row => {
      const idx = row.dataset.idx;
      const name = row.querySelector(`[name="criteria[${idx}][name]"]`).value.trim();
      if (!name) return;
      payload.criteria.push({
        name,
        weight:      Number(row.querySelector(`[name="criteria[${idx}][weight]"]`).value) || 5,
        description: row.querySelector(`[name="criteria[${idx}][description]"]`).value.trim(),
      });
    });

    // Gather questions
    payload.questions = [];
    document.querySelectorAll('.question-row').forEach(row => {
      const idx = row.dataset.idx;
      const text = row.querySelector(`[name="questions[${idx}][text]"]`).value.trim();
      if (!text) return;
      payload.questions.push({
        question_text: text,
        question_type: row.querySelector(`[name="questions[${idx}][type]"]`).value,
        is_required:   row.querySelector(`[name="questions[${idx}][required]"]`).checked,
      });
    });

    // Disable buttons
    [btnDraft, btnPublish].forEach(b => { b.disabled = true; b.classList.add('opacity-60'); });

    try {
      const url    = IS_EDIT ? `/api/v1/jobs/${JOB_ID}` : '/api/v1/jobs';
      const method = IS_EDIT ? 'PUT' : 'POST';
      const res  = await fetch(url, {
        method,
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Failed to save job');

      showAlert(IS_EDIT ? 'Job updated successfully! Redirecting...' : 'Job created successfully! Redirecting...', 'success');
      setTimeout(() => {
        window.location.href = '/jobs/' + (json.data?.id || JOB_ID);
      }, 1200);
    } catch (e) {
      showAlert(e.message || 'An error occurred. Please try again.', 'error');
      [btnDraft, btnPublish].forEach(b => { b.disabled = false; b.classList.remove('opacity-60'); });
    }
  };

  function showAlert(msg, type) {
    const el = document.getElementById('form-alert');
    el.textContent = msg;
    el.className = `mb-5 p-4 rounded-xl border text-sm font-medium ${type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'}`;
    el.classList.remove('hidden');
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }

  function escAttr(str) {
    return String(str ?? '').replace(/"/g, '&quot;');
  }

  // Pre-populate on edit
  <?php if ($isEdit && !empty($job['criteria'])): ?>
  const existingCriteria = <?= json_encode($job['criteria']) ?>;
  existingCriteria.forEach(c => addCriterion(c));
  <?php else: ?>
  // Show empty state
  document.getElementById('criteria-empty').classList.remove('hidden');
  <?php endif; ?>

  <?php if ($isEdit && !empty($job['questions'])): ?>
  const existingQuestions = <?= json_encode($job['questions']) ?>;
  existingQuestions.forEach(q => addQuestion(q));
  <?php else: ?>
  document.getElementById('questions-empty').classList.remove('hidden');
  <?php endif; ?>
})();
</script>
