/* ============================================================
   AI Recruit — core front-end helpers
   Exposes window.AR (Api, Toast, Modal, I18n, etc.)
   ============================================================ */
(function () {
  'use strict';

  const TOKEN_KEY = 'ar_token';
  const LANG_KEY = 'ar_lang';

  // ---- CSRF ----------------------------------------------------------------
  function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  // ---- Token storage -------------------------------------------------------
  const Auth = {
    token() { return localStorage.getItem(TOKEN_KEY) || ''; },
    setToken(t) { if (t) localStorage.setItem(TOKEN_KEY, t); },
    clear() { localStorage.removeItem(TOKEN_KEY); },
  };

  // ---- API helper (fetch wrapper) -----------------------------------------
  const Api = {
    base: '/api/v1',
    async request(method, path, body) {
      const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken(),
      };
      const token = Auth.token();
      if (token) headers['Authorization'] = 'Bearer ' + token;
      const opts = { method, headers, credentials: 'same-origin' };
      if (body !== undefined && body !== null) {
        if (body instanceof FormData) {
          opts.body = body; // browser sets content-type
        } else {
          headers['Content-Type'] = 'application/json';
          opts.body = JSON.stringify(body);
        }
      }
      const res = await fetch(this.base + path, opts);
      let data = null;
      try { data = await res.json(); } catch (e) { data = null; }
      if (!res.ok || (data && data.success === false)) {
        const msg = (data && data.error) || ('Request failed (' + res.status + ')');
        const err = new Error(msg);
        err.status = res.status;
        err.details = data && data.details;
        throw err;
      }
      return data ? data.data : null;
    },
    get(p) { return this.request('GET', p); },
    post(p, b) { return this.request('POST', p, b); },
    put(p, b) { return this.request('PUT', p, b); },
    del(p) { return this.request('DELETE', p); },
  };

  // ---- Toast ---------------------------------------------------------------
  const Toast = {
    root() {
      let r = document.getElementById('toast-root');
      if (!r) { r = document.createElement('div'); r.id = 'toast-root'; document.body.appendChild(r); }
      return r;
    },
    show(message, type) {
      type = type || 'info';
      const el = document.createElement('div');
      el.className = 'toast ' + type;
      el.textContent = message;
      this.root().appendChild(el);
      setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 250); }, 3200);
    },
    success(m) { this.show(m, 'success'); },
    error(m) { this.show(m, 'error'); },
    info(m) { this.show(m, 'info'); },
  };

  // ---- Modal ---------------------------------------------------------------
  const Modal = {
    open(id) { const el = document.getElementById(id); if (el) el.classList.remove('hidden-modal'); },
    close(id) { const el = document.getElementById(id); if (el) el.classList.add('hidden-modal'); },
    toggle(id) { const el = document.getElementById(id); if (el) el.classList.toggle('hidden-modal'); },
  };

  // ---- I18n / language switcher -------------------------------------------
  const I18n = {
    current() { return localStorage.getItem(LANG_KEY) || document.documentElement.lang || 'en'; },
    set(lang) {
      localStorage.setItem(LANG_KEY, lang);
      document.cookie = 'lang=' + lang + ';path=/;max-age=31536000';
      document.documentElement.lang = lang;
      document.documentElement.dir = (lang === 'ar') ? 'rtl' : 'ltr';
      location.reload();
    },
    apply() {
      const lang = this.current();
      document.documentElement.lang = lang;
      document.documentElement.dir = (lang === 'ar') ? 'rtl' : 'ltr';
    },
  };

  // ---- Navigation active state --------------------------------------------
  function highlightNav() {
    const path = location.pathname;
    document.querySelectorAll('[data-nav]').forEach((a) => {
      const href = a.getAttribute('href');
      if (href && (path === href || (href !== '/' && path.indexOf(href) === 0))) {
        a.classList.add('active');
      }
    });
  }

  // ---- Mobile sidebar toggle ----------------------------------------------
  function bindSidebar() {
    const btn = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('app-sidebar');
    if (btn && sidebar) {
      btn.addEventListener('click', () => sidebar.classList.toggle('-translate-x-full'));
    }
  }

  // ---- User menu dropdown --------------------------------------------------
  function bindDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((trigger) => {
      const menu = document.getElementById(trigger.getAttribute('data-dropdown'));
      if (!menu) return;
      trigger.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('hidden'); });
    });
    document.addEventListener('click', () => {
      document.querySelectorAll('[data-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
    });
  }

  // ---- Helpers exposed -----------------------------------------------------
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }
  function scoreColor(score) {
    if (score >= 75) return 'badge-green';
    if (score >= 50) return 'badge-yellow';
    return 'badge-red';
  }
  function recoBadge(reco) {
    const map = { hire: 'badge-green', invite: 'badge-green', maybe: 'badge-yellow', reject: 'badge-red' };
    return map[reco] || 'badge-gray';
  }

  // ---- Boot ----------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    I18n.apply();
    highlightNav();
    bindSidebar();
    bindDropdowns();

    // Language switch buttons.
    document.querySelectorAll('[data-lang]').forEach((b) => {
      b.addEventListener('click', () => I18n.set(b.getAttribute('data-lang')));
    });

    // Generic modal open/close triggers.
    document.querySelectorAll('[data-modal-open]').forEach((b) => {
      b.addEventListener('click', () => Modal.open(b.getAttribute('data-modal-open')));
    });
    document.querySelectorAll('[data-modal-close]').forEach((b) => {
      b.addEventListener('click', () => Modal.close(b.getAttribute('data-modal-close')));
    });
  });

  window.AR = { Api, Auth, Toast, Modal, I18n, esc, scoreColor, recoBadge, csrfToken };
})();
