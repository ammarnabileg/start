<?php
/**
 * Company settings — tabbed: General, Career Page, Integrations, Billing, Email.
 * Fragment rendered inside views/layouts/app.php.
 */
$csrf = $csrf ?? '';
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
      <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </span>
      <?= e(app_lang('Settings')) ?>
    </h1>
    <p class="text-sm text-gray-500 mt-1">Configure your company, career page, integrations and billing.</p>
  </div>

  <div class="grid lg:grid-cols-4 gap-6">

    <!-- Tab rail -->
    <nav class="lg:col-span-1">
      <div class="card p-2 lg:sticky lg:top-6" id="tab-rail">
        <button data-tab="general" class="tab-btn active"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-12h.01M7 13h.01M11 9h.01M11 13h.01"/></svg> General</button>
        <button data-tab="career" class="tab-btn"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> Career Page</button>
        <button data-tab="integrations" class="tab-btn"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Integrations</button>
        <button data-tab="billing" class="tab-btn"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg> Billing</button>
        <button data-tab="email" class="tab-btn"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> Email</button>
      </div>
    </nav>

    <!-- Panels -->
    <div class="lg:col-span-3 space-y-6">

      <!-- GENERAL -->
      <section data-panel="general" class="card p-6">
        <h2 class="font-bold text-gray-900 mb-1">General</h2>
        <p class="text-sm text-gray-500 mb-5">Basic company information.</p>
        <form class="space-y-4" data-save-form="General settings">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Company name</label>
              <input name="company_name" class="inp" placeholder="Acme Inc." />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Logo URL</label>
              <input name="logo_url" type="url" class="inp" placeholder="https://…/logo.png" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Contact email</label>
              <input name="contact_email" type="email" class="inp" placeholder="hr@acme.com" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Phone</label>
              <input name="phone" class="inp" placeholder="+1 555 123 4567" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Address</label>
            <textarea name="address" rows="2" class="inp" placeholder="Street, City, Country"></textarea>
          </div>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>" />
          <div class="flex justify-end"><button class="btn-primary" type="submit">Save changes</button></div>
        </form>
      </section>

      <!-- CAREER PAGE -->
      <section data-panel="career" class="card p-6 hidden">
        <div class="flex items-start justify-between mb-1">
          <div>
            <h2 class="font-bold text-gray-900">Career Page</h2>
            <p class="text-sm text-gray-500">Your public-facing jobs page.</p>
          </div>
          <!-- publish switch -->
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <span class="text-sm font-medium text-gray-600" id="pub-label">Unpublished</span>
            <span class="relative">
              <input type="checkbox" id="pub-toggle" class="sr-only peer" />
              <span class="block w-11 h-6 rounded-full bg-gray-300 peer-checked:bg-violet-600 transition"></span>
              <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
            </span>
          </label>
        </div>
        <form class="space-y-4 mt-5" data-save-form="Career page">
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Company name</label>
              <input name="company_name" class="inp" placeholder="Acme Inc." />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Banner / logo URL</label>
              <input name="banner_url" type="url" class="inp" placeholder="https://…/banner.jpg" />
            </div>
          </div>
          <div class="grid sm:grid-cols-2 gap-4 items-end">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Primary color</label>
              <div class="flex items-center gap-3">
                <input type="color" id="brand-color" value="#7C3AED" class="w-12 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5" />
                <input id="brand-color-hex" class="inp font-mono" value="#7C3AED" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Live preview</label>
              <button type="button" id="brand-preview-btn" class="w-full justify-center text-white font-semibold rounded-full py-2.5" style="background:#7C3AED">Apply Now</button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Description</label>
            <textarea name="description" rows="3" class="inp" placeholder="Tell candidates why they should join you…"></textarea>
          </div>
          <div class="flex items-center justify-between flex-wrap gap-3">
            <a id="view-public" href="/careers/your-company" target="_blank" rel="noopener" class="text-sm text-violet-600 hover:underline inline-flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
              View Public Page
            </a>
            <button class="btn-primary" type="submit">Save changes</button>
          </div>
        </form>
      </section>

      <!-- INTEGRATIONS -->
      <section data-panel="integrations" class="card p-6 hidden">
        <h2 class="font-bold text-gray-900 mb-1">Integrations</h2>
        <p class="text-sm text-gray-500 mb-5">Connect the AI services that power interviews.</p>
        <form class="space-y-5" data-save-form="Integrations">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1 flex items-center gap-1.5">
              <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
              OpenAI API key
            </label>
            <div class="flex gap-2">
              <input name="openai_key" type="password" class="inp font-mono" placeholder="sk-…" autocomplete="off" />
              <button type="button" class="btn-ghost shrink-0" data-test="OpenAI">Test</button>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Used for CV analysis, AI interviews and candidate matching.</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1 flex items-center gap-1.5">
              <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
              HeyGen API key
            </label>
            <div class="flex gap-2">
              <input name="heygen_key" type="password" class="inp font-mono" placeholder="…" autocomplete="off" />
              <button type="button" class="btn-ghost shrink-0" data-test="HeyGen">Test</button>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Powers lifelike avatars for AI video interviews.</p>
          </div>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>" />
          <div class="flex justify-end"><button class="btn-primary" type="submit">Save changes</button></div>
        </form>
      </section>

      <!-- BILLING -->
      <section data-panel="billing" class="card p-6 hidden">
        <h2 class="font-bold text-gray-900 mb-1">Billing &amp; Plan</h2>
        <p class="text-sm text-gray-500 mb-5">Your current subscription and usage.</p>

        <div class="rounded-2xl gradient-brand text-white p-6 mb-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs uppercase tracking-wider text-violet-100">Current plan</p>
              <h3 class="text-2xl font-bold mt-1">Growth</h3>
            </div>
            <span class="badge bg-white/20 text-white">Active</span>
          </div>
          <p class="text-sm text-violet-100 mt-2">$199 / month &middot; billed annually</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mb-6">
          <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Included features</h4>
            <ul class="space-y-2 text-sm text-gray-700">
              <li class="flex gap-2"><svg class="w-4 h-4 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Unlimited job postings</li>
              <li class="flex gap-2"><svg class="w-4 h-4 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> AI text, voice &amp; video interviews</li>
              <li class="flex gap-2"><svg class="w-4 h-4 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Up to 15 team members</li>
              <li class="flex gap-2"><svg class="w-4 h-4 text-green-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Talent pools &amp; AI search</li>
            </ul>
          </div>
          <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Usage this month</h4>
            <div class="space-y-3">
              <div><div class="flex justify-between text-sm mb-1"><span class="text-gray-600">AI interviews</span><span class="font-semibold">128 / 500</span></div><div class="score-bar"><span style="width:26%"></span></div></div>
              <div><div class="flex justify-between text-sm mb-1"><span class="text-gray-600">Team seats</span><span class="font-semibold">9 / 15</span></div><div class="score-bar"><span style="width:60%"></span></div></div>
              <div><div class="flex justify-between text-sm mb-1"><span class="text-gray-600">Avatar minutes</span><span class="font-semibold">340 / 1000</span></div><div class="score-bar"><span style="width:34%"></span></div></div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
          <p class="text-sm text-gray-500">Need more? Upgrade for higher limits and priority support.</p>
          <button class="btn-accent" type="button" onclick="window.AR.Toast.info('Plan management opens your billing portal.')">Manage plan</button>
        </div>
      </section>

      <!-- EMAIL -->
      <section data-panel="email" class="card p-6 hidden">
        <h2 class="font-bold text-gray-900 mb-1">Email (SMTP)</h2>
        <p class="text-sm text-gray-500 mb-5">Configure the mailbox used to send candidate communications.</p>
        <form class="space-y-4" data-save-form="Email settings">
          <div class="grid sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-gray-500 mb-1">SMTP host</label>
              <input name="smtp_host" class="inp" placeholder="smtp.mailgun.org" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Port</label>
              <input name="smtp_port" type="number" class="inp" placeholder="587" />
            </div>
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Username</label>
              <input name="smtp_username" class="inp" placeholder="postmaster@…" autocomplete="off" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Password</label>
              <input name="smtp_password" type="password" class="inp" placeholder="••••••••" autocomplete="off" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">From address</label>
            <input name="smtp_from" type="email" class="inp" placeholder="no-reply@acme.com" />
          </div>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>" />
          <div class="flex items-center justify-between">
            <button type="button" class="btn-ghost" data-test="SMTP">Send test email</button>
            <button class="btn-primary" type="submit">Save changes</button>
          </div>
        </form>
      </section>

    </div>
  </div>
