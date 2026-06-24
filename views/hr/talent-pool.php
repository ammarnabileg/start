<?php
/**
 * Talent pool management — two-pane: pools list + AI search detail.
 * Fragment rendered inside views/layouts/app.php.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-3-6.65"/></svg>
        </span>
        <?= e(app_lang('Talent Pool')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Curated talent pools you can source from with AI search.</p>
    </div>
    <button id="open-create" class="btn-primary self-start sm:self-auto">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Create Pool
    </button>
  </div>

  <div class="grid lg:grid-cols-3 gap-6">

    <!-- Left: pools list -->
    <div class="lg:col-span-1">
      <div class="card p-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3 px-1">Pools</h2>
        <div id="pool-list" class="space-y-2"></div>
      </div>
    </div>

    <!-- Right: selected pool detail -->
    <div class="lg:col-span-2">
      <div id="pool-detail" class="card p-6 min-h-[24rem]">
        <div class="h-full flex flex-col items-center justify-center text-center py-16">
          <div class="w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7M9 5l7 7-7 7" opacity="0"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m7-7l-7 7 7 7"/></svg>
          </div>
          <p class="text-gray-900 font-semibold">Select a pool</p>
          <p class="text-gray-500 text-sm mt-1">Choose a talent pool on the left to view and search its candidates.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Pool Modal -->
<div id="pool-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6">
    <div class="flex items-start justify-between mb-4">
      <h3 class="text-lg font-bold text-gray-900">Create Talent Pool</h3>
      <button class="text-gray-400 hover:text-gray-600" data-modal-close="pool-modal"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form id="pool-form" class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Name <span class="text-red-500">*</span></label>
        <input name="name" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="e.g. Senior Frontend Engineers" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="What kind of talent lives in this pool?"></textarea>
      </div>
      <input type="hidden" name="_csrf" value="<?= e($csrf ?? '') ?>" />
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-ghost" data-modal-close="pool-modal">Cancel</button>
        <button type="submit" id="pool-submit" class="btn-primary">Create pool</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  let POOLS = [];
  let ACTIVE = null;

  function unwrapList(d) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d.pools)) return d.pools;
    if (d && Array.isArray(d.data)) return d.data;
    return [];
  }
  function unwrapPool(d) {
    if (d && d.pool) return d.pool;
    return d || {};
  }
  function unwrapCandidates(d) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d.candidates)) return d.candidates;
    if (d && d.pool && Array.isArray(d.pool.candidates)) return d.pool.candidates;
    if (d && Array.isArray(d.data)) return d.data;
    return [];
  }
  function fullName(c) { return ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || c.email || 'Candidate'; }
  function initials(name) { return name.split(' ').map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?'; }

  // ---- left list ----
  function renderPools() {
    const wrap = $('pool-list');
    if (!POOLS.length) {
      wrap.innerHTML = '<div class="text-center py-10 text-sm text-gray-400">No pools yet.<br>Create one to get started.</div>';
      return;
    }
    wrap.innerHTML = POOLS.map(p => {
      const active = ACTIVE && String(ACTIVE) === String(p.id);
      return '<button data-pool="' + AR.esc(p.id) + '" class="w-full text-left rounded-xl border p-3 transition ' +
        (active ? 'border-violet-300 bg-violet-50' : 'border-gray-100 hover:border-violet-200 hover:bg-gray-50') + '">' +
        '<div class="flex items-center justify-between gap-2">' +
          '<span class="font-semibold text-gray-900 truncate">' + AR.esc(p.name || 'Untitled pool') + '</span>' +
          '<span class="badge badge-violet shrink-0">' + (p.candidate_count != null ? p.candidate_count : 0) + '</span>' +
        '</div>' +
        (p.description ? '<p class="text-xs text-gray-500 mt-1 line-clamp-2">' + AR.esc(p.description) + '</p>' : '') +
      '</button>';
    }).join('');
    wrap.querySelectorAll('[data-pool]').forEach(b => b.addEventListener('click', () => selectPool(b.getAttribute('data-pool'))));
  }

  function detailSkeleton() {
    $('pool-detail').innerHTML = '<div class="space-y-4">' +
      '<div class="skeleton h-6 w-48"></div><div class="skeleton h-10 w-full"></div>' +
      Array.from({ length: 4 }).map(() => '<div class="skeleton h-14 w-full"></div>').join('') + '</div>';
  }

  function renderDetail(pool) {
    const candidates = unwrapCandidates(pool);
    const name = pool.name || 'Pool';
    $('pool-detail').innerHTML =
      '<div class="flex items-start justify-between gap-4 mb-1">' +
        '<div><h2 class="text-xl font-bold text-gray-900">' + AR.esc(name) + '</h2>' +
        (pool.description ? '<p class="text-sm text-gray-500 mt-1">' + AR.esc(pool.description) + '</p>' : '') + '</div>' +
        '<span class="badge badge-violet shrink-0">' + candidates.length + ' candidate' + (candidates.length === 1 ? '' : 's') + '</span>' +
      '</div>' +
      // AI search box
      '<div class="mt-5 rounded-2xl bg-gradient-to-r from-violet-600 to-violet-500 p-0.5">' +
        '<div class="bg-white rounded-[14px] p-3">' +
          '<label class="flex items-center gap-1.5 text-xs font-semibold text-violet-600 mb-2"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg> AI Talent Search</label>' +
          '<div class="flex gap-2">' +
            '<input id="pool-search" type="text" placeholder="Find me a React developer with 5+ years&hellip;" class="flex-1 rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />' +
            '<button id="pool-search-btn" class="btn-primary shrink-0">Search</button>' +
            '<button id="pool-search-clear" class="btn-ghost shrink-0 hidden">Clear</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div id="search-context" class="hidden mt-3 text-xs text-gray-500"></div>' +
      '<div id="cand-list" class="mt-5 space-y-2"></div>';

    renderCandidates(candidates, false);

    $('pool-search-btn').addEventListener('click', runSearch);
    $('pool-search').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); runSearch(); } });
    $('pool-search-clear').addEventListener('click', () => { $('pool-search').value = ''; $('search-context').classList.add('hidden'); $('pool-search-clear').classList.add('hidden'); selectPool(ACTIVE, true); });
  }

  function renderCandidates(candidates, isSearch) {
    const wrap = $('cand-list');
    if (!wrap) return;
    if (!candidates.length) {
      wrap.innerHTML = '<div class="text-center py-10 text-sm text-gray-400">' +
        (isSearch ? 'No matching candidates found. Try a different query.' : 'This pool has no candidates yet. Use AI search to find and add candidates.') + '</div>';
      return;
    }
    wrap.innerHTML = candidates.map(c => {
      const name = fullName(c);
      const inPool = c.in_pool !== false;
      const actionBtn = (isSearch && !inPool)
        ? '<button class="btn-ghost !py-1.5 !px-3 text-xs !text-violet-600 !border-violet-200 hover:!bg-violet-50" data-add="' + AR.esc(c.id) + '"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Add</button>'
        : '<button class="btn-ghost !py-1.5 !px-3 text-xs !text-red-600 !border-red-200 hover:!bg-red-50" data-remove="' + AR.esc(c.id) + '"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>Remove</button>';
      return '<div class="flex items-center gap-3 rounded-xl border border-gray-100 p-3 hover:bg-gray-50 transition">' +
        '<div class="w-9 h-9 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold shrink-0">' + AR.esc(initials(name)) + '</div>' +
        '<div class="min-w-0 flex-1">' +
          '<div class="font-medium text-gray-900 truncate">' + AR.esc(name) + '</div>' +
          '<div class="text-xs text-gray-500 truncate">' + AR.esc(c.email || '') + (c.headline ? ' &middot; ' + AR.esc(c.headline) : '') + '</div>' +
        '</div>' +
        '<a href="/candidates/' + AR.esc(c.id) + '" class="text-xs text-violet-600 hover:underline shrink-0">View</a>' +
        actionBtn +
      '</div>';
    }).join('');

    wrap.querySelectorAll('[data-remove]').forEach(b => b.addEventListener('click', () => removeCandidate(b.getAttribute('data-remove'))));
    wrap.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => addCandidate(b.getAttribute('data-add'))));
  }

  async function selectPool(id, silent) {
    ACTIVE = id;
    renderPools();
    if (!silent) detailSkeleton();
    try {
      const pool = unwrapPool(await AR.Api.get('/talent-pools/' + encodeURIComponent(id)));
      renderDetail(pool);
    } catch (e) {
      $('pool-detail').innerHTML = '<div class="text-center py-16 text-red-600"><div class="font-semibold">Could not load pool</div><div class="text-sm text-gray-500 mt-1">' + AR.esc(e.message || '') + '</div></div>';
    }
  }

  async function runSearch() {
    const q = ($('pool-search').value || '').trim();
    if (!q) { selectPool(ACTIVE, true); return; }
    const wrap = $('cand-list');
    wrap.innerHTML = '<div class="text-center py-8 text-sm text-gray-400"><span class="inline-block spin">⏳</span> Searching talent&hellip;</div>';
    try {
      const results = unwrapCandidates(await AR.Api.get('/talent-pools/' + encodeURIComponent(ACTIVE) + '/search?q=' + encodeURIComponent(q)));
      $('search-context').textContent = results.length + ' result' + (results.length === 1 ? '' : 's') + ' for "' + q + '"';
      $('search-context').classList.remove('hidden');
      $('pool-search-clear').classList.remove('hidden');
      renderCandidates(results, true);
    } catch (e) {
      wrap.innerHTML = '<div class="text-center py-8 text-sm text-red-600">' + AR.esc(e.message || 'Search failed.') + '</div>';
    }
  }

  async function addCandidate(cid) {
    try {
      await AR.Api.post('/talent-pools/' + encodeURIComponent(ACTIVE) + '/candidates', { candidate_id: Number(cid) });
      AR.Toast.success('Candidate added to pool.');
      bumpCount(1);
    } catch (e) {
      AR.Toast.error(e.message || 'Could not add candidate.');
    }
  }

  async function removeCandidate(cid) {
    try {
      await AR.Api.del('/talent-pools/' + encodeURIComponent(ACTIVE) + '/candidates/' + encodeURIComponent(cid));
      AR.Toast.success('Candidate removed.');
      bumpCount(-1);
      selectPool(ACTIVE, true);
    } catch (e) {
      AR.Toast.error(e.message || 'Could not remove candidate.');
    }
  }

  function bumpCount(delta) {
    const p = POOLS.find(x => String(x.id) === String(ACTIVE));
    if (p) { p.candidate_count = Math.max(0, (p.candidate_count || 0) + delta); renderPools(); }
  }

  async function loadPools(selectFirst) {
    try {
      POOLS = unwrapList(await AR.Api.get('/talent-pools'));
      renderPools();
      if (selectFirst && POOLS.length) selectPool(POOLS[0].id);
    } catch (e) {
      $('pool-list').innerHTML = '<div class="text-center py-8 text-sm text-red-600">' + AR.esc(e.message || 'Could not load pools.') + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    $('open-create').addEventListener('click', () => AR.Modal.open('pool-modal'));
    $('pool-form').addEventListener('submit', async function (e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const btn = $('pool-submit');
      btn.disabled = true; btn.textContent = 'Creating…';
      try {
        await AR.Api.post('/talent-pools', { name: fd.get('name'), description: fd.get('description') || '' });
        AR.Toast.success('Pool created.');
        AR.Modal.close('pool-modal');
        e.target.reset();
        await loadPools(false);
      } catch (err) {
        AR.Toast.error(err.message || 'Could not create pool.');
      } finally {
        btn.disabled = false; btn.textContent = 'Create pool';
      }
    });
    loadPools(true);
  });
})();
</script>
