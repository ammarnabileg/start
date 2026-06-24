<?php
// Candidate Profile Editor — rendered inside candidate.php layout
// Variables: $user
$db          = Database::getInstance();
$candidateId = $user['id'] ?? 0;

$candidate = $db->fetchRow("SELECT * FROM candidates WHERE user_id = ?", [$candidateId])
             ?? $db->fetchRow("SELECT * FROM candidates WHERE id = ?", [$candidateId])
             ?? [];

$experiences = $db->fetchAll(
    "SELECT * FROM candidate_experiences WHERE candidate_id = ? ORDER BY start_date DESC",
    [$candidateId]
) ?: [];

$education = $db->fetchAll(
    "SELECT * FROM candidate_education WHERE candidate_id = ? ORDER BY end_date DESC",
    [$candidateId]
) ?: [];

$skills = $db->fetchAll(
    "SELECT * FROM candidate_skills WHERE candidate_id = ? ORDER BY level DESC",
    [$candidateId]
) ?: [];

$languages = $db->fetchAll(
    "SELECT * FROM candidate_languages WHERE candidate_id = ? ORDER BY proficiency DESC",
    [$candidateId]
) ?: [];

$availabilityOptions = [
    'immediate'    => 'Immediately available',
    '1_month'      => 'Available in 1 month',
    '2_months'     => 'Available in 2 months',
    '3_months'     => 'Available in 3 months',
    'negotiable'   => 'Negotiable',
];

$skillLevels = ['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced','expert'=>'Expert'];
$profLevels  = ['basic'=>'Basic','conversational'=>'Conversational','fluent'=>'Fluent','native'=>'Native'];
?>

<!-- Toast notification -->
<div id="toast" class="fixed top-20 right-4 z-50 hidden">
  <div id="toast-inner" class="bg-emerald-600 text-white text-sm px-5 py-3 rounded-xl shadow-xl flex items-center gap-3">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <span id="toast-msg">Saved successfully</span>
  </div>
</div>

<!-- Page header -->
<div class="mb-6 flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
    <p class="text-gray-500 text-sm mt-0.5">Keep your profile up to date to improve your match score</p>
  </div>
</div>

<div class="space-y-6">

<!-- ═══════════════════════════ SECTION 1: PERSONAL INFO ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-personal">
  <div class="flex items-center justify-between mb-5">
    <h2 class="text-base font-semibold text-gray-900">Personal Information</h2>
    <button onclick="saveSection('personal')"
      class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      Save
    </button>
  </div>

  <!-- Photo upload -->
  <div class="flex items-center gap-5 mb-6">
    <div class="relative flex-shrink-0">
      <div id="avatar-preview" class="w-20 h-20 rounded-2xl bg-violet-100 flex items-center justify-center overflow-hidden border-2 border-gray-100">
        <?php if (!empty($candidate['avatar'])): ?>
        <img src="<?= htmlspecialchars($candidate['avatar']) ?>" class="w-full h-full object-cover" id="avatar-img" alt="Profile photo">
        <?php else: ?>
        <span class="text-2xl font-bold text-violet-600" id="avatar-initials">
          <?= strtoupper(substr($candidate['full_name'] ?? $user['full_name'] ?? 'C', 0, 1)) ?>
        </span>
        <?php endif; ?>
      </div>
      <label for="avatar-input" class="absolute -bottom-1 -right-1 w-7 h-7 bg-violet-600 hover:bg-violet-700 rounded-full flex items-center justify-center cursor-pointer shadow-md transition-colors">
        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </label>
      <input type="file" id="avatar-input" accept="image/*" class="hidden" onchange="previewAvatar(this)">
    </div>
    <div>
      <p class="text-sm font-medium text-gray-900">Profile Photo</p>
      <p class="text-xs text-gray-400 mt-0.5">JPG or PNG. Recommended: 400×400px</p>
      <label for="avatar-input" class="mt-2 inline-block text-xs text-violet-600 hover:text-violet-800 font-medium cursor-pointer">Upload photo</label>
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-xs font-medium text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
      <input type="text" id="personal-full_name" value="<?= htmlspecialchars($candidate['full_name'] ?? $user['full_name'] ?? '') ?>"
        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:bg-white transition-colors">
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-700 mb-1.5">Email Address</label>
      <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly
        class="w-full px-3.5 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm text-gray-500 cursor-not-allowed">
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-700 mb-1.5">Phone Number</label>
      <input type="tel" id="personal-phone" value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>"
        placeholder="+1 (555) 000-0000"
        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:bg-white transition-colors">
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-700 mb-1.5">Location</label>
      <input type="text" id="personal-location" value="<?= htmlspecialchars($candidate['location'] ?? '') ?>"
        placeholder="City, Country"
        class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:bg-white transition-colors">
    </div>
    <div class="sm:col-span-2">
      <label class="block text-xs font-medium text-gray-700 mb-1.5">LinkedIn URL</label>
      <div class="relative">
        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
        </span>
        <input type="url" id="personal-linkedin_url" value="<?= htmlspecialchars($candidate['linkedin_url'] ?? '') ?>"
          placeholder="https://linkedin.com/in/yourprofile"
          class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:bg-white transition-colors">
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════ SECTION 2: PROFESSIONAL SUMMARY ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-bio">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-base font-semibold text-gray-900">Professional Summary</h2>
    <div class="flex items-center gap-2">
      <button onclick="rewriteBio()" id="rewrite-btn"
        class="flex items-center gap-1.5 border border-violet-300 text-violet-700 hover:bg-violet-50 px-3 py-2 rounded-full text-xs font-medium transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
        AI Rewrite
      </button>
      <button onclick="saveSection('bio')"
        class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
        Save
      </button>
    </div>
  </div>
  <textarea id="bio-bio" rows="5"
    placeholder="Write a compelling summary of your professional background, key skills, and career goals. The AI can help you craft this."
    class="w-full px-3.5 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:bg-white transition-colors resize-none leading-relaxed"
  ><?= htmlspecialchars($candidate['bio'] ?? '') ?></textarea>
  <p class="text-xs text-gray-400 mt-2">Tip: A great summary is 3–5 sentences highlighting your expertise, achievements, and what you bring to a role.</p>
