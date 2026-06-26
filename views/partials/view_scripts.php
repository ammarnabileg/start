<script>
/* ============================================================
   view_scripts.php — Common JS utilities for AI Recruit views
   ============================================================ */

/* ---- Toast notification system ---- */
(function () {
    var _container = null;

    function _getContainer() {
        if (!_container) {
            _container = document.getElementById('toast-container');
            if (!_container) {
                _container = document.createElement('div');
                _container.id = 'toast-container';
                _container.style.cssText =
                    'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
                document.body.appendChild(_container);
            }
        }
        return _container;
    }

    var _typeStyles = {
        success: { bg: 'rgba(34,197,94,0.12)',  border: 'rgba(34,197,94,0.35)',  color: '#86efac', icon: '✓' },
        error:   { bg: 'rgba(239,68,68,0.12)',  border: 'rgba(239,68,68,0.35)',  color: '#fca5a5', icon: '✕' },
        warning: { bg: 'rgba(245,158,11,0.12)', border: 'rgba(245,158,11,0.35)', color: '#fde68a', icon: '⚠' },
        info:    { bg: 'rgba(79,70,229,0.12)',  border: 'rgba(79,70,229,0.35)',  color: '#a5b4fc', icon: 'ℹ' },
    };

    /**
     * showToast(msg, type='info', duration=4000)
     * type: 'success' | 'error' | 'warning' | 'info'
     */
    window.showToast = function (msg, type, duration) {
        type = type || 'info';
        duration = duration !== undefined ? duration : 4000;
        var s = _typeStyles[type] || _typeStyles.info;
        var c = _getContainer();

        var toast = document.createElement('div');
        toast.style.cssText = [
            'display:flex;align-items:flex-start;gap:10px;',
            'background:' + s.bg + ';',
            'border:1px solid ' + s.border + ';',
            'border-radius:10px;',
            'padding:12px 16px;',
            'max-width:340px;min-width:240px;',
            'box-shadow:0 8px 24px rgba(0,0,0,0.3);',
            'font-family:inherit;font-size:0.875rem;color:' + s.color + ';',
            'animation:_toastIn 0.22s ease;',
        ].join('');

        toast.innerHTML =
            '<span style="font-size:1rem;flex-shrink:0;margin-top:1px;">' + s.icon + '</span>' +
            '<span style="flex:1;line-height:1.4;">' + msg + '</span>' +
            '<button onclick="this.parentNode.remove()" style="background:none;border:none;color:' + s.color + ';cursor:pointer;font-size:1rem;padding:0;opacity:0.6;flex-shrink:0;">×</button>';

        c.appendChild(toast);

        if (duration > 0) {
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(20px)';
                    toast.style.transition = 'opacity 0.3s,transform 0.3s';
                    setTimeout(function () { if (toast.parentNode) toast.remove(); }, 300);
                }
            }, duration);
        }
    };

    // Keyframe injection
    if (!document.getElementById('_toastKeyframes')) {
        var style = document.createElement('style');
        style.id = '_toastKeyframes';
        style.textContent = '@keyframes _toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}';
        document.head.appendChild(style);
    }
})();


/* ---- AJAX helper ---- */
/**
 * api(url, method='GET', data=null) -> Promise<{ok, status, data}>
 * Sends JSON, returns parsed JSON or {error}.
 */
window.api = function (url, method, data) {
    method = (method || 'GET').toUpperCase();
    var opts = {
        method: method,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    };

    // Attach CSRF token if available (looks for meta tag or hidden input)
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfInput = document.querySelector('input[name="_token"]');
    var csrfToken = (csrfMeta && csrfMeta.content) || (csrfInput && csrfInput.value) || null;
    if (csrfToken) opts.headers['X-CSRF-Token'] = csrfToken;

    if (data && method !== 'GET') {
        opts.body = JSON.stringify(data);
    }

    return fetch(url, opts).then(function (res) {
        return res.text().then(function (text) {
            var parsed;
            try { parsed = JSON.parse(text); } catch (e) { parsed = { _raw: text }; }
            return { ok: res.ok, status: res.status, data: parsed };
        });
    }).catch(function (err) {
        return { ok: false, status: 0, data: { error: err.message } };
    });
};


/* ---- Confirm dialog helper ---- */
/**
 * confirmAction(message, onConfirm, onCancel)
 * Shows a styled modal confirm. Falls back to window.confirm if modal not present.
 */
