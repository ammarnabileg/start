/**
 * app-compat.js — Compatibility shim.
 *
 * Some views in this project use an `App.*` helper API plus a set of
 * data-attribute hooks (data-modal-open, data-dropdown-trigger, [data-tabs]).
 * The shared global runtime (app.js) instead exposes window.showToast /
 * openModal / closeModal / confirm2 / initTabs and a different set of hooks.
 *
 * This shim bridges the two so both styles work on the same page WITHOUT
 * modifying app.js or any view. It is additive and idempotent.
 *
 * Load AFTER app.js. Safe to load even if app.js is absent (it degrades).
 */
(function () {
  'use strict';
  if (window.App && window.App.__compat) return;

  var App = window.App || {};
  App.__compat = true;

  function escapeHtml(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  App.escapeHtml = App.escapeHtml || escapeHtml;

  /* ---- Toasts: prefer global showToast, else build a local one ---- */
  App.toast = App.toast || function (message, type, opts) {
    type = type || 'info';
    if (typeof window.showToast === 'function') {
      return window.showToast(message, type, opts && typeof opts.duration === 'number' ? opts.duration : 4000);
    }
    var wrap = document.getElementById('toastContainer') || document.getElementById('toast-container');
    if (!wrap) { wrap = document.createElement('div'); wrap.id = 'toastContainer'; wrap.className = 'fixed top-4 right-4 z-[100] space-y-2 w-80'; document.body.appendChild(wrap); }
    var colors = { success: 'bg-emerald-600', error: 'bg-rose-600', warning: 'bg-amber-500', info: 'bg-violet-600' };
    var el = document.createElement('div');
    el.className = (colors[type] || colors.info) + ' text-white px-4 py-3 rounded-xl shadow-lg text-sm font-medium translate-x-4 opacity-0 transition-all duration-300';
    el.textContent = message;
    wrap.appendChild(el);
    requestAnimationFrame(function () { el.classList.remove('translate-x-4', 'opacity-0'); });
    setTimeout(function () { el.classList.add('translate-x-4', 'opacity-0'); setTimeout(function () { el.remove(); }, 300); }, (opts && opts.duration) || 4000);
    return el;
  };

  /* ---- Modals ----
     Views use: <div id="x" data-modal class="hidden …"> … with
     [data-modal-open="x"], [data-modal-close], [data-modal-overlay].
     We implement open/close directly (independent of the global modal system,
     which keys off different markup) so these views are fully functional. */
  App.openModal = function (id) {
    var m = document.getElementById(id);
    if (!m) { if (typeof window.openModal === 'function') return window.openModal(id); return; }
    m.classList.remove('hidden');
    m.classList.add('is-open');
    if (getComputedStyle(m).display === 'none') m.style.display = 'flex';
    requestAnimationFrame(function () { m.setAttribute('data-shown', '1'); });
    document.body.style.overflow = 'hidden';
  };
  App.closeModal = function (id) {
    var m = document.getElementById(id);
    if (!m) { if (typeof window.closeModal === 'function') return window.closeModal(id); return; }
    m.removeAttribute('data-shown');
    m.classList.remove('is-open');
    m.classList.add('hidden');
    m.style.removeProperty('display');
    document.body.style.overflow = '';
  };

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) { e.preventDefault(); App.openModal(opener.getAttribute('data-modal-open')); return; }
    var closer = e.target.closest('[data-modal-close]');
    if (closer) {
      var host = closer.closest('[data-modal]');
      if (host) { e.preventDefault(); App.closeModal(host.id); return; }
    }
    var overlay = e.target.closest('[data-modal-overlay]');
    if (overlay) {
      var hostO = overlay.closest('[data-modal]');
      if (hostO) App.closeModal(hostO.id);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('[data-modal].is-open').forEach(function (m) { App.closeModal(m.id); });
    }
  });

  /* ---- Confirm: promise wrapper over confirm2 if present, else custom ---- */
  App.confirm = function (opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      if (typeof window.confirm2 === 'function') {
        // confirm2 only fires onConfirm; treat cancel as false via DOM removal watch is unreliable,
        // so we render our own dialog when finer control (Promise) is needed.
      }
      var title = opts.title || 'Are you sure?';
      var message = opts.message || 'This action cannot be undone.';
      var confirmText = opts.confirmText || 'Confirm';
      var danger = opts.danger !== false;
      var overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-[120] flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm opacity-0 transition-opacity';
      overlay.innerHTML =
        '<div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 scale-95 transition-transform">' +
          '<h3 class="text-lg font-bold text-gray-900">' + escapeHtml(title) + '</h3>' +
          '<p class="mt-2 text-sm text-gray-600">' + escapeHtml(message) + '</p>' +
          '<div class="mt-6 flex justify-end gap-3">' +
            '<button data-cancel class="rounded-full px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>' +
            '<button data-ok class="rounded-full px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors ' +
              (danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-violet-600 hover:bg-violet-700') + '">' + escapeHtml(confirmText) + '</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(overlay);
      requestAnimationFrame(function () { overlay.classList.remove('opacity-0'); overlay.querySelector('div').classList.remove('scale-95'); });
      function close(v) { overlay.classList.add('opacity-0'); setTimeout(function () { overlay.remove(); }, 200); resolve(v); }
      overlay.querySelector('[data-ok]').addEventListener('click', function () { close(true); });
      overlay.querySelector('[data-cancel]').addEventListener('click', function () { close(false); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) close(false); });
    });
  };

  /* ---- AI "thinking" state ---- */
  App.aiThinking = function (target, label) {
    var el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return;
    el.classList.remove('hidden');
    el.innerHTML =
      '<div class="flex items-center gap-3 text-violet-700 bg-violet-50 rounded-xl px-4 py-3 text-sm font-medium">' +
        '<span class="flex gap-1">' +
          '<span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-bounce"></span>' +
          '<span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-bounce" style="animation-delay:.15s"></span>' +
          '<span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-bounce" style="animation-delay:.3s"></span>' +
        '</span>' + escapeHtml(label || 'AI is thinking…') +
      '</div>';
  };

  /* ---- Tabs ----
     Views use a [data-tabs] wrapper with [data-tab="x"] buttons and
     [data-panel="x"] panels, and call App.activateTab(rootEl, name). They also
     style the active tab with a `.tab-active` class. We delegate clicks and
     expose activateTab. (The global initTabs uses a different active-class
     scheme; scoping to [data-tabs] avoids conflicts.) */
  App.activateTab = function (scope, name) {
    var root = typeof scope === 'string' ? document.querySelector(scope) : scope;
    if (!root) return;
    root.querySelectorAll('[data-tab]').forEach(function (t) {
      var on = t.getAttribute('data-tab') === name;
      t.classList.toggle('tab-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('[data-panel]').forEach(function (p) {
      p.classList.toggle('hidden', p.getAttribute('data-panel') !== name);
    });
    root.dispatchEvent(new CustomEvent('tab:change', { detail: { name: name } }));
  };
  document.addEventListener('click', function (e) {
    var tab = e.target.closest('[data-tabs] [data-tab]');
    if (!tab) return;
    var root = tab.closest('[data-tabs]');
    App.activateTab(root, tab.getAttribute('data-tab'));
  });

  /* ---- Dropdowns ----
     Views use [data-dropdown] as a wrapper containing [data-dropdown-trigger]
     and a sibling [data-dropdown-menu]. The global handler instead treats
     [data-dropdown] as the trigger with a data-dropdown="id" target. To avoid
     double-handling, we only act when a [data-dropdown-trigger] is clicked. */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-dropdown-trigger]');
    if (trigger) {
      e.stopPropagation();
      var wrap = trigger.closest('[data-dropdown]');
      var menu = wrap ? wrap.querySelector('[data-dropdown-menu]') : null;
      if (!menu) return;
      var hidden = menu.classList.contains('hidden');
      // close other compat menus
      document.querySelectorAll('[data-dropdown] [data-dropdown-menu]:not(.hidden)').forEach(function (m) { if (m !== menu) m.classList.add('hidden'); });
      menu.classList.toggle('hidden', !hidden);
      return;
    }
    if (!e.target.closest('[data-dropdown-menu]')) {
      document.querySelectorAll('[data-dropdown] [data-dropdown-menu]:not(.hidden)').forEach(function (m) { m.classList.add('hidden'); });
    }
  });

  /* ---- CSRF + ajax convenience (mirror of the richer app.js) ---- */
  App.csrf = App.csrf || function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    var input = document.querySelector('input[name="_csrf"]');
    return input ? input.value : '';
  };
  App.ajax = App.ajax || (typeof window.ajax === 'function' ? window.ajax : function (url, options) {
    options = options || {}; options.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': App.csrf() }, options.headers || {});
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(options.body); }
    return fetch(url, options).then(function (r) { return r.json().catch(function () { return {}; }); });
  });

  /* ---- Sidebar toggle used by some views (App.toggleSidebar) ---- */
  App.toggleSidebar = App.toggleSidebar || function (open) {
    var sidebar = document.getElementById('appSidebar') || document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop') || document.getElementById('sidebar-overlay') || document.getElementById('overlay');
    if (!sidebar) return;
    var willOpen = typeof open === 'boolean' ? open : sidebar.classList.contains('-translate-x-full') || sidebar.classList.contains('closed');
    sidebar.classList.toggle('-translate-x-full', !willOpen);
    sidebar.classList.toggle('closed', !willOpen);
    if (backdrop) backdrop.classList.toggle('hidden', !willOpen);
  };

  window.App = App;
})();