</div>

<!-- ═══════════════════════════ SECTION 3: WORK EXPERIENCE ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-experience">
  <div class="flex items-center justify-between mb-5">
    <h2 class="text-base font-semibold text-gray-900">Work Experience</h2>
    <button onclick="addExperience()"
      class="flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Add
    </button>
  </div>

  <div id="experience-list" class="space-y-4">
    <?php if (empty($experiences)): ?>
    <div id="no-experience" class="text-center py-8 text-gray-400">
      <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01"/></svg>
      <p class="text-sm">No work experience added yet</p>
      <button onclick="addExperience()" class="mt-2 text-xs text-violet-600 hover:text-violet-800 font-medium">Add your first job</button>
    </div>
    <?php else: ?>
    <?php foreach ($experiences as $exp): ?>
    <div class="border border-gray-100 rounded-xl p-4 hover:border-violet-200 transition-colors group" data-exp-id="<?= (int)($exp['id'] ?? 0) ?>">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
          <div class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($exp['title'] ?? '') ?></div>
          <div class="text-sm text-gray-600 mt-0.5"><?= htmlspecialchars($exp['company'] ?? '') ?></div>
          <div class="text-xs text-gray-400 mt-1">
            <?= !empty($exp['start_date']) ? date('M Y', strtotime($exp['start_date'])) : '' ?>
            <?= !empty($exp['end_date']) ? ' – ' . date('M Y', strtotime($exp['end_date'])) : ($exp['current'] ?? false ? ' – Present' : '') ?>
          </div>
          <?php if (!empty($exp['description'])): ?>
          <p class="text-xs text-gray-500 mt-2 leading-relaxed"><?= htmlspecialchars($exp['description']) ?></p>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button onclick="editExperience(<?= (int)($exp['id'] ?? 0) ?>, this)"
            class="w-7 h-7 bg-gray-100 hover:bg-violet-100 hover:text-violet-700 rounded-lg flex items-center justify-center transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <button onclick="deleteExperience(<?= (int)($exp['id'] ?? 0) ?>, this)"
            class="w-7 h-7 bg-gray-100 hover:bg-red-100 hover:text-red-600 rounded-lg flex items-center justify-center transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════ SECTION 4: EDUCATION ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-education">
  <div class="flex items-center justify-between mb-5">
    <h2 class="text-base font-semibold text-gray-900">Education</h2>
    <button onclick="addEducation()"
      class="flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Add
    </button>
  </div>
  <div id="education-list" class="space-y-4">
    <?php if (empty($education)): ?>
    <div id="no-education" class="text-center py-8 text-gray-400">
      <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
      <p class="text-sm">No education added yet</p>
    </div>
    <?php else: ?>
    <?php foreach ($education as $edu): ?>
    <div class="border border-gray-100 rounded-xl p-4 hover:border-violet-200 transition-colors group" data-edu-id="<?= (int)($edu['id'] ?? 0) ?>">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
          <div class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($edu['degree'] ?? '') ?> in <?= htmlspecialchars($edu['field'] ?? '') ?></div>
          <div class="text-sm text-gray-600 mt-0.5"><?= htmlspecialchars($edu['institution'] ?? '') ?></div>
          <div class="text-xs text-gray-400 mt-1">
            <?= !empty($edu['start_year']) ? $edu['start_year'] : '' ?>
            <?= !empty($edu['end_year']) ? ' – ' . $edu['end_year'] : '' ?>
          </div>
        </div>
        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button onclick="editEducation(<?= (int)($edu['id'] ?? 0) ?>, this)" class="w-7 h-7 bg-gray-100 hover:bg-violet-100 hover:text-violet-700 rounded-lg flex items-center justify-center transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <button onclick="deleteEducation(<?= (int)($edu['id'] ?? 0) ?>, this)" class="w-7 h-7 bg-gray-100 hover:bg-red-100 hover:text-red-600 rounded-lg flex items-center justify-center transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════ SECTION 5: SKILLS ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-skills">
  <div class="flex items-center justify-between mb-5">
    <h2 class="text-base font-semibold text-gray-900">Skills</h2>
    <button onclick="saveSection('skills')"
      class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      Save
    </button>
  </div>

  <!-- Skill tags display -->
  <div id="skills-container" class="flex flex-wrap gap-2 mb-4">
    <?php foreach ($skills as $skill): ?>
    <div class="skill-tag flex items-center gap-2 bg-violet-50 border border-violet-200 rounded-full pl-3 pr-1 py-1"
      data-skill="<?= htmlspecialchars($skill['name'] ?? '') ?>" data-level="<?= htmlspecialchars($skill['level'] ?? 'intermediate') ?>">
      <span class="text-sm text-violet-700 font-medium"><?= htmlspecialchars($skill['name'] ?? '') ?></span>
      <span class="text-[10px] text-violet-400"><?= htmlspecialchars(ucfirst($skill['level'] ?? '')) ?></span>
      <button onclick="removeSkill(this)" class="w-5 h-5 bg-violet-200 hover:bg-red-200 hover:text-red-600 rounded-full flex items-center justify-center transition-colors ml-0.5">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Add skill input -->
  <div class="flex gap-2 flex-wrap sm:flex-nowrap">
    <input type="text" id="skill-input" placeholder="Add a skill (e.g. Python, Project Management)"
      class="flex-1 min-w-40 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:bg-white transition-colors"
      onkeydown="if(event.key==='Enter'){event.preventDefault();addSkillTag()}">
    <select id="skill-level-select"
      class="px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:border-violet-400">
      <?php foreach ($skillLevels as $v => $l): ?>
      <option value="<?= $v ?>" <?= $v === 'intermediate' ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <button onclick="addSkillTag()"
      class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
      Add
    </button>
  </div>
