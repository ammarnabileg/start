/**
 * kanban-board.js — Drag & drop pipeline board (self-contained for pipeline.php).
 *
 * Named distinctly from the shared kanban.js to avoid clobbering. Works with
 * the markup emitted by views/hr/pipeline.php:
 *   section[data-stage][data-stage-label] > [data-kanban-list] > article[data-card][data-application-id]
 *   header has [data-count]; cards have [data-drag-handle] and [data-card-select].
 *
 * On drop: PATCH /api/v1/applications/{id}/stage {stage} (optimistic; rollback
 * on error). Bulk selection drives #bulkBar / #bulkCount and [data-bulk-stage].
 * Depends on SortableJS (loaded via CDN by the view) and, optionally, window.App
 * for toasts/confirm.
 */
(function () {
  'use strict';

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m) return m.getAttribute('content');
    var i = document.querySelector('input[name="_csrf"]');
    return i ? i.value : '';
  }
  function toast(msg, type) { if (window.App && App.toast) App.toast(msg, type); else console.log('[kanban]', type, msg); }

  function updateCount(stage) {
    var col = document.querySelector('[data-stage="' + stage + '"]');
    if (!col) return;
    var list = col.querySelector('[data-kanban-list]');
    var badge = col.querySelector('[data-count]');
    if (list && badge) badge.textContent = list.querySelectorAll('[data-card]').length;
  }

  function persistStage(applicationId, stage) {
    return fetch('/api/v1/applications/' + applicationId + '/stage', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ stage: stage })
    }).then(function (res) { if (!res.ok) throw new Error('stage update failed ' + res.status); return res.json().catch(function () { return {}; }); });
  }

  function initSortable() {
    if (typeof window.Sortable === 'undefined') { console.warn('SortableJS not loaded — drag disabled.'); return; }
    document.querySelectorAll('[data-kanban-list]').forEach(function (list) {
      new Sortable(list, {
        group: 'pipeline',
        animation: 180,
        ghostClass: 'opacity-40',
        dragClass: 'rotate-2',
        handle: '[data-drag-handle]',
        onEnd: function () {
          document.querySelectorAll('[data-kanban-list]').forEach(function (l) { l.classList.remove('ring-2', 'ring-violet-300', 'bg-violet-50/50'); });
        },
        onSort: function (evt) {
          var card = evt.item;
          var newCol = evt.to.closest('[data-stage]');
          var oldCol = evt.from.closest('[data-stage]');
          if (!newCol || newCol === oldCol) return;
          var id = card.getAttribute('data-application-id');
          var newStage = newCol.getAttribute('data-stage');
          var oldStage = oldCol.getAttribute('data-stage');
          updateCount(newStage); updateCount(oldStage);
          persistStage(id, newStage)
            .then(function () { toast('Moved to ' + newCol.getAttribute('data-stage-label'), 'success'); })
            .catch(function () {
              evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
              updateCount(newStage); updateCount(oldStage);
              toast('Could not move candidate. Reverted.', 'error');
            });
        }
      });
    });
  }

  // Drop-target highlight.
  document.addEventListener('dragenter', function (e) {
    var list = e.target.closest && e.target.closest('[data-kanban-list]');
    if (list) list.classList.add('ring-2', 'ring-violet-300', 'bg-violet-50/50');
  });
  document.addEventListener('dragleave', function (e) {
    var list = e.target.closest && e.target.closest('[data-kanban-list]');
    if (list && !list.contains(e.relatedTarget)) list.classList.remove('ring-2', 'ring-violet-300', 'bg-violet-50/50');
  });

  /* ----- Bulk selection ----- */
  var selected = new Set();
  function refreshBulkBar() {
    var bar = document.getElementById('bulkBar'); var count = document.getElementById('bulkCount');
    if (!bar) return;
    if (selected.size > 0) { bar.classList.remove('hidden'); if (count) count.textContent = selected.size; }
    else bar.classList.add('hidden');
  }
  document.addEventListener('change', function (e) {
    var cb = e.target.closest('[data-card-select]'); if (!cb) return;
    var card = cb.closest('[data-card]'); var id = card.getAttribute('data-application-id');
    if (cb.checked) { selected.add(id); card.classList.add('ring-2', 'ring-violet-400'); }
    else { selected.delete(id); card.classList.remove('ring-2', 'ring-violet-400'); }
    refreshBulkBar();
  });
  document.addEventListener('click', function (e) {
    var action = e.target.closest('[data-bulk-stage]'); if (!action) return;
    var stage = action.getAttribute('data-bulk-stage');
    var ids = Array.from(selected); if (!ids.length) return;
    var run = function () {
      var done = 0; var pending = ids.length;
      ids.forEach(function (id) {
        persistStage(id, stage).then(function () {
          var card = document.querySelector('[data-card][data-application-id="' + id + '"]');
          var target = document.querySelector('[data-stage="' + stage + '"] [data-kanban-list]');
          if (card && target) target.appendChild(card);
          done++;
        }).catch(function () {}).finally(function () {
          if (--pending === 0) {
            document.querySelectorAll('[data-stage]').forEach(function (c) { updateCount(c.getAttribute('data-stage')); });
            selected.clear();
            document.querySelectorAll('[data-card-select]').forEach(function (cb) { cb.checked = false; });
            document.querySelectorAll('[data-card]').forEach(function (c) { c.classList.remove('ring-2', 'ring-violet-400'); });
            refreshBulkBar();
            toast(done + ' candidate(s) moved.', done ? 'success' : 'error');
          }
        });
      });
    };
    if (window.App && App.confirm) {
      App.confirm({ title: 'Move ' + ids.length + ' candidate(s)?', message: 'They will be moved to "' + action.textContent.trim() + '".', danger: false, confirmText: 'Move' })
        .then(function (ok) { if (ok) run(); });
    } else { run(); }
  });

  if (document.readyState !== 'loading') initSortable();
  else document.addEventListener('DOMContentLoaded', initSortable);
})();
