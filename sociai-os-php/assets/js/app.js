/**
 * SociAI OS - Application JavaScript
 * Vanilla JS — no jQuery dependency
 */

'use strict';

// ============================================================
// CSRF Token helper
// ============================================================
const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || '';

// ============================================================
// API Client
// ============================================================
const API = {
    async request(method, url, data = null) {
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-Token': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        };
        if (data && method !== 'GET') {
            opts.body = JSON.stringify(data);
        }
        try {
            const res  = await fetch(url, opts);
            const json = await res.json();
            if (!res.ok) throw { status: res.status, ...json };
            return json;
        } catch (err) {
            console.error('[API]', method, url, err);
            throw err;
        }
    },
    get:    (url)         => API.request('GET',    url),
    post:   (url, data)   => API.request('POST',   url, data),
    put:    (url, data)   => API.request('PUT',    url, data),
    patch:  (url, data)   => API.request('PATCH',  url, data),
    delete: (url)         => API.request('DELETE', url),
};

// ============================================================
// Sidebar toggle
// ============================================================
(function initSidebar() {
    const sidebar        = document.getElementById('sidebar');
    const mobileToggle   = document.getElementById('mobileSidebarToggle');
    const desktopToggle  = document.getElementById('sidebarToggle');

    if (!sidebar) return;

    // Create overlay for mobile
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.id = 'sidebarOverlay';
    document.body.appendChild(overlay);

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (mobileToggle) mobileToggle.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);

    // Close on nav link click (mobile)
    sidebar.querySelectorAll('.nav-link').forEach(link =>
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) closeSidebar();
        })
    );
})();

// ============================================================
// Auto-dismiss alerts
// ============================================================
document.querySelectorAll('.alert.alert-dismissible').forEach(el => {
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        if (bsAlert) bsAlert.close();
    }, 5000);
});

// ============================================================
// AJAX Form Submission
// ============================================================
document.querySelectorAll('form[data-ajax]').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn     = this.querySelector('[type="submit"]');
        const origText = btn?.innerHTML;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait...';
        }

        try {
            const formData = new FormData(this);
            const data     = Object.fromEntries(formData.entries());
            const method   = (data._method || this.method || 'POST').toUpperCase();
            const url      = this.action || window.location.pathname;

            const result = await API.request(method, url, data);

            const successCallback = this.dataset.onSuccess;
            if (successCallback && window[successCallback]) {
                window[successCallback](result, this);
            } else {
                showToast(result.message || 'Success!', 'success');
                if (this.dataset.redirect) {
                    window.location.href = this.dataset.redirect;
                }
            }
        } catch (err) {
            const msg = err.message || (err.errors ? Object.values(err.errors).flat().join(' ') : 'An error occurred.');
            showToast(msg, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
        }
    });
});

// ============================================================
// Toast Notifications
// ============================================================
function showToast(message, type = 'info', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;max-width:360px;';
        document.body.appendChild(container);
    }

    const colors = {
        success: { bg: 'rgba(32,201,151,.15)', border: 'rgba(32,201,151,.3)', color: '#a3e6cf', icon: '✓' },
        error:   { bg: 'rgba(220,53,69,.15)',  border: 'rgba(220,53,69,.3)',  color: '#f8d7da', icon: '✕' },
        warning: { bg: 'rgba(255,193,7,.15)',  border: 'rgba(255,193,7,.3)',  color: '#ffe69c', icon: '⚠' },
        info:    { bg: 'rgba(13,202,240,.15)', border: 'rgba(13,202,240,.3)', color: '#9eeaf9', icon: 'ℹ' },
    };
    const c = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.style.cssText = `background:${c.bg};border:1px solid ${c.border};color:${c.color};padding:.75rem 1rem;border-radius:12px;font-size:.875rem;display:flex;align-items:flex-start;gap:.5rem;box-shadow:0 8px 30px rgba(0,0,0,.3);animation:slideIn .3s ease;`;
    toast.innerHTML = `<span style="font-weight:700;flex-shrink:0">${c.icon}</span><span style="flex:1">${escapeHtml(message)}</span><button onclick="this.closest('[style]').remove()" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1rem;padding:0;margin-left:.5rem;opacity:.6">×</button>`;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut .3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ============================================================
