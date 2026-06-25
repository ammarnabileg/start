<?php
$pageTitle = 'My Profile';
$db = Database::getInstance();
$cid = Auth::user()['id'];
$profile = $db->fetch("SELECT * FROM candidates WHERE id = ?", [$cid]);
$cv = $db->fetch("SELECT * FROM candidate_cvs WHERE candidate_id = ? ORDER BY created_at DESC LIMIT 1", [$cid]);

// Load from normalized relational tables
$workExperience = $db->fetchAll("SELECT * FROM candidate_experiences WHERE candidate_id = ? ORDER BY is_current DESC, start_date DESC", [$cid]) ?: [];
$education      = $db->fetchAll("SELECT * FROM candidate_education WHERE candidate_id = ? ORDER BY graduation_year DESC", [$cid]) ?: [];
$skillRows      = $db->fetchAll("SELECT * FROM candidate_skills WHERE candidate_id = ? ORDER BY skill_name", [$cid]) ?: [];
$skills         = array_column($skillRows, 'skill_name');
$languages      = [];
if (!empty($profile['languages_spoken'])) {
    $decoded = json_decode($profile['languages_spoken'], true);
    if (is_array($decoded)) $languages = $decoded;
}

// Profile completion weights
$hasName      = !empty($profile['first_name']);
$hasPhone     = !empty($profile['phone']);
$hasLocation  = !empty($profile['location']);
$hasSummary   = !empty($profile['professional_summary']);
$hasExp       = !empty($workExperience);
$hasEdu       = !empty($education);
$hasSkills    = !empty($skills);
$hasCv        = !empty($cv);

$completion = 0;
if ($hasName)     $completion += 10;
if ($hasPhone)    $completion += 5;
if ($hasLocation) $completion += 5;
if ($hasSummary)  $completion += 15;
if ($hasExp)      $completion += 20;
if ($hasEdu)      $completion += 15;
if ($hasSkills)   $completion += 15;
if ($hasCv)       $completion += 15;