</div>

<!-- ═══════════════════════════ SECTION 6: CV UPLOAD ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-cv">
  <h2 class="text-base font-semibold text-gray-900 mb-4">CV / Resume</h2>

  <?php if (!empty($candidate['cv_path'])): ?>
  <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-4">
    <svg class="w-8 h-8 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-gray-900">CV uploaded</p>
      <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars(basename($candidate['cv_path'])) ?></p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= htmlspecialchars($candidate['cv_path']) ?>" target="_blank"
        class="text-xs text-emerald-700 hover:text-emerald-900 font-medium">View</a>
      <span class="text-gray-300">|</span>
      <button onclick="document.getElementById('cv-drop-zone').classList.remove('hidden')"
        class="text-xs text-violet-600 hover:text-violet-800 font-medium">Replace</button>
    </div>
  </div>
  <?php endif; ?>

  <div id="cv-drop-zone" class="<?= !empty($candidate['cv_path']) ? 'hidden' : '' ?> border-2 border-dashed border-gray-200 hover:border-violet-400 rounded-xl p-8 text-center cursor-pointer transition-colors"
    ondragover="event.preventDefault();this.classList.add('border-violet-500','bg-violet-50')"
    ondragleave="this.classList.remove('border-violet-500','bg-violet-50')"
    ondrop="handleCVDrop(event)"
    onclick="document.getElementById('cv-file-input').click()">
    <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
    <p class="text-sm font-medium text-gray-700">Drop your CV here or <span class="text-violet-600">browse</span></p>
    <p class="text-xs text-gray-400 mt-1">PDF up to 10MB</p>
    <input type="file" id="cv-file-input" accept=".pdf,.doc,.docx" class="hidden" onchange="uploadCV(this)">
  </div>
  <div id="cv-upload-progress" class="hidden mt-3">
    <div class="flex items-center gap-3 text-sm text-gray-600">
      <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
        <div id="cv-progress-bar" class="h-full bg-violet-500 rounded-full transition-all" style="width:0%"></div>
      </div>
      <span id="cv-progress-text">Uploading...</span>
    </div>
  </div>
