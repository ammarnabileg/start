<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6 max-w-3xl">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">My Profile</h2>
        <p class="text-gray-500 mt-1">Keep your profile up to date to improve your chances.</p>
    </div>

    <!-- Personal Info -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-5">Personal Information</h3>
        <form id="profile-form" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                    <input type="text" name="first_name" id="first_name"
                        class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                    <input type="text" name="last_name" id="last_name"
                        class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                <input type="email" name="email" id="email"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                <input type="tel" name="phone" id="phone"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="+1 (555) 000-0000">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">LinkedIn URL</label>
                <input type="url" name="linkedin_url" id="linkedin_url"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="https://linkedin.com/in/yourprofile">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Portfolio / Website</label>
                <input type="url" name="portfolio_url" id="portfolio_url"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="https://yourportfolio.com">
            </div>
        </form>
    </div>

    <!-- CV Upload -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Curriculum Vitae (CV)</h3>
        <p class="text-sm text-gray-500 mb-5">PDF or DOC format, max 5MB.</p>

        <div id="cv-current" class="hidden mb-4 flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl">
            <svg class="w-8 h-8 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-green-800" id="cv-filename">cv.pdf</p>
                <p class="text-xs text-green-600">Uploaded successfully</p>
            </div>
            <button onclick="removeCv()" class="text-red-500 hover:text-red-600 text-xs font-medium">Remove</button>
        </div>

        <label id="cv-drop-zone"
            class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-indigo-300 hover:bg-indigo-50/30 transition-colors">
            <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <p class="text-sm font-medium text-gray-600">Click to upload or drag & drop</p>
            <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX — max 5MB</p>
            <input type="file" id="cv-file" accept=".pdf,.doc,.docx" class="hidden" onchange="handleCvSelect(this)">
        </label>
        <p id="cv-error" class="text-xs text-red-500 mt-2 hidden"></p>
    </div>

    <!-- Skills -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Skills</h3>
        <div id="skills-container" class="flex flex-wrap gap-2 mb-3"></div>
        <div class="flex gap-2">
            <input type="text" id="skill-input" placeholder="Add a skill…"
                class="flex-1 px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                onkeydown="if(event.key==='Enter'){event.preventDefault();addSkill();}">
            <button onclick="addSkill()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">Add</button>
        </div>
    </div>

    <!-- Work Experience -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Work Experience</h3>
        <div id="experience-list" class="space-y-3 mb-4"></div>
        <button onclick="addExperienceBlock()" class="flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Experience
        </button>
    </div>

    <!-- Save Button -->
    <div class="flex items-center gap-4">
        <button onclick="saveProfile()" id="save-btn"
            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
            Save Profile
        </button>
        <p id="save-status" class="text-sm text-gray-500 hidden"></p>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
let skills = [];
let cvFile = null;
let profileData = {};

async function loadProfile() {
    try {
        const res = await fetch('/api/v1/candidate/profile', {headers:{'X-CSRF-Token':CSRF}});
        const json = await res.json();
        if (!json.ok) return;
        const d = json.data;
        profileData = d;

        if (d.phone) document.getElementById('phone').value = d.phone;
        if (d.linkedin_url) document.getElementById('linkedin_url').value = d.linkedin_url;
        if (d.portfolio_url) document.getElementById('portfolio_url').value = d.portfolio_url;

        skills = d.skills || [];
        renderSkills();

        if (d.experiences) renderExperiences(d.experiences);
        else addExperienceBlock();

        if (d.cv_filename) {
            document.getElementById('cv-filename').textContent = d.cv_filename;
            document.getElementById('cv-current').classList.remove('hidden');
        }
    } catch(e) {
        console.error(e);
        addExperienceBlock();
    }
}

function renderSkills() {
    document.getElementById('skills-container').innerHTML = skills.map((s,i) =>
        `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-100 text-indigo-800 text-sm rounded-full font-medium">
            ${escHtml(s)}
            <button onclick="removeSkill(${i})" class="w-4 h-4 text-indigo-400 hover:text-indigo-600 flex items-center justify-center leading-none text-lg">&times;</button>
        </span>`
    ).join('');
}

function addSkill() {
    const inp = document.getElementById('skill-input');
    const v = inp.value.trim();
    if (v && !skills.includes(v)) { skills.push(v); renderSkills(); }
    inp.value = '';
}

