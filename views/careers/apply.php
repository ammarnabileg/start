<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= $req->csrf() ?>">
    <title>Apply — <?= htmlspecialchars($job['title'] ?? 'Position') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', system-ui, sans-serif; }
        .drop-zone { transition: border-color 0.2s, background 0.2s; }
        .drop-zone.drag-over { border-color: #6366f1; background: #eef2ff; }
        @keyframes checkmark {
            0%   { stroke-dashoffset: 50; }
            100% { stroke-dashoffset: 0; }
        }
        .check-draw { stroke-dasharray: 50; animation: checkmark 0.4s ease-out forwards; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 0.8s linear infinite; }
        .file-pill { animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">

<!-- Header -->
<header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 sticky top-0 z-10 shadow-sm">
    <div class="max-w-3xl mx-auto flex items-center gap-3">
        <a href="javascript:history.back()" class="text-slate-400 hover:text-slate-700 transition p-1.5 rounded-lg hover:bg-slate-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <p class="font-semibold text-slate-900 leading-tight">Job Application</p>
            <p class="text-xs text-slate-500 leading-tight"><?= htmlspecialchars($job['company_name'] ?? '') ?></p>
        </div>
    </div>
</header>

<main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 pb-16">

    <!-- Job Summary Card -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 mb-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <h1 class="text-xl font-bold text-slate-900"><?= htmlspecialchars($job['title'] ?? 'Position') ?></h1>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm text-slate-500">
                <?php if (!empty($job['location'])): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    <?= htmlspecialchars($job['location']) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($job['type'])): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <?= htmlspecialchars(ucfirst($job['type'])) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($job['work_mode'])): ?>
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <?= htmlspecialchars(ucfirst($job['work_mode'])) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex-shrink-0">
            <span class="inline-flex items-center gap-1 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-100 px-3 py-1 rounded-full">
                <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></span>
                Now Hiring
            </span>
        </div>
    </div>

    <!-- Application Form -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100">
            <h2 class="font-semibold text-slate-900">Your Application</h2>
            <p class="text-sm text-slate-500 mt-0.5">All fields marked with * are required.</p>
        </div>

        <form id="apply-form" class="p-6 space-y-6" novalidate>
            <input type="hidden" name="job_id" value="<?= htmlspecialchars($job['id'] ?? '') ?>">

            <!-- CV Section -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Curriculum Vitae (CV / Résumé) <span class="text-red-500">*</span>
                </label>

                <?php if (!empty($existingCv)): ?>
                <!-- Existing CV option -->
                <div class="space-y-3">
                    <div id="existing-cv-opt" class="flex items-center gap-3 border-2 border-indigo-500 bg-indigo-50 rounded-xl px-4 py-3 cursor-pointer" onclick="useExistingCv(true)">
                        <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-indigo-700">Use my existing CV</p>
                            <p class="text-xs text-slate-500 truncate"><?= htmlspecialchars($existingCvName ?? 'Previously uploaded resume') ?></p>
                        </div>
                        <div id="existing-check" class="w-5 h-5 rounded-full border-2 border-indigo-600 bg-indigo-600 flex items-center justify-center flex-shrink-0">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 12 12"><path d="M10 3L4.5 8.5 2 6"/></svg>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-slate-400">
                        <div class="flex-1 border-t border-slate-200"></div>
                        <span>or upload a new one</span>
                        <div class="flex-1 border-t border-slate-200"></div>
                    </div>
                </div>
                <input type="hidden" id="use-existing" name="use_existing_cv" value="1">
                <?php endif; ?>

                <!-- Drop Zone -->
                <div id="drop-zone"
                    class="drop-zone border-2 border-dashed border-slate-300 rounded-xl p-6 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/30 transition <?= !empty($existingCv) ? 'mt-0' : '' ?>"
                    onclick="document.getElementById('cv-file').click()"
                    ondragover="onDragOver(event)"
                    ondragleave="onDragLeave(event)"
                    ondrop="onDrop(event)">
                    <input type="file" id="cv-file" name="cv" accept=".pdf,.doc,.docx" class="hidden" onchange="onFileSelect(this)">
                    <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <p class="text-sm font-medium text-slate-600">Drag & drop or <span class="text-indigo-600">browse files</span></p>
                    <p class="text-xs text-slate-400 mt-1">PDF, DOC, DOCX — Max 10 MB</p>
                </div>

                <!-- File preview -->
                <div id="file-preview" class="hidden mt-3">
                    <div class="file-pill flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5">
                        <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p id="file-name" class="text-sm font-medium text-slate-700 truncate"></p>
                            <p id="file-size" class="text-xs text-slate-400"></p>
                        </div>
                        <button type="button" onclick="clearFile()" class="text-slate-400 hover:text-red-500 transition p-1 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <p id="cv-error" class="hidden text-xs text-red-500 mt-1.5 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    Please upload your CV to continue.
                </p>
            </div>

            <!-- Cover Letter -->
            <div>
                <label for="cover-letter" class="block text-sm font-medium text-slate-700 mb-2">
                    Cover Letter
                    <span class="text-slate-400 font-normal">(optional)</span>
                </label>
                <textarea
                    id="cover-letter"
                    name="cover_letter"
                    rows="6"
                    maxlength="3000"
                    placeholder="Tell us why you're a great fit for this role. Share relevant experience, achievements, and what excites you about this opportunity..."
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition leading-relaxed"
                    oninput="updateCharCount(this)"
                ></textarea>
                <div class="flex justify-between mt-1.5">
                    <p class="text-xs text-slate-400">A personalised cover letter greatly improves your chances.</p>
                    <p class="text-xs text-slate-400"><span id="char-count">0</span>/3000</p>
                </div>
            </div>

            <!-- Consent -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex gap-3">
                <input type="checkbox" id="consent" name="consent" class="mt-0.5 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 cursor-pointer flex-shrink-0">
                <label for="consent" class="text-sm text-slate-600 cursor-pointer leading-relaxed">
                    I consent to my personal data being processed for this application. I understand it may be shared with the hiring team and stored for up to 12 months. <a href="#" class="text-indigo-600 hover:underline">Privacy Policy</a>
                </label>
            </div>
            <p id="consent-error" class="hidden text-xs text-red-500 -mt-4 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                You must agree to our privacy policy to apply.
            </p>

            <!-- Submit -->
            <div class="pt-2">
                <button type="submit" id="submit-btn" class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3.5 rounded-xl transition shadow-lg shadow-indigo-200 flex items-center justify-center gap-2 text-base">
                    <svg id="submit-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span id="submit-label">Submit Application</span>
                </button>
                <p class="text-center text-xs text-slate-400 mt-3">Your application will be reviewed by the hiring team. Typical response time: 3–5 business days.</p>
            </div>
        </form>
    </div>

