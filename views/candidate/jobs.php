<?php
/**
 * Browse open positions / public career page — fragment wrapped by
 * layouts/candidate.php. Receives optional $subdomain, $public.
 * Jobs hydrated from the public jobs API; includes an application modal
 * that POSTs multipart (with CV file) to /api/v1/candidates/apply/{jobId}.
 */
$active = 'jobs';
$__subdomain = trim((string)($subdomain ?? ''));
$__public = !empty($public);
?>
<!-- ============ Page header ============ -->
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
  <div>
    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Open positions</h1>
    <p class="mt-1 text-gray-500">Find your next role and apply in minutes.</p>
  </div>
  <p id="jobs-count" class="text-sm font-medium text-gray-400"></p>
</div>

<!-- ============ Search + filters ============ -->
<div class="card p-4 mt-5">
  <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
    <div class="md:col-span-6 relative">
      <svg class="w-5 h-5 text-gray-400 absolute top-1/2 -translate-y-1/2 ms-3.5 start-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
      <input id="job-search" type="search" placeholder="Search by title, keyword…"
             class="w-full rounded-xl border border-gray-200 ps-11 pe-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
    </div>
    <select id="filter-department" class="md:col-span-3 rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      <option value=""><?= e(app_lang('department')) ?> · All</option>
    </select>
    <select id="filter-type" class="md:col-span-3 rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
      <option value=""><?= e(app_lang('job_type')) ?> · All</option>
      <option value="full_time"><?= e(app_lang('full_time')) ?></option>
      <option value="part_time"><?= e(app_lang('part_time')) ?></option>
      <option value="contract"><?= e(app_lang('contract')) ?></option>
      <option value="remote"><?= e(app_lang('remote')) ?></option>
      <option value="internship"><?= e(app_lang('internship')) ?></option>
    </select>
  </div>
</div>

<!-- ============ Loading ============ -->
<div id="jobs-loading" class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
  <div class="card p-5"><div class="skeleton h-5 w-2/3"></div><div class="skeleton h-3 w-1/2 mt-3"></div><div class="skeleton h-16 w-full mt-4"></div></div>
  <div class="card p-5"><div class="skeleton h-5 w-2/3"></div><div class="skeleton h-3 w-1/2 mt-3"></div><div class="skeleton h-16 w-full mt-4"></div></div>
  <div class="card p-5"><div class="skeleton h-5 w-2/3"></div><div class="skeleton h-3 w-1/2 mt-3"></div><div class="skeleton h-16 w-full mt-4"></div></div>
</div>

<!-- ============ Grid ============ -->
<div id="jobs-grid" class="mt-6 hidden grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5"></div>

<!-- ============ Empty / error ============ -->
<div id="jobs-empty" class="mt-6 hidden card p-12 text-center">
  <div class="mx-auto w-16 h-16 rounded-2xl bg-violet-50 flex items-center justify-center">
    <svg class="w-8 h-8 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25a2.25 2.25 0 00-2.25-2.25h-4.5A2.25 2.25 0 007.5 5.25v1.5m13.5 0H3.75a1.5 1.5 0 00-1.5 1.5v3.026c0 .55.27 1.06.71 1.39l.01.01a17.93 17.93 0 0019.06 0l.01-.01c.44-.33.71-.84.71-1.39V8.25a1.5 1.5 0 00-1.5-1.5z" /></svg>
  </div>
  <p id="jobs-empty-title" class="mt-4 text-lg font-semibold text-gray-800">No open positions right now</p>
  <p class="mt-1 text-sm text-gray-500">Check back soon — new roles are posted regularly.</p>
</div>