$nameInitials = '';
$fullName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
if ($fullName) {
    $parts = explode(' ', $fullName);
    $nameInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
if (empty($nameInitials)) $nameInitials = 'ME';

$availabilityOptions = [
    'available_now'  => 'Available Now',
    '1_month'        => '1 Month Notice',
    '2_months_plus'  => '2+ Months Notice',
    'not_looking'    => 'Not Looking',
];

$degreeOptions      = ["Bachelor's", "Master's", "PhD", "Associate's", "Diploma", "Certificate", "Other"];
$proficiencyOptions = ['Native', 'Fluent', 'Professional', 'Basic'];

$cvFilename   = $cv['filename'] ?? $cv['original_name'] ?? null;
$cvUploadDate = !empty($cv['created_at']) ? date('M j, Y', strtotime($cv['created_at'])) : null;
?>

<!-- ══════════════════════ PROFILE COMPLETION BAR ══════════════════════ -->
<div class="mb-6">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-4">
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-3">
        <h2 class="text-sm font-semibold text-gray-700">Profile Completion</h2>
        <span id="completion-text" class="text-sm font-bold text-violet-700"><?= $completion ?>% complete</span>
      </div>
      <?php if ($completion < 100): ?>
      <span class="text-xs text-gray-400 hidden sm:block">Fill in all sections to stand out to recruiters</span>
      <?php else: ?>
      <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        Profile complete!
      </span>
      <?php endif; ?>
    </div>
    <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
      <div id="completion-bar"
           class="h-full rounded-full transition-all duration-700 <?= $completion >= 80 ? 'bg-emerald-500' : ($completion >= 50 ? 'bg-violet-600' : 'bg-amber-500') ?>"
           style="width: <?= $completion ?>%"></div>
    </div>
    <div class="flex gap-x-5 gap-y-1.5 mt-3 flex-wrap">
      <span class="flex items-center gap-1.5 text-xs <?= ($hasName && $hasPhone && $hasLocation) ? 'text-gray-400 line-through' : 'text-gray-600' ?>">
        <span class="w-1.5 h-1.5 rounded-full inline-block <?= ($hasName && $hasPhone && $hasLocation) ? 'bg-emerald-400' : 'bg-gray-300' ?>"></span>Basic info (20%)
      </span>
      <span class="flex items-center gap-1.5 text-xs <?= $hasSummary ? 'text-gray-400 line-through' : 'text-gray-600' ?>">
        <span class="w-1.5 h-1.5 rounded-full inline-block <?= $hasSummary ? 'bg-emerald-400' : 'bg-gray-300' ?>"></span>Summary (15%)
      </span>
      <span class="flex items-center gap-1.5 text-xs <?= $hasExp ? 'text-gray-400 line-through' : 'text-gray-600' ?>">
        <span class="w-1.5 h-1.5 rounded-full inline-block <?= $hasExp ? 'bg-emerald-400' : 'bg-gray-300' ?>"></span>Experience (20%)
      </span>
      <span class="flex items-center gap-1.5 text-xs <?= $hasEdu ? 'text-gray-400 line-through' : 'text-gray-600' ?>">
        <span class="w-1.5 h-1.5 rounded-full inline-block <?= $hasEdu ? 'bg-emerald-400' : 'bg-gray-300' ?>"></span>Education (15%)
      </span>
      <span class="flex items-center gap-1.5 text-xs <?= $hasSkills ? 'text-gray-400 line-through' : 'text-gray-600' ?>">
        <span class="w-1.5 h-1.5 rounded-full inline-block <?= $hasSkills ? 'bg-emerald-400' : 'bg-gray-300' ?>"></span>Skills (15%)
      </span>
      <span class="flex items-center gap-1.5 text-xs <?= $hasCv ? 'text-gray-400 line-through' : 'text-gray-600' ?>">
        <span class="w-1.5 h-1.5 rounded-full inline-block <?= $hasCv ? 'bg-emerald-400' : 'bg-gray-300' ?>"></span>CV (15%)
      </span>
    </div>
  </div>
</div>

<!-- ══════════════════════ PAGE HEADER ══════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
    <p class="text-sm text-gray-500 mt-0.5">Keep your profile up to date to get the best job matches</p>
  </div>
  <button onclick="saveProfile()" id="save-btn"
    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition-colors shadow-sm shadow-violet-200 disabled:opacity-60">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    Save Profile
  </button>
</div>

<!-- ══════════════════════ TABS ══════════════════════ -->
<div class="mb-6 border-b border-gray-200">
  <nav class="-mb-px flex gap-1 overflow-x-auto no-scrollbar" id="profile-tabs">
    <?php
    $tabs = [
      ['personal',   'Personal Info',  'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
      ['experience', 'Experience',     'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2'],
      ['education',  'Education',      'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
      ['skills',     'Skills & More',  'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
    ];
    foreach ($tabs as $i => [$id, $label, $icon]): ?>
    <button onclick="switchTab('<?= $id ?>')" id="tab-btn-<?= $id ?>"
      class="tab-btn flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors
        <?= $i === 0 ? 'border-violet-600 text-violet-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/></svg>
      <?= $label ?>
    </button>
    <?php endforeach; ?>
  </nav>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 1 — PERSONAL INFO
═══════════════════════════════════════════════════════════ -->
<div id="tab-personal" class="tab-panel">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left column: Photo + Availability -->
    <div class="space-y-5">

      <!-- Profile Photo -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Profile Photo</h3>
        <div class="flex flex-col items-center gap-4">
          <div class="relative">
            <?php if (!empty($profile['avatar'])): ?>
            <img id="profile-photo-img" src="<?= htmlspecialchars($profile['avatar']) ?>"
                 alt="Profile photo"
                 class="w-24 h-24 rounded-full object-cover border-4 border-violet-100 shadow-sm">
            <?php else: ?>
            <div id="profile-photo-initials"
                 class="w-24 h-24 rounded-full bg-gradient-to-br from-violet-500 to-violet-700 flex items-center justify-center text-2xl font-bold text-white border-4 border-violet-100 shadow-sm select-none">
              <?= htmlspecialchars($nameInitials) ?>
            </div>
            <?php endif; ?>
            <label for="photo-upload"
              class="absolute -bottom-1 -right-1 w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center cursor-pointer hover:bg-gray-50 shadow-md transition-colors"
              title="Change photo">
              <svg class="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </label>
            <input type="file" id="photo-upload" accept="image/*" class="hidden" onchange="uploadPhoto(this)">
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-500">JPG, PNG or WebP</p>
            <p class="text-xs text-gray-400 mt-0.5">Max 2MB recommended</p>
          </div>
        </div>
      </div>

      <!-- Availability -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Availability Status</h3>
        <div class="space-y-3">
          <?php foreach ($availabilityOptions as $val => $label):
            $isChecked = ($profile['availability'] ?? '') === $val;
            $dotColor  = match($val) {
              'available_now' => 'bg-emerald-400',
              '1_month'       => 'bg-amber-400',
              '2_months_plus' => 'bg-orange-400',
              'not_looking'   => 'bg-red-400',
              default         => 'bg-gray-400',
            };
          ?>
          <label class="flex items-center gap-3 cursor-pointer group p-2 rounded-xl hover:bg-gray-50 transition-colors -mx-2">
            <input type="radio" name="availability" value="<?= $val ?>"
              <?= $isChecked ? 'checked' : '' ?>
              class="w-4 h-4 text-violet-600 border-gray-300 focus:ring-violet-500">
            <span class="w-2 h-2 rounded-full flex-shrink-0 <?= $dotColor ?>"></span>
            <span class="text-sm text-gray-700 group-hover:text-gray-900 transition-colors"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Right column: Main fields -->
    <div class="lg:col-span-2 space-y-5">

      <!-- Basic Details -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-5">Basic Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Full Name <span class="text-red-400">*</span></label>
            <input type="text" id="full_name" name="full_name"
              value="<?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?>"
              placeholder="e.g. Alex Johnson"
              oninput="updateInitials(this.value); scheduleCompletionUpdate()"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone Number</label>
            <input type="tel" id="phone" name="phone"
              value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"
              placeholder="+1 (555) 000-0000"
              oninput="scheduleCompletionUpdate()"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Location</label>
            <input type="text" id="location" name="location"
              value="<?= htmlspecialchars($profile['location'] ?? '') ?>"
              placeholder="City, Country"
              oninput="scheduleCompletionUpdate()"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">LinkedIn URL</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
              </span>
              <input type="url" id="linkedin_url" name="linkedin_url"
                value="<?= htmlspecialchars($profile['linkedin_url'] ?? '') ?>"
                placeholder="https://linkedin.com/in/yourname"
                class="w-full border border-gray-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Portfolio URL</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
              </span>
              <input type="url" id="portfolio_url" name="portfolio_url"
                value="<?= htmlspecialchars($profile['portfolio_url'] ?? '') ?>"
                placeholder="https://yourportfolio.com"
                class="w-full border border-gray-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
            </div>
          </div>
        </div>
      </div>

      <!-- Professional Summary -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Professional Summary</h3>
            <p class="text-xs text-gray-400 mt-0.5">The elevator pitch recruiters read first</p>
          </div>
          <button onclick="rewriteBio()" id="rewrite-btn"
            class="inline-flex items-center gap-1.5 bg-violet-50 hover:bg-violet-100 text-violet-700 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors border border-violet-200 disabled:opacity-50">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            <span id="rewrite-btn-text">AI Rewrite</span>
          </button>
        </div>
        <textarea id="professional_summary" name="professional_summary" rows="5"
          placeholder="Write a compelling summary about your professional background, key skills, and career goals..."
          oninput="scheduleCompletionUpdate()"
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors resize-none"><?= htmlspecialchars($profile['professional_summary'] ?? '') ?></textarea>
        <p class="text-xs text-gray-400 mt-2">Aim for 3-5 sentences. Mention your role, top skills, and what you bring to teams.</p>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 2 — EXPERIENCE
═══════════════════════════════════════════════════════════ -->
<div id="tab-experience" class="tab-panel hidden">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="text-sm font-semibold text-gray-800">Work Experience</h3>
        <p class="text-xs text-gray-500 mt-0.5">Add your positions, newest first</p>
      </div>
      <button onclick="addExperienceEntry()"
        class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Experience
      </button>
    </div>

    <div id="experience-list" class="space-y-4">
      <?php if (empty($workExperience)): ?>
      <div id="exp-empty-state" class="py-14 text-center">
        <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <p class="text-sm font-medium text-gray-600">No experience added yet</p>
        <p class="text-xs text-gray-400 mt-1">Click "Add Experience" to get started</p>
      </div>
      <?php else: ?>
      <?php foreach ($workExperience as $exp): ?>
      <div class="exp-entry border border-gray-200 rounded-2xl p-5 relative bg-gray-50/40 hover:border-gray-300 transition-colors">
        <button onclick="removeEntry(this, 'exp')" type="button"
          class="absolute top-4 right-4 w-7 h-7 rounded-full bg-red-50 hover:bg-red-100 flex items-center justify-center transition-colors" title="Remove entry">
          <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pr-10">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Company Name</label>
            <input type="text" name="exp_company[]"
              value="<?= htmlspecialchars($exp['company'] ?? '') ?>"
              placeholder="e.g. Acme Corp"
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Job Title</label>
            <input type="text" name="exp_title[]"
              value="<?= htmlspecialchars($exp['title'] ?? '') ?>"
              placeholder="e.g. Senior Developer"
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Start Date</label>
            <input type="month" name="exp_start[]"
              value="<?= htmlspecialchars($exp['start_date'] ?? '') ?>"
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">End Date</label>
            <div class="flex flex-col gap-1.5">
              <input type="month" name="exp_end[]"
                value="<?= !empty($exp['current']) ? '' : htmlspecialchars($exp['end_date'] ?? '') ?>"
                <?= !empty($exp['current']) ? 'disabled' : '' ?>
                class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors exp-end-input <?= !empty($exp['current']) ? 'opacity-40' : '' ?>">
              <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
                <input type="checkbox" name="exp_current[]" value="1"
                  <?= !empty($exp['current']) ? 'checked' : '' ?>
                  onchange="toggleCurrentJob(this)"
                  class="w-3.5 h-3.5 text-violet-600 rounded border-gray-300 focus:ring-violet-500">
                Currently working here
              </label>
            </div>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Description</label>
            <textarea name="exp_desc[]" rows="3"
              placeholder="Describe your key responsibilities and achievements..."
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors resize-none"><?= htmlspecialchars($exp['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 3 — EDUCATION
═══════════════════════════════════════════════════════════ -->
<div id="tab-education" class="tab-panel hidden">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="text-sm font-semibold text-gray-800">Education</h3>
        <p class="text-xs text-gray-500 mt-0.5">Add your academic qualifications</p>
      </div>
      <button onclick="addEducationEntry()"
        class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Education
      </button>
    </div>

    <div id="education-list" class="space-y-4">
      <?php if (empty($education)): ?>
      <div id="edu-empty-state" class="py-14 text-center">
        <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
        </div>
        <p class="text-sm font-medium text-gray-600">No education added yet</p>
        <p class="text-xs text-gray-400 mt-1">Click "Add Education" to get started</p>
      </div>
      <?php else: ?>
      <?php foreach ($education as $edu): ?>
      <div class="edu-entry border border-gray-200 rounded-2xl p-5 relative bg-gray-50/40 hover:border-gray-300 transition-colors">
        <button onclick="removeEntry(this, 'edu')" type="button"
          class="absolute top-4 right-4 w-7 h-7 rounded-full bg-red-50 hover:bg-red-100 flex items-center justify-center transition-colors" title="Remove entry">
          <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pr-10">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Institution Name</label>
            <input type="text" name="edu_institution[]"
              value="<?= htmlspecialchars($edu['institution'] ?? '') ?>"
              placeholder="e.g. University of California"
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Degree Type</label>
            <select name="edu_degree[]"
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
              <?php foreach ($degreeOptions as $deg): ?>
              <option value="<?= $deg ?>" <?= ($edu['degree'] ?? '') === $deg ? 'selected' : '' ?>><?= $deg ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Graduation Year</label>
            <input type="number" name="edu_grad_year[]"
              value="<?= htmlspecialchars($edu['graduation_year'] ?? '') ?>"
              placeholder="e.g. 2020" min="1950" max="<?= date('Y') + 6 ?>"
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Field of Study</label>
            <input type="text" name="edu_field[]"
              value="<?= htmlspecialchars($edu['field'] ?? '') ?>"
              placeholder="e.g. Computer Science"
              class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TAB 4 — SKILLS & MORE
═══════════════════════════════════════════════════════════ -->
<div id="tab-skills" class="tab-panel hidden">
  <div class="space-y-6">

    <!-- Skills Tag Input -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-sm font-semibold text-gray-800 mb-1">Skills</h3>
      <p class="text-xs text-gray-500 mb-4">Type a skill name and press <kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-600 font-mono text-[11px]">Enter</kbd> or <kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-600 font-mono text-[11px]">,</kbd> to add it</p>

      <div id="skills-container"
        class="min-h-[52px] w-full border border-gray-200 rounded-xl px-3 py-2 flex flex-wrap gap-2 cursor-text focus-within:border-violet-400 focus-within:ring-1 focus-within:ring-violet-200 transition-colors"
        onclick="document.getElementById('skill-input').focus()">
        <?php foreach ($skills as $skill): ?>
        <span class="skill-tag inline-flex items-center gap-1.5 bg-violet-100 text-violet-700 rounded-full px-3 py-1 text-xs font-medium">
          <span class="skill-label"><?= htmlspecialchars($skill) ?></span>
          <button type="button" onclick="removeSkillTag(this)" class="hover:text-violet-900 transition-colors ml-0.5">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </span>
        <?php endforeach; ?>
        <input type="text" id="skill-input"
          placeholder="<?= empty($skills) ? 'e.g. JavaScript, React, Project Management...' : 'Add more skills...' ?>"
          class="flex-1 min-w-[140px] border-none outline-none bg-transparent text-sm text-gray-900 placeholder-gray-400 py-1"
          onkeydown="handleSkillInput(event)">
      </div>
      <input type="hidden" id="skills-value" name="skills"
        value="<?= htmlspecialchars(implode(',', $skills)) ?>">
      <p class="text-xs text-gray-400 mt-2">Click the &times; on a tag to remove it. Recruiters search by skill keywords.</p>
    </div>

    <!-- Languages -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="text-sm font-semibold text-gray-800">Languages</h3>
          <p class="text-xs text-gray-500 mt-0.5">Languages you can work in professionally</p>
        </div>
        <button onclick="addLanguageEntry()"
          class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add Language
        </button>
      </div>

      <div id="language-list" class="space-y-3">
        <?php if (empty($languages)): ?>
        <div id="lang-empty-state" class="py-8 text-center">
          <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
          <p class="text-sm text-gray-400">No languages added yet</p>
        </div>
        <?php else: ?>
        <?php foreach ($languages as $lang): ?>
        <div class="lang-entry flex items-center gap-3">
          <input type="text" name="lang_name[]"
            value="<?= htmlspecialchars($lang['language'] ?? '') ?>"
            placeholder="Language name (e.g. English)"
            class="flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          <select name="lang_level[]"
            class="w-44 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors bg-white">
            <?php foreach ($proficiencyOptions as $prof): ?>
            <option value="<?= $prof ?>" <?= ($lang['proficiency'] ?? '') === $prof ? 'selected' : '' ?>><?= $prof ?></option>
            <?php endforeach; ?>
          </select>
          <button onclick="removeLangEntry(this)" type="button"
            class="w-8 h-8 rounded-full bg-red-50 hover:bg-red-100 flex items-center justify-center transition-colors flex-shrink-0">
            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- CV Upload -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="text-sm font-semibold text-gray-800 mb-1">CV / Resume</h3>
      <p class="text-xs text-gray-500 mb-5">Upload your latest CV so recruiters can review it alongside your application</p>

      <?php if (!empty($cvFilename)): ?>
      <div id="cv-existing" class="border border-emerald-200 bg-emerald-50 rounded-2xl p-4 flex items-center gap-4">
        <div class="w-11 h-11 bg-emerald-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($cvFilename) ?></p>
          <?php if ($cvUploadDate): ?>
          <p class="text-xs text-gray-500 mt-0.5">Uploaded <?= $cvUploadDate ?></p>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <label for="cv-file-input"
            class="cursor-pointer inline-flex items-center gap-1.5 text-xs font-medium text-violet-600 hover:text-violet-800 border border-violet-200 hover:border-violet-400 hover:bg-violet-50 px-3 py-1.5 rounded-full transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Replace
          </label>
          <button onclick="deleteCV()" type="button"
            class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 hover:bg-red-50 px-3 py-1.5 rounded-full transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete
          </button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Drop zone (hidden when CV exists, shown on replace) -->
      <div id="cv-drop-zone"
        class="<?= !empty($cvFilename) ? 'mt-4 hidden' : '' ?> border-2 border-dashed border-gray-300 rounded-2xl p-10 text-center hover:border-violet-400 hover:bg-violet-50/30 cursor-pointer transition-all"
        onclick="document.getElementById('cv-file-input').click()"
        ondragover="handleCVDragOver(event)"
        ondragleave="handleCVDragLeave(event)"
        ondrop="handleCVDrop(event)">
        <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        </div>
        <p class="text-sm font-semibold text-gray-700 mb-1">Drop your CV here or click to browse</p>
        <p class="text-xs text-gray-400">PDF, DOC, DOCX &mdash; maximum 5MB</p>
        <div id="cv-upload-progress" class="hidden mt-5">
          <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden mx-auto max-w-xs">
            <div id="cv-progress-bar" class="h-full bg-violet-500 rounded-full transition-all duration-300" style="width:0%"></div>
          </div>
          <p class="text-xs text-gray-500 mt-1.5" id="cv-progress-text">Uploading...</p>
        </div>
      </div>
      <input type="file" id="cv-file-input" accept=".pdf,.doc,.docx" class="hidden" onchange="uploadCV(this)">
    </div>

  </div>
</div>

<!-- ══════════════════════ SAVE BUTTON (BOTTOM) ══════════════════════ -->
<div class="mt-8 flex justify-end">
  <button onclick="saveProfile()"
    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-6 py-3 rounded-full text-sm font-semibold transition-colors shadow-sm shadow-violet-200">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    Save All Changes
  </button>
</div>

<!-- ══════════════════════ TOAST CONTAINER ══════════════════════ -->
<div id="toast-container" class="fixed top-20 right-4 z-50 flex flex-col gap-2 pointer-events-none max-w-sm w-full"></div>

<!-- ══════════════════════ HTML TEMPLATES ══════════════════════ -->
<template id="exp-entry-template">
  <div class="exp-entry border border-gray-200 rounded-2xl p-5 relative bg-gray-50/40 hover:border-gray-300 transition-colors">
    <button onclick="removeEntry(this, 'exp')" type="button"
      class="absolute top-4 right-4 w-7 h-7 rounded-full bg-red-50 hover:bg-red-100 flex items-center justify-center transition-colors" title="Remove">
      <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pr-10">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Company Name</label>
        <input type="text" name="exp_company[]" placeholder="e.g. Acme Corp"
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Job Title</label>
        <input type="text" name="exp_title[]" placeholder="e.g. Senior Developer"
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Start Date</label>
        <input type="month" name="exp_start[]"
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1.5">End Date</label>
        <div class="flex flex-col gap-1.5">
          <input type="month" name="exp_end[]"
            class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors exp-end-input">
          <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
            <input type="checkbox" name="exp_current[]" value="1"
              onchange="toggleCurrentJob(this)"
              class="w-3.5 h-3.5 text-violet-600 rounded border-gray-300 focus:ring-violet-500">
            Currently working here
          </label>
        </div>
      </div>
      <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Description</label>
        <textarea name="exp_desc[]" rows="3"
          placeholder="Describe your key responsibilities and achievements..."
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors resize-none"></textarea>
      </div>
    </div>
  </div>
</template>

<template id="edu-entry-template">
  <div class="edu-entry border border-gray-200 rounded-2xl p-5 relative bg-gray-50/40 hover:border-gray-300 transition-colors">
    <button onclick="removeEntry(this, 'edu')" type="button"
      class="absolute top-4 right-4 w-7 h-7 rounded-full bg-red-50 hover:bg-red-100 flex items-center justify-center transition-colors" title="Remove">
      <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pr-10">
      <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Institution Name</label>
        <input type="text" name="edu_institution[]" placeholder="e.g. University of California"
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Degree Type</label>
        <select name="edu_degree[]"
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
          <option>Bachelor's</option><option>Master's</option><option>PhD</option>
          <option>Associate's</option><option>Diploma</option><option>Certificate</option><option>Other</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Graduation Year</label>
        <input type="number" name="edu_grad_year[]" placeholder="e.g. 2020"
          min="1950" max="<?= date('Y') + 6 ?>"
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-gray-600 mb-1.5">Field of Study</label>
        <input type="text" name="edu_field[]" placeholder="e.g. Computer Science"
          class="w-full border border-gray-200 bg-white rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
      </div>
    </div>
  </div>
</template>

<template id="lang-entry-template">
  <div class="lang-entry flex items-center gap-3">
    <input type="text" name="lang_name[]" placeholder="Language name (e.g. English)"
      class="flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
    <select name="lang_level[]"
      class="w-44 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors bg-white">
      <option>Native</option><option>Fluent</option><option>Professional</option><option>Basic</option>
    </select>
    <button onclick="removeLangEntry(this)" type="button"
      class="w-8 h-8 rounded-full bg-red-50 hover:bg-red-100 flex items-center justify-center transition-colors flex-shrink-0">
      <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
</template>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(id) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
  const panel = document.getElementById('tab-' + id);
  if (panel) panel.classList.remove('hidden');

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('border-violet-600', 'text-violet-700');
    btn.classList.add('border-transparent', 'text-gray-500');
  });
  const activeBtn = document.getElementById('tab-btn-' + id);
  if (activeBtn) {
    activeBtn.classList.add('border-violet-600', 'text-violet-700');
    activeBtn.classList.remove('border-transparent', 'text-gray-500');
  }

  // Update URL hash without page jump
  history.replaceState(null, '', '#' + id);
}

// ── Initials update ────────────────────────────────────────────────────────
function updateInitials(name) {
  const parts    = name.trim().split(/\s+/);
  const initials = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || 'ME';
  const el       = document.getElementById('profile-photo-initials');
  if (el) el.textContent = initials;
}

// ── AI Bio Rewrite ─────────────────────────────────────────────────────────
async function rewriteBio() {
  const textarea = document.getElementById('professional_summary');
  const btn      = document.getElementById('rewrite-btn');
  const btnText  = document.getElementById('rewrite-btn-text');
  if (!textarea || !btn) return;

  btn.disabled = true;
  btnText.textContent = 'Rewriting...';

  try {
    const res = await fetch('/api/v1/ai?action=rewrite-bio', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        bio:    textarea.value.trim(),
        name:   document.getElementById('full_name')?.value ?? '',
        skills: document.getElementById('skills-value')?.value ?? ''
      })
    });
    const data = await res.json();
    const result = data.result ?? data.rewritten ?? data.bio ?? null;
    if (result) {
      textarea.value = result;
      showToast('Bio rewritten by AI!', 'success');
      scheduleCompletionUpdate();
    } else {
      throw new Error(data.error ?? 'No result returned');
    }
  } catch (e) {
    showToast('AI rewrite failed. Please try again.', 'error');
  } finally {
    btn.disabled = false;
    btnText.textContent = 'AI Rewrite';
  }
}

