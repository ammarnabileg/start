/**
 * app.js — Global utilities for AI Recruitment Platform
 * Vanilla JS, no framework dependencies.
 */

// ─── Toast Notifications ──────────────────────────────────────────────────────
window.showToast = function(message, type = 'info', duration = 4000) {
  const colors = {
    success: 'bg-emerald-600',
    error:   'bg-red-600',
    warning: 'bg-amber-500',
    info:    'bg-violet-600'
  };
  const icons = {
    success: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
    error:   '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
    warning: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    info:    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
  };

  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'fixed top-4 right-4 z-50 flex flex-col gap-2';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `${colors[type]} text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 min-w-72 max-w-sm transform translate-x-full transition-transform duration-300`;
  toast.innerHTML = `
    <span class="shrink-0">${icons[type]}</span>
    <span class="text-sm font-medium flex-1">${message}</span>
    <button onclick="this.closest('[class]').remove()" class="shrink-0 opacity-75 hover:opacity-100">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  `;
  container.appendChild(toast);
  requestAnimationFrame(() => { toast.classList.remove('translate-x-full'); });
  setTimeout(() => {
    toast.classList.add('translate-x-full');
    setTimeout(() => toast.remove(), 300);
  }, duration);
};

// ─── AJAX Helper ─────────────────────────────────────────────────────────────
window.ajax = async function(url, options = {}) {
  const defaults = {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  };
  const config = { ...defaults, ...options };
  if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
    config.body = JSON.stringify(config.body);
  }
  if (config.body instanceof FormData) {
    delete config.headers['Content-Type'];
  }
  const res = await fetch(url, config);
  const data = await res.json().catch(() => ({ ok: false, message: 'Invalid response' }));
  if (!res.ok && !data.ok) throw new Error(data.message || `HTTP ${res.status}`);
  return data;
};

// ─── Modal System ─────────────────────────────────────────────────────────────
window.openModal = function(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('hidden');
  el.classList.add('flex');
  document.body.style.overflow = 'hidden';
  el.querySelector('[data-modal-close], .modal-close')?.focus();
};

window.closeModal = function(id) {
  const el = id ? document.getElementById(id) : null;
  const modals = el ? [el] : document.querySelectorAll('.modal-overlay:not(.hidden)');
  modals.forEach(m => {
    m.classList.add('hidden');
    m.classList.remove('flex');
  });
  document.body.style.overflow = '';
};

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) closeModal();
  if (e.target.closest('[data-modal-close]')) {
    const modal = e.target.closest('[data-modal-id]');
    if (modal) closeModal(modal.id);
    else closeModal();
  }
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ─── Tabs ─────────────────────────────────────────────────────────────────────
window.initTabs = function(container) {
  const tabs = (container || document).querySelectorAll('[data-tab]');
  const panels = (container || document).querySelectorAll('[data-panel]');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.tab;
      tabs.forEach(t => {
        const active = t.dataset.tab === target;
        t.classList.toggle('border-violet-600', active);
        t.classList.toggle('text-violet-600', active);
        t.classList.toggle('border-transparent', !active);
        t.classList.toggle('text-gray-500', !active);
      });
      panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== target));
    });
  });
};
document.addEventListener('DOMContentLoaded', () => initTabs());

// ─── Confirmation Dialog ───────────────────────────────────────────────────────
window.confirm2 = function(message, onConfirm, type = 'danger') {
  const id = 'confirm-dialog-' + Date.now();
  const btnClass = type === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-violet-600 hover:bg-violet-700';
  const div = document.createElement('div');
  div.id = id;
  div.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 modal-overlay';
  div.innerHTML = `
    <div class="bg-white rounded-2xl shadow-xl p-6 max-w-sm w-full mx-4">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900">Confirm Action</h3>
          <p class="text-sm text-gray-500">${message}</p>
        </div>
      </div>
      <div class="flex gap-3 justify-end">
        <button onclick="document.getElementById('${id}').remove()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full">Cancel</button>
        <button id="${id}-confirm" class="px-4 py-2 text-sm font-medium text-white ${btnClass} rounded-full">Confirm</button>
      </div>
    </div>
  `;
  document.body.appendChild(div);
  document.getElementById(id + '-confirm').addEventListener('click', () => {
    div.remove();
    onConfirm();
  });
};

// ─── Loading Button State ─────────────────────────────────────────────────────
window.setLoading = function(btn, loading, text) {
  if (loading) {
    btn._originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>${text || 'Loading...'}`;
  } else {
    btn.disabled = false;
    btn.innerHTML = btn._originalHTML || text || 'Submit';
  }
};

// ─── Dropdown Toggles ─────────────────────────────────────────────────────────
document.addEventListener('click', e => {
  const trigger = e.target.closest('[data-dropdown]');
  if (trigger) {
    e.stopPropagation();
    const targetId = trigger.dataset.dropdown;
    const menu = document.getElementById(targetId);
    if (!menu) return;
    const isOpen = !menu.classList.contains('hidden');
    document.querySelectorAll('[data-dropdown-menu]:not(.hidden)').forEach(m => m.classList.add('hidden'));
    menu.classList.toggle('hidden', isOpen);
    return;
  }
  document.querySelectorAll('[data-dropdown-menu]:not(.hidden)').forEach(m => m.classList.add('hidden'));
});

// ─── Sidebar Mobile Toggle ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const openBtn = document.getElementById('sidebar-open');
  const closeBtn = document.getElementById('sidebar-close');

  openBtn?.addEventListener('click', () => {
    sidebar?.classList.remove('-translate-x-full');
    overlay?.classList.remove('hidden');
  });
  const closeSidebar = () => {
    sidebar?.classList.add('-translate-x-full');
    overlay?.classList.add('hidden');
  };
  closeBtn?.addEventListener('click', closeSidebar);
  overlay?.addEventListener('click', closeSidebar);
});

// ─── Auto-hide alerts ────────────────────────────────────────────────────────
document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
  setTimeout(() => el.remove(), parseInt(el.dataset.autoDismiss) || 5000);
});

// ─── Clipboard Copy ───────────────────────────────────────────────────────────
window.copyToClipboard = function(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn?.innerHTML;
    if (btn) btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
    showToast('Copied to clipboard', 'success', 2000);
    if (btn) setTimeout(() => { btn.innerHTML = orig; }, 2000);
  });
};

// ─── AI Copilot (defined in app.php layout, here just the API call) ───────────
window.sendCopilotMessage = async function(message, history, onChunk) {
  const res = await fetch('/api/v1/ai?action=copilot', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
    body: JSON.stringify({ message, history })
  });
  const data = await res.json();
  return data.data?.reply || data.reply || 'Sorry, I could not process that.';
};