window.confirmAction = function (message, onConfirm, onCancel) {
    var modal = document.getElementById('confirmModal');
    if (!modal) {
        // Inject a minimal confirm modal
        modal = document.createElement('div');
        modal.id = 'confirmModal';
        modal.style.cssText =
            'display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.6);' +
            'align-items:center;justify-content:center;';
        modal.innerHTML = [
            '<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,0.2);border-radius:16px;',
            'padding:32px;max-width:380px;width:90%;box-shadow:0 25px 60px rgba(0,0,0,0.5);">',
            '<div id="_confirmIcon" style="font-size:2rem;text-align:center;margin-bottom:12px;">⚠️</div>',
            '<p id="_confirmMsg" style="color:#e2e8f0;font-size:0.9375rem;text-align:center;line-height:1.5;margin-bottom:24px;"></p>',
            '<div style="display:flex;gap:12px;justify-content:center;">',
            '<button id="_confirmOk" style="padding:10px 28px;background:linear-gradient(135deg,#4f46e5,#7c3aed);',
            'border:none;border-radius:8px;color:#fff;font-size:0.9375rem;font-weight:600;cursor:pointer;">Confirm</button>',
            '<button id="_confirmCancel" style="padding:10px 28px;background:rgba(79,70,229,0.1);',
            'border:1px solid rgba(79,70,229,0.3);border-radius:8px;color:#a5b4fc;font-size:0.9375rem;font-weight:600;cursor:pointer;">Cancel</button>',
            '</div></div>',
        ].join('');
        document.body.appendChild(modal);
    }

    document.getElementById('_confirmMsg').textContent = message || 'Are you sure?';
    modal.style.display = 'flex';

    var ok = document.getElementById('_confirmOk');
    var cancel = document.getElementById('_confirmCancel');

    function cleanup() { modal.style.display = 'none'; ok.onclick = null; cancel.onclick = null; }

    ok.onclick = function () { cleanup(); if (typeof onConfirm === 'function') onConfirm(); };
    cancel.onclick = function () { cleanup(); if (typeof onCancel === 'function') onCancel(); };

    modal.onclick = function (e) {
        if (e.target === modal) { cleanup(); if (typeof onCancel === 'function') onCancel(); }
    };
};


/* ---- Modal open/close helpers ---- */
/**
 * openModal(id)  — show a modal element by id
 * closeModal(id) — hide a modal element by id
 */
window.openModal = function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // Close on backdrop click
    el.onclick = function (e) { if (e.target === el) window.closeModal(id); };
    // Close on Escape
    var _esc = function (e) { if (e.key === 'Escape') { window.closeModal(id); document.removeEventListener('keydown', _esc); } };
    document.addEventListener('keydown', _esc);
};

window.closeModal = function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
};

// Wire up [data-modal-open] and [data-modal-close] attributes
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.openModal(btn.getAttribute('data-modal-open')); });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.closeModal(btn.getAttribute('data-modal-close')); });
    });
});


/* ---- Auto-dismissing flash messages ---- */
document.addEventListener('DOMContentLoaded', function () {
    // Elements with class "flash-message" auto-dismiss after 5s
    var flashes = document.querySelectorAll('.flash-message[data-auto-dismiss]');
    flashes.forEach(function (el) {
        var delay = parseInt(el.getAttribute('data-auto-dismiss'), 10) || 5000;
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s, max-height 0.4s, margin 0.4s, padding 0.4s';
            el.style.opacity = '0';
            el.style.maxHeight = '0';
            el.style.overflow = 'hidden';
            el.style.marginBottom = '0';
            el.style.padding = '0';
            setTimeout(function () { if (el.parentNode) el.remove(); }, 450);
        }, delay);
    });

    // Wire close buttons inside flash alerts
    document.querySelectorAll('[data-flash-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.closest('.flash-message') || document.getElementById(btn.getAttribute('data-flash-close'));
            if (target) target.remove();
        });
    });
});


/* ---- Convenient delete-with-confirm helper ---- */
/**
 * Usage in HTML:
 *   <button onclick="deleteRecord('/api/jobs/5', 'Delete this job?')">Delete</button>
 */
window.deleteRecord = function (url, message, onSuccess) {
    window.confirmAction(message || 'Delete this record? This cannot be undone.', function () {
        window.api(url, 'DELETE').then(function (res) {
            if (res.ok) {
                window.showToast('Deleted successfully.', 'success');
                if (typeof onSuccess === 'function') onSuccess(res);
                else setTimeout(function () { window.location.reload(); }, 800);
            } else {
                var msg = (res.data && res.data.message) || 'Delete failed. Please try again.';
                window.showToast(msg, 'error');
            }
        });
    });
};
</script>