</div>

<!-- ═══════════════════════════ SECTION 7: LANGUAGES ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-languages">
  <div class="flex items-center justify-between mb-5">
    <h2 class="text-base font-semibold text-gray-900">Languages</h2>
    <button onclick="saveSection('languages')"
      class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      Save
    </button>
  </div>

  <div id="languages-list" class="space-y-3 mb-4">
    <?php foreach ($languages as $lang): ?>
    <div class="lang-entry flex items-center gap-3 bg-gray-50 rounded-xl p-3">
      <input type="text" class="lang-name flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-400"
        value="<?= htmlspecialchars($lang['language'] ?? '') ?>" placeholder="Language">
      <select class="lang-level bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-400">
        <?php foreach ($profLevels as $v => $l): ?>
        <option value="<?= $v ?>" <?= ($lang['proficiency'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <button onclick="this.closest('.lang-entry').remove()" class="w-8 h-8 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg flex items-center justify-center transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </button>
    </div>
    <?php endforeach; ?>
  </div>
  <button onclick="addLanguageEntry()" class="text-sm text-violet-600 hover:text-violet-800 font-medium flex items-center gap-1.5">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Add Language
  </button>
</div>

<!-- ═══════════════════════════ SECTION 8: AVAILABILITY ═══════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6" id="section-availability">
  <div class="flex items-center justify-between mb-5">
    <h2 class="text-base font-semibold text-gray-900">Availability</h2>
    <button onclick="saveSection('availability')"
      class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      Save
    </button>
  </div>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
    <?php foreach ($availabilityOptions as $value => $label): ?>
    <label class="relative cursor-pointer">
      <input type="radio" name="availability" value="<?= $value ?>" id="avail-<?= $value ?>"
        <?= ($candidate['availability'] ?? 'negotiable') === $value ? 'checked' : '' ?>
        class="peer sr-only">
      <div class="border-2 border-gray-200 peer-checked:border-violet-500 peer-checked:bg-violet-50 rounded-xl p-4 transition-all">
        <div class="flex items-center gap-3">
          <div class="w-4 h-4 rounded-full border-2 border-gray-300 peer-checked:border-violet-500 flex items-center justify-center flex-shrink-0">
            <div class="w-2 h-2 rounded-full bg-violet-600 hidden peer-checked:block"></div>
          </div>
          <span class="text-sm font-medium text-gray-700 peer-checked:text-violet-700"><?= $label ?></span>
        </div>
      </div>
    </label>
    <?php endforeach; ?>
  </div>
</div>

</div><!-- /space-y-6 -->

<!-- ═══════════════════════════ MODALS ═══════════════════════════ -->
<!-- Experience Modal -->
<div id="experience-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900" id="exp-modal-title">Add Work Experience</h3>
      <button onclick="closeModal('experience-modal')" class="text-gray-400 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-6 py-5 space-y-4">
      <input type="hidden" id="exp-id" value="">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Job Title <span class="text-red-500">*</span></label>
          <input type="text" id="exp-title" placeholder="e.g. Senior Software Engineer"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Company <span class="text-red-500">*</span></label>
          <input type="text" id="exp-company" placeholder="e.g. Acme Corporation"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Start Date</label>
          <input type="month" id="exp-start"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">End Date</label>
          <input type="month" id="exp-end"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
          <label class="flex items-center gap-2 mt-2 cursor-pointer">
            <input type="checkbox" id="exp-current" class="rounded text-violet-600">
            <span class="text-xs text-gray-600">I currently work here</span>
          </label>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Description</label>
          <textarea id="exp-description" rows="3" placeholder="Describe your responsibilities and achievements..."
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm resize-none focus:outline-none focus:border-violet-400 focus:bg-white"></textarea>
        </div>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
      <button onclick="closeModal('experience-modal')" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 font-medium">Cancel</button>
      <button onclick="saveExperience()" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-semibold transition-colors">Save Experience</button>
    </div>
  </div>
</div>

<!-- Education Modal -->
<div id="education-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900" id="edu-modal-title">Add Education</h3>
      <button onclick="closeModal('education-modal')" class="text-gray-400 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-6 py-5 space-y-4">
      <input type="hidden" id="edu-id" value="">
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1.5">Institution <span class="text-red-500">*</span></label>
        <input type="text" id="edu-institution" placeholder="e.g. MIT"
          class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Degree</label>
          <input type="text" id="edu-degree" placeholder="e.g. Bachelor's"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Field of Study</label>
          <input type="text" id="edu-field" placeholder="e.g. Computer Science"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Start Year</label>
          <input type="number" id="edu-start" min="1950" max="2030" placeholder="2018"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">End Year</label>
          <input type="number" id="edu-end" min="1950" max="2030" placeholder="2022"
            class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
      <button onclick="closeModal('education-modal')" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 font-medium">Cancel</button>
      <button onclick="saveEducation()" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-semibold transition-colors">Save Education</button>
    </div>
  </div>
</div>

<script>
const CANDIDATE_ID = <?= (int)$candidateId ?>;

// ── Toast ─────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  const inner = document.getElementById('toast-inner');
  document.getElementById('toast-msg').textContent = msg;
  inner.className = `${type === 'error' ? 'bg-red-600' : 'bg-emerald-600'} text-white text-sm px-5 py-3 rounded-xl shadow-xl flex items-center gap-3`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3500);
}