</main>

<!-- ── Success Overlay ────────────────────────────────────────────────────── -->
<div id="success-overlay" class="hidden fixed inset-0 z-50 bg-white flex flex-col items-center justify-center p-6 text-center gap-6">
    <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
        <svg class="w-10 h-10" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
            <path class="check-draw" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <div>
        <h2 class="text-2xl font-bold text-slate-900">Application Submitted!</h2>
        <p class="text-slate-500 mt-2 max-w-sm">
            Thank you for applying for <strong class="text-slate-700"><?= htmlspecialchars($job['title'] ?? 'this position') ?></strong>.
            The hiring team will review your application and be in touch soon.
        </p>
    </div>
    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 max-w-sm w-full text-sm text-slate-600 space-y-2 text-left">
        <p class="flex items-center gap-2"><span class="text-green-500">✓</span> Application received</p>
        <p class="flex items-center gap-2"><span class="text-green-500">✓</span> Confirmation email sent</p>
        <p class="flex items-center gap-2"><span class="text-slate-400">·</span> Review in progress (3–5 days)</p>
    </div>
    <a href="javascript:history.back()" class="text-sm text-indigo-600 hover:underline">Browse more opportunities →</a>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
let selectedFile = null;
let usingExisting = <?= !empty($existingCv) ? 'true' : 'false' ?>;

// ── CV selection ──────────────────────────────────────────────────────────────