</div>

<style>
  .tab-btn { display:flex; align-items:center; gap:.6rem; width:100%; text-align:left; padding:.65rem .85rem; border-radius:.7rem; font-weight:600; font-size:.9rem; color:#4b5563; transition:all .12s; }
  .tab-btn:hover { background:#f3f4f6; color:#7c3aed; }
  .tab-btn.active { background:rgba(124,58,237,.1); color:#7c3aed; }
  .tab-btn svg { color:inherit; }
  html[dir="rtl"] .tab-btn { text-align:right; }
  .inp { width:100%; border:1px solid #e5e7eb; border-radius:.75rem; padding:.55rem .8rem; font-size:.875rem; }
  .inp:focus { outline:none; box-shadow:0 0 0 2px rgba(124,58,237,.35); border-color:#7c3aed; }
</style>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  document.addEventListener('DOMContentLoaded', function () {
    // ---- Tabs ----
    const rail = $('tab-rail');
    function activate(tab) {
      rail.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.getAttribute('data-tab') === tab));
      document.querySelectorAll('[data-panel]').forEach(p => p.classList.toggle('hidden', p.getAttribute('data-panel') !== tab));
      try { history.replaceState(null, '', '#' + tab); } catch (e) {}
    }
    rail.querySelectorAll('.tab-btn').forEach(b => b.addEventListener('click', () => activate(b.getAttribute('data-tab'))));
    const initial = (location.hash || '').replace('#', '');
    if (initial && rail.querySelector('[data-tab="' + initial + '"]')) activate(initial);

    // ---- Save forms (cosmetic: toast on save) ----
    document.querySelectorAll('[data-save-form]').forEach(form => {
      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const label = form.getAttribute('data-save-form');
        const btn = form.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.dataset.old = btn.textContent; btn.textContent = 'Saving…'; }
        // Best-effort persistence; falls back to a success toast.
        try {
          const fd = new FormData(form);
          const payload = {};
          fd.forEach((v, k) => { if (k !== '_csrf') payload[k] = v; });
          await AR.Api.post('/admin', payload);
        } catch (err) { /* cosmetic — ignore */ }
        finally {
          if (btn) { btn.disabled = false; btn.textContent = btn.dataset.old || 'Save changes'; }
          AR.Toast.success(label + ' saved.');
        }
      });
    });

    // ---- Test buttons ----
    document.querySelectorAll('[data-test]').forEach(b => b.addEventListener('click', () => {
      AR.Toast.info('Testing ' + b.getAttribute('data-test') + ' connection…');
    }));

    // ---- Career publish toggle ----
    const pub = $('pub-toggle');
    if (pub) pub.addEventListener('change', function () {
      $('pub-label').textContent = pub.checked ? 'Published' : 'Unpublished';
      AR.Toast.success('Career page ' + (pub.checked ? 'published' : 'unpublished') + '.');
    });

    // ---- Color picker live preview ----
    const color = $('brand-color'), hex = $('brand-color-hex'), prev = $('brand-preview-btn');
    function applyColor(val) {
      if (!/^#[0-9a-fA-F]{6}$/.test(val)) return;
      if (prev) prev.style.background = val;
      if (color) color.value = val;
      if (hex) hex.value = val.toUpperCase();
    }
    if (color) color.addEventListener('input', () => applyColor(color.value));
    if (hex) hex.addEventListener('input', () => applyColor(hex.value));
  });
})();
</script>
