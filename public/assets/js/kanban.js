/* ============================================================
   AI Recruit — Pipeline Kanban board
   HTML5 drag & drop. Moving a card PUTs the new stage to
   /api/v1/candidates or pipeline endpoint. Optimistic UI.
   ============================================================ */
(function () {
  'use strict';

  const STAGES = ['applied', 'screening', 'ai_interview', 'human_interview', 'offer', 'hired', 'rejected'];

  class KanbanBoard {
    constructor(rootId) {
      this.root = document.getElementById(rootId);
      this.jobFilter = '';
      this.search = '';
      this.cards = []; // {id, candidate, job, score, stage}
      this.dragId = null;
    }

    async init() {
      if (!this.root) return;
      this.bindControls();
      this.render();           // render empty columns first
      await this.load();       // then fill with data
    }

    bindControls() {
      const jf = document.getElementById('kanban-job-filter');
      if (jf) jf.addEventListener('change', (e) => { this.jobFilter = e.target.value; this.load(); });
      const sb = document.getElementById('kanban-search');
      if (sb) sb.addEventListener('input', (e) => { this.search = e.target.value.toLowerCase(); this.render(); });
    }

    async load() {
      try {
        const qs = this.jobFilter ? ('?job_id=' + encodeURIComponent(this.jobFilter)) : '';
        const data = await window.AR.Api.get('/candidates' + qs);
        this.cards = (data || []).map((c) => ({
          id: c.application_id || c.id,
          candidateId: c.id,
          name: ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || c.email,
          job: c.job_title || '',
          score: c.ai_match_score != null ? Math.round(c.ai_match_score) : null,
          stage: c.pipeline_stage || 'applied',
        }));
      } catch (e) {
        this.cards = [];
        if (window.AR) AR.Toast.error('Could not load pipeline.');
      }
      this.render();
    }

    render() {
      if (!this.root) return;
      this.root.innerHTML = '';
      const labels = {
        applied: 'Applied', screening: 'Screening', ai_interview: 'AI Interview',
        human_interview: 'Human Interview', offer: 'Offer', hired: 'Hired', rejected: 'Rejected',
      };
      STAGES.forEach((stage) => {
        const col = document.createElement('div');
        col.className = 'kanban-col';
        col.dataset.stage = stage;

        const items = this.cards.filter((c) =>
          c.stage === stage &&
          (!this.search || c.name.toLowerCase().indexOf(this.search) >= 0 || (c.job || '').toLowerCase().indexOf(this.search) >= 0)
        );

        col.innerHTML =
          '<div class="px-3 py-3 flex items-center justify-between">' +
            '<span class="font-semibold text-sm text-gray-700">' + labels[stage] + '</span>' +
            '<span class="badge badge-violet">' + items.length + '</span>' +
          '</div>' +
          '<div class="kanban-body flex-1 px-2 pb-3 space-y-2 overflow-y-auto" style="min-height:120px"></div>';

        const body = col.querySelector('.kanban-body');
        items.forEach((card) => body.appendChild(this.cardEl(card)));

        // Drop zone handlers.
        col.addEventListener('dragover', (e) => { e.preventDefault(); col.classList.add('drag-over'); });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', (e) => {
          e.preventDefault();
          col.classList.remove('drag-over');
          this.onDrop(stage);
        });

        this.root.appendChild(col);
      });
    }

    cardEl(card) {
      const el = document.createElement('div');
      el.className = 'kanban-card';
      el.draggable = true;
      el.dataset.id = card.id;
      const initials = (card.name || '?').split(' ').map((s) => s[0]).slice(0, 2).join('').toUpperCase();
      const scoreHtml = card.score != null
        ? '<span class="badge ' + window.AR.scoreColor(card.score) + '">' + card.score + '%</span>'
        : '<span class="badge badge-gray">—</span>';
      el.innerHTML =
        '<div class="flex items-center gap-2">' +
          '<div class="w-8 h-8 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold">' + window.AR.esc(initials) + '</div>' +
          '<div class="min-w-0 flex-1">' +
            '<div class="text-sm font-semibold truncate">' + window.AR.esc(card.name) + '</div>' +
            '<div class="text-xs text-gray-500 truncate">' + window.AR.esc(card.job) + '</div>' +
          '</div>' +
          scoreHtml +
        '</div>';

      el.addEventListener('dragstart', (e) => {
        this.dragId = card.id;
        el.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', String(card.id)); } catch (err) {}
      });
      el.addEventListener('dragend', () => el.classList.remove('dragging'));
      el.addEventListener('dblclick', () => {
        if (card.candidateId) window.location.href = '/candidates/' + card.candidateId;
      });
      return el;
    }

    async onDrop(newStage) {
      const id = this.dragId;
      if (!id) return;
      const card = this.cards.find((c) => String(c.id) === String(id));
      if (!card || card.stage === newStage) { this.dragId = null; return; }

      const prevStage = card.stage;
      card.stage = newStage;     // optimistic
      this.render();

      try {
        await window.AR.Api.put('/candidates/' + card.candidateId, { pipeline_stage: newStage, application_id: card.id });
        if (window.AR) AR.Toast.success('Moved to ' + newStage.replace('_', ' '));
      } catch (e) {
        card.stage = prevStage;  // rollback
        this.render();
        if (window.AR) AR.Toast.error('Could not update stage.');
      }
      this.dragId = null;
    }
  }

  window.KanbanBoard = KanbanBoard;
})();