// ── Modal helpers ─────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

// ── AJAX save helper ──────────────────────────────────────────────────
async function ajaxPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data)
  });
  return await res.json();
}

// ── SECTION SAVE ─────────────────────────────────────────────────────
async function saveSection(section) {
  let data = { section };
  switch (section) {
    case 'personal':
      data.full_name   = document.getElementById('personal-full_name')?.value;
      data.phone       = document.getElementById('personal-phone')?.value;
      data.location    = document.getElementById('personal-location')?.value;
      data.linkedin_url = document.getElementById('personal-linkedin_url')?.value;
      // Avatar upload handled separately
      break;
    case 'bio':
      data.bio = document.getElementById('bio-bio')?.value;
      break;
    case 'skills':
      data.skills = Array.from(document.querySelectorAll('.skill-tag')).map(el => ({
        name: el.dataset.skill, level: el.dataset.level
      }));
      break;
    case 'languages':
      data.languages = Array.from(document.querySelectorAll('.lang-entry')).map(el => ({
        language: el.querySelector('.lang-name')?.value,
        proficiency: el.querySelector('.lang-level')?.value
      })).filter(l => l.language);
      break;
    case 'availability':
      const av = document.querySelector('input[name="availability"]:checked');
      data.availability = av ? av.value : 'negotiable';
      break;
  }
  try {
    const res = await ajaxPost('/api/v1/candidate/profile', data);
    showToast(res.message || 'Saved successfully');
  } catch(e) {
    showToast('Failed to save. Please try again.', 'error');
  }
}

// ── AVATAR PREVIEW ────────────────────────────────────────────────────
function previewAvatar(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    const preview = document.getElementById('avatar-preview');
    preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover" alt="Avatar">`;
    // Upload
    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('section', 'avatar');
    fetch('/api/v1/candidate/profile', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json()).then(d => showToast(d.message || 'Photo updated'))
      .catch(() => showToast('Upload failed', 'error'));
  };
  reader.readAsDataURL(file);
}

