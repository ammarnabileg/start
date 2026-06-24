<?php
/**
 * Job Creation Form — AI-assisted or Manual.
 * POST target: /modules/HR/HRRouter.php → JobController::store()
 * AJAX AI generation: POST /api/v1/ai?action=build-job
 */
require_once __DIR__ . '/../../partials/helpers.php';

$departments = $departments ?? ['Engineering','Marketing','Sales','HR','Finance','Operations','Design','Legal','Other'];
$avatars     = $avatars     ?? [
    ['id'=>'sophia',  'name'=>'Sophia (Professional)'],
    ['id'=>'marcus',  'name'=>'Marcus (Friendly)'],
    ['id'=>'aria',    'name'=>'Aria (Formal)'],
    ['id'=>'leo',     'name'=>'Leo (Casual)'],
];
$questionCategories = $questionCategories ?? ['General','Technical','Behavioral','Situational','Leadership','Culture'];

$pageTitle   = 'Create Job';
$activeNav   = 'jobs';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Jobs','url'=>'/jobs'],['label'=>'Create Job']];

ob_start();
?>
<div class="max-w-4xl mx-auto">

  <!-- Page header -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <p class="text-sm text-gray-500">Define the role, interview settings, and evaluation criteria.</p>
    </div>
    <a href="/jobs" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">Cancel</a>
  </div>

  <!-- Mode toggle tabs -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex gap-1 p-1 bg-gray-100 rounded-xl w-fit mb-6">
      <button onclick="setMode('ai')" id="tabAI"
        class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-colors bg-white shadow-sm text-violet-700">
        <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="currentColor">
          <path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/>
        </svg>
        AI Builder
      </button>
      <button onclick="setMode('manual')" id="tabManual"
        class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-gray-500 transition-colors hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Manual Form
      </button>
    </div>

    <!-- ═══════════════ AI BUILDER ═══════════════ -->
    <div id="aiBuilderSection">
      <div class="bg-gradient-to-br from-violet-50 to-amber-50 rounded-2xl border border-violet-100 p-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 bg-violet-600 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
          </div>
          <div>
            <h3 class="font-bold text-gray-900">AI Job Builder</h3>
            <p class="text-xs text-gray-500">Describe the role and our AI will craft a complete job posting in seconds.</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <div class="sm:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Title <span class="text-rose-500">*</span></label>
            <input id="aiTitle" type="text" placeholder="e.g. Senior React Developer"
              class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none bg-white">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
            <select id="aiDept" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
              <?php foreach ($departments as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Experience Level</label>
            <select id="aiLevel" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
              <option value="junior">Junior</option>
              <option value="mid">Mid-Level</option>
              <option value="senior" selected>Senior</option>
              <option value="lead">Lead</option>
              <option value="executive">Executive</option>
            </select>
          </div>
        </div>
        <button onclick="generateWithAI()" id="aiGenerateBtn"
          class="w-full flex items-center justify-center gap-2 bg-amber-400 hover:bg-amber-500 text-gray-900 font-bold rounded-xl py-3 text-sm transition-all shadow-sm hover:shadow-md">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
          Generate with AI
        </button>
      </div>

      <!-- AI loading state -->
      <div id="aiLoading" class="hidden mt-6 flex flex-col items-center justify-center py-12 text-center">
        <div class="relative w-16 h-16 mb-4">
          <div class="w-16 h-16 rounded-full border-4 border-violet-100 border-t-violet-600 animate-spin"></div>
          <div class="absolute inset-0 flex items-center justify-center">
            <svg class="w-6 h-6 text-violet-600" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
          </div>
        </div>
        <p class="font-semibold text-gray-900">AI is crafting your job description...</p>
        <p class="text-sm text-gray-500 mt-1">This usually takes 3–5 seconds</p>
      </div>

      <!-- AI generated notice (shown after generation) -->
      <div id="aiGeneratedBadge" class="hidden mt-4 flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm font-semibold text-emerald-700">AI has populated the form below. Review and edit as needed.</span>
      </div>
    </div>
  </div>

  <!-- ═══════════════ MAIN FORM (both modes) ═══════════════ -->
  <form method="POST" action="/modules/HR/HRRouter.php" id="jobForm">
    <input type="hidden" name="action" value="store">
    <input type="hidden" name="_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="ai_generated" id="aiGeneratedFlag" value="0">

    <!-- Section 1: Basic Info -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
      <h3 class="font-bold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-violet-600 text-white text-xs flex items-center justify-center font-bold">1</span>
        Basic Information
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Title <span class="text-rose-500">*</span></label>
          <input type="text" name="title" id="formTitle" required placeholder="e.g. Senior React Developer"
            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
          <select name="department" id="formDept" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
            <?php foreach ($departments as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Location</label>
          <input type="text" name="location" id="formLocation" placeholder="e.g. London, UK / Remote"
            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
      </div>

      <!-- Job Type multi-select pills -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Job Type</label>
        <div class="flex flex-wrap gap-2" id="jobTypePills">
          <?php
          $jobTypes = ['Remote','Hybrid','On-site','Full-time','Part-time','Contract'];
          foreach ($jobTypes as $t):
          ?>
          <label class="cursor-pointer">
            <input type="checkbox" name="job_type[]" value="<?= e($t) ?>" class="sr-only peer" <?= in_array($t,['Full-time','Hybrid'])?'checked':'' ?>>
            <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-sm font-medium border-2 border-gray-200 text-gray-600 peer-checked:border-violet-500 peer-checked:bg-violet-50 peer-checked:text-violet-700 transition-colors select-none">
              <?= e($t) ?>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Experience Level</label>
          <select name="experience_level" id="formLevel" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
            <option value="junior">Junior</option>
            <option value="mid">Mid-Level</option>
            <option value="senior">Senior</option>
            <option value="lead">Lead</option>
            <option value="executive">Executive</option>
          </select>
        </div>
        <div></div>
      </div>

      <!-- Salary range -->
      <div class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
        <label class="block text-sm font-medium text-gray-700 mb-3">Salary Range</label>
        <div class="flex flex-wrap items-center gap-3">
          <select name="currency" class="rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white w-24">
            <option>USD</option><option>EUR</option><option>GBP</option><option>AED</option><option>SGD</option>
          </select>
          <input type="number" name="salary_min" id="formSalaryMin" placeholder="Min" min="0"
            class="w-32 rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
          <span class="text-gray-400 font-medium">—</span>
          <input type="number" name="salary_max" id="formSalaryMax" placeholder="Max" min="0"
            class="w-32 rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
          <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer ml-2">
            <input type="checkbox" name="show_salary" value="1" checked class="rounded accent-violet-600">
            Show to candidates
          </label>
        </div>
      </div>
    </div>

    <!-- Section 2: Description -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
      <h3 class="font-bold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-violet-600 text-white text-xs flex items-center justify-center font-bold">2</span>
        Job Content
      </h3>

      <div class="mb-4">
        <div class="flex items-center justify-between mb-1.5">
          <label class="text-sm font-medium text-gray-700">Job Description <span class="text-rose-500">*</span></label>
          <span id="descCount" class="text-xs text-gray-400">0 / 500+ chars</span>
        </div>
        <textarea name="description" id="formDesc" rows="8" required
          placeholder="Describe the role, team, impact, and what makes this opportunity exciting..."
          oninput="updateCharCount(this,'descCount',500)"
          class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none resize-y leading-relaxed"></textarea>
        <p class="text-xs text-gray-400 mt-1">Minimum 500 characters for best AI matching results.</p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Requirements</label>
        <textarea name="requirements" id="formRequirements" rows="5"
          placeholder="• 5+ years of experience in...&#10;• Strong understanding of...&#10;• Experience with..."
          class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-y font-mono text-sm"></textarea>
        <p class="text-xs text-gray-400 mt-1">Use bullet points (•) for each requirement.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Benefits</label>
        <textarea name="benefits" id="formBenefits" rows="4"
          placeholder="• Competitive salary&#10;• Remote-first culture&#10;• Health & dental insurance&#10;• 25 days annual leave"
          class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-y font-mono text-sm"></textarea>
      </div>
    </div>

    <!-- Section 3: Interview Settings -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
      <h3 class="font-bold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-violet-600 text-white text-xs flex items-center justify-center font-bold">3</span>
        Interview Process
      </h3>

      <div class="mb-5">
        <label class="block text-sm font-medium text-gray-700 mb-2">Interview Process Type</label>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <?php
          $processTypes = [
            'ai_only'       => ['AI Only',         'AI conducts the full interview autonomously','text-violet-600','border-violet-500 bg-violet-50'],
            'ai_then_human' => ['AI then Human',   'AI screens first, then human interview rounds','text-blue-600','border-blue-500 bg-blue-50'],
            'human_only'    => ['Human Only',       'Traditional human-led interview process','text-gray-600','border-gray-400 bg-gray-50'],
          ];
          foreach ($processTypes as $val=>[$label,$desc,$textCls,$selCls]):
          ?>
          <label class="cursor-pointer">
            <input type="radio" name="interview_process" value="<?= e($val) ?>" class="sr-only peer" <?= $val==='ai_then_human'?'checked':'' ?> onchange="toggleAISettings()">
            <div class="rounded-xl border-2 border-gray-200 p-4 peer-checked:<?= $selCls ?> transition-all hover:border-gray-300 peer-checked:border-2">
              <div class="font-semibold text-sm text-gray-900 mb-1"><?= e($label) ?></div>
              <div class="text-xs text-gray-500"><?= e($desc) ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- AI Interview Settings (shown when AI involved) -->
      <div id="aiInterviewSettings" class="mt-4 p-5 bg-violet-50 rounded-xl border border-violet-100">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-4 h-4 text-violet-600" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
          <span class="text-sm font-bold text-violet-800">AI Interview Settings</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Avatar</label>
            <select name="avatar_id" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
              <?php foreach ($avatars as $av): ?>
                <option value="<?= e($av['id']) ?>"><?= e($av['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Time Limit</label>
            <select name="time_limit" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
              <option value="15">15 minutes</option>
              <option value="20">20 minutes</option>
              <option value="30" selected>30 minutes</option>
              <option value="45">45 minutes</option>
              <option value="60">60 minutes</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Question Bank Category</label>
            <select name="question_category" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
              <?php foreach ($questionCategories as $cat): ?>
                <option value="<?= e(strtolower($cat)) ?>"><?= e($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Enable Voice</label>
            <div class="flex gap-3 mt-2">
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" name="enable_voice" value="1" checked class="accent-violet-600"> Yes
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="radio" name="enable_voice" value="0" class="accent-violet-600"> No
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Section 4: Question Criteria -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
      <div class="flex items-center justify-between mb-5">
        <h3 class="font-bold text-gray-900 flex items-center gap-2">
          <span class="w-6 h-6 rounded-full bg-violet-600 text-white text-xs flex items-center justify-center font-bold">4</span>
          Evaluation Criteria
        </h3>
        <div class="flex items-center gap-3">
          <span id="totalWeightDisplay" class="text-sm font-bold text-gray-500">Total: <span id="totalWeightValue">0</span>%</span>
          <button type="button" onclick="addCriterion()" id="addCriterionBtn"
            class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
            + Add Criterion
          </button>
        </div>
      </div>

      <div id="weightError" class="hidden mb-3 flex items-center gap-2 bg-rose-50 border border-rose-200 rounded-xl px-4 py-3 text-sm text-rose-600">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        Total weight must equal 100%. Currently: <strong id="errorWeightVal">0</strong>%
      </div>

      <div id="criteriaList" class="space-y-3 mb-3">
        <!-- Default criteria -->
        <div class="criterion-row flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100">
          <input type="text" name="criteria[0][name]" placeholder="Criterion name" value="Technical Skills"
            class="flex-1 min-w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
          <div class="flex items-center gap-2">
            <input type="range" name="criteria[0][weight]" min="0" max="100" value="40"
              oninput="updateWeight(this)" class="w-28 accent-violet-600">
            <span class="text-sm font-bold text-gray-700 w-10 text-right weight-display">40%</span>
          </div>
          <select name="criteria[0][type]" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
            <option value="technical">Technical</option>
            <option value="soft">Soft Skill</option>
            <option value="cultural">Cultural</option>
          </select>
          <button type="button" onclick="removeCriterion(this)" class="p-1.5 text-gray-400 hover:text-rose-500 transition-colors rounded-lg hover:bg-rose-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="criterion-row flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100">
          <input type="text" name="criteria[1][name]" placeholder="Criterion name" value="Communication"
            class="flex-1 min-w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
          <div class="flex items-center gap-2">
            <input type="range" name="criteria[1][weight]" min="0" max="100" value="35"
              oninput="updateWeight(this)" class="w-28 accent-violet-600">
            <span class="text-sm font-bold text-gray-700 w-10 text-right weight-display">35%</span>
          </div>
          <select name="criteria[1][type]" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
            <option value="technical">Technical</option>
            <option value="soft" selected>Soft Skill</option>
            <option value="cultural">Cultural</option>
          </select>
          <button type="button" onclick="removeCriterion(this)" class="p-1.5 text-gray-400 hover:text-rose-500 transition-colors rounded-lg hover:bg-rose-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="criterion-row flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100">
          <input type="text" name="criteria[2][name]" placeholder="Criterion name" value="Culture Fit"
            class="flex-1 min-w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
          <div class="flex items-center gap-2">
            <input type="range" name="criteria[2][weight]" min="0" max="100" value="25"
              oninput="updateWeight(this)" class="w-28 accent-violet-600">
            <span class="text-sm font-bold text-gray-700 w-10 text-right weight-display">25%</span>
          </div>
          <select name="criteria[2][type]" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">
            <option value="technical">Technical</option>
            <option value="soft">Soft Skill</option>
            <option value="cultural" selected>Cultural</option>
          </select>
          <button type="button" onclick="removeCriterion(this)" class="p-1.5 text-gray-400 hover:text-rose-500 transition-colors rounded-lg hover:bg-rose-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
      <p class="text-xs text-gray-400">Up to 10 criteria. Total weight must equal exactly 100%.</p>
    </div>

    <!-- Section 5: Application Settings -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
      <h3 class="font-bold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-violet-600 text-white text-xs flex items-center justify-center font-bold">5</span>
        Application Settings
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Application Deadline</label>
          <input type="date" name="deadline" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Applications</label>
          <input type="number" name="max_applications" placeholder="Unlimited" min="1"
            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <label class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-colors">
          <input type="checkbox" name="require_cv" value="1" checked class="mt-0.5 accent-violet-600 w-4 h-4 rounded">
          <div>
            <div class="text-sm font-medium text-gray-900">Require CV</div>
            <div class="text-xs text-gray-500 mt-0.5">Candidates must upload their resume</div>
          </div>
        </label>
        <label class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-colors">
          <input type="checkbox" name="require_cover_letter" value="1" class="mt-0.5 accent-violet-600 w-4 h-4 rounded">
          <div>
            <div class="text-sm font-medium text-gray-900">Cover Letter Required</div>
            <div class="text-xs text-gray-500 mt-0.5">Request a written motivation letter</div>
          </div>
        </label>
      </div>

      <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm font-medium text-gray-700">Auto-reject below score threshold</label>
          <span id="rejectThresholdDisplay" class="text-sm font-bold text-gray-900">0 (off)</span>
        </div>
        <input type="range" name="auto_reject_threshold" min="0" max="100" value="0"
          oninput="updateRejectThreshold(this)"
          class="w-full accent-violet-600">
        <div class="flex justify-between text-xs text-gray-400 mt-1">
          <span>0 (disabled)</span>
          <span>50 (moderate)</span>
          <span>100 (strict)</span>
        </div>
        <p class="text-xs text-gray-500 mt-2">Candidates scoring below this threshold will be automatically rejected. Set to 0 to disable.</p>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-wrap items-center justify-between gap-3">
      <a href="/jobs" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2.5 rounded-full text-sm font-medium transition-colors">
        Cancel
      </a>
      <div class="flex items-center gap-3">
        <button type="submit" name="publish_status" value="draft"
          class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2.5 rounded-full text-sm font-medium transition-colors">
          Save as Draft
        </button>
        <button type="submit" name="publish_status" value="active" onclick="return validateForm()"
          class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-bold transition-all shadow-sm hover:shadow-md">
          Publish Job
        </button>
      </div>
    </div>
  </form>
</div>

<style>
input[type="range"] { height: 6px; cursor: pointer; }
</style>

<script>
var criterionCount = 3;

function setMode(mode) {
  var aiSection   = document.getElementById('aiBuilderSection');
  var tabAI       = document.getElementById('tabAI');
  var tabManual   = document.getElementById('tabManual');
  if (mode === 'ai') {
    aiSection.classList.remove('hidden');
    tabAI.classList.add('bg-white','shadow-sm','text-violet-700');
    tabAI.classList.remove('text-gray-500');
    tabManual.classList.remove('bg-white','shadow-sm','text-violet-700');
    tabManual.classList.add('text-gray-500');
  } else {
    aiSection.classList.add('hidden');
    tabManual.classList.add('bg-white','shadow-sm','text-violet-700');
    tabManual.classList.remove('text-gray-500');
    tabAI.classList.remove('bg-white','shadow-sm','text-violet-700');
    tabAI.classList.add('text-gray-500');
  }
}

async function generateWithAI() {
  var title = document.getElementById('aiTitle').value.trim();
  if (!title) { showToast('Please enter a job title first.', 'warning'); return; }

  var dept  = document.getElementById('aiDept').value;
  var level = document.getElementById('aiLevel').value;
  var btn   = document.getElementById('aiGenerateBtn');
  var loading = document.getElementById('aiLoading');

  btn.disabled = true;
  loading.classList.remove('hidden');

  try {
    var resp = await fetch('/api/v1/ai?action=build-job', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify({title: title, department: dept, experience_level: level})
    });
    var data = await resp.json();
    var job  = data.data || {};

    // Populate form fields with AI result
    document.getElementById('formTitle').value        = job.title        || title;
    document.getElementById('formDept').value         = job.department   || dept;
    document.getElementById('formLevel').value        = job.experience_level || level;
    document.getElementById('formLocation').value     = job.location     || '';
    document.getElementById('formDesc').value         = job.description  || generateFallbackDesc(title, dept, level);
    document.getElementById('formRequirements').value = job.requirements || generateFallbackReqs(title, level);
    document.getElementById('formBenefits').value     = job.benefits     || '• Competitive salary\n• Remote-first culture\n• Health & dental insurance\n• 25 days annual leave\n• Professional development budget';

    // Update char counter
    updateCharCount(document.getElementById('formDesc'), 'descCount', 500);

    document.getElementById('aiGeneratedFlag').value = '1';
    document.getElementById('aiGeneratedBadge').classList.remove('hidden');

    showToast('AI job description generated! Review and edit below.', 'success');

  } catch(e) {
    // Fallback with demo content
    document.getElementById('formTitle').value        = title;
    document.getElementById('formDept').value         = dept;
    document.getElementById('formLevel').value        = level;
    document.getElementById('formDesc').value         = generateFallbackDesc(title, dept, level);
    document.getElementById('formRequirements').value = generateFallbackReqs(title, level);
    document.getElementById('formBenefits').value     = '• Competitive salary and equity\n• Flexible remote-first work\n• Health, dental, and vision coverage\n• 401(k) with company match\n• Annual learning & development budget\n• 25 days PTO + public holidays';
    updateCharCount(document.getElementById('formDesc'), 'descCount', 500);
    document.getElementById('aiGeneratedFlag').value = '1';
    document.getElementById('aiGeneratedBadge').classList.remove('hidden');
    showToast('AI generated a draft. Review and customize before publishing.', 'info');
  }

  loading.classList.add('hidden');
  btn.disabled = false;
  // Scroll to form
  document.getElementById('jobForm').scrollIntoView({behavior: 'smooth', block: 'start'});
}

function generateFallbackDesc(title, dept, level) {
  var lvl = level.charAt(0).toUpperCase() + level.slice(1);
  return 'We are looking for a talented ' + lvl + ' ' + title + ' to join our ' + dept + ' team.\n\nIn this role, you will be responsible for designing, building, and shipping high-quality work that directly impacts our users and business. You will collaborate cross-functionally with product, design, and engineering teams to deliver outstanding outcomes.\n\nThis is an exciting opportunity to work on challenging problems at scale with a team of passionate professionals who care deeply about quality and impact. We offer a collaborative environment where your ideas are valued and your growth is a priority.\n\nYou will be part of a fast-moving team that values ownership, transparency, and continuous improvement. We work with modern tools and technologies and are committed to engineering excellence.';
}

function generateFallbackReqs(title, level) {
  var years = {junior:'1-2', mid:'3-5', senior:'5+', lead:'7+', executive:'10+'}[level] || '3+';
  return '• ' + years + ' years of relevant professional experience\n• Strong problem-solving skills and analytical mindset\n• Excellent communication and collaboration abilities\n• Experience working in agile/scrum environments\n• Proven track record of delivering high-quality results\n• Ability to work independently and as part of a team\n• Strong attention to detail and commitment to quality';
}

function updateCharCount(el, counterId, min) {
  var len = el.value.length;
  var el2 = document.getElementById(counterId);
  if (!el2) return;
  el2.textContent = len + ' / ' + min + '+ chars';
  el2.className = len >= min ? 'text-xs text-emerald-600 font-medium' : 'text-xs text-gray-400';
}

function toggleAISettings() {
  var process = document.querySelector('input[name="interview_process"]:checked')?.value;
  var settings = document.getElementById('aiInterviewSettings');
  if (!settings) return;
  settings.classList.toggle('hidden', process === 'human_only');
}

function addCriterion() {
  var list = document.getElementById('criteriaList');
  if (!list) return;
  var rows = list.querySelectorAll('.criterion-row');
  if (rows.length >= 10) { showToast('Maximum 10 criteria allowed.', 'warning'); return; }
  var idx = criterionCount++;
  var html = '<div class="criterion-row flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-100">' +
    '<input type="text" name="criteria[' + idx + '][name]" placeholder="Criterion name" class="flex-1 min-w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">' +
    '<div class="flex items-center gap-2">' +
      '<input type="range" name="criteria[' + idx + '][weight]" min="0" max="100" value="0" oninput="updateWeight(this)" class="w-28 accent-violet-600">' +
      '<span class="text-sm font-bold text-gray-700 w-10 text-right weight-display">0%</span>' +
    '</div>' +
    '<select name="criteria[' + idx + '][type]" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none bg-white">' +
      '<option value="technical">Technical</option><option value="soft">Soft Skill</option><option value="cultural">Cultural</option>' +
    '</select>' +
    '<button type="button" onclick="removeCriterion(this)" class="p-1.5 text-gray-400 hover:text-rose-500 transition-colors rounded-lg hover:bg-rose-50">' +
      '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
    '</button></div>';
  list.insertAdjacentHTML('beforeend', html);
  updateTotalWeight();
}

function removeCriterion(btn) {
  var rows = document.querySelectorAll('#criteriaList .criterion-row');
  if (rows.length <= 1) { showToast('At least one criterion required.', 'warning'); return; }
  btn.closest('.criterion-row').remove();
  updateTotalWeight();
}

function updateWeight(rangeEl) {
  var display = rangeEl.closest('.flex.items-center').querySelector('.weight-display');
  if (display) display.textContent = rangeEl.value + '%';
  updateTotalWeight();
}

function updateTotalWeight() {
  var sliders = document.querySelectorAll('#criteriaList input[type="range"]');
  var total = 0;
  sliders.forEach(function(s){ total += parseInt(s.value||0); });
  var display = document.getElementById('totalWeightValue');
  var errBox  = document.getElementById('weightError');
  var errVal  = document.getElementById('errorWeightVal');
  if (display) display.textContent = total;
  var isOK = total === 100;
  if (errBox) errBox.classList.toggle('hidden', isOK || total === 0);
  if (errVal) errVal.textContent = total;
  var tw = document.getElementById('totalWeightDisplay');
  if (tw) tw.className = 'text-sm font-bold ' + (isOK ? 'text-emerald-600' : (total > 0 ? 'text-rose-500' : 'text-gray-500'));
}

function updateRejectThreshold(el) {
  var display = document.getElementById('rejectThresholdDisplay');
  if (!display) return;
  var v = parseInt(el.value);
  display.textContent = v === 0 ? '0 (off)' : v;
  display.className = 'text-sm font-bold ' + (v === 0 ? 'text-gray-400' : (v >= 70 ? 'text-rose-600' : 'text-amber-600'));
}

function validateForm() {
  var sliders = document.querySelectorAll('#criteriaList input[type="range"]');
  var total = 0;
  sliders.forEach(function(s){ total += parseInt(s.value||0); });
  if (sliders.length > 0 && total !== 100) {
    showToast('Criteria weights must total 100%. Currently: ' + total + '%.', 'error');
    document.getElementById('criteriaList').scrollIntoView({behavior:'smooth'});
    return false;
  }
  var desc = document.getElementById('formDesc');
  if (desc && desc.value.length < 50) {
    showToast('Please add a more detailed job description.', 'warning');
    return false;
  }
  return true;
}

// Init
document.addEventListener('DOMContentLoaded', function() {
  updateTotalWeight();
  // Listen for process radio changes
  document.querySelectorAll('input[name="interview_process"]').forEach(function(r){
    r.addEventListener('change', toggleAISettings);
  });
});
</script>
<?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
