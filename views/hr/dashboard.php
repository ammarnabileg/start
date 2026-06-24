<?php
/**
 * HR Dashboard — hiring snapshot, recent applications, quick actions,
 * AI analysis summary, and an embedded AI Copilot chat modal.
 * Fragment: rendered into $content and wrapped by views/layouts/app.php.
 * Guarded vars: $__user (assoc array of current user, any key may be missing).
 */
$u = $__user ?? [];
$name = trim((string)($u['first_name'] ?? ''));
?>
<div class="space-y-6">

  <!-- ============ Page header ============ -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="text-3xl font-bold tracking-tight text-gray-900">Dashboard</h1>
      <p class="mt-1 text-gray-500">
        <?= $name !== '' ? ('Welcome back, ' . e($name) . '.') : 'Welcome back.' ?>
        Here's your hiring snapshot.
      </p>
    </div>
    <div class="flex items-center gap-3">
      <button type="button" data-modal-open="copilot-modal" class="btn-accent">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
        </svg>
        <span>AI Copilot</span>
      </button>
      <a href="/jobs/create" class="btn-primary">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span>New Job</span>
      </a>
    </div>
  </div>

  <!-- ============ Stat cards ============ -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

    <!-- Open Jobs -->
    <div class="card p-5 fade-in">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 shrink-0 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25a2.25 2.25 0 00-2.25-2.25h-4.5A2.25 2.25 0 007.5 5.25v1.5m13.5 0H3.75a1.5 1.5 0 00-1.5 1.5v3.026c0 .55.27 1.06.71 1.39l.01.01a17.93 17.93 0 0019.06 0l.01-.01c.44-.33.71-.84.71-1.39V8.25a1.5 1.5 0 00-1.5-1.5z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-3xl font-bold text-gray-900 leading-none">
            <span id="stat-open-jobs" class="skeleton inline-block w-12 h-8 align-middle"></span>
          </div>
          <p class="mt-1.5 text-sm font-medium text-gray-500">Open Jobs</p>
          <p class="text-xs text-gray-400">published roles</p>
        </div>
      </div>
    </div>

    <!-- Total Candidates -->
    <div class="card p-5 fade-in">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 shrink-0 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-3xl font-bold text-gray-900 leading-none">
            <span id="stat-total-candidates" class="skeleton inline-block w-12 h-8 align-middle"></span>
          </div>
          <p class="mt-1.5 text-sm font-medium text-gray-500">Total Candidates</p>
          <p class="text-xs text-gray-400">across all roles</p>
        </div>
      </div>
    </div>

    <!-- Pending Interviews -->
    <div class="card p-5 fade-in">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 shrink-0 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-3xl font-bold text-gray-900 leading-none">
            <span id="stat-pending-interviews" class="skeleton inline-block w-12 h-8 align-middle"></span>
          </div>
          <p class="mt-1.5 text-sm font-medium text-gray-500">Pending Interviews</p>
          <p class="text-xs text-gray-400">awaiting completion</p>
        </div>
      </div>
    </div>

    <!-- Offers Made -->
    <div class="card p-5 fade-in">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 shrink-0 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="text-3xl font-bold text-gray-900 leading-none">
            <span id="stat-offers-made" class="skeleton inline-block w-12 h-8 align-middle"></span>
          </div>
          <p class="mt-1.5 text-sm font-medium text-gray-500">Offers Made</p>
          <p class="text-xs text-gray-400">extended to date</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ Main grid ============ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT: Recent Applications -->
    <div class="lg:col-span-2">
      <div class="card">
        <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-gray-100">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Recent Applications</h2>
            <p class="text-sm text-gray-400">Latest candidates entering your pipeline</p>
          </div>
          <a href="/candidates" class="inline-flex items-center gap-1 text-sm font-semibold text-brand hover:text-brand-dark transition">
            View all
            <svg class="w-4 h-4 rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
          </a>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-start text-xs font-semibold uppercase tracking-wider text-gray-400 border-b border-gray-100">
                <th class="px-5 py-3 text-start font-semibold">Name</th>
                <th class="px-5 py-3 text-start font-semibold">Job</th>
                <th class="px-5 py-3 text-start font-semibold">AI Score</th>
                <th class="px-5 py-3 text-start font-semibold">Stage</th>
                <th class="px-5 py-3 text-start font-semibold">Applied</th>
              </tr>
            </thead>
            <tbody id="recent-apps-body" class="divide-y divide-gray-100">
              <!-- skeleton rows (replaced by JS) -->
              <tr>
                <td class="px-5 py-3.5"><div class="flex items-center gap-3"><span class="skeleton w-9 h-9 rounded-full"></span><span class="skeleton inline-block w-32 h-4"></span></div></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-24 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-12 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-20 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-16 h-4"></span></td>
              </tr>
              <tr>
                <td class="px-5 py-3.5"><div class="flex items-center gap-3"><span class="skeleton w-9 h-9 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span></div></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-24 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-12 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-20 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-16 h-4"></span></td>
              </tr>
              <tr>
                <td class="px-5 py-3.5"><div class="flex items-center gap-3"><span class="skeleton w-9 h-9 rounded-full"></span><span class="skeleton inline-block w-36 h-4"></span></div></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-24 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-12 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-20 h-4"></span></td>
                <td class="px-5 py-3.5"><span class="skeleton inline-block w-16 h-4"></span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- RIGHT: Quick Actions + AI Summary -->
    <div class="space-y-6">

      <!-- Quick Actions -->
      <div class="card p-5">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Quick Actions</h2>
        <div class="space-y-1">
          <a href="/jobs/create" class="flex items-center gap-3 rounded-xl p-3 hover:bg-gray-50 transition group">
            <span class="w-10 h-10 shrink-0 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-gray-900">Post a Job</span>
              <span class="block text-xs text-gray-400">Create and publish a new role</span>
            </span>
            <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500 transition rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
          </a>

          <a href="/candidates" class="flex items-center gap-3 rounded-xl p-3 hover:bg-gray-50 transition group">
            <span class="w-10 h-10 shrink-0 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-gray-900">Browse Candidates</span>
              <span class="block text-xs text-gray-400">Search and review applicants</span>
            </span>
            <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500 transition rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
          </a>

          <a href="/pipeline" class="flex items-center gap-3 rounded-xl p-3 hover:bg-gray-50 transition group">
            <span class="w-10 h-10 shrink-0 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-gray-900">Open Pipeline</span>
              <span class="block text-xs text-gray-400">Manage stages and move candidates</span>
            </span>
            <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500 transition rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
          </a>

          <button type="button" data-modal-open="copilot-modal" class="w-full text-start flex items-center gap-3 rounded-xl p-3 hover:bg-gray-50 transition group">
            <span class="w-10 h-10 shrink-0 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-gray-900">Ask AI Copilot</span>
              <span class="block text-xs text-gray-400">Get sourcing & screening help</span>
            </span>
            <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500 transition rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
          </button>
        </div>
      </div>

      <!-- AI Analysis Summary -->
      <div class="card p-5">
        <div class="flex items-center gap-2 mb-3">
          <span class="w-8 h-8 rounded-lg gradient-brand flex items-center justify-center text-white">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
          </span>
          <h2 class="text-lg font-semibold text-gray-900">AI Analysis Summary</h2>
        </div>
        <div id="ai-summary" class="space-y-3">
          <div class="flex items-center gap-3"><span class="skeleton w-2.5 h-2.5 rounded-full"></span><span class="skeleton inline-block h-4 flex-1"></span></div>
          <div class="flex items-center gap-3"><span class="skeleton w-2.5 h-2.5 rounded-full"></span><span class="skeleton inline-block h-4 flex-1"></span></div>
          <div class="flex items-center gap-3"><span class="skeleton w-2.5 h-2.5 rounded-full"></span><span class="skeleton inline-block h-4 flex-1"></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ AI Copilot modal (chat) ============ -->
