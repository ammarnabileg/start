<meta name="csrf" content="<?= $req->csrf() ?>">

<?php
$job        = $job ?? [];
$existingCv = $existingCv ?? false;
$jobTitle   = htmlspecialchars($job['title'] ?? 'Position');
$companyName= htmlspecialchars($job['company_name'] ?? 'Company');
$jobId      = (int)($job['id'] ?? 0);
?>

<div class="min-h-screen bg-gray-50 flex items-start justify-center py-8 px-4">
    <div class="w-full max-w-xl">

        <!-- Header Card -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-gray-900"><?= $jobTitle ?></h1>
                    <p class="text-sm text-gray-500"><?= $companyName ?></p>
                </div>
            </div>
            <?php if (!empty($job['type']) || !empty($job['work_mode']) || !empty($job['location'])): ?>
            <div class="flex flex-wrap gap-2 mt-4">
                <?php if (!empty($job['type'])): ?>
                    <span class="text-xs bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full font-medium"><?= htmlspecialchars(str_replace('_',' ',$job['type'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($job['work_mode'])): ?>
                    <span class="text-xs bg-green-100 text-green-700 px-2.5 py-1 rounded-full font-medium"><?= htmlspecialchars(ucfirst($job['work_mode'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($job['location'])): ?>
                    <span class="text-xs text-gray-500 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <?= htmlspecialchars($job['location']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Application Form -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Complete Your Application</h2>

            <form id="apply-form" class="space-y-5" onsubmit="submitApplication(event)">
                <input type="hidden" name="job_id" value="<?= $jobId ?>">

                <!-- CV Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        CV / Resume <span class="text-red-500">*</span>
                    </label>

                    <?php if ($existingCv): ?>
                    <div class="space-y-2 mb-1">
                        <label class="flex items-center gap-3 p-4 border border-green-200 bg-green-50 rounded-xl cursor-pointer">
                            <input type="radio" name="cv_option" value="existing" checked
                                onchange="toggleCvOption('existing')"
                                class="text-indigo-600 focus:ring-indigo-500 focus:ring-2">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <svg class="w-8 h-8 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800">Use my existing CV</p>
                                    <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($existingCv['filename'] ?? 'Previously uploaded') ?></p>
                                </div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                            <input type="radio" name="cv_option" value="new"
                                onchange="toggleCvOption('new')"
                                class="text-indigo-600 focus:ring-indigo-500 focus:ring-2">
                            <span class="text-sm text-gray-700">Upload a different CV</span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div id="cv-upload-area" class="<?= $existingCv ? 'hidden mt-3' : '' ?>">
                        <label id="cv-drop-zone"
                            class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-indigo-300 hover:bg-indigo-50/40 transition-colors">
                            <svg class="w-7 h-7 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm font-medium text-gray-600">Click or drag to upload CV</p>
                            <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX — max 5MB</p>
                            <input type="file" id="cv-file" accept=".pdf,.doc,.docx" class="hidden" onchange="handleCvFile(this)">
                        </label>
                        <div id="cv-selected" class="hidden mt-2 flex items-center gap-2 text-sm text-green-700 bg-green-50 px-3 py-2 rounded-lg">
                            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span id="cv-filename" class="truncate flex-1">file.pdf</span>
                            <button type="button" onclick="clearCv()" class="text-red-400 hover:text-red-500 flex-shrink-0">✕</button>
                        </div>
                        <p id="cv-error" class="hidden text-xs text-red-500 mt-1.5"></p>
                    </div>
                </div>

                <!-- Cover Letter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Cover Letter <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea name="cover_letter" id="cover_letter" rows="5"
                        placeholder="Tell us why you're a great fit for this role…"
                        class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none leading-relaxed text-gray-800 placeholder-gray-400"></textarea>
                    <p class="text-xs text-gray-400 mt-1">A personalised cover letter strengthens your application.</p>
                </div>

                <!-- Submit -->
                <div class="pt-1">
                    <button type="submit" id="submit-btn"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl transition-colors shadow-sm text-sm">
                        Submit Application
                    </button>
                    <p id="submit-error" class="hidden text-sm text-red-500 text-center mt-2"></p>
                </div>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            By applying you agree to our <a href="/privacy" class="underline hover:text-gray-600">Privacy Policy</a>.
        </p>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
let cvFile = null;
let useExisting = <?= $existingCv ? 'true' : 'false' ?>;

function toggleCvOption(val) {
    useExisting = (val === 'existing');
    document.getElementById('cv-upload-area').classList.toggle('hidden', useExisting);
}

function handleCvFile(input) {
    const file = input.files[0];
    const err  = document.getElementById('cv-error');
    err.classList.add('hidden');
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { err.textContent = 'File exceeds 5MB limit.'; err.classList.remove('hidden'); return; }
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['pdf','doc','docx'].includes(ext)) { err.textContent = 'Invalid file type. Use PDF or DOC.'; err.classList.remove('hidden'); return; }
    cvFile = file;
    document.getElementById('cv-filename').textContent = file.name;
    document.getElementById('cv-selected').classList.remove('hidden');
}

function clearCv() {
    cvFile = null;
    document.getElementById('cv-file').value = '';
    document.getElementById('cv-selected').classList.add('hidden');
}

// Drag and drop
const dz = document.getElementById('cv-drop-zone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('border-indigo-400','bg-indigo-50'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('border-indigo-400','bg-indigo-50'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('border-indigo-400','bg-indigo-50');
        if (e.dataTransfer.files[0]) { document.getElementById('cv-file').files = e.dataTransfer.files; handleCvFile(document.getElementById('cv-file')); }
    });
}

async function submitApplication(e) {
    e.preventDefault();
    const errEl = document.getElementById('submit-error');
    const btn   = document.getElementById('submit-btn');
    errEl.classList.add('hidden');

    if (!useExisting && !cvFile) {
        errEl.textContent = 'Please upload your CV to continue.';
        errEl.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Submitting…';

    const fd = new FormData();
    fd.append('job_id',          <?= $jobId ?>);
    fd.append('cover_letter',    document.getElementById('cover_letter').value);
    fd.append('use_existing_cv', useExisting ? '1' : '0');
    if (!useExisting && cvFile)  fd.append('cv', cvFile);

    try {
        const res  = await fetch('/api/v1/careers/apply', { method:'POST', headers:{'X-CSRF-Token':CSRF}, body:fd });
        const json = await res.json();
        if (json.ok) {
            window.location.href = '/candidate/applications?applied=1';
        } else {
            errEl.textContent = json.message || 'Failed to submit. Please try again.';
            errEl.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = 'Submit Application';
        }
    } catch(err) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.classList.remove('hidden');
        btn.disabled = false;
        btn.textContent = 'Submit Application';
    }
}
</script>