function removeSkill(i) { skills.splice(i, 1); renderSkills(); }

function addExperienceBlock(data = {}) {
    const list = document.getElementById('experience-list');
    const id = Date.now();
    const div = document.createElement('div');
    div.className = 'p-4 border border-gray-200 rounded-xl space-y-3 experience-block';
    div.dataset.id = id;
    div.innerHTML = `
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input type="text" placeholder="Job Title" value="${escHtml(data.title||'')}"
                class="exp-title px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="text" placeholder="Company" value="${escHtml(data.company||'')}"
                class="exp-company px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="text" placeholder="Start Date (e.g. Jan 2022)" value="${escHtml(data.start_date||'')}"
                class="exp-start px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="text" placeholder="End Date (or 'Present')" value="${escHtml(data.end_date||'')}"
                class="exp-end px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <textarea placeholder="Describe your responsibilities and achievements…" rows="3"
            class="exp-desc w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none">${escHtml(data.description||'')}</textarea>
        <button onclick="this.closest('.experience-block').remove()" class="text-xs text-red-500 hover:text-red-600">Remove</button>
    `;
    list.appendChild(div);
}

function renderExperiences(exps) {
    exps.forEach(e => addExperienceBlock(e));
}

function handleCvSelect(input) {
    const file = input.files[0];
    const errEl = document.getElementById('cv-error');
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { errEl.textContent = 'File exceeds 5MB limit.'; errEl.classList.remove('hidden'); return; }
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['pdf','doc','docx'].includes(ext)) { errEl.textContent = 'Invalid file type. Use PDF or DOC.'; errEl.classList.remove('hidden'); return; }
    errEl.classList.add('hidden');
    cvFile = file;
    document.getElementById('cv-filename').textContent = file.name;
    document.getElementById('cv-current').classList.remove('hidden');
}

function removeCv() {
    cvFile = null;
    document.getElementById('cv-current').classList.add('hidden');
    document.getElementById('cv-file').value = '';
}

async function saveProfile() {
    const btn = document.getElementById('save-btn');
    const status = document.getElementById('save-status');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const experiences = [...document.querySelectorAll('.experience-block')].map(el => ({
        title:       el.querySelector('.exp-title').value,
        company:     el.querySelector('.exp-company').value,
        start_date:  el.querySelector('.exp-start').value,
        end_date:    el.querySelector('.exp-end').value,
        description: el.querySelector('.exp-desc').value,
    }));

    const formData = new FormData();
    formData.append('first_name',    document.getElementById('first_name').value);
    formData.append('last_name',     document.getElementById('last_name').value);
    formData.append('email',         document.getElementById('email').value);
    formData.append('phone',         document.getElementById('phone').value);
    formData.append('linkedin_url',  document.getElementById('linkedin_url').value);
    formData.append('portfolio_url', document.getElementById('portfolio_url').value);
    formData.append('skills',        JSON.stringify(skills));
    formData.append('experiences',   JSON.stringify(experiences));
    if (cvFile) formData.append('cv', cvFile);

    try {
        const res = await fetch('/api/v1/candidate/profile', {
            method: 'POST',
            headers: {'X-CSRF-Token': CSRF},
            body: formData,
        });
        const json = await res.json();
        if (json.ok) {
            status.textContent = 'Profile saved successfully!';
            status.className = 'text-sm text-green-600';
        } else {
            status.textContent = json.message || 'Failed to save profile.';
            status.className = 'text-sm text-red-500';
        }
        status.classList.remove('hidden');
        setTimeout(() => status.classList.add('hidden'), 3000);
    } catch(e) {
        status.textContent = 'Network error. Please try again.';
        status.className = 'text-sm text-red-500';
        status.classList.remove('hidden');
    }

    btn.disabled = false;
    btn.textContent = 'Save Profile';
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

const dropZone = document.getElementById('cv-drop-zone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-indigo-400','bg-indigo-50'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-indigo-400','bg-indigo-50'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('border-indigo-400','bg-indigo-50');
    const dt = e.dataTransfer;
    if (dt.files.length) { document.getElementById('cv-file').files = dt.files; handleCvSelect(document.getElementById('cv-file')); }
});

loadProfile();
</script>
