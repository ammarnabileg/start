/**
 * kanban.js — Drag & Drop Pipeline Board with SortableJS
 * Requires SortableJS loaded before this script.
 */

(function() {
  'use strict';

  const STAGE_LABELS = {
    applied:           { label: 'Applied',            color: 'bg-gray-100 text-gray-700' },
    ai_screening:      { label: 'AI Screening',       color: 'bg-blue-100 text-blue-700' },
    qualified:         { label: 'Qualified',           color: 'bg-emerald-100 text-emerald-700' },
    disqualified:      { label: 'Disqualified',        color: 'bg-red-100 text-red-700' },
    tech_interview:    { label: 'Tech Interview',      color: 'bg-violet-100 text-violet-700' },
    manager_interview: { label: 'Manager Interview',   color: 'bg-indigo-100 text-indigo-700' },
    final_review:      { label: 'Final Review',        color: 'bg-amber-100 text-amber-700' },
    offer:             { label: 'Offer',               color: 'bg-yellow-100 text-yellow-700' },
    hired:             { label: 'Hired',               color: 'bg-green-100 text-green-700' },
    rejected:          { label: 'Rejected',            color: 'bg-red-100 text-red-700' },
    withdrawn:         { label: 'Withdrawn',           color: 'bg-gray-100 text-gray-500' }
  };

  let sortableInstances = [];
  let pendingMove = null;

  function initKanban() {
    const columns = document.querySelectorAll('[data-kanban-column]');
    if (!columns.length) return;

    sortableInstances.forEach(s => s.destroy());
    sortableInstances = [];

    columns.forEach(col => {
      const list = col.querySelector('[data-kanban-list]');
      if (!list) return;

      const instance = Sortable.create(list, {
        group: 'pipeline',
        animation: 150,
        ghostClass: 'kanban-ghost',
        dragClass: 'kanban-dragging',
        handle: '.kanban-drag-handle',
        onStart(evt) {
          document.querySelectorAll('[data-kanban-column]').forEach(c => c.classList.add('drag-target-highlight'));
        },
        onEnd(evt) {
          document.querySelectorAll('[data-kanban-column]').forEach(c => c.classList.remove('drag-target-highlight'));
          const card = evt.item;
          const newStage = evt.to.closest('[data-kanban-column]')?.dataset.kanbanColumn;
          const applicationId = card.dataset.applicationId;
          if (!applicationId || !newStage) return;
          if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
          moveCard(applicationId, newStage, card, evt.from, evt.oldIndex);
        }
      });
      sortableInstances.push(instance);
    });

    updateCounts();
  }

  async function moveCard(applicationId, newStage, card, originalList, originalIndex) {
    const oldStage = card.dataset.stage;
    card.dataset.stage = newStage;
    updateCounts();

    try {
      const res = await ajax('/api/v1/applications', {
        method: 'POST',
        body: { action: 'move_stage', application_id: applicationId, stage: newStage }
      });
      if (res.ok) {
        showToast(`Moved to ${STAGE_LABELS[newStage]?.label || newStage}`, 'success');
        const badge = card.querySelector('[data-stage-badge]');
        if (badge) {
          const stageInfo = STAGE_LABELS[newStage] || { label: newStage, color: 'bg-gray-100 text-gray-700' };
          badge.className = `text-xs font-medium px-2 py-0.5 rounded-full ${stageInfo.color}`;
          badge.textContent = stageInfo.label;
        }
      } else {
        throw new Error(res.message || 'Move failed');
      }
    } catch (err) {
      showToast(err.message || 'Failed to move card', 'error');
      // Revert
      card.dataset.stage = oldStage;
      const originalItem = originalList.children[originalIndex];
      originalList.insertBefore(card, originalItem || null);
      updateCounts();
    }
  }

  function updateCounts() {
    document.querySelectorAll('[data-kanban-column]').forEach(col => {
      const stage = col.dataset.kanbanColumn;
      const count = col.querySelectorAll('[data-application-id]').length;
      const countEl = col.querySelector('[data-kanban-count]');
      if (countEl) countEl.textContent = count;
    });
  }

  // ─── Bulk Selection ────────────────────────────────────────────────────────
  let selectedCards = new Set();

  function toggleCardSelection(card) {
    const id = card.dataset.applicationId;
    if (selectedCards.has(id)) {
      selectedCards.delete(id);
      card.classList.remove('ring-2', 'ring-violet-500');
      card.querySelector('[data-select-check]')?.classList.add('hidden');
    } else {
      selectedCards.add(id);
      card.classList.add('ring-2', 'ring-violet-500');
      card.querySelector('[data-select-check]')?.classList.remove('hidden');
    }
    updateBulkBar();
  }

  function updateBulkBar() {
    const bar = document.getElementById('kanban-bulk-bar');
    const countEl = document.getElementById('kanban-selected-count');
    if (bar) bar.classList.toggle('hidden', selectedCards.size === 0);
    if (countEl) countEl.textContent = selectedCards.size;
  }

  window.kanbanBulkAction = async function(action) {
    if (selectedCards.size === 0) return;
    const ids = Array.from(selectedCards);
    try {
      setLoading(document.getElementById(`bulk-${action}-btn`), true);
      const res = await ajax('/api/v1/applications', {
        method: 'POST',
        body: { action: 'bulk_action', bulk_action: action, application_ids: ids }
      });
      if (res.ok) {
        showToast(`${action.replace(/_/g,' ')} applied to ${ids.length} candidate(s)`, 'success');
        setTimeout(() => location.reload(), 800);
      } else throw new Error(res.message);
    } catch (err) {
      showToast(err.message || 'Bulk action failed', 'error');
    }
  };

  // ─── Card Quick View ───────────────────────────────────────────────────────
  function openCardQuickView(applicationId) {
    const modal = document.getElementById('card-quick-view');
    if (!modal) return;
    const body = modal.querySelector('[data-quick-view-body]');
    body.innerHTML = '<div class="flex justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-2 border-violet-600 border-t-transparent"></div></div>';
    openModal('card-quick-view');
    fetch(`/api/v1/applications?action=quick_view&id=${applicationId}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
      if (data.ok) body.innerHTML = renderQuickView(data.data);
      else body.innerHTML = `<p class="text-red-500 text-sm">${data.message}</p>`;
    })
    .catch(() => { body.innerHTML = '<p class="text-red-500 text-sm">Failed to load.</p>'; });
  }

  function renderQuickView(d) {
    const rec = { strong_yes: 'Strong Yes', yes: 'Yes', possible: 'Possible', no: 'No' };
    const recColors = { strong_yes: 'text-emerald-700 bg-emerald-50', yes: 'text-blue-700 bg-blue-50', possible: 'text-amber-700 bg-amber-50', no: 'text-red-700 bg-red-50' };
    return `
      <div class="space-y-4">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-lg">${(d.candidate_name||'?')[0]}</div>
          <div>
            <h3 class="font-semibold text-gray-900">${d.candidate_name||'—'}</h3>
            <p class="text-sm text-gray-500">${d.candidate_email||''}</p>
          </div>
          ${d.overall_score ? `<div class="ml-auto text-center"><div class="text-2xl font-bold text-violet-700">${d.overall_score}</div><div class="text-xs text-gray-500">Score</div></div>` : ''}
        </div>
        ${d.recommendation ? `<div class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium ${recColors[d.recommendation]||'bg-gray-100 text-gray-700'}">${rec[d.recommendation]||d.recommendation}</div>` : ''}
        ${d.ai_summary ? `<div class="bg-gray-50 rounded-xl p-3 text-sm text-gray-700">${d.ai_summary}</div>` : ''}
        <div class="flex gap-2 pt-2">
          <a href="/hr/candidates/${d.application_id}" class="flex-1 text-center py-2 bg-violet-600 text-white text-sm font-medium rounded-full hover:bg-violet-700">View Full Profile</a>
          ${d.interview_token ? `<a href="/interview/${d.interview_token}" target="_blank" class="flex-1 text-center py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-200">Interview Room</a>` : ''}
        </div>
      </div>
    `;
  }

  // ─── Event Delegation ──────────────────────────────────────────────────────
  document.addEventListener('click', e => {
    // Card select checkbox
    const selectBtn = e.target.closest('[data-select-card]');
    if (selectBtn) {
      e.stopPropagation();
      const card = selectBtn.closest('[data-application-id]');
      if (card) toggleCardSelection(card);
      return;
    }
    // Quick view
    const quickBtn = e.target.closest('[data-quick-view]');
    if (quickBtn) {
      e.stopPropagation();
      const id = quickBtn.dataset.quickView;
      if (id) openCardQuickView(id);
      return;
    }
  });

  // ─── Filters ──────────────────────────────────────────────────────────────
  window.filterKanban = function(searchTerm) {
    const cards = document.querySelectorAll('[data-application-id]');
    const term = searchTerm.toLowerCase();
    cards.forEach(card => {
      const text = card.textContent.toLowerCase();
      card.classList.toggle('opacity-30', term !== '' && !text.includes(term));
    });
  };

  // Initialize
  if (typeof Sortable !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initKanban);
  } else {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
    script.onload = initKanban;
    document.head.appendChild(script);
  }

  window.initKanban = initKanban;
})();
