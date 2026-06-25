<?php
/**
 * Super Admin – Platform Settings View
 * Rendered inside the super-admin layout (no <html>/<body> tags).
 */
?>
<meta name="csrf" content="<?= $req->csrf() ?>">

<!-- Toast container -->
<div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-3 pointer-events-none"></div>

<div class="max-w-5xl mx-auto px-4 py-8 space-y-8">

  <!-- Page Heading -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Platform Settings</h1>
      <p class="mt-1 text-sm text-gray-500">Manage global configuration for the AI Recruitment platform.</p>
    </div>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-200">
      Super Admin
    </span>
  </div>

  <!-- ================================================================
       SECTION 1 · Platform Info
  ================================================================ -->
  <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
        </svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-gray-900">Platform Info</h2>
        <p class="text-xs text-gray-500">Name and branding shown across the platform.</p>
      </div>
    </div>
    <div class="px-6 py-5 space-y-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label for="platform_name" class="block text-sm font-medium text-gray-700 mb-1">Platform Name</label>
          <input id="platform_name" type="text" placeholder="e.g. HireAI"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
        </div>
        <div>
          <label for="logo_url" class="block text-sm font-medium text-gray-700 mb-1">Logo URL</label>
          <input id="logo_url" type="url" placeholder="https://cdn.example.com/logo.png"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition">
        </div>
      </div>
      <!-- Logo preview -->
      <div id="logo-preview-wrap" class="hidden">
        <p class="text-xs text-gray-500 mb-2">Preview</p>
        <img id="logo-preview" src="" alt="Logo preview" class="h-12 object-contain rounded border border-gray-200 bg-gray-50 p-1">
      </div>
    </div>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
      <button onclick="savePlatformInfo(this)"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V7l-4-4z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3v4H9V3" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6" />
        </svg>
        Save Platform Info
      </button>
    </div>
  </div>

  <!-- ================================================================
       SECTION 2 · Default Plan Limits
  ================================================================ -->
  <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
        </svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-gray-900">Default Plan Limits</h2>
        <p class="text-xs text-gray-500">Default resource caps applied when a new tenant is assigned a plan.</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="px-6 py-3 text-left font-semibold text-gray-600 w-36">Plan</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Max Jobs</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Max Users</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">AI Interviews / mo</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Token Limit</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach (['basic' => ['Basic', 'bg-gray-100 text-gray-700'], 'pro' => ['Pro', 'bg-blue-100 text-blue-700'], 'enterprise' => ['Enterprise', 'bg-purple-100 text-purple-700']] as $planKey => $planMeta): ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-4">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $planMeta[1] ?>">
                <?= $planMeta[0] ?>
              </span>
            </td>
            <?php foreach (['max_jobs', 'max_users', 'ai_interviews_per_month', 'token_limit'] as $col): ?>
            <td class="px-4 py-3 text-center">
              <input type="number" min="0"
                id="plan_<?= $planKey ?>_<?= $col ?>"
                class="w-28 rounded-lg border border-gray-300 px-2 py-1.5 text-center text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition"
                placeholder="0">
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
      <button onclick="savePlanLimits(this)"
        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V7l-4-4z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3v4H9V3" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6" />
        </svg>
        Save Plan Limits
      </button>
    </div>
  </div>

  <!-- ================================================================
       SECTION 3 · SMTP Settings
  ================================================================ -->
  <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-gray-900">SMTP Settings</h2>
        <p class="text-xs text-gray-500">Outgoing mail server configuration for platform emails.</p>
      </div>
    </div>
    <div class="px-6 py-5 space-y-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label for="from_email" class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
          <input id="from_email" type="email" placeholder="no-reply@example.com"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
        </div>
        <div>
          <label for="from_name" class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
          <input id="from_name" type="text" placeholder="HireAI Notifications"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
        </div>
        <div>
          <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
          <input id="smtp_host" type="text" placeholder="smtp.mailgun.org"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
        </div>
        <div>
          <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
          <input id="smtp_port" type="number" placeholder="587"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
        </div>
        <div>
          <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
          <input id="smtp_username" type="text" placeholder="apikey"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
        </div>
        <div>
          <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
          <div class="relative">
            <input id="smtp_password" type="password" placeholder="••••••••••••"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-10 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
            <button type="button" onclick="toggleSmtpPassword()"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition"
              tabindex="-1">
              <svg id="eye-open" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <svg id="eye-closed" class="w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Test Email Target -->
      <div class="rounded-lg bg-amber-50 border border-amber-200 p-4">
        <p class="text-xs font-medium text-amber-800 mb-2">Send a test email to verify your SMTP configuration.</p>
        <div class="flex gap-3 items-end">
          <div class="flex-1">
            <label for="test_email_to" class="block text-xs font-medium text-gray-700 mb-1">Recipient Address</label>
            <input id="test_email_to" type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>"
              placeholder="admin@example.com"
              class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
          </div>
          <button onclick="sendTestEmail(this)"
            class="inline-flex items-center gap-2 rounded-lg border border-amber-400 bg-white px-4 py-2 text-sm font-semibold text-amber-700 shadow-sm hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 transition whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
            </svg>
            Send Test Email
          </button>
        </div>
      </div>
    </div>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
      <button onclick="saveSmtp(this)"
        class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V7l-4-4z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3v4H9V3" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6" />
        </svg>
        Save SMTP Settings
      </button>
    </div>
  </div>

  <!-- ================================================================
       SECTION 4 · Feature Flags
  ================================================================ -->
  <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
      <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-violet-600 flex items-center justify-center">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </div>
      <div>
        <h2 class="text-base font-semibold text-gray-900">Feature Flags</h2>
        <p class="text-xs text-gray-500">Toggle platform features per subscription plan.</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="px-6 py-3 text-left font-semibold text-gray-600">Feature</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">
              <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">Basic</span>
            </th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">
              <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">Pro</span>
            </th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">
              <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-700">Enterprise</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php
          $features = [
            'enable_ai_interviews'    => 'AI Interviews',
            'enable_video_interviews' => 'Video Interviews',
            'enable_offers'           => 'Offer Management',
            'enable_bulk_import'      => 'Bulk Import',
            'enable_analytics'        => 'Advanced Analytics',
            'enable_api_access'       => 'API Access',
          ];
          foreach ($features as $flagKey => $flagLabel):
          ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-3.5 font-medium text-gray-800"><?= htmlspecialchars($flagLabel) ?></td>
            <?php foreach (['basic', 'pro', 'enterprise'] as $plan): ?>
            <td class="px-4 py-3.5 text-center">
              <label class="relative inline-flex items-center justify-center cursor-pointer">
                <input type="checkbox"
                  id="flag_<?= $plan ?>_<?= $flagKey ?>"
                  class="sr-only peer"
                  data-flag="<?= $flagKey ?>"
                  data-plan="<?= $plan ?>">
                <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-violet-400
                            peer-checked:bg-violet-600 transition-colors duration-200 after:content-[''] after:absolute
                            after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4
                            after:transition-all after:duration-200 peer-checked:after:translate-x-4"></div>
              </label>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
      <button onclick="saveFeatureFlags(this)"
        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V7l-4-4z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 3v4H9V3" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6" />
        </svg>
        Save Feature Flags
      </button>
    </div>
  </div>

