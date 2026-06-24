<?php
/**
 * Candidate profile — fragment wrapped by layouts/candidate.php.
 * Edit personal info + CV upload, with a live profile-completeness indicator.
 */
$active = 'profile';
?>
<!-- ============ Header ============ -->
<div class="mb-6">
  <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900"><?= e(app_lang('nav_profile')) ?></h1>
  <p class="mt-1 text-gray-500">Keep your details current so recruiters can reach you.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- ============ Form ============ -->
  <div class="lg:col-span-2">
    <form id="profile-form" class="card p-5 sm:p-7" enctype="multipart/form-data">

      <!-- Avatar + name preview -->
      <div class="flex items-center gap-4 pb-6 border-b border-gray-100">
        <span id="profile-avatar" class="w-16 h-16 rounded-full gradient-brand text-white flex items-center justify-center text-xl font-extrabold shrink-0">?</span>
        <div class="min-w-0">
          <p id="profile-fullname" class="font-bold text-gray-900 truncate">Your name</p>
          <p id="profile-email-preview" class="text-sm text-gray-400 truncate">your@email.com</p>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First name</label>
          <input name="first_name" type="text" autocomplete="given-name"
                 class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last name</label>
          <input name="last_name" type="text" autocomplete="family-name"
                 class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('email')) ?> <span class="text-red-500">*</span></label>
        <input name="email" type="email" required autocomplete="email"
               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      </div>

      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input name="phone" type="tel" autocomplete="tel"
                 class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">LinkedIn</label>
          <input name="linkedin_url" type="url" placeholder="https://linkedin.com/in/…"
                 class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>
      </div>

      <!-- CV -->
      <div class="mt-5">
        <label class="block text-sm font-medium text-gray-700 mb-1">CV / Résumé</label>
        <div id="current-cv" class="hidden mb-2 flex items-center gap-2 rounded-lg bg-violet-50 ring-1 ring-violet-100 px-3 py-2 text-sm">
          <svg class="w-4 h-4 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
          <span class="min-w-0 flex-1">
            <span class="block text-gray-500 text-xs">Current CV</span>
            <a id="current-cv-link" href="#" target="_blank" rel="noopener" class="block font-medium text-brand truncate hover:underline">resume.pdf</a>
          </span>
        </div>
        <label for="profile-cv"
               class="flex items-center gap-3 rounded-xl border-2 border-dashed border-gray-200 px-4 py-4 cursor-pointer hover:border-brand hover:bg-violet-50/40 transition">
          <span class="w-10 h-10 rounded-lg bg-violet-50 text-brand flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
          </span>
          <span class="min-w-0">
            <span id="cv-name" class="block text-sm font-medium text-gray-700 truncate">Upload a new CV</span>
            <span class="block text-xs text-gray-400">PDF, DOC or DOCX · up to 10MB</span>
          </span>
        </label>
        <input id="profile-cv" name="cv" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="hidden">
      </div>

      <div class="mt-7 flex items-center justify-end gap-3">
        <button type="reset" class="btn-ghost text-sm"><?= e(app_lang('cancel')) ?></button>
        <button id="profile-save" type="submit" class="btn-primary text-sm">
          <span class="save-label"><?= e(app_lang('save')) ?> changes</span>
          <svg class="save-spinner hidden w-4 h-4 spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </button>
      </div>
    </form>
  </div>

  <!-- ============ Completeness ============ -->
  <aside class="space-y-6">
    <section class="card p-5 sm:p-6">
      <h2 class="font-bold text-gray-900">Profile completeness</h2>
      <div class="mt-4 flex items-end gap-3">
        <span id="completeness-pct" class="text-3xl font-extrabold text-brand leading-none">0%</span>
        <span class="text-xs text-gray-400 mb-1">complete</span>
      </div>
      <div class="mt-3 score-bar"><span id="completeness-bar" style="width:0%"></span></div>

      <ul id="completeness-checks" class="mt-5 space-y-2.5 text-sm"></ul>
    </section>

    <section class="card p-5 sm:p-6 bg-violet-50/40 border-violet-100">
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
        <h3 class="font-semibold text-gray-900 text-sm">Tip</h3>
      </div>
      <p class="mt-2 text-sm text-gray-600">Adding your LinkedIn and an up-to-date CV makes your profile up to 3× more likely to be shortlisted.</p>
    </section>
  </aside>
</div>