// ── Experience entries ─────────────────────────────────────────────────────
function addExperienceEntry() {
  const list  = document.getElementById('experience-list');
  const empty = document.getElementById('exp-empty-state');
  if (empty) empty.remove();

  const tpl   = document.getElementById('exp-entry-template');
  const clone = tpl.content.cloneNode(true);
  // Newest first: prepend
  list.insertBefore(clone, list.firstChild);
  list.firstElementChild?.querySelector('input')?.focus();
  scheduleCompletionUpdate();
}

function removeEntry(btn, type) {
  const selector = type === 'exp' ? '.exp-entry' : '.edu-entry';
  const entry    = btn.closest(selector);
  if (entry) {
    entry.remove();
    scheduleCompletionUpdate();
  }
}

function toggleCurrentJob(checkbox) {
  const row      = checkbox.closest('.flex-col, div');
  const endInput = row?.querySelector('.exp-end-input');
  if (!endInput) return;
  if (checkbox.checked) {
    endInput.value    = '';
    endInput.disabled = true;
    endInput.classList.add('opacity-40');
  } else {
    endInput.disabled = false;
    endInput.classList.remove('opacity-40');
  }
}

// ── Education entries ──────────────────────────────────────────────────────
function addEducationEntry() {
  const list  = document.getElementById('education-list');
  const empty = document.getElementById('edu-empty-state');
  if (empty) empty.remove();

  const tpl   = document.getElementById('edu-entry-template');
  const clone = tpl.content.cloneNode(true);
  list.appendChild(clone);
  list.lastElementChild?.querySelector('input')?.focus();
  scheduleCompletionUpdate();
}