// ── AI BIO REWRITE ─────────────────────────────────────────────────────
async function rewriteBio() {
  const btn = document.getElementById('rewrite-btn');
  const ta  = document.getElementById('bio-bio');
  btn.disabled = true;
  btn.textContent = 'Rewriting...';
  try {
    const res = await ajaxPost('/api/v1/ai?action=rewrite-bio', { bio: ta?.value });
    if (res.result) {
      ta.value = res.result;
      showToast('Bio rewritten by AI. Review and save.');
    }
  } catch(e) {
    showToast('AI rewrite failed. Try again.', 'error');
  }
  btn.disabled = false;
  btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> AI Rewrite';
}

// ── SKILLS ─────────────────────────────────────────────────────────────
function addSkillTag() {
  const input = document.getElementById('skill-input');
  const level = document.getElementById('skill-level-select')?.value || 'intermediate';
  const name  = input?.value.trim();
  if (!name) return;
  // Check duplicate
  const existing = Array.from(document.querySelectorAll('.skill-tag')).find(el => el.dataset.skill.toLowerCase() === name.toLowerCase());
  if (existing) { showToast('Skill already added', 'error'); return; }

  const div = document.createElement('div');
  div.className = 'skill-tag flex items-center gap-2 bg-violet-50 border border-violet-200 rounded-full pl-3 pr-1 py-1';
  div.dataset.skill = name;
  div.dataset.level = level;
  div.innerHTML = `<span class="text-sm text-violet-700 font-medium">${escHtml(name)}</span>
    <span class="text-[10px] text-violet-400">${escHtml(level.charAt(0).toUpperCase() + level.slice(1))}</span>
    <button onclick="removeSkill(this)" class="w-5 h-5 bg-violet-200 hover:bg-red-200 hover:text-red-600 rounded-full flex items-center justify-center transition-colors ml-0.5">
      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>`;
  document.getElementById('skills-container').appendChild(div);
  input.value = '';
}
function removeSkill(btn) { btn.closest('.skill-tag').remove(); }

// ── EXPERIENCE ─────────────────────────────────────────────────────────
function addExperience() {
  document.getElementById('exp-id').value = '';
  document.getElementById('exp-title').value = '';
  document.getElementById('exp-company').value = '';
  document.getElementById('exp-start').value = '';
  document.getElementById('exp-end').value = '';
  document.getElementById('exp-current').checked = false;
  document.getElementById('exp-description').value = '';
  document.getElementById('exp-modal-title').textContent = 'Add Work Experience';
  openModal('experience-modal');
}

async function saveExperience() {
  const data = {
    id:          document.getElementById('exp-id').value,
    title:       document.getElementById('exp-title').value,
    company:     document.getElementById('exp-company').value,
    start_date:  document.getElementById('exp-start').value,
    end_date:    document.getElementById('exp-current').checked ? null : document.getElementById('exp-end').value,
    current:     document.getElementById('exp-current').checked,
    description: document.getElementById('exp-description').value,
  };
  if (!data.title || !data.company) { showToast('Title and company are required', 'error'); return; }
  try {
    const res = await ajaxPost('/api/v1/candidate/experience', data);
    showToast(res.message || 'Experience saved');
    closeModal('experience-modal');
    setTimeout(() => location.reload(), 800);
  } catch(e) { showToast('Save failed', 'error'); }
}

function editExperience(id, btn) {
  const card = btn.closest('[data-exp-id]');
  document.getElementById('exp-id').value = id;
  document.getElementById('exp-title').value = card.querySelector('.font-semibold')?.textContent || '';
  document.getElementById('exp-company').value = card.querySelectorAll('.text-sm')[1]?.textContent || '';
  document.getElementById('exp-modal-title').textContent = 'Edit Work Experience';
  openModal('experience-modal');
}

async function deleteExperience(id, btn) {
  if (!confirm('Remove this experience?')) return;
  try {
    const res = await ajaxPost('/api/v1/candidate/experience/delete', { id });
    btn.closest('[data-exp-id]').remove();
    showToast('Experience removed');
  } catch(e) { showToast('Delete failed', 'error'); }
}

