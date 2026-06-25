<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6 max-w-2xl">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Notifications</h2>
            <p class="text-gray-500 mt-1">Stay up to date with your applications.</p>
        </div>
        <button id="mark-all-btn" onclick="markAllRead()"
            class="text-sm text-indigo-600 hover:text-indigo-700 font-medium px-4 py-2 border border-indigo-200 rounded-xl hover:bg-indigo-50 transition-colors">
            Mark all read
        </button>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden" id="notif-container">
        <div class="flex items-center justify-center py-16 text-gray-400">
            <svg class="w-6 h-6 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Loading notifications…
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function timeAgo(dateStr) {
    const now = new Date();
    const then = new Date(dateStr);
    const diff = Math.floor((now - then) / 1000);
    if (diff < 60)    return 'Just now';
    if (diff < 3600)  return `${Math.floor(diff/60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
    if (diff < 604800)return `${Math.floor(diff/86400)}d ago`;
    return then.toLocaleDateString('en-US',{month:'short',day:'numeric'});
}

const notifIcons = {
    application: { icon:'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2', bg:'bg-indigo-100', color:'text-indigo-600' },
    interview:   { icon:'M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', bg:'bg-purple-100', color:'text-purple-600' },
    offer:       { icon:'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', bg:'bg-amber-100', color:'text-amber-600' },
    qualified:   { icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', bg:'bg-green-100', color:'text-green-600' },
    rejected:    { icon:'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z', bg:'bg-red-100', color:'text-red-600' },
    default:     { icon:'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9', bg:'bg-gray-100', color:'text-gray-600' },
};

let notifications = [];

async function loadNotifications() {
    try {
        const res = await fetch('/api/v1/notifications', {headers:{'X-CSRF-Token':CSRF}});
        const json = await res.json();
        notifications = json.data || [];
        renderNotifications();
    } catch(e) {
        document.getElementById('notif-container').innerHTML = `<div class="px-6 py-12 text-center text-red-400">Failed to load. Please refresh.</div>`;
    }
}

function renderNotifications() {
    const container = document.getElementById('notif-container');
    const unreadCount = notifications.filter(n => !n.read_at).length;
    document.getElementById('mark-all-btn').style.display = unreadCount > 0 ? '' : 'none';

    if (notifications.length === 0) {
        container.innerHTML = `<div class="px-6 py-16 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="font-medium">All caught up!</p>
            <p class="text-sm mt-1">No notifications at this time.</p>
        </div>`;
        return;
    }

    container.innerHTML = `<div class="divide-y divide-gray-100">` +
        notifications.map((n, i) => {
            const cfg = notifIcons[n.type] || notifIcons.default;
            const isUnread = !n.read_at;
            return `
            <div class="flex items-start gap-4 px-6 py-4 ${isUnread ? 'bg-indigo-50/40' : 'hover:bg-gray-50'} transition-colors cursor-pointer" onclick="markRead(${i})">
                <div class="w-10 h-10 ${cfg.bg} rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 ${cfg.color}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${cfg.icon}"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-800 ${isUnread ? 'font-medium' : ''}">${escHtml(n.message)}</p>
                    <p class="text-xs text-gray-400 mt-1">${timeAgo(n.created_at)}</p>
                </div>
                ${isUnread ? `<div class="w-2 h-2 bg-indigo-500 rounded-full mt-2 flex-shrink-0"></div>` : ''}
            </div>`;
        }).join('') + `</div>`;
}

async function markRead(idx) {
    const n = notifications[idx];
    if (n.read_at) return;
    try {
        await fetch(`/api/v1/notifications/${n.id}/read`, {
            method: 'POST',
            headers: {'X-CSRF-Token':CSRF}
        });
        n.read_at = new Date().toISOString();
        renderNotifications();
    } catch(e) {}
}

async function markAllRead() {
    try {
        await fetch('/api/v1/notifications/read-all', {
            method: 'POST',
            headers: {'X-CSRF-Token':CSRF}
        });
        notifications.forEach(n => n.read_at = n.read_at || new Date().toISOString());
        renderNotifications();
    } catch(e) {}
}

loadNotifications();
</script>