</div><!-- /max-w-5xl -->

<!-- ================================================================
     JavaScript
================================================================ -->
<script>
(function () {
  'use strict';

  /* ── helpers ────────────────────────────────────────────────────── */
  const csrf = () => document.querySelector('meta[name=csrf]').content;

  async function apiGet(url) {
    const res = await fetch(url, {
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf() }
    });
    return res.json();
  }

  async function apiPost(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf()
      },
      body: JSON.stringify(body)
    });
    return res.json();
  }

  /* ── toast ──────────────────────────────────────────────────────── */
  function toast(msg, type) {
    type = type || 'success';
    var container = document.getElementById('toast-container');
    var colours = {
      success: 'bg-emerald-600 text-white',
      error:   'bg-red-600 text-white',
      info:    'bg-indigo-600 text-white'
    };
    var icons = {
      success: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>',
      error:   '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
      info:    '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>'
    };
    var el = document.createElement('div');
    var colour = colours[type] || colours.info;
    var icon   = icons[type]   || icons.info;
    el.className = 'pointer-events-auto flex items-center gap-2 rounded-lg px-4 py-3 shadow-lg text-sm font-medium max-w-sm transition-all duration-300 opacity-0 translate-y-1 ' + colour;
    el.innerHTML = icon + '<span>' + msg + '</span>';
    container.appendChild(el);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        el.classList.remove('opacity-0', 'translate-y-1');
      });
    });
    setTimeout(function () {
      el.classList.add('opacity-0', 'translate-y-1');
      setTimeout(function () { el.remove(); }, 300);
    }, 4000);
  }

  /* ── button loading state ───────────────────────────────────────── */
  function btnLoading(btn, loading) {
    if (loading) {
      btn.dataset.origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Saving…';
    } else {
      btn.disabled = false;
      if (btn.dataset.origHtml) btn.innerHTML = btn.dataset.origHtml;
    }
  }

  /* ── populate a field safely ────────────────────────────────────── */
  function setVal(id, val) {
    var el = document.getElementById(id);
    if (el && val !== undefined && val !== null) el.value = val;
  }

  /* ── logo preview ───────────────────────────────────────────────── */
  function updateLogoPreview(url) {
    var wrap = document.getElementById('logo-preview-wrap');
    var img  = document.getElementById('logo-preview');
    if (url && url.length > 0) {
      img.src = url;
      wrap.classList.remove('hidden');
    } else {
      wrap.classList.add('hidden');
    }
  }

  document.getElementById('logo_url').addEventListener('input', function () {
    updateLogoPreview(this.value.trim());
  });

  /* ── SMTP password toggle ───────────────────────────────────────── */
  window.toggleSmtpPassword = function () {
    var inp       = document.getElementById('smtp_password');
    var eyeOpen   = document.getElementById('eye-open');
    var eyeClosed = document.getElementById('eye-closed');
    if (inp.type === 'password') {
      inp.type = 'text';
      eyeOpen.classList.add('hidden');
      eyeClosed.classList.remove('hidden');
    } else {
      inp.type = 'password';
      eyeOpen.classList.remove('hidden');
      eyeClosed.classList.add('hidden');
    }
  };

  /* ── load all settings on boot ──────────────────────────────────── */
  async function loadSettings() {
    var json;
    try {
      json = await apiGet('/api/v1/super/settings');
    } catch (e) {
      toast('Could not load settings.', 'error');
      return;
    }
    if (!json.ok || !json.data) {
      toast(json.message || 'Failed to load settings.', 'error');
      return;
    }
    var d = json.data;

    /* Platform Info */
    if (d.platform) {
      setVal('platform_name', d.platform.platform_name);
      setVal('logo_url',      d.platform.logo_url);
      updateLogoPreview(d.platform.logo_url);
    }

    /* Plan Limits */
    var plans = ['basic', 'pro', 'enterprise'];
    var cols  = ['max_jobs', 'max_users', 'ai_interviews_per_month', 'token_limit'];
    if (d.plan_limits) {
      plans.forEach(function (plan) {
        cols.forEach(function (col) {
          if (d.plan_limits[plan]) setVal('plan_' + plan + '_' + col, d.plan_limits[plan][col]);
        });
      });
    }

    /* SMTP */
    if (d.smtp) {
      setVal('from_email',    d.smtp.from_email);
      setVal('from_name',     d.smtp.from_name);
      setVal('smtp_host',     d.smtp.smtp_host);
      setVal('smtp_port',     d.smtp.smtp_port);
      setVal('smtp_username', d.smtp.smtp_username);
      if (d.smtp.smtp_password_set) {
        document.getElementById('smtp_password').placeholder = '(password saved – leave blank to keep)';
      }
    }

    /* Feature Flags */
    var flags = ['enable_ai_interviews','enable_video_interviews','enable_offers',
                 'enable_bulk_import','enable_analytics','enable_api_access'];
    if (d.features) {
      plans.forEach(function (plan) {
        flags.forEach(function (flag) {
          var el = document.getElementById('flag_' + plan + '_' + flag);
          if (el && d.features[plan]) el.checked = !!d.features[plan][flag];
        });
      });
    }
  }

  /* ── save: Platform Info ────────────────────────────────────────── */
  window.savePlatformInfo = async function (btn) {
    btnLoading(btn, true);
    try {
      var json = await apiPost('/api/v1/super/settings', {
        section:       'platform',
        platform_name: document.getElementById('platform_name').value.trim(),
        logo_url:      document.getElementById('logo_url').value.trim()
      });
      json.ok ? toast('Platform info saved.', 'success') : toast(json.message || 'Save failed.', 'error');
    } catch (e) {
      toast('Network error. Please retry.', 'error');
    } finally {
      btnLoading(btn, false);
    }
  };

  /* ── save: Plan Limits ──────────────────────────────────────────── */
  window.savePlanLimits = async function (btn) {
    btnLoading(btn, true);
    var plans = ['basic', 'pro', 'enterprise'];
    var cols  = ['max_jobs', 'max_users', 'ai_interviews_per_month', 'token_limit'];
    var plan_limits = {};
    plans.forEach(function (plan) {
      plan_limits[plan] = {};
      cols.forEach(function (col) {
        var el = document.getElementById('plan_' + plan + '_' + col);
        plan_limits[plan][col] = el ? (parseInt(el.value, 10) || 0) : 0;
      });
    });
    try {
      var json = await apiPost('/api/v1/super/settings', { section: 'plan_limits', plan_limits: plan_limits });
      json.ok ? toast('Plan limits saved.', 'success') : toast(json.message || 'Save failed.', 'error');
    } catch (e) {
      toast('Network error. Please retry.', 'error');
    } finally {
      btnLoading(btn, false);
    }
  };

  /* ── save: SMTP ─────────────────────────────────────────────────── */
  window.saveSmtp = async function (btn) {
    btnLoading(btn, true);
    var payload = {
      section:       'smtp',
      from_email:    document.getElementById('from_email').value.trim(),
      from_name:     document.getElementById('from_name').value.trim(),
      smtp_host:     document.getElementById('smtp_host').value.trim(),
      smtp_port:     parseInt(document.getElementById('smtp_port').value, 10) || 587,
      smtp_username: document.getElementById('smtp_username').value.trim()
    };
    var pwd = document.getElementById('smtp_password').value;
    if (pwd) payload.smtp_password = pwd;
    try {
      var json = await apiPost('/api/v1/super/settings', payload);
      json.ok ? toast('SMTP settings saved.', 'success') : toast(json.message || 'Save failed.', 'error');
    } catch (e) {
      toast('Network error. Please retry.', 'error');
    } finally {
      btnLoading(btn, false);
    }
  };

  /* ── test email ─────────────────────────────────────────────────── */
  window.sendTestEmail = async function (btn) {
    var origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg> Sending…';
    var to = document.getElementById('test_email_to').value.trim();
    if (!to) {
      toast('Enter a recipient email address first.', 'error');
      btn.disabled = false;
      btn.innerHTML = origHtml;
      return;
    }
    try {
      var json = await apiPost('/api/v1/super/settings/test-email', { to: to });
      json.ok ? toast('Test email sent to ' + to + '.', 'success') : toast(json.message || 'Send failed.', 'error');
    } catch (e) {
      toast('Network error. Please retry.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  };

  /* ── save: Feature Flags ────────────────────────────────────────── */
  window.saveFeatureFlags = async function (btn) {
    btnLoading(btn, true);
    var plans = ['basic', 'pro', 'enterprise'];
    var flags = ['enable_ai_interviews','enable_video_interviews','enable_offers',
                 'enable_bulk_import','enable_analytics','enable_api_access'];
    var features = {};
    plans.forEach(function (plan) {
      features[plan] = {};
      flags.forEach(function (flag) {
        var el = document.getElementById('flag_' + plan + '_' + flag);
        features[plan][flag] = el ? el.checked : false;
      });
    });
    try {
      var json = await apiPost('/api/v1/super/settings', { section: 'features', features: features });
      json.ok ? toast('Feature flags saved.', 'success') : toast(json.message || 'Save failed.', 'error');
    } catch (e) {
      toast('Network error. Please retry.', 'error');
    } finally {
      btnLoading(btn, false);
    }
  };

  /* ── boot ───────────────────────────────────────────────────────── */
  loadSettings();

})();
</script>