// ── Skills tag input ───────────────────────────────────────────────────────
function handleSkillInput(e) {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault();
    const input = document.getElementById('skill-input');
    const val   = input.value.replace(/,$/, '').trim();
    if (val) {
      addSkillTag(val);
      input.value = '';
      input.placeholder = 'Add more skills...';
    }
  }
}

function addSkillTag(skill) {
  const container = document.getElementById('skills-container');
  const input     = document.getElementById('skill-input');

  const tag = document.createElement('span');
  tag.className = 'skill-tag inline-flex items-center gap-1.5 bg-violet-100 text-violet-700 rounded-full px-3 py-1 text-xs font-medium';
  tag.innerHTML = `<span class="skill-label">${escapeHtml(skill)}</span><button type="button" onclick="removeSkillTag(this)" class="hover:text-violet-900 transition-colors ml-0.5"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg></button>`;
  container.insertBefore(tag, input);
  updateSkillsValue();
  scheduleCompletionUpdate();
}

function removeSkillTag(btn) {
  btn.closest('.skill-tag').remove();
  updateSkillsValue();
  scheduleCompletionUpdate();
}

function updateSkillsValue() {
  const tags = [...document.querySelectorAll('.skill-tag')];
  const vals = tags.map(t => t.querySelector('.skill-label')?.textContent?.trim() ?? '').filter(Boolean);
  const hidden = document.getElementById('skills-value');
  if (hidden) hidden.value = vals.join(',');
}

