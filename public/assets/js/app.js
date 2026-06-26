/**
 * AI Recruit – Global JS
 * Provides: api(), showToast(), modal helpers, AJAX form handling
 */

/* ---- Toast ---------------------------------------------------------- */
var _toastContainer = null;

function showToast(message, type, duration) {
    if (!_toastContainer) {
        _toastContainer = document.createElement('div');
        _toastContainer.className = 'toast-container';
        document.body.appendChild(_toastContainer);
    }
    var icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
    var t = document.createElement('div');
    t.className = 'toast ' + (type || 'info');
    t.innerHTML = '<span>' + (icons[type] || icons.info) + '</span><span>' + escapeHtml(message) + '</span>';
    _toastContainer.appendChild(t);
    setTimeout(function() {
        t.style.opacity = '0';
        t.style.transition = 'opacity .3s';
        setTimeout(function() { t.remove(); }, 300);
    }, duration || 3500);
}

function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

/* ---- API Helper ----------------------------------------------------- */
function api(url, method, data) {
    var opts = {
        method: method || 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    if (data && method !== 'GET') {
        opts.body = JSON.stringify(data);
    }
    return fetch(url, opts)
        .then(function(res) {
            return res.json().then(function(json) {
                json._status = res.status;
                json.ok = res.ok;
                return json;
            });
        })
        .catch(function(err) {
            showToast('Network error. Please check your connection.', 'error');
            return { ok: false, error: err.message };
        });
}

/* ---- Flash messages from data attribute ----------------------------- */
document.addEventListener('DOMContentLoaded', function() {
    var flash = document.querySelector('[data-flash]');
    if (flash) {
        var msg = flash.getAttribute('data-flash');
        var type = flash.getAttribute('data-flash-type') || 'success';
        if (msg) showToast(msg, type);
    }
});

/* ---- Auto-resize textarea ------------------------------------------- */
document.addEventListener('input', function(e) {
    if (e.target.tagName === 'TEXTAREA' && e.target.classList.contains('auto-resize')) {
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 300) + 'px';
    }
});

/* ---- Confirm data attribute ----------------------------------------- */
document.addEventListener('click', function(e) {
    var el = e.target.closest('[data-confirm]');
    if (el) {
        if (!confirm(el.getAttribute('data-confirm'))) {
            e.preventDefault();
            e.stopPropagation();
        }
    }
});

/* ---- AJAX delete buttons -------------------------------------------- */
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-delete]');
    if (!btn) return;
    var url = btn.getAttribute('data-delete');
    var msg = btn.getAttribute('data-confirm') || 'Are you sure you want to delete this?';
    if (!confirm(msg)) return;
    e.preventDefault();
    api(url, 'DELETE', {}).then(function(res) {
        if (res.ok) {
            showToast(res.message || 'Deleted successfully', 'success');
            var row = btn.closest('[data-delete-target]') || btn.closest('tr');
            if (row) {
                row.style.opacity = '0';
                row.style.transition = 'opacity .3s';
                setTimeout(function() { row.remove(); }, 300);
            }
        } else {
            showToast(res.message || 'Error deleting item', 'error');
        }
    });
});

/* ---- Dropdown menus -------------------------------------------------- */
document.addEventListener('click', function(e) {
    if (!e.target.closest('[data-dropdown]')) {
        document.querySelectorAll('.dropdown-open').forEach(function(d) {
            d.classList.remove('dropdown-open');
        });
    }
});

document.addEventListener('click', function(e) {
    var trigger = e.target.closest('[data-dropdown-trigger]');
    if (trigger) {
        var menu = trigger.closest('[data-dropdown]');
        if (menu) menu.classList.toggle('dropdown-open');
    }
});

/* ---- Modal helpers -------------------------------------------------- */
function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'flex';
}

function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('[id$="Modal"]').forEach(function(m) {
            m.style.display = 'none';
        });
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.style.display = 'none';
    }
});

/* ---- Sidebar active link -------------------------------------------- */
document.addEventListener('DOMContentLoaded', function() {
    var path = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(function(a) {
        var href = a.getAttribute('href');
        if (href && (path === href || (href !== '/' && path.startsWith(href)))) {
            a.classList.add('active');
        }
    });
});

/* ---- Copy to clipboard ---------------------------------------------- */
function copyText(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard', 'success');
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('Copied!', 'success');
    }
}

/* ---- Pagination links – preserve query params ----------------------- */
function paginate(page) {
    var url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}
