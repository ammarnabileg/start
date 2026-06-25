<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">My Offers</h2>
        <p class="text-gray-500 mt-1">Review and respond to your job offers.</p>
    </div>

    <!-- Offers Grid -->
    <div id="offers-container" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <div class="col-span-full flex items-center justify-center py-16 text-gray-400">
            <svg class="w-6 h-6 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Loading offers…
        </div>
    </div>
</div>

<!-- Offer Detail Modal -->
<div id="offer-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Offer Details</h3>
            <button onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-4 overflow-y-auto">
            <div class="flex items-start justify-between">
                <div>
                    <h4 class="text-xl font-bold text-gray-900" id="modal-job-title">—</h4>
                    <p class="text-gray-500 text-sm" id="modal-company">—</p>
                </div>
                <span id="modal-status-badge" class="text-xs px-2.5 py-1 rounded-full font-medium"></span>
            </div>

            <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-xl">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Salary</p>
                    <p class="font-semibold text-gray-900" id="modal-salary">—</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Start Date</p>
                    <p class="font-semibold text-gray-900" id="modal-start-date">—</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Offer Expires</p>
                    <p class="font-semibold text-gray-900" id="modal-expiry">—</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Work Mode</p>
                    <p class="font-semibold text-gray-900" id="modal-work-mode">—</p>
                </div>
            </div>

            <div id="modal-notes-wrap" class="hidden">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Additional Notes</p>
                <p class="text-sm text-gray-700 p-3 bg-gray-50 rounded-xl" id="modal-notes"></p>
            </div>

            <div id="modal-actions" class="flex gap-3 pt-2">
                <button id="reject-btn" onclick="respondToOffer('rejected')"
                    class="flex-1 px-4 py-2.5 border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium rounded-xl transition-colors">
                    Decline Offer
                </button>
                <button id="accept-btn" onclick="respondToOffer('accepted')"
                    class="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-xl transition-colors">
                    Accept Offer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
let currentOfferId = null;

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

const formatDate = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
const formatSalary = (min, max, cur='USD') => {
    if (!min && !max) return 'Not specified';
    const fmt = n => new Intl.NumberFormat('en-US',{style:'currency',currency:cur,maximumFractionDigits:0}).format(n);
    if (min && max) return `${fmt(min)} – ${fmt(max)}`;
    return min ? `From ${fmt(min)}` : `Up to ${fmt(max)}`;
};

const statusBadge = s => ({
    pending:  {cls:'bg-amber-100 text-amber-700',  label:'Pending'},
    accepted: {cls:'bg-green-100 text-green-700',  label:'Accepted'},
    rejected: {cls:'bg-red-100 text-red-700',      label:'Declined'},
    expired:  {cls:'bg-gray-100 text-gray-500',    label:'Expired'},
}[s] || {cls:'bg-gray-100 text-gray-600', label: s||'Unknown'});

async function loadOffers() {
    try {
        const res = await fetch('/api/v1/candidate/offers', {headers:{'X-CSRF-Token':CSRF}});
        const json = await res.json();
        const offers = json.data || [];
        renderOffers(offers);
    } catch(e) {
        document.getElementById('offers-container').innerHTML = `<div class="col-span-full text-center py-16 text-red-400">Failed to load offers. Please refresh.</div>`;
    }
}

function renderOffers(offers) {
    const container = document.getElementById('offers-container');
    if (offers.length === 0) {
        container.innerHTML = `<div class="col-span-full text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="font-medium">No offers yet</p>
            <p class="text-sm mt-1">Keep applying — offers will appear here when extended.</p>
        </div>`;
        return;
    }

    container.innerHTML = offers.map(o => {
        const badge = statusBadge(o.status);
        const isExpired = o.status === 'expired' || (o.expires_at && new Date(o.expires_at) < new Date());
        return `
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col gap-4 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate">${escHtml(o.job_title)}</h3>
                    <p class="text-sm text-gray-500">${escHtml(o.company_name)}</p>
                </div>
                <span class="flex-shrink-0 text-xs px-2.5 py-1 rounded-full font-medium ${badge.cls}">${badge.label}</span>
            </div>

            <div class="space-y-2 text-sm text-gray-600">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ${formatSalary(o.salary_min, o.salary_max, o.currency)}
                </div>
                ${o.expires_at ? `<div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Expires ${formatDate(o.expires_at)}
                </div>` : ''}
            </div>

            <button onclick='openOfferModal(${JSON.stringify(o).replace(/'/g,"&#39;")})' class="mt-auto w-full py-2.5 border border-indigo-200 text-indigo-600 hover:bg-indigo-50 text-sm font-medium rounded-xl transition-colors">
                View Offer Details
            </button>
        </div>`;
    }).join('');
}

function openOfferModal(offer) {
    currentOfferId = offer.id;
    const badge = statusBadge(offer.status);

    document.getElementById('modal-job-title').textContent = offer.job_title;
    document.getElementById('modal-company').textContent   = offer.company_name;
    document.getElementById('modal-status-badge').textContent = badge.label;
    document.getElementById('modal-status-badge').className = `text-xs px-2.5 py-1 rounded-full font-medium ${badge.cls}`;
    document.getElementById('modal-salary').textContent   = formatSalary(offer.salary_min, offer.salary_max, offer.currency);
    document.getElementById('modal-start-date').textContent = formatDate(offer.start_date);
    document.getElementById('modal-expiry').textContent   = formatDate(offer.expires_at);
    document.getElementById('modal-work-mode').textContent = offer.work_mode ? offer.work_mode.charAt(0).toUpperCase() + offer.work_mode.slice(1) : '—';

    const notesEl = document.getElementById('modal-notes');
    const notesWrap = document.getElementById('modal-notes-wrap');
    if (offer.notes) {
        notesEl.textContent = offer.notes;
        notesWrap.classList.remove('hidden');
    } else {
        notesWrap.classList.add('hidden');
    }

    const actionsEl = document.getElementById('modal-actions');
    actionsEl.style.display = offer.status === 'pending' ? 'flex' : 'none';

    document.getElementById('offer-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('offer-modal').classList.add('hidden');
    currentOfferId = null;
}

async function respondToOffer(decision) {
    if (!currentOfferId) return;
    const acceptBtn = document.getElementById('accept-btn');
    const rejectBtn = document.getElementById('reject-btn');
    acceptBtn.disabled = rejectBtn.disabled = true;

    try {
        const res = await fetch(`/api/v1/candidate/offers/${currentOfferId}/respond`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
            body: JSON.stringify({decision})
        });
        const json = await res.json();
        if (json.ok) {
            closeModal();
            loadOffers();
        } else {
            alert(json.message || 'Failed to respond. Please try again.');
            acceptBtn.disabled = rejectBtn.disabled = false;
        }
    } catch(e) {
        alert('Network error. Please try again.');
        acceptBtn.disabled = rejectBtn.disabled = false;
    }
}

document.getElementById('offer-modal').addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(); });

loadOffers();
</script>