function useExistingCv(yes) {
    usingExisting = yes;
    const existOpt  = document.getElementById('existing-cv-opt');
    const useHidden = document.getElementById('use-existing');
    const dropZone  = document.getElementById('drop-zone');

    if (yes) {
        existOpt?.classList.add('border-indigo-500', 'bg-indigo-50');
        existOpt?.classList.remove('border-slate-200');
        if (useHidden) useHidden.value = '1';
        clearFile();
        dropZone.style.opacity = '0.5';
        dropZone.style.pointerEvents = 'none';
    } else {
        existOpt?.classList.remove('border-indigo-500', 'bg-indigo-50');
        existOpt?.classList.add('border-slate-200');
        if (useHidden) useHidden.value = '0';
        dropZone.style.opacity = '1';
        dropZone.style.pointerEvents = 'auto';
    }
}

function onFileSelect(input) {
    if (input.files && input.files[0]) {
        setFile(input.files[0]);
        useExistingCv(false);
    }
}

function setFile(file) {
    const maxMb = 10;
    if (file.size > maxMb * 1024 * 1024) {
        showCvError(`File is too large. Maximum size is ${maxMb} MB.`);
        return;
    }
    const allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!allowed.includes(file.type) && !file.name.match(/\.(pdf|doc|docx)$/i)) {
        showCvError('Please upload a PDF, DOC, or DOCX file.');
        return;
    }

    selectedFile = file;
    hideCvError();

    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = formatBytes(file.size);
    document.getElementById('file-preview').classList.remove('hidden');
    document.getElementById('drop-zone').classList.add('hidden');
}

function clearFile() {
    selectedFile = null;
    document.getElementById('cv-file').value = '';
    document.getElementById('file-preview').classList.add('hidden');
    document.getElementById('drop-zone').classList.remove('hidden');
}

function onDragOver(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.add('drag-over');
}

function onDragLeave(e) {
    document.getElementById('drop-zone').classList.remove('drag-over');
}

function onDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) { setFile(file); useExistingCv(false); }
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
}

function showCvError(msg) {
    const el = document.getElementById('cv-error');
    el.lastChild.textContent = msg || 'Please upload your CV to continue.';
    el.classList.remove('hidden');
}

function hideCvError() {
    document.getElementById('cv-error').classList.add('hidden');
}

function updateCharCount(el) {
    document.getElementById('char-count').textContent = el.value.length;
}

// ── Submit ────────────────────────────────────────────────────────────────────

document.getElementById('apply-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    let valid = true;

    // Validate CV
    if (!usingExisting && !selectedFile) {
        showCvError('Please upload your CV to continue.');
        valid = false;
    } else { hideCvError(); }

    // Validate consent
    const consent = document.getElementById('consent');
    const consentErr = document.getElementById('consent-error');
    if (!consent.checked) {
        consentErr.classList.remove('hidden');
        valid = false;
    } else { consentErr.classList.add('hidden'); }

    if (!valid) return;

    // Build form data
    const formData = new FormData();
    formData.append('job_id', document.querySelector('[name=job_id]').value);
    if (!usingExisting && selectedFile) {
        formData.append('cv', selectedFile);
    } else {
        formData.append('use_existing_cv', '1');
    }
    const coverLetter = document.getElementById('cover-letter').value.trim();
    if (coverLetter) formData.append('cover_letter', coverLetter);

    // UI: loading state
    const btn   = document.getElementById('submit-btn');
    const icon  = document.getElementById('submit-icon');
    const label = document.getElementById('submit-label');
    btn.disabled = true;
    icon.outerHTML = '<svg id="submit-icon" class="w-5 h-5 spinner" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>';
    label.textContent = 'Submitting...';

    try {
        const res  = await fetch('/api/v1/careers/apply', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF },
            body: formData
        });
        const json = await res.json();

        if (!json.ok) throw new Error(json.message || 'Submission failed');

        // Show success
        document.getElementById('success-overlay').classList.remove('hidden');
        document.body.style.overflow = 'hidden';

    } catch (err) {
        // Reset button
        btn.disabled = false;
        document.getElementById('submit-icon').outerHTML = '<svg id="submit-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>';
        document.getElementById('submit-label').textContent = 'Submit Application';

        // Show error toast
        showToast(err.message || 'Something went wrong. Please try again.');
    }
});

function showToast(msg) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 bg-red-600 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-xl z-50 flex items-center gap-2';
    toast.innerHTML = `<svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>${escHtml(msg)}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Init existing CV state
<?php if (!empty($existingCv)): ?>
useExistingCv(true);
<?php endif; ?>
</script>
</body>
</html>