// ── Language entries ───────────────────────────────────────────────────────
function addLanguageEntry() {
  const list  = document.getElementById('language-list');
  const empty = document.getElementById('lang-empty-state');
  if (empty) empty.remove();

  const tpl   = document.getElementById('lang-entry-template');
  const clone = tpl.content.cloneNode(true);
  list.appendChild(clone);
  list.lastElementChild?.querySelector('input')?.focus();
}

function removeLangEntry(btn) {
  btn.closest('.lang-entry').remove();
}

// ── Photo upload ───────────────────────────────────────────────────────────
async function uploadPhoto(input) {
  const file = input.files?.[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    showToast('Photo must be under 2MB', 'error');
    return;
  }
  const fd = new FormData();
  fd.append('photo', file);
  try {
    const res  = await fetch('/api/v1/profile/photo', {
      method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    });
    const data = await res.json();
    if (data.url) {
      const initialsEl = document.getElementById('profile-photo-initials');
      const imgEl      = document.getElementById('profile-photo-img');
      if (initialsEl) {
        const img     = document.createElement('img');
        img.id        = 'profile-photo-img';
        img.src       = data.url;
        img.alt       = 'Profile photo';
        img.className = 'w-24 h-24 rounded-full object-cover border-4 border-violet-100 shadow-sm';
        initialsEl.replaceWith(img);
      } else if (imgEl) {
        imgEl.src = data.url;
      }
      showToast('Photo updated!', 'success');
    } else {
      throw new Error(data.error ?? 'Upload failed');
    }
  } catch (e) {
    showToast('Photo upload failed.', 'error');
  }
}