// Confirm dialogs
// ============================================================
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm || 'Are you sure?')) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    });
});

// ============================================================
// AI Content Generator (front-end helper)
// ============================================================
const AIGenerator = {
    async generate(brandSlug, params) {
        const overlay = document.getElementById('ai-loading');
        if (overlay) overlay.classList.remove('d-none');

        try {
            const result = await API.post(`/brands/${brandSlug}/agents/generate`, params);
            if (result.success) {
                showToast('Content generated successfully! 🔥', 'success');
                return result.data;
            }
        } catch (err) {
            showToast(err.message || 'AI generation failed.', 'error');
        } finally {
            if (overlay) overlay.classList.add('d-none');
        }
        return null;
    },

    async pollTaskStatus(taskId, onUpdate) {
        const poll = async () => {
            try {
                const res = await API.get(`/api/v1/ai/tasks/${taskId}`);
                if (res.success) {
                    onUpdate(res.data);
                    if (res.data.status === 'running' || res.data.status === 'pending') {
                        setTimeout(poll, 1500);
                    }
                }
            } catch { /* ignore */ }
        };
        poll();
    }
};

// ============================================================
// Copy to clipboard
// ============================================================
document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', async function() {
        const text = this.dataset.copy || document.querySelector(this.dataset.copyTarget)?.innerText;
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            const orig = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check2"></i>';
            setTimeout(() => { this.innerHTML = orig; }, 1500);
        } catch { showToast('Copy failed.', 'error'); }
    });
});

// ============================================================
// Viral Score Visualiser
// ============================================================
function renderViralScore(score, element) {
    if (!element) return;
    const cls   = score >= 70 ? 'high' : score >= 40 ? 'medium' : 'low';
    const emoji = score >= 70 ? '🔥' : score >= 40 ? '⚡' : '❄️';
    element.innerHTML = `<span class="viral-score ${cls}">${emoji} ${score.toFixed(1)}</span>`;
}

// ============================================================
// Character counter for textareas
// ============================================================
document.querySelectorAll('textarea[data-maxlength]').forEach(ta => {
    const max     = parseInt(ta.dataset.maxlength, 10);
    const counter = document.createElement('small');
    counter.className = 'char-counter text-muted';
    ta.parentNode.appendChild(counter);

    const update = () => {
        const remaining = max - ta.value.length;
        counter.textContent = `${ta.value.length} / ${max}`;
        counter.style.color = remaining < 20 ? '#dc3545' : remaining < 50 ? '#ffc107' : '#8b8b9e';
    };
    ta.addEventListener('input', update);
    update();
});

// ============================================================
// Inline Content Approval Buttons
// ============================================================
document.querySelectorAll('[data-action="approve-content"],[data-action="reject-content"]').forEach(btn => {
    btn.addEventListener('click', async function() {
        const action    = this.dataset.action;
        const contentId = this.dataset.contentId;
        const brandSlug = this.dataset.brandSlug;
        const url       = `/brands/${brandSlug}/content/${contentId}/${action === 'approve-content' ? 'approve' : 'reject'}`;

        let reason = '';
        if (action === 'reject-content') {
            reason = prompt('Rejection reason:');
            if (reason === null) return;
        }

        try {
            const res = await API.post(url, { reason, _csrf: getCsrfToken() });
            showToast(res.message || 'Done.', 'success');
            this.closest('[data-content-row]')?.classList.toggle('opacity-50', true);
        } catch (err) {
            showToast(err.message || 'Action failed.', 'error');
        }
    });
});

// ============================================================
// Utilities
// ============================================================
function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatNumber(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return String(n);
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' });
}

// Add CSS keyframes for toast animations
const style = document.createElement('style');
style.textContent = `
@keyframes slideIn { from{transform:translateX(120%);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes slideOut { from{transform:translateX(0);opacity:1} to{transform:translateX(120%);opacity:0} }
`;
document.head.appendChild(style);

// Export to global scope
window.SociAI = { API, AIGenerator, showToast, renderViralScore, formatNumber, formatDate };