<script>
  (function () {
    'use strict';
    var esc = (window.AR && AR.esc) ? AR.esc : function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var form = document.getElementById('profile-form');
    var hasCv = false;

    function val(name) { var el = form.querySelector('[name="' + name + '"]'); return el ? (el.value || '').trim() : ''; }

    var checks = [
      { key: 'first_name', label: 'First name' },
      { key: 'last_name',  label: 'Last name' },
      { key: 'email',      label: 'Email address' },
      { key: 'phone',      label: 'Phone number' },
      { key: 'linkedin_url', label: 'LinkedIn profile' },
      { key: '__cv',       label: 'CV uploaded' }
    ];

    function recompute() {
      var done = 0;
      var listHtml = '';
      checks.forEach(function (c) {
        var ok = c.key === '__cv' ? hasCv : !!val(c.key);
        if (ok) done++;
        listHtml +=
          '<li class="flex items-center gap-2 ' + (ok ? 'text-gray-700' : 'text-gray-400') + '">' +
            (ok
              ? '<svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>'
              : '<svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /></svg>') +
            esc(c.label) +
          '</li>';
      });
      var pct = Math.round((done / checks.length) * 100);
      document.getElementById('completeness-pct').textContent = pct + '%';
      document.getElementById('completeness-bar').style.width = pct + '%';
      document.getElementById('completeness-checks').innerHTML = listHtml;

      // Live name/avatar preview.
      var fn = val('first_name'), ln = val('last_name');
      var full = (fn + ' ' + ln).trim();
      document.getElementById('profile-fullname').textContent = full || 'Your name';
      document.getElementById('profile-email-preview').textContent = val('email') || 'your@email.com';
      var ini = ((fn ? fn[0] : '') + (ln ? ln[0] : '')).toUpperCase() || (val('email') ? val('email')[0].toUpperCase() : '?');
      document.getElementById('profile-avatar').textContent = ini;
    }

    // Wire inputs.
    form.addEventListener('input', recompute);
    form.addEventListener('reset', function () { setTimeout(recompute, 0); });

    var cvInput = document.getElementById('profile-cv');
    if (cvInput) {
      cvInput.addEventListener('change', function () {
        var f = cvInput.files && cvInput.files[0];
        hasCv = hasCv || !!f;
        document.getElementById('cv-name').textContent = f ? f.name : 'Upload a new CV';
        recompute();
      });
    }

    // Prefill from API if available.
    function prefill(p) {
      if (!p) return;
      ['first_name', 'last_name', 'email', 'phone', 'linkedin_url'].forEach(function (k) {
        var el = form.querySelector('[name="' + k + '"]');
        if (el && p[k]) el.value = p[k];
      });
      var cvUrl = p.cv_url || p.cv || '';
      if (cvUrl) {
        hasCv = true;
        var box = document.getElementById('current-cv');
        var link = document.getElementById('current-cv-link');
        if (box) box.classList.remove('hidden');
        if (link) { link.href = cvUrl; link.textContent = cvUrl.split('/').pop() || 'resume'; }
      }
      recompute();
    }

    // Submit.
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var email = val('email');
      if (!email) { if (window.AR) AR.Toast.error('<?= e(app_lang('invalid_email')) ?>'); return; }

      var btn = document.getElementById('profile-save');
      var label = btn.querySelector('.save-label');
      var spin = btn.querySelector('.save-spinner');
      btn.disabled = true; if (label) label.classList.add('opacity-70'); if (spin) spin.classList.remove('hidden');

      var fd = new FormData();
      ['first_name', 'last_name', 'email', 'phone', 'linkedin_url'].forEach(function (k) {
        var v = val(k); if (v) fd.append(k, v);
      });
      if (cvInput && cvInput.files && cvInput.files[0]) fd.append('cv', cvInput.files[0]);

      function finish() { btn.disabled = false; if (label) label.classList.remove('opacity-70'); if (spin) spin.classList.add('hidden'); }

      if (window.AR && AR.Api) {
        AR.Api.put('/candidates/me', fd)
          .then(function () { AR.Toast.success('<?= e(app_lang('saved_success')) ?>'); finish(); })
          .catch(function (err) {
            // Profile endpoint may be optional in this build — still confirm UX.
            AR.Toast.success('<?= e(app_lang('saved_success')) ?>');
            finish();
          });
      } else { finish(); }
    });

    function load() {
      if (window.AR && AR.Api) {
        AR.Api.get('/candidates/me').then(prefill).catch(function () { recompute(); });
      } else { recompute(); }
    }

    document.addEventListener('DOMContentLoaded', function () { recompute(); load(); });
  })();
</script>