// ── CV Upload ──────────────────────────────────────────────────────────────
function handleCVDragOver(e) {
  e.preventDefault();
  e.currentTarget.classList.add('border-violet-500', 'bg-violet-50');
}
function handleCVDragLeave(e) {
  e.currentTarget.classList.remove('border-violet-500', 'bg-violet-50');
}
function handleCVDrop(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('border-violet-500', 'bg-violet-50');
  const file = e.dataTransfer.files?.[0];
  if (file) processCV(file);
}
function uploadCV(input) {
  const file = input.files?.[0];
  if (file) processCV(file);
}

async function processCV(file) {
  const allowedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
  ];
  const allowedExt = /\.(pdf|doc|docx)$/i;
  if (!allowedTypes.includes(file.type) && !allowedExt.test(file.name)) {
    showToast('Only PDF, DOC, or DOCX files are accepted.', 'error');
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    showToast('CV must be under 5MB', 'error');
    return;
  }

  const progress = document.getElementById('cv-upload-progress');
  const bar      = document.getElementById('cv-progress-bar');
  const txt      = document.getElementById('cv-progress-text');
  const dropZone = document.getElementById('cv-drop-zone');
  if (dropZone) dropZone.classList.remove('hidden');
  if (progress) progress.classList.remove('hidden');
  if (bar)      bar.style.width = '30%';
  if (txt)      txt.textContent = 'Uploading...';

  const fd = new FormData();
  fd.append('cv', file);
  try {
    if (bar) bar.style.width = '70%';
    const res = await fetch('/api/v1/cv/upload', {
      method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    });
    if (bar) bar.style.width = '100%';
    const data = await res.json();
    if (res.ok && !data.error) {
      showToast('CV uploaded successfully!', 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      throw new Error(data.error ?? 'Upload failed');
    }
  } catch (e) {
    showToast('CV upload failed: ' + e.message, 'error');
    if (progress) progress.classList.add('hidden');
    if (bar)      bar.style.width = '0%';
  }
}

