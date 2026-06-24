<?php
/**
 * Create / Edit Job — recruiter form with AI Job Builder.
 * Fragment rendered inside views/layouts/app.php.
 * Controller passes $jobId and $editing ONLY when editing.
 */
$editing = isset($editing) && $editing;
$jobId   = isset($jobId) ? (int) $jobId : 0;
$csrf    = $csrf ?? '';
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- ============ Header ============ -->
  <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
    <div>
      <nav class="mb-2">
        <a href="/jobs" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-violet-600 transition">
          <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
          Back to Jobs
        </a>
      </nav>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2.5">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center shrink-0">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25a2.25 2.25 0 00-2.25-2.25h-4.5A2.25 2.25 0 007.5 5.25v1.5m13.5 0H3.75a1.5 1.5 0 00-1.5 1.5v3.026c0 .55.27 1.06.71 1.39l.01.01a17.93 17.93 0 0019.06 0l.01-.01c.44-.33.71-.84.71-1.39V8.25a1.5 1.5 0 00-1.5-1.5z"/></svg>
        </span>
        <?= $editing ? 'Edit Job' : 'Create Job' ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Fill in the details or let AI draft it for you.</p>
    </div>
    <a href="/jobs" class="btn-ghost self-start sm:self-auto shrink-0">Cancel</a>
  </div>

  <!-- ============ Two-column grid ============ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ======== LEFT: the form ======== -->
    <div class="lg:col-span-2">
      <form id="job-form" novalidate>
        <input type="hidden" name="csrf" id="job-csrf" value="<?= e($csrf) ?>">

        <!-- Job details -->
        <div class="card p-6 mb-6">
          <div class="flex items-center gap-2 mb-5">
            <span class="inline-flex w-7 h-7 rounded-lg bg-violet-50 text-violet-600 items-center justify-center">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            </span>
            <h2 class="text-base font-semibold text-gray-900">Job Details</h2>
          </div>

          <div class="space-y-5">
            <!-- Title -->
            <div>
              <label for="f-title" class="block text-sm font-semibold text-gray-700 mb-1.5">
                Job Title <span class="text-red-500">*</span>
              </label>
              <input type="text" id="f-title" required placeholder="e.g. Senior Backend Engineer"
                     class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
            </div>

            <!-- Description -->
            <div>
              <label for="f-description" class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
              <textarea id="f-description" rows="8" placeholder="Describe the role, responsibilities, team and impact…"
                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 leading-relaxed transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"></textarea>
            </div>

            <!-- Requirements -->
            <div>
              <label for="f-requirements" class="block text-sm font-semibold text-gray-700 mb-1.5">Requirements</label>
              <textarea id="f-requirements" rows="5" placeholder="Required skills, experience and qualifications…"
                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 leading-relaxed transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"></textarea>
            </div>

            <!-- Department / Location -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label for="f-department" class="block text-sm font-semibold text-gray-700 mb-1.5">Department</label>
                <input type="text" id="f-department" placeholder="e.g. Engineering"
                       class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
              </div>
              <div>
                <label for="f-location" class="block text-sm font-semibold text-gray-700 mb-1.5">Location</label>
                <input type="text" id="f-location" placeholder="e.g. Riyadh, Saudi Arabia"
                       class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
              </div>
            </div>

            <!-- Job Type / Currency -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label for="f-job_type" class="block text-sm font-semibold text-gray-700 mb-1.5">Job Type</label>
                <select id="f-job_type"
                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 bg-white transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                  <option value="full-time">Full-time</option>
                  <option value="part-time">Part-time</option>
                  <option value="contract">Contract</option>
                  <option value="remote">Remote</option>
                  <option value="internship">Internship</option>
                </select>
              </div>
              <div>
                <label for="f-currency" class="block text-sm font-semibold text-gray-700 mb-1.5">Currency</label>
                <select id="f-currency"
                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 bg-white transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="GBP">GBP</option>
                  <option value="SAR">SAR</option>
                  <option value="AED">AED</option>
                </select>
              </div>
            </div>

            <!-- Salary Min / Max -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label for="f-salary_min" class="block text-sm font-semibold text-gray-700 mb-1.5">Salary Min</label>
                <input type="number" id="f-salary_min" min="0" step="1000" placeholder="e.g. 80000"
                       class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
              </div>
              <div>
                <label for="f-salary_max" class="block text-sm font-semibold text-gray-700 mb-1.5">Salary Max</label>
                <input type="number" id="f-salary_max" min="0" step="1000" placeholder="e.g. 120000"
                       class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
              </div>
            </div>
          </div>
        </div>

        <!-- Interview configuration -->
        <div class="card p-6 mb-6">
          <div class="flex items-center gap-2 mb-1.5">
            <span class="inline-flex w-7 h-7 rounded-lg bg-violet-50 text-violet-600 items-center justify-center">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-3 3-3-3z"/></svg>
            </span>
            <h2 class="text-base font-semibold text-gray-900">Interview Type</h2>
          </div>
          <p class="text-sm text-gray-500 mb-4">Choose how AI will conduct the screening interview.</p>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="interview-cards">
            <!-- AI Text -->
            <label data-itype="ai_text"
                   class="itype-card relative flex flex-col gap-2 rounded-xl border border-gray-300 p-4 cursor-pointer transition hover:border-violet-300 hover:bg-violet-50/40 focus-within:ring-2 focus-within:ring-violet-500">
              <input type="radio" name="interview_type" value="ai_text" class="sr-only" checked />
              <span class="itype-icon inline-flex w-9 h-9 rounded-lg bg-gray-100 text-gray-500 items-center justify-center transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.83L3 20l1.13-3.39A7.94 7.94 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              </span>
              <span class="font-semibold text-gray-900 text-sm">AI Text</span>
              <span class="text-xs text-gray-500 leading-snug">Chat-based written Q&amp;A interview.</span>
              <span class="itype-check absolute top-3 end-3 text-violet-600 opacity-0 transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd"/></svg>
              </span>
            </label>

            <!-- AI Voice -->
            <label data-itype="ai_voice"
                   class="itype-card relative flex flex-col gap-2 rounded-xl border border-gray-300 p-4 cursor-pointer transition hover:border-violet-300 hover:bg-violet-50/40 focus-within:ring-2 focus-within:ring-violet-500">
              <input type="radio" name="interview_type" value="ai_voice" class="sr-only" />
              <span class="itype-icon inline-flex w-9 h-9 rounded-lg bg-gray-100 text-gray-500 items-center justify-center transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v3m0-3a4 4 0 01-4-4V7a4 4 0 118 0v4a4 4 0 01-4 4z"/></svg>
              </span>
              <span class="font-semibold text-gray-900 text-sm">AI Voice</span>
              <span class="text-xs text-gray-500 leading-snug">Spoken phone-style voice interview.</span>
              <span class="itype-check absolute top-3 end-3 text-violet-600 opacity-0 transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd"/></svg>
              </span>
            </label>

            <!-- AI Video -->
            <label data-itype="ai_video"
                   class="itype-card relative flex flex-col gap-2 rounded-xl border border-gray-300 p-4 cursor-pointer transition hover:border-violet-300 hover:bg-violet-50/40 focus-within:ring-2 focus-within:ring-violet-500">
              <input type="radio" name="interview_type" value="ai_video" class="sr-only" />
              <span class="itype-icon inline-flex w-9 h-9 rounded-lg bg-gray-100 text-gray-500 items-center justify-center transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
              </span>
              <span class="font-semibold text-gray-900 text-sm">AI Video</span>
              <span class="text-xs text-gray-500 leading-snug">Avatar-led on-camera video interview.</span>
              <span class="itype-check absolute top-3 end-3 text-violet-600 opacity-0 transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z" clip-rule="evenodd"/></svg>
              </span>
            </label>
          </div>

          <!-- Avatar selection (revealed for AI Video) -->
          <div id="avatar-block" class="hidden mt-5 pt-5 border-t border-gray-100">
            <label for="avatar-select" class="block text-sm font-semibold text-gray-700 mb-1.5">Interview Avatar</label>
            <select id="avatar-select"
                    class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 bg-white transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
              <option value="">Loading avatars…</option>
            </select>
            <p class="text-xs text-gray-400 mt-1.5">The selected avatar will host the video interview.</p>
          </div>
        </div>

        <!-- Question Bank -->
        <div class="card p-6 mb-6">
          <div class="flex items-center gap-2 mb-1.5">
            <span class="inline-flex w-7 h-7 rounded-lg bg-violet-50 text-violet-600 items-center justify-center">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
            </span>
            <h2 class="text-base font-semibold text-gray-900">Question Bank</h2>
          </div>
          <p class="text-sm text-gray-500 mb-4">Add custom questions the AI interviewer should ask. Leave empty to let AI decide.</p>

          <div class="flex flex-col sm:flex-row gap-2 mb-4">
            <input type="text" id="q-input" placeholder="Add an interview question…"
                   class="flex-1 rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
            <button type="button" id="q-add" class="btn-ghost justify-center shrink-0">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
              Add
            </button>
          </div>

          <ul id="q-list" class="space-y-2"></ul>
          <div id="q-empty" class="rounded-lg border border-dashed border-gray-200 py-6 text-center text-sm text-gray-400">
            No questions added yet.
          </div>
        </div>

        <!-- Submit footer -->
        <div class="card p-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
          <a href="/jobs" class="btn-ghost justify-center sm:justify-start">Cancel</a>
          <button type="submit" id="save-job" class="btn-primary justify-center">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            <span class="save-label">Save Job</span>
          </button>
        </div>
      </form>
    </div>

    <!-- ======== RIGHT: AI Job Builder ======== -->
    <div class="lg:col-span-1">
      <div class="card overflow-hidden lg:sticky lg:top-6">
        <!-- Gradient header strip -->
        <div class="gradient-brand p-5 text-white">
          <div class="flex items-center gap-2.5">
            <span class="inline-flex w-9 h-9 rounded-lg bg-white/15 items-center justify-center">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>
            </span>
            <div>
              <h2 class="font-bold text-base leading-tight">AI Job Builder</h2>
              <p class="text-xs text-white/80 leading-tight mt-0.5">Describe the role and AI will fill the form.</p>
            </div>
          </div>
        </div>

        <div class="p-5">
          <label for="ai-prompt" class="block text-sm font-semibold text-gray-700 mb-1.5">Role brief</label>
          <textarea id="ai-prompt" rows="5"
                    placeholder="e.g. 'Senior backend engineer, Python, Riyadh, 6+ yrs, remote-friendly, fintech.'"
                    class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 leading-relaxed transition focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"></textarea>

          <button type="button" id="ai-build" class="btn-accent w-full justify-center mt-3">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
            <span class="build-label">Build with AI</span>
          </button>

          <p class="flex items-start gap-1.5 text-xs text-gray-400 mt-3">
            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
            <span>Building will overwrite the current form fields.</span>
          </p>

          <!-- Results -->
          <div id="ai-results" class="hidden mt-5 pt-5 border-t border-gray-100 fade-in">
            <div class="flex items-center gap-1.5 mb-3">
              <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <h3 class="text-sm font-semibold text-gray-900">Generated draft</h3>
            </div>

            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Evaluation criteria</p>
            <div id="ai-criteria" class="space-y-2 mb-4"></div>

            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Suggested questions</p>
            <ul id="ai-questions" class="space-y-1.5 text-sm text-gray-600 list-none"></ul>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  'use strict';
  <?php if ($editing): ?>
  var EDITING = true, JOB_ID = <?= $jobId ?>;
  <?php else: ?>
  var EDITING = false, JOB_ID = 0;
  <?php endif; ?>

  const $ = (id) => document.getElementById(id);

  document.addEventListener('DOMContentLoaded', function () {
    const titleEl  = $('f-title');
    const descEl   = $('f-description');
    const reqEl    = $('f-requirements');
    const deptEl   = $('f-department');
    const locEl    = $('f-location');
    const typeEl   = $('f-job_type');
    const curEl    = $('f-currency');
    const minEl    = $('f-salary_min');
    const maxEl    = $('f-salary_max');
    const avatarBlock  = $('avatar-block');
    const avatarSelect = $('avatar-select');
    const qInput   = $('q-input');
    const qList    = $('q-list');
    const qEmpty   = $('q-empty');

    // ---------- Question bank state ----------
    let questions = [];

    function renderQuestions() {
      if (!questions.length) {
        qList.innerHTML = '';
        qEmpty.classList.remove('hidden');
        return;
      }
      qEmpty.classList.add('hidden');
      qList.innerHTML = questions.map(function (q, i) {
        return '<li class="flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50/60 px-3.5 py-2.5 group hover:border-violet-200 transition">' +
            '<span class="inline-flex w-5 h-5 shrink-0 mt-0.5 rounded-full bg-violet-100 text-violet-700 text-[11px] font-bold items-center justify-center">' + (i + 1) + '</span>' +
            '<span class="flex-1 text-sm text-gray-700 leading-snug">' + AR.esc(q) + '</span>' +
            '<button type="button" data-remove="' + i + '" aria-label="Remove question" ' +
              'class="shrink-0 text-gray-400 hover:text-red-500 transition rounded-md p-0.5">' +
              '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' +
            '</button>' +
          '</li>';
      }).join('');
    }

    function addQuestion() {
      const v = (qInput.value || '').trim();
      if (!v) return;
      questions.push(v);
      qInput.value = '';
      renderQuestions();
      qInput.focus();
    }

    $('q-add').addEventListener('click', addQuestion);
    qInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); addQuestion(); }
    });
    qList.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-remove]');
      if (!btn) return;
      const idx = parseInt(btn.getAttribute('data-remove'), 10);
      if (!isNaN(idx)) { questions.splice(idx, 1); renderQuestions(); }
    });
    renderQuestions();

    // ---------- Interview type cards ----------
    const cards = Array.prototype.slice.call(document.querySelectorAll('.itype-card'));
    let avatarsLoaded = false;

    function selectType(value, focusCard) {
      cards.forEach(function (card) {
        const radio = card.querySelector('input[type=radio]');
        const on = card.getAttribute('data-itype') === value;
        radio.checked = on;
        card.classList.toggle('border-violet-500', on);
        card.classList.toggle('ring-2', on);
        card.classList.toggle('ring-violet-500', on);
        card.classList.toggle('bg-violet-50', on);
        const icon = card.querySelector('.itype-icon');
        icon.classList.toggle('bg-violet-600', on);
        icon.classList.toggle('text-white', on);
        icon.classList.toggle('bg-gray-100', !on);
        icon.classList.toggle('text-gray-500', !on);
        const check = card.querySelector('.itype-check');
        check.classList.toggle('opacity-0', !on);
        if (on && focusCard) card.focus();
      });
      if (value === 'ai_video') {
        avatarBlock.classList.remove('hidden');
        loadAvatars();
      } else {
        avatarBlock.classList.add('hidden');
      }
    }

    async function loadAvatars() {
      if (avatarsLoaded) return;
      avatarsLoaded = true;
      try {
        const list = await AR.Api.get('/avatars');
        const arr = Array.isArray(list) ? list : [];
        if (!arr.length) {
          avatarSelect.innerHTML = '<option value="" disabled selected>No avatars available</option>';
          return;
        }
        const want = avatarSelect.getAttribute('data-want') || '';
        avatarSelect.innerHTML = arr.map(function (a) {
          const id = a.id != null ? String(a.id) : '';
          const sel = want && want === id ? ' selected' : '';
          return '<option value="' + AR.esc(id) + '"' + sel + '>' + AR.esc(a.name || ('Avatar ' + id)) + '</option>';
        }).join('');
      } catch (err) {
        avatarsLoaded = false; // allow retry on next reveal
        avatarSelect.innerHTML = '<option value="" disabled selected>Could not load avatars</option>';
        AR.Toast.error(err.message || 'Failed to load avatars');
      }
    }

    cards.forEach(function (card) {
      card.addEventListener('click', function (e) {
        e.preventDefault();
        selectType(card.getAttribute('data-itype'), false);
      });
      card.setAttribute('tabindex', '0');
      card.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectType(card.getAttribute('data-itype'), false); }
      });
    });
    selectType('ai_text', false); // default

    function currentType() {
      const checked = document.querySelector('input[name="interview_type"]:checked');
      return checked ? checked.value : 'ai_text';
    }

    // ---------- Helpers to fill the form ----------
    function setSelectIfPresent(sel, value) {
      if (value == null || value === '') return;
      const v = String(value);
      for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === v) { sel.value = v; return; }
      }
    }
    function normQuestion(item) {
      if (item == null) return '';
      if (typeof item === 'string') return item;
      return String(item.question || item.text || item.criterion_name || JSON.stringify(item));
    }
    function fillForm(d) {
      if (!d || typeof d !== 'object') return;
      if (d.title != null) titleEl.value = d.title;
      if (d.description != null) descEl.value = d.description;
      if (d.requirements != null) reqEl.value = d.requirements;
      if (d.department != null) deptEl.value = d.department;
      if (d.location != null) locEl.value = d.location;
      setSelectIfPresent(typeEl, d.job_type);
      setSelectIfPresent(curEl, d.currency);
      if (d.salary_min != null && d.salary_min !== '') minEl.value = d.salary_min;
      if (d.salary_max != null && d.salary_max !== '') maxEl.value = d.salary_max;
    }

    // ---------- AI results rendering ----------
    function weightBadge(w) {
      if (w == null || w === '') return '';
      let n = Number(w);
      if (isNaN(n)) return '<span class="badge badge-violet">' + AR.esc(String(w)) + '</span>';
      if (n > 0 && n <= 1) n = Math.round(n * 100);
      else n = Math.round(n);
      return '<span class="badge badge-violet">' + n + '%</span>';
    }
    function renderAiResults(criteria, qs) {
      const cBox = $('ai-criteria');
      const crit = Array.isArray(criteria) ? criteria : [];
      if (!crit.length) {
        cBox.innerHTML = '<p class="text-xs text-gray-400">No evaluation criteria generated.</p>';
      } else {
        cBox.innerHTML = crit.map(function (c) {
          return '<div class="rounded-lg border border-gray-200 p-3">' +
              '<div class="flex items-center justify-between gap-2 mb-1">' +
                '<span class="text-sm font-semibold text-gray-900">' + AR.esc(c.criterion_name || c.name || 'Criterion') + '</span>' +
                weightBadge(c.weight) +
              '</div>' +
              (c.description ? '<p class="text-xs text-gray-500 leading-snug">' + AR.esc(c.description) + '</p>' : '') +
            '</div>';
        }).join('');
      }
      const qBox = $('ai-questions');
      const list = Array.isArray(qs) ? qs : [];
      if (!list.length) {
        qBox.innerHTML = '<li class="text-xs text-gray-400">No questions generated.</li>';
      } else {
        qBox.innerHTML = list.slice(0, 20).map(function (q) {
          return '<li class="flex items-start gap-1.5">' +
              '<span class="text-violet-500 mt-1">&bull;</span>' +
              '<span class="leading-snug">' + AR.esc(normQuestion(q)) + '</span>' +
            '</li>';
        }).join('');
      }
      $('ai-results').classList.remove('hidden');
    }

    // ---------- Build with AI ----------
    const buildBtn = $('ai-build');
    const buildLabel = buildBtn.querySelector('.build-label');
    buildBtn.addEventListener('click', async function () {
      const prompt = ($('ai-prompt').value || '').trim();
      if (!prompt) { AR.Toast.error('Describe the role first'); $('ai-prompt').focus(); return; }
      buildBtn.disabled = true;
      buildBtn.classList.add('opacity-70', 'cursor-wait');
      const prev = buildLabel.textContent;
      buildLabel.textContent = 'Building…';
      try {
        const d = await AR.Api.post('/ai/build-job', { prompt: prompt });
        fillForm(d || {});
        // Reset interview cards to default selection.
        selectType('ai_text', false);
        // Replace question bank from generated question_bank.
        const qb = (d && Array.isArray(d.question_bank)) ? d.question_bank : [];
        questions = qb.map(normQuestion).filter(function (s) { return s && s.length; });
        renderQuestions();
        renderAiResults(d && d.ai_criteria, questions);
        AR.Toast.success('Draft generated');
      } catch (err) {
        AR.Toast.error(err.message || 'Could not generate draft');
      } finally {
        buildBtn.disabled = false;
        buildBtn.classList.remove('opacity-70', 'cursor-wait');
        buildLabel.textContent = prev;
      }
    });

    // ---------- Prefill when editing ----------
    async function loadJobForEdit() {
      try {
        const job = await AR.Api.get('/jobs/' + JOB_ID);
        if (!job || typeof job !== 'object') return;
        fillForm(job);
        const it = job.interview_type || 'ai_text';
        if (it === 'ai_video' && job.avatar_id != null) {
          avatarSelect.setAttribute('data-want', String(job.avatar_id));
        }
        selectType(it, false);
        const qb = job.question_bank;
        if (Array.isArray(qb) && qb.length) {
          questions = qb.map(normQuestion).filter(function (s) { return s && s.length; });
          renderQuestions();
        }
      } catch (err) {
        AR.Toast.error(err.message || 'Could not load this job');
      }
    }
    if (EDITING && JOB_ID) loadJobForEdit();

    // ---------- Save ----------
    const form = $('job-form');
    const saveBtn = $('save-job');
    const saveLabel = saveBtn.querySelector('.save-label');

    function numOrNull(el) {
      const v = (el.value || '').trim();
      if (v === '') return null;
      const n = Number(v);
      return isNaN(n) ? null : n;
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const title = (titleEl.value || '').trim();
      if (!title) { AR.Toast.error('Title is required'); titleEl.focus(); return; }

      const itype = currentType();
      const payload = {
        title: title,
        description: (descEl.value || '').trim(),
        requirements: (reqEl.value || '').trim(),
        department: (deptEl.value || '').trim(),
        location: (locEl.value || '').trim(),
        job_type: typeEl.value,
        salary_min: numOrNull(minEl),
        salary_max: numOrNull(maxEl),
        currency: curEl.value,
        interview_type: itype,
        avatar_id: itype === 'ai_video' ? (avatarSelect.value || null) : null,
        question_bank: questions.slice()
      };

      saveBtn.disabled = true;
      saveBtn.classList.add('opacity-70', 'cursor-wait');
      const prev = saveLabel.textContent;
      saveLabel.textContent = 'Saving…';
      try {
        if (EDITING && JOB_ID) {
          await AR.Api.put('/jobs/' + JOB_ID, payload);
          AR.Toast.success('Job updated');
        } else {
          await AR.Api.post('/jobs', payload);
          AR.Toast.success('Job created');
        }
        window.location = '/jobs';
      } catch (err) {
        AR.Toast.error(err.message || 'Could not save job');
        saveBtn.disabled = false;
        saveBtn.classList.remove('opacity-70', 'cursor-wait');
        saveLabel.textContent = prev;
      }
    });
  });
})();
</script>
