<?php
/**
 * Offers management — list, create (modal), send, view details.
 * Fragment rendered inside views/layouts/app.php.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </span>
        <?= e(app_lang('Offers')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Create, send and track candidate offers.</p>
    </div>
    <button id="open-create" class="btn-primary self-start sm:self-auto">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Create Offer
    </button>
  </div>

  <!-- Status filter chips -->
  <div class="flex flex-wrap gap-2 mb-6" id="status-chips">
    <button data-status="" class="chip active">All</button>
    <button data-status="draft" class="chip">Draft</button>
    <button data-status="sent" class="chip">Sent</button>
    <button data-status="accepted" class="chip">Accepted</button>
    <button data-status="rejected" class="chip">Rejected</button>
    <button data-status="expired" class="chip">Expired</button>
  </div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/60">
            <th class="px-5 py-3 font-semibold">Candidate</th>
            <th class="px-5 py-3 font-semibold">Job</th>
            <th class="px-5 py-3 font-semibold">Salary</th>
            <th class="px-5 py-3 font-semibold">Status</th>
            <th class="px-5 py-3 font-semibold">Date</th>
            <th class="px-5 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="of-rows" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
    <div id="of-empty" class="hidden py-16 text-center">
      <div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      </div>
      <p class="text-gray-900 font-semibold">No offers yet</p>
      <p class="text-gray-500 text-sm mt-1">Create an offer for a candidate who has cleared the interview stage.</p>
      <button class="btn-primary mt-5 mx-auto" onclick="document.getElementById('open-create').click()">Create your first offer</button>
    </div>
  </div>
</div>

<!-- Create Offer Modal -->
<div id="offer-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-start justify-between mb-4">
      <div>
        <h3 class="text-lg font-bold text-gray-900">Create Offer</h3>
        <p class="text-sm text-gray-500">Draft an offer to send to the candidate.</p>
      </div>
      <button class="text-gray-400 hover:text-gray-600" data-modal-close="offer-modal"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form id="offer-form" class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Application ID <span class="text-red-500">*</span></label>
        <input name="application_id" type="number" min="1" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="e.g. 1024" />
        <p class="text-[11px] text-gray-400 mt-1">The application this offer is for (from the candidate pipeline).</p>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="block text-xs font-semibold text-gray-500 mb-1">Salary <span class="text-red-500">*</span></label>
          <input name="salary" type="number" min="0" step="any" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="120000" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Currency</label>
          <select name="currency" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="GBP">GBP</option>
            <option value="AED">AED</option>
            <option value="SAR">SAR</option>
            <option value="EGP">EGP</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Start date</label>
          <input name="start_date" type="date" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Expiry date</label>
          <input name="expiry_date" type="date" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Notes</label>
        <textarea name="notes" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Benefits, bonus, terms&hellip;"></textarea>
      </div>
      <input type="hidden" name="_csrf" value="<?= e($csrf ?? '') ?>" />
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-ghost" data-modal-close="offer-modal">Cancel</button>
        <button type="submit" id="offer-submit" class="btn-primary">Create offer</button>
      </div>
    </form>
  </div>
</div>

<!-- Details Modal -->
<div id="details-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6">
    <div class="flex items-start justify-between mb-4">
      <h3 class="text-lg font-bold text-gray-900">Offer details</h3>
      <button class="text-gray-400 hover:text-gray-600" data-modal-close="details-modal"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <div id="details-body" class="space-y-3 text-sm"></div>
  </div>
</div>

<style>
  .chip { padding: .35rem .9rem; border-radius: 9999px; font-size: .8rem; font-weight: 600; background: #fff; border: 1px solid #e5e7eb; color: #4b5563; cursor: pointer; transition: all .12s; }
  .chip:hover { border-color: #c4b5fd; color: #7c3aed; }
  .chip.active { background: #7c3aed; color: #fff; border-color: #7c3aed; }
</style>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const rows = $('of-rows');
  let CURRENT_STATUS = '';
  let CACHE = [];

  function unwrap(d) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d.offers)) return d.offers;
    if (d && Array.isArray(d.data)) return d.data;
    return [];
  }

  const STATUS_META = {
    draft:    'badge-gray',
    sent:     'badge-blue',
    accepted: 'badge-green',
    rejected: 'badge-red',
    declined: 'badge-red',
    expired:  'badge-yellow'
  };

  function money(amount, currency) {
    if (amount == null || amount === '') return '<span class="text-gray-400">—</span>';
    const n = Number(amount);
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD', maximumFractionDigits: 0 }).format(n);
    } catch (e) {
      return AR.esc((currency || '') + ' ' + n.toLocaleString());
    }
  }
  function statusBadge(s) {
    const cls = STATUS_META[s] || 'badge-gray';
    return '<span class="badge ' + cls + ' capitalize">' + AR.esc(s || 'draft') + '</span>';
  }
  function fmtDate(d) {
    if (!d) return '<span class="text-gray-400">—</span>';
    const dt = new Date(String(d).replace(' ', 'T'));
    if (isNaN(dt)) return AR.esc(d);
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function skeleton() {
    rows.innerHTML = Array.from({ length: 5 }).map(() =>
      '<tr>' + Array.from({ length: 6 }).map(() =>
        '<td class="px-5 py-4"><div class="skeleton h-4 w-24"></div></td>').join('') + '</tr>').join('');
    $('of-empty').classList.add('hidden');
  }

  function rowHtml(o) {
    const name = o.candidate_name || 'Unknown candidate';
    const initials = name.split(' ').map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
    const isDraft = (o.status || 'draft') === 'draft';
    const sendBtn = isDraft
      ? '<button class="btn-primary !py-1.5 !px-3 text-xs" data-send="' + AR.esc(o.id) + '"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>Send</button>'
      : '';
    return '<tr class="hover:bg-violet-50/40 transition">' +
      '<td class="px-5 py-4"><div class="flex items-center gap-3">' +
        '<div class="w-9 h-9 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold shrink-0">' + AR.esc(initials) + '</div>' +
        '<div class="font-medium text-gray-900">' + AR.esc(name) + '</div></div></td>' +
      '<td class="px-5 py-4 text-gray-600">' + AR.esc(o.job_title || '—') + '</td>' +
      '<td class="px-5 py-4 font-semibold text-gray-900">' + money(o.salary, o.currency) + '</td>' +
      '<td class="px-5 py-4">' + statusBadge(o.status) + '</td>' +
      '<td class="px-5 py-4 text-gray-600">' + fmtDate(o.sent_at || o.created_at) + '</td>' +
      '<td class="px-5 py-4"><div class="flex items-center justify-end gap-2">' +
        sendBtn +
        '<button class="btn-ghost !py-1.5 !px-3 text-xs" data-view="' + AR.esc(o.id) + '">Details</button>' +
      '</div></td>' +
    '</tr>';
  }

  function applyFilterAndRender() {
    const list = CURRENT_STATUS ? CACHE.filter(o => (o.status || 'draft') === CURRENT_STATUS) : CACHE;
    if (!list.length) {
      rows.innerHTML = '';
      $('of-empty').classList.remove('hidden');
      return;
    }
    $('of-empty').classList.add('hidden');
    rows.innerHTML = list.map(rowHtml).join('');
    bindRowActions();
  }

  function bindRowActions() {
    rows.querySelectorAll('[data-send]').forEach(btn => btn.addEventListener('click', () => sendOffer(btn.getAttribute('data-send'), btn)));
    rows.querySelectorAll('[data-view]').forEach(btn => btn.addEventListener('click', () => viewOffer(btn.getAttribute('data-view'))));
  }

  async function sendOffer(id, btn) {
    btn.disabled = true; btn.textContent = 'Sending…';
    try {
      await AR.Api.post('/offers/' + encodeURIComponent(id) + '/send');
      AR.Toast.success('Offer sent to candidate.');
      const o = CACHE.find(x => String(x.id) === String(id));
      if (o) { o.status = 'sent'; o.sent_at = new Date().toISOString(); }
      applyFilterAndRender();
    } catch (e) {
      AR.Toast.error(e.message || 'Could not send offer.');
      btn.disabled = false; btn.textContent = 'Send';
    }
  }

  function viewOffer(id) {
    const o = CACHE.find(x => String(x.id) === String(id));
    if (!o) return;
    function row(label, val) {
      return '<div class="flex justify-between gap-4 py-1.5 border-b border-gray-50 last:border-0">' +
        '<span class="text-gray-500">' + label + '</span><span class="font-medium text-gray-900 text-right">' + val + '</span></div>';
    }
    $('details-body').innerHTML =
      row('Candidate', AR.esc(o.candidate_name || '—')) +
      row('Job', AR.esc(o.job_title || '—')) +
      row('Salary', money(o.salary, o.currency)) +
      row('Status', statusBadge(o.status)) +
      row('Start date', fmtDate(o.start_date)) +
      row('Expiry date', fmtDate(o.expiry_date)) +
      row('Created', fmtDate(o.created_at)) +
      (o.notes ? '<div class="pt-2"><div class="text-gray-500 mb-1">Notes</div><p class="text-gray-700 bg-gray-50 rounded-xl p-3">' + AR.esc(o.notes) + '</p></div>' : '');
    AR.Modal.open('details-modal');
  }

  async function load() {
    skeleton();
    try {
      CACHE = unwrap(await AR.Api.get('/offers'));
      applyFilterAndRender();
    } catch (e) {
      rows.innerHTML = '<tr><td colspan="6" class="px-5 py-12 text-center text-red-600">' +
        '<div class="font-semibold">Could not load offers</div>' +
        '<div class="text-sm text-gray-500 mt-1">' + AR.esc(e.message || 'Please try again.') + '</div></td></tr>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    $('open-create').addEventListener('click', () => AR.Modal.open('offer-modal'));

    $('status-chips').querySelectorAll('.chip').forEach(chip => {
      chip.addEventListener('click', function () {
        $('status-chips').querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        CURRENT_STATUS = chip.getAttribute('data-status') || '';
        applyFilterAndRender();
      });
    });

    $('offer-form').addEventListener('submit', async function (e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const payload = {
        application_id: Number(fd.get('application_id')),
        salary: Number(fd.get('salary')),
        currency: fd.get('currency') || 'USD',
        start_date: fd.get('start_date') || null,
        expiry_date: fd.get('expiry_date') || null,
        notes: fd.get('notes') || ''
      };
      const btn = $('offer-submit');
      btn.disabled = true; btn.textContent = 'Creating…';
      try {
        await AR.Api.post('/offers', payload);
        AR.Toast.success('Offer created.');
        AR.Modal.close('offer-modal');
        e.target.reset();
        load();
      } catch (err) {
        AR.Toast.error(err.message || 'Could not create offer.');
      } finally {
        btn.disabled = false; btn.textContent = 'Create offer';
      }
    });

    load();
  });
})();
</script>