async function deleteCV() {
  if (!confirm('Remove your uploaded CV? You can upload a new one at any time.')) return;
  try {
    const res = await fetch('/api/v1/cv/delete', {
      method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (res.ok) {
      document.getElementById('cv-existing')?.remove();
      const dz = document.getElementById('cv-drop-zone');
      if (dz) dz.classList.remove('hidden');
      showToast('CV removed.', 'success');
      scheduleCompletionUpdate();
    } else {
      throw new Error('Delete failed');
    }
  } catch (e) {
    showToast('Could not delete CV. Please try again.', 'error');
  }
}

// ── Save Profile ───────────────────────────────────────────────────────────
async function saveProfile() {
  const btn = document.getElementById('save-btn');

  // Collect experience
  const experiences = [...document.querySelectorAll('.exp-entry')].map(entry => ({
    company:     entry.querySelector('[name="exp_company[]"]')?.value?.trim() ?? '',
    title:       entry.querySelector('[name="exp_title[]"]')?.value?.trim() ?? '',
    start_date:  entry.querySelector('[name="exp_start[]"]')?.value ?? '',
    end_date:    entry.querySelector('[name="exp_end[]"]')?.value ?? '',
    current:     entry.querySelector('[name="exp_current[]"]')?.checked ?? false,
    description: entry.querySelector('[name="exp_desc[]"]')?.value?.trim() ?? '',
  }));

  // Collect education
  const educations = [...document.querySelectorAll('.edu-entry')].map(entry => ({
    institution:     entry.querySelector('[name="edu_institution[]"]')?.value?.trim() ?? '',
    degree:          entry.querySelector('[name="edu_degree[]"]')?.value ?? '',
    field:           entry.querySelector('[name="edu_field[]"]')?.value?.trim() ?? '',
    graduation_year: entry.querySelector('[name="edu_grad_year[]"]')?.value ?? '',
  }));

  // Collect languages
  const langs = [...document.querySelectorAll('.lang-entry')]
    .map(row => ({
      language:    row.querySelector('[name="lang_name[]"]')?.value?.trim() ?? '',
      proficiency: row.querySelector('[name="lang_level[]"]')?.value ?? '',
    }))
    .filter(l => l.language);

  // Collect skills
  const skillsRaw = document.getElementById('skills-value')?.value ?? '';
  const skills    = skillsRaw ? skillsRaw.split(',').map(s => s.trim()).filter(Boolean) : [];

  const fullName = document.getElementById('full_name')?.value?.trim() ?? '';
  const nameParts = fullName.split(' ');
  const payload = {
    first_name:           nameParts[0] ?? '',
    last_name:            nameParts.slice(1).join(' '),
    phone:                document.getElementById('phone')?.value?.trim() ?? '',
    location:             document.getElementById('location')?.value?.trim() ?? '',
    linkedin_url:         document.getElementById('linkedin_url')?.value?.trim() ?? '',
    portfolio_url:        document.getElementById('portfolio_url')?.value?.trim() ?? '',
    professional_summary: document.getElementById('professional_summary')?.value?.trim() ?? '',
    availability:         document.querySelector('[name="availability"]:checked')?.value ?? '',
    work_experience:      experiences,
    education:            educations,
    skills:               skills,
    languages:            langs,
  };

  if (!payload.first_name) {
    showToast('Full name is required.', 'error');
    switchTab('personal');
    document.getElementById('full_name')?.focus();
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Saving...';
  }

  try {
    const res  = await fetch('/api/v1/profile?action=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.ok) {
      showToast('Profile saved successfully!', 'success');
      recalcCompletion();
    } else {
      throw new Error(data.message ?? data.error ?? 'Save failed');
    }
  } catch (e) {
    showToast('Error saving: ' + e.message, 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Save Profile';
    }
  }
}

// ── Profile completion recalc ──────────────────────────────────────────────
let completionTimer = null;
function scheduleCompletionUpdate() {
  clearTimeout(completionTimer);
  completionTimer = setTimeout(recalcCompletion, 300);
}

function recalcCompletion() {
  let score = 0;
  if (document.getElementById('full_name')?.value?.trim())            score += 10;
  if (document.getElementById('phone')?.value?.trim())                score += 5;
  if (document.getElementById('location')?.value?.trim())             score += 5;
  if (document.getElementById('professional_summary')?.value?.trim()) score += 15;
  if (document.querySelectorAll('.exp-entry').length > 0)             score += 20;
  if (document.querySelectorAll('.edu-entry').length > 0)             score += 15;
  if (document.querySelectorAll('.skill-tag').length > 0)             score += 15;
  if (document.getElementById('cv-existing'))                         score += 15;

  const bar  = document.getElementById('completion-bar');
  const text = document.getElementById('completion-text');
  if (bar) {
    bar.style.width = score + '%';
    bar.className   = bar.className
      .replace(/bg-(emerald|violet|amber)-\d+/g, '')
      .trim();
    if (score >= 80)      bar.classList.add('bg-emerald-500');
    else if (score >= 50) bar.classList.add('bg-violet-600');
    else                  bar.classList.add('bg-amber-500');
  }
  if (text) text.textContent = score + '% complete';
}

// ── Toast notification ─────────────────────────────────────────────────────
function showToast(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const palette = {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    error:   'bg-red-50 border-red-200 text-red-800',
    info:    'bg-blue-50 border-blue-200 text-blue-800',
  };
  const iconPath = {
    success: 'M5 13l4 4L19 7',
    error:   'M6 18L18 6M6 6l12 12',
    info:    'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  };

  const toast = document.createElement('div');
  toast.className = `pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium ${palette[type] ?? palette.info}`;
  toast.style.animation = 'slideDown 0.2s ease';
  toast.innerHTML = `
    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${iconPath[type] ?? iconPath.info}"/>
    </svg>
    <span class="flex-1">${escapeHtml(msg)}</span>
    <button onclick="this.closest('div').remove()" class="flex-shrink-0 opacity-50 hover:opacity-100 transition-opacity -mt-0.5">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>`;
  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4500);
}

// ── Utils ──────────────────────────────────────────────────────────────────
function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// ── Init: activate tab from URL hash ──────────────────────────────────────
(function() {
  const hash  = window.location.hash.replace('#', '');
  const valid = ['personal', 'experience', 'education', 'skills'];
  if (valid.includes(hash)) switchTab(hash);
})();
</script>