// ── EDUCATION ─────────────────────────────────────────────────────────
function addEducation() {
  ['edu-id','edu-institution','edu-degree','edu-field','edu-start','edu-end'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('edu-modal-title').textContent = 'Add Education';
  openModal('education-modal');
}

async function saveEducation() {
  const data = {
    id:          document.getElementById('edu-id').value,
    institution: document.getElementById('edu-institution').value,
    degree:      document.getElementById('edu-degree').value,
    field:       document.getElementById('edu-field').value,
    start_year:  document.getElementById('edu-start').value,
    end_year:    document.getElementById('edu-end').value,
  };
  if (!data.institution) { showToast('Institution is required', 'error'); return; }
  try {
    const res = await ajaxPost('/api/v1/candidate/education', data);
    showToast(res.message || 'Education saved');
    closeModal('education-modal');
    setTimeout(() => location.reload(), 800);
  } catch(e) { showToast('Save failed', 'error'); }
}

function editEducation(id, btn) {
  document.getElementById('edu-id').value = id;
  document.getElementById('edu-modal-title').textContent = 'Edit Education';
  openModal('education-modal');
}

async function deleteEducation(id, btn) {
  if (!confirm('Remove this education entry?')) return;
  try {
    await ajaxPost('/api/v1/candidate/education/delete', { id });
    btn.closest('[data-edu-id]').remove();
    showToast('Education removed');
  } catch(e) { showToast('Delete failed', 'error'); }
}

// ── LANGUAGES ─────────────────────────────────────────────────────────
function addLanguageEntry() {
  const div = document.createElement('div');
  div.className = 'lang-entry flex items-center gap-3 bg-gray-50 rounded-xl p-3';
  div.innerHTML = `
    <input type="text" class="lang-name flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-400" placeholder="Language">
    <select class="lang-level bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-violet-400">
      <option value="basic">Basic</option>
      <option value="conversational">Conversational</option>
      <option value="fluent" selected>Fluent</option>
      <option value="native">Native</option>
    </select>
    <button onclick="this.closest('.lang-entry').remove()" class="w-8 h-8 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg flex items-center justify-center transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
    </button>`;
  document.getElementById('languages-list').appendChild(div);
}

// ── CV UPLOAD ─────────────────────────────────────────────────────────
function handleCVDrop(event) {
  event.preventDefault();
  const file = event.dataTransfer.files[0];
  if (file) uploadCVFile(file);
}

function uploadCV(input) {
  const file = input.files[0];
  if (file) uploadCVFile(file);
}

async function uploadCVFile(file) {
  if (file.size > 10 * 1024 * 1024) { showToast('File too large (max 10MB)', 'error'); return; }
  const progress   = document.getElementById('cv-upload-progress');
  const bar        = document.getElementById('cv-progress-bar');
  const progressTx = document.getElementById('cv-progress-text');
  progress.classList.remove('hidden');
  bar.style.width = '0%';

  const fd = new FormData();
  fd.append('cv', file);
  const xhr = new XMLHttpRequest();
  xhr.upload.onprogress = (e) => {
    if (e.lengthComputable) {
      const pct = Math.round((e.loaded / e.total) * 100);
      bar.style.width = pct + '%';
      progressTx.textContent = pct + '%';
    }
  };
  xhr.onload = () => {
    progress.classList.add('hidden');
    const res = JSON.parse(xhr.responseText);
    if (res.success) { showToast('CV uploaded successfully'); setTimeout(() => location.reload(), 1000); }
    else showToast(res.message || 'Upload failed', 'error');
  };
  xhr.onerror = () => { progress.classList.add('hidden'); showToast('Upload failed', 'error'); };
  xhr.open('POST', '/api/v1/candidate/cv');
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.send(fd);
}

// ── RADIO button visual ───────────────────────────────────────────────
document.querySelectorAll('input[name="availability"]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('input[name="availability"]').forEach(r => {
      const div = r.closest('label').querySelector('.border-2');
      const dot = div.querySelector('.rounded-full > .rounded-full');
      if (r.checked) {
        div.classList.add('border-violet-500','bg-violet-50');
        if (dot) dot.classList.remove('hidden');
      } else {
        div.classList.remove('border-violet-500','bg-violet-50');
        if (dot) dot.classList.add('hidden');
      }
    });
  });
});

function escHtml(s) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(s));
  return d.innerHTML;
}
</script>