<div id="copilot-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card max-w-lg overflow-hidden flex flex-col" style="max-height:90vh">
    <!-- Header -->
    <div class="gradient-brand text-white px-5 py-4 flex items-center gap-3">
      <span class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
      </span>
      <div class="flex-1 min-w-0">
        <p class="font-semibold leading-tight">AI Copilot</p>
        <p class="text-xs text-white/70">Recruitment assistant</p>
      </div>
      <button type="button" data-modal-close="copilot-modal" aria-label="Close"
              class="w-8 h-8 rounded-full hover:bg-white/15 flex items-center justify-center transition">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>

    <!-- Messages -->
    <div id="copilot-messages" class="h-80 overflow-y-auto p-4 space-y-3 bg-gray-50">
      <div class="flex items-start gap-2.5">
        <span class="w-8 h-8 shrink-0 rounded-full gradient-brand flex items-center justify-center text-white">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
        </span>
        <div class="max-w-[80%] rounded-2xl rounded-tl-sm bg-white border border-gray-200 px-3.5 py-2.5 text-sm text-gray-700 shadow-sm">
          Hi! I'm your recruitment copilot. Ask me about sourcing, screening, or your pipeline.
        </div>
      </div>
    </div>

    <!-- Footer form -->
    <form id="copilot-form" class="border-t border-gray-100 p-3 flex items-center gap-2">
      <input id="copilot-input" type="text" autocomplete="off" placeholder="Ask anything…"
             class="flex-1 rounded-full border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand transition" />
      <button id="copilot-send" type="submit" aria-label="Send"
              class="w-10 h-10 shrink-0 rounded-full bg-brand text-white flex items-center justify-center hover:bg-brand-dark transition disabled:opacity-50 disabled:cursor-not-allowed">
        <svg class="w-5 h-5 rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
      </button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var AR = window.AR;

  // ---------------------------------------------------------------- helpers
  function prettyStage(s) {
    if (!s) return '';
    return String(s).replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }
  function shortDate(x) {
    if (!x) return '—';
    var d = new Date(x);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }
  function initials(first, last, email) {
    var a = (first || '').trim();
    var b = (last || '').trim();
    var out = (a ? a[0] : '') + (b ? b[0] : '');
    if (!out && email) out = email[0];
    return (out || '?').toUpperCase();
  }
  function setStat(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('skeleton');
    el.className = 'align-middle';
    el.textContent = value;
  }

  // ---------------------------------------------------------------- recent apps
  function renderRecentApps(candidates) {
    var body = document.getElementById('recent-apps-body');
    if (!body) return;

    var list = Array.isArray(candidates) ? candidates.slice() : [];
    list.sort(function (a, b) {
      var ad = new Date(a.applied_at || a.created_at || 0).getTime() || 0;
      var bd = new Date(b.applied_at || b.created_at || 0).getTime() || 0;
      return bd - ad;
    });
    list = list.slice(0, 8);

    if (!list.length) {
      body.innerHTML =
        '<tr><td colspan="5" class="px-5 py-12 text-center">' +
          '<div class="flex flex-col items-center gap-3 text-gray-400">' +
            '<span class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">' +
              '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>' +
            '</span>' +
            '<p class="text-sm font-medium text-gray-500">No applications yet</p>' +
            '<p class="text-xs text-gray-400">New candidates will appear here as they apply.</p>' +
          '</div>' +
        '</td></tr>';
      return;
    }

    var rows = list.map(function (c) {
      var id = c.id != null ? c.id : '';
      var fullName = ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || 'Unnamed';
      var email = c.email || '';
      var job = c.job_title || '—';

      var score = c.ai_match_score;
      var scoreCell;
      if (score === null || score === undefined || score === '' || isNaN(Number(score))) {
        scoreCell = '<span class="badge badge-gray">—</span>';
      } else {
        var n = Math.round(Number(score));
        scoreCell = '<span class="badge ' + AR.scoreColor(n) + '">' + n + '%</span>';
      }

      var stage = c.pipeline_stage ? prettyStage(c.pipeline_stage) : '';
      var stageCell = stage
        ? '<span class="badge badge-violet">' + AR.esc(stage) + '</span>'
        : '<span class="badge badge-gray">—</span>';

      var avatarInit = initials(c.first_name, c.last_name, email);
      var emailLine = email
        ? '<span class="block text-xs text-gray-400 truncate max-w-[180px]">' + AR.esc(email) + '</span>'
        : '';

      return '<tr data-href="/candidates/' + AR.esc(id) + '" class="cursor-pointer hover:bg-gray-50 transition">' +
        '<td class="px-5 py-3.5">' +
          '<div class="flex items-center gap-3 min-w-0">' +
            '<span class="w-9 h-9 shrink-0 rounded-full gradient-brand flex items-center justify-center text-white text-xs font-bold">' + AR.esc(avatarInit) + '</span>' +
            '<span class="min-w-0">' +
              '<span class="block text-sm font-medium text-gray-900 truncate max-w-[180px]">' + AR.esc(fullName) + '</span>' +
              emailLine +
            '</span>' +
          '</div>' +
        '</td>' +
        '<td class="px-5 py-3.5 text-gray-600">' + AR.esc(job) + '</td>' +
        '<td class="px-5 py-3.5">' + scoreCell + '</td>' +
        '<td class="px-5 py-3.5">' + stageCell + '</td>' +
        '<td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">' + AR.esc(shortDate(c.applied_at || c.created_at)) + '</td>' +
      '</tr>';
    }).join('');

    body.innerHTML = rows;

    body.querySelectorAll('tr[data-href]').forEach(function (tr) {
      tr.addEventListener('click', function () {
        var href = tr.getAttribute('data-href');
        if (href) window.location = href;
      });
    });
  }

  // ---------------------------------------------------------------- ai summary
  function renderAiSummary(candidates) {
    var box = document.getElementById('ai-summary');
    if (!box) return;

    var list = Array.isArray(candidates) ? candidates : [];
    if (!list.length) {
      box.innerHTML = '<p class="text-sm text-gray-400">Insights will appear once you have candidates.</p>';
      return;
    }

    var scored = list.filter(function (c) {
      var s = c.ai_match_score;
      return s !== null && s !== undefined && s !== '' && !isNaN(Number(s));
    });
    var above75 = scored.filter(function (c) { return Number(c.ai_match_score) >= 75; }).length;

    var interviewStages = ['interview', 'interviewing', 'interviewed', 'screening', 'screen', 'assessment'];
    var inInterview = list.filter(function (c) {
      var st = (c.pipeline_stage || '').toLowerCase();
      return interviewStages.some(function (k) { return st.indexOf(k) !== -1; });
    }).length;

    var avg = scored.length
      ? Math.round(scored.reduce(function (sum, c) { return sum + Number(c.ai_match_score); }, 0) / scored.length)
      : null;

    function line(dotClass, html) {
      return '<div class="flex items-start gap-3">' +
        '<span class="mt-1.5 w-2.5 h-2.5 shrink-0 rounded-full ' + dotClass + '"></span>' +
        '<p class="text-sm text-gray-600">' + html + '</p>' +
      '</div>';
    }

    var parts = [];
    parts.push(line('bg-violet-500', '<b class="text-gray-900">' + above75 + '</b> candidate' + (above75 === 1 ? '' : 's') + ' scored above 75%'));
    parts.push(line('bg-amber-500', '<b class="text-gray-900">' + inInterview + '</b> ' + (inInterview === 1 ? 'is' : 'are') + ' in interview stages'));
    if (avg !== null) {
      parts.push(line('bg-violet-500', 'Average AI match score is <b class="text-gray-900">' + avg + '%</b>'));
    } else {
      parts.push(line('bg-gray-400', 'No AI match scores recorded yet'));
    }

    box.innerHTML = parts.join('');
  }

  // ---------------------------------------------------------------- load stats
  (function loadDashboard() {
    Promise.allSettled([
      AR.Api.get('/jobs'),
      AR.Api.get('/candidates'),
      AR.Api.get('/interviews'),
      AR.Api.get('/offers')
    ]).then(function (results) {
      var jobsR = results[0], candsR = results[1], intsR = results[2], offersR = results[3];

      // Open Jobs
      if (jobsR.status === 'fulfilled' && Array.isArray(jobsR.value)) {
        var openJobs = jobsR.value.filter(function (j) { return j && j.status === 'published'; }).length;
        setStat('stat-open-jobs', String(openJobs));
      } else {
        setStat('stat-open-jobs', '—');
      }

      // Total Candidates (+ recent apps + ai summary)
      var candidates = (candsR.status === 'fulfilled' && Array.isArray(candsR.value)) ? candsR.value : null;
      if (candidates) {
        setStat('stat-total-candidates', String(candidates.length));
        renderRecentApps(candidates);
        renderAiSummary(candidates);
      } else {
        setStat('stat-total-candidates', '—');
        renderRecentApps([]);
        var box = document.getElementById('ai-summary');
        if (box) box.innerHTML = '<p class="text-sm text-gray-400">Could not load candidate insights.</p>';
      }

      // Pending Interviews
      if (intsR.status === 'fulfilled' && Array.isArray(intsR.value)) {
        var doneStates = ['completed', 'done', 'evaluated', 'finished', 'cancelled'];
        var pending = intsR.value.filter(function (i) {
          return doneStates.indexOf(((i && i.status) || '').toLowerCase()) === -1;
        }).length;
        setStat('stat-pending-interviews', String(pending));
      } else {
        setStat('stat-pending-interviews', '—');
      }

      // Offers Made
      if (offersR.status === 'fulfilled' && Array.isArray(offersR.value)) {
        setStat('stat-offers-made', String(offersR.value.length));
      } else {
        setStat('stat-offers-made', '—');
      }
    });
  })();

  // ---------------------------------------------------------------- copilot chat
  (function copilot() {
    var form = document.getElementById('copilot-form');
    var input = document.getElementById('copilot-input');
    var messages = document.getElementById('copilot-messages');
    var sendBtn = document.getElementById('copilot-send');
    if (!form || !input || !messages) return;

    var history = [];

    function scrollDown() { messages.scrollTop = messages.scrollHeight; }

    function appendUser(text) {
      var wrap = document.createElement('div');
      wrap.className = 'flex justify-end';
      wrap.innerHTML =
        '<div class="max-w-[80%] rounded-2xl rounded-tr-sm bg-brand text-white px-3.5 py-2.5 text-sm shadow-sm">' +
          AR.esc(text) +
        '</div>';
      messages.appendChild(wrap);
      scrollDown();
    }

    function appendAssistant(text) {
      var wrap = document.createElement('div');
      wrap.className = 'flex items-start gap-2.5';
      wrap.innerHTML =
        '<span class="w-8 h-8 shrink-0 rounded-full gradient-brand flex items-center justify-center text-white">' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>' +
        '</span>' +
        '<div class="max-w-[80%] rounded-2xl rounded-tl-sm bg-white border border-gray-200 px-3.5 py-2.5 text-sm text-gray-700 shadow-sm whitespace-pre-wrap">' +
          AR.esc(text) +
        '</div>';
      messages.appendChild(wrap);
      scrollDown();
    }

    function appendError(text) {
      var wrap = document.createElement('div');
      wrap.className = 'flex items-start gap-2.5';
      wrap.innerHTML =
        '<span class="w-8 h-8 shrink-0 rounded-full bg-red-100 text-red-600 flex items-center justify-center">' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>' +
        '</span>' +
        '<div class="max-w-[80%] rounded-2xl rounded-tl-sm bg-red-50 border border-red-100 px-3.5 py-2.5 text-sm text-red-700">' +
          AR.esc(text) +
        '</div>';
      messages.appendChild(wrap);
      scrollDown();
    }

    function showTyping() {
      var wrap = document.createElement('div');
      wrap.className = 'flex items-start gap-2.5';
      wrap.setAttribute('data-typing', '1');
      wrap.innerHTML =
        '<span class="w-8 h-8 shrink-0 rounded-full gradient-brand flex items-center justify-center text-white">' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>' +
        '</span>' +
        '<div class="rounded-2xl rounded-tl-sm bg-white border border-gray-200 px-3.5 py-3 shadow-sm">' +
          '<div class="typing"><span></span><span></span><span></span></div>' +
        '</div>';
      messages.appendChild(wrap);
      scrollDown();
      return wrap;
    }

    function capHistory() {
      if (history.length > 12) history = history.slice(history.length - 12);
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text) return;

      appendUser(text);
      history.push({ role: 'user', content: text });
      capHistory();
      input.value = '';

      sendBtn.disabled = true;
      var typing = showTyping();

      AR.Api.post('/ai/copilot', { message: text, history: history })
        .then(function (reply) {
          if (typing && typing.parentNode) typing.parentNode.removeChild(typing);
          var answer = (reply && reply.reply) ? reply.reply : 'I did not receive a response. Please try again.';
          appendAssistant(answer);
          history.push({ role: 'assistant', content: answer });
          capHistory();
        })
        .catch(function (err) {
          if (typing && typing.parentNode) typing.parentNode.removeChild(typing);
          AR.Toast.error((err && err.message) || 'Copilot is unavailable right now.');
          appendError('Sorry, I could not reach the copilot. Please try again.');
        })
        .finally(function () {
          sendBtn.disabled = false;
          input.focus();
        });
    });
  })();
});
</script>