<!-- ============ Apply modal ============ -->
<div id="apply-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card max-h-[92vh] overflow-y-auto">
    <form id="apply-form" enctype="multipart/form-data">
      <div class="flex items-start justify-between gap-3 p-5 sm:p-6 border-b border-gray-100">
        <div class="min-w-0">
          <h3 class="text-lg font-bold text-gray-900">Apply for this role</h3>
          <p id="apply-job-title" class="text-sm text-brand font-semibold truncate"></p>
        </div>
        <button type="button" data-modal-close="apply-modal" aria-label="<?= e(app_lang('close')) ?>"
                class="shrink-0 w-9 h-9 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 flex items-center justify-center transition">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <input type="hidden" id="apply-job-id" name="job_id" value="">

      <div class="p-5 sm:p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('email')) ?> <span class="text-red-500">*</span></label>
          <input name="email" type="email" required autocomplete="email"
                 class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

        <!-- CV upload -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">CV / Résumé</label>
          <label id="cv-drop" for="apply-cv"
                 class="flex items-center gap-3 rounded-xl border-2 border-dashed border-gray-200 px-4 py-4 cursor-pointer hover:border-brand hover:bg-violet-50/40 transition">
            <span class="w-10 h-10 rounded-lg bg-violet-50 text-brand flex items-center justify-center shrink-0">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
            </span>
            <span class="min-w-0">
              <span id="cv-name" class="block text-sm font-medium text-gray-700 truncate">Click to upload your CV</span>
              <span class="block text-xs text-gray-400">PDF, DOC or DOCX · up to 10MB</span>
            </span>
          </label>
          <input id="apply-cv" name="cv" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="hidden">
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 p-5 sm:p-6 border-t border-gray-100">
        <button type="button" data-modal-close="apply-modal" class="btn-ghost text-sm"><?= e(app_lang('cancel')) ?></button>
        <button id="apply-submit" type="submit" class="btn-primary text-sm">
          <span class="apply-label"><?= e(app_lang('send')) ?> application</span>
          <svg class="apply-spinner hidden w-4 h-4 spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    'use strict';
    var esc = (window.AR && AR.esc) ? AR.esc : function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var SUBDOMAIN = <?= json_encode($__subdomain) ?>;
    var ALL = [];

    var typeLabels = {
      full_time: <?= json_encode(app_lang('full_time')) ?>,
      part_time: <?= json_encode(app_lang('part_time')) ?>,
      contract: <?= json_encode(app_lang('contract')) ?>,
      remote: <?= json_encode(app_lang('remote')) ?>,
      internship: <?= json_encode(app_lang('internship')) ?>
    };

    function show(id) { var el = document.getElementById(id); if (el) el.classList.remove('hidden'); }
    function hide(id) { var el = document.getElementById(id); if (el) el.classList.add('hidden'); }

    function fmtSalary(j) {
      var cur = j.currency || 'USD';
      var min = j.salary_min, max = j.salary_max;
      function n(v) { try { return Number(v).toLocaleString(); } catch (e) { return v; } }
      if (min && max) return cur + ' ' + n(min) + ' – ' + n(max);
      if (min) return 'From ' + cur + ' ' + n(min);
      if (max) return 'Up to ' + cur + ' ' + n(max);
      return 'Competitive';
    }
    function excerpt(text, len) {
      text = (text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
      if (text.length <= len) return text;
      return text.slice(0, len).replace(/\s+\S*$/, '') + '…';
    }

    function card(j) {
      var dept = j.department || '';
      var loc = j.location || (j.job_type === 'remote' ? 'Remote' : '');
      var meta = [dept, loc].filter(Boolean).join(' • ');
      var typeLabel = typeLabels[j.job_type] || (j.job_type ? String(j.job_type).replace(/_/g, ' ') : '');
      return '' +
        '<article class="card p-5 flex flex-col hover:shadow-lg transition group">' +
          '<div class="flex items-start gap-3">' +
            '<span class="w-11 h-11 rounded-xl gradient-brand text-white flex items-center justify-center font-bold shrink-0">' + esc((j.title || 'J').charAt(0).toUpperCase()) + '</span>' +
            '<div class="min-w-0 flex-1">' +
              '<h3 class="font-bold text-gray-900 leading-snug line-clamp-2">' + esc(j.title || 'Untitled role') + '</h3>' +
              (meta ? '<p class="mt-0.5 text-xs text-gray-400 truncate">' + esc(meta) + '</p>' : '') +
            '</div>' +
          '</div>' +
          '<div class="mt-3 flex flex-wrap items-center gap-2">' +
            (typeLabel ? '<span class="badge badge-violet">' + esc(typeLabel) + '</span>' : '') +
            '<span class="badge badge-gray">' + esc(fmtSalary(j)) + '</span>' +
          '</div>' +
          (j.description ? '<p class="mt-3 text-sm text-gray-500 leading-relaxed flex-1">' + esc(excerpt(j.description, 140)) + '</p>' : '<div class="flex-1"></div>') +
          '<button type="button" class="btn-primary mt-4 text-sm justify-center w-full apply-btn" ' +
            'data-id="' + esc(j.id) + '" data-title="' + esc(j.title || '') + '">' +
            '<?= e(app_lang('view')) ?> & Apply' +
          '</button>' +
        '</article>';
    }

    function buildDepartments(jobs) {
      var sel = document.getElementById('filter-department');
      var seen = {};
      jobs.forEach(function (j) { if (j.department) seen[j.department] = true; });
      Object.keys(seen).sort().forEach(function (d) {
        var o = document.createElement('option'); o.value = d; o.textContent = d; sel.appendChild(o);
      });
    }

    function render(list) {
      var grid = document.getElementById('jobs-grid');
      var count = document.getElementById('jobs-count');
      if (!list.length) {
        hide('jobs-grid'); show('jobs-empty');
        if (count) count.textContent = '';
        return;
      }
      hide('jobs-empty'); show('jobs-grid');
      grid.innerHTML = list.map(card).join('');
      if (count) count.textContent = list.length + (list.length === 1 ? ' role' : ' roles');
      bindApplyButtons();
    }

    function applyFilters() {
      var q = (document.getElementById('job-search').value || '').toLowerCase().trim();
      var dep = document.getElementById('filter-department').value;
      var type = document.getElementById('filter-type').value;
      var filtered = ALL.filter(function (j) {
        if (dep && j.department !== dep) return false;
        if (type && j.job_type !== type) return false;
        if (q) {
          var hay = ((j.title || '') + ' ' + (j.department || '') + ' ' + (j.location || '') + ' ' + (j.description || '')).toLowerCase();
          if (hay.indexOf(q) === -1) return false;
        }
        return true;
      });
      render(filtered);
    }

    // ---- Apply modal ---------------------------------------------------
    function openApply(id, title) {
      document.getElementById('apply-job-id').value = id;
      document.getElementById('apply-job-title').textContent = title || '';
      document.getElementById('apply-form').reset();
      document.getElementById('cv-name').textContent = 'Click to upload your CV';
      if (window.AR) AR.Modal.open('apply-modal');
    }
    function bindApplyButtons() {
      document.querySelectorAll('.apply-btn').forEach(function (b) {
        b.addEventListener('click', function () { openApply(b.getAttribute('data-id'), b.getAttribute('data-title')); });
      });
    }

    var cvInput = document.getElementById('apply-cv');
    if (cvInput) {
      cvInput.addEventListener('change', function () {
        var f = cvInput.files && cvInput.files[0];
        document.getElementById('cv-name').textContent = f ? f.name : 'Click to upload your CV';
      });
    }

    var form = document.getElementById('apply-form');
    if (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var jobId = document.getElementById('apply-job-id').value;
        var email = (form.querySelector('[name="email"]').value || '').trim();
        if (!email) { if (window.AR) AR.Toast.error('<?= e(app_lang('invalid_email')) ?>'); return; }

        var btn = document.getElementById('apply-submit');
        var labelEl = btn.querySelector('.apply-label');
        var spin = btn.querySelector('.apply-spinner');
        btn.disabled = true; if (labelEl) labelEl.classList.add('opacity-70'); if (spin) spin.classList.remove('hidden');

        var fd = new FormData();
        ['first_name', 'last_name', 'email', 'phone', 'linkedin_url'].forEach(function (k) {
          var el = form.querySelector('[name="' + k + '"]');
          if (el && el.value) fd.append(k, el.value);
        });
        if (cvInput && cvInput.files && cvInput.files[0]) fd.append('cv', cvInput.files[0]);

        function finish() { btn.disabled = false; if (labelEl) labelEl.classList.remove('opacity-70'); if (spin) spin.classList.add('hidden'); }

        if (window.AR && AR.Api) {
          AR.Api.post('/candidates/apply/' + encodeURIComponent(jobId), fd)
            .then(function () {
              AR.Toast.success('Application submitted!');
              AR.Modal.close('apply-modal');
              finish();
            })
            .catch(function (err) {
              AR.Toast.error((err && err.message) || '<?= e(app_lang('error_generic')) ?>');
              finish();
            });
        } else {
          finish();
        }
      });
    }

    // ---- Load ----------------------------------------------------------
    function load() {
      var url = SUBDOMAIN ? ('/jobs/public/' + encodeURIComponent(SUBDOMAIN)) : '/jobs/public/' + encodeURIComponent(location.hostname.split('.')[0] || '');
      if (!(window.AR && AR.Api)) { hide('jobs-loading'); show('jobs-empty'); return; }
      AR.Api.get(url)
        .then(function (jobs) {
          hide('jobs-loading');
          ALL = Array.isArray(jobs) ? jobs : [];
          buildDepartments(ALL);
          render(ALL);
        })
        .catch(function () {
          hide('jobs-loading');
          document.getElementById('jobs-empty-title').textContent = 'We couldn’t load positions';
          show('jobs-empty');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
      ['job-search'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('input', applyFilters); });
      ['filter-department', 'filter-type'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('change', applyFilters); });
      load();
    });
  })();
</script>
