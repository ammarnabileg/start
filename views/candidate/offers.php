<?php
// Candidate Offers page — rendered inside candidate.php layout
// Variables: $user
$db          = Database::getInstance();
$candidateId = $user['id'] ?? 0;

$offers = $db->fetchAll(
    "SELECT o.*, j.title as job_title, j.company_name, j.location, j.job_type,
            a.id as application_id
     FROM offers o
     JOIN applications a ON a.id = o.application_id
     JOIN jobs j ON j.id = a.job_id
     WHERE a.candidate_id = ?
     ORDER BY o.created_at DESC",
    [$candidateId]
) ?: [];

// Partition offers
$pendingOffers  = array_filter($offers, fn($o) => in_array($o['status'] ?? '', ['pending','sent','']));
$acceptedOffers = array_filter($offers, fn($o) => ($o['status'] ?? '') === 'accepted');
$declinedOffers = array_filter($offers, fn($o) => in_array($o['status'] ?? '', ['declined','negotiating']));

function offerStatusBadge(string $status): string {
    return match($status) {
        'accepted'   => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Accepted</span>',
        'declined'   => '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Declined</span>',
        'negotiating'=> '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Negotiating</span>',
        'pending','sent',''=> '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-violet-100 text-violet-700">Action Required</span>',
        default      => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">' . htmlspecialchars(ucfirst($status)) . '</span>',
    };
}
function formatSalary(?float $salary): string {
    if (!$salary) return 'Not specified';
    return '$' . number_format((int)$salary, 0) . ' / year';
}
function daysUntil(?string $date): ?int {
    if (!$date) return null;
    $diff = (strtotime($date) - time()) / 86400;
    return (int)ceil($diff);
}
?>

<!-- Toast -->
<div id="toast" class="fixed top-20 right-4 z-50 hidden">
  <div id="toast-inner" class="bg-emerald-600 text-white text-sm px-5 py-3 rounded-xl shadow-xl flex items-center gap-3">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <span id="toast-msg"></span>
  </div>
</div>

<!-- Offer Details Modal -->
<div id="offer-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
      <h3 class="font-semibold text-gray-900" id="modal-offer-title">Offer Details</h3>
      <button onclick="closeOfferModal()" class="text-gray-400 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="flex-1 overflow-y-auto p-6">
      <!-- Offer summary -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-violet-50 rounded-xl p-3">
          <div class="text-xs text-gray-400 mb-1">Position</div>
          <div class="text-sm font-semibold text-gray-900" id="modal-position">—</div>
        </div>
        <div class="bg-violet-50 rounded-xl p-3">
          <div class="text-xs text-gray-400 mb-1">Salary</div>
          <div class="text-sm font-semibold text-emerald-700" id="modal-salary">—</div>
        </div>
        <div class="bg-violet-50 rounded-xl p-3">
          <div class="text-xs text-gray-400 mb-1">Start Date</div>
          <div class="text-sm font-semibold text-gray-900" id="modal-start">—</div>
        </div>
        <div class="bg-violet-50 rounded-xl p-3">
          <div class="text-xs text-gray-400 mb-1">Deadline</div>
          <div class="text-sm font-semibold text-red-600" id="modal-deadline">—</div>
        </div>
        <div class="bg-violet-50 rounded-xl p-3">
          <div class="text-xs text-gray-400 mb-1">Company</div>
          <div class="text-sm font-semibold text-gray-900" id="modal-company">—</div>
        </div>
        <div class="bg-violet-50 rounded-xl p-3">
          <div class="text-xs text-gray-400 mb-1">Location</div>
          <div class="text-sm font-semibold text-gray-900" id="modal-location">—</div>
        </div>
      </div>
      <!-- Offer letter -->
      <div>
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Offer Letter</h4>
        <div id="modal-offer-letter" class="bg-gray-50 rounded-xl p-5 text-sm text-gray-700 leading-relaxed whitespace-pre-line border border-gray-100">
          Loading...
        </div>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0" id="modal-actions">
    </div>
  </div>
</div>

<!-- Counter Offer Modal -->
<div id="counter-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900">Negotiate Offer</h3>
      <button onclick="closeCounterModal()" class="text-gray-400 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-6 py-5 space-y-4">
      <input type="hidden" id="counter-offer-id">
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1.5">Your Counter Salary <span class="text-xs text-gray-400">(annual)</span></label>
        <div class="relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">$</span>
          <input type="number" id="counter-salary" min="0" step="1000" placeholder="85000"
            class="w-full pl-8 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-400 focus:bg-white">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1.5">Message to Recruiter</label>
        <textarea id="counter-message" rows="4"
          placeholder="Explain your reasoning for the counter offer. Mention your experience, market rate, or specific requirements..."
          class="w-full px-3.5 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm resize-none focus:outline-none focus:border-violet-400 focus:bg-white leading-relaxed"></textarea>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
      <button onclick="closeCounterModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 font-medium">Cancel</button>
      <button onclick="submitCounter()"
        class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-full text-sm font-semibold transition-colors">
        Send Counter Offer
      </button>
    </div>
  </div>
</div>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">My Offers</h1>
    <p class="text-gray-500 text-sm mt-0.5"><?= count($pendingOffers) ?> pending review<?= count($pendingOffers) !== 1 ? 's' : '' ?></p>
  </div>
</div>

<?php if (empty($offers)): ?>
<!-- Empty state -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm py-20 text-center">
  <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14"/></svg>
  </div>
  <p class="text-gray-700 font-semibold text-lg mb-1">No offers yet</p>
  <p class="text-gray-400 text-sm">Keep applying — your first offer will appear here</p>
  <a href="/candidate/jobs" class="mt-4 inline-flex items-center gap-2 bg-violet-600 text-white text-sm rounded-full px-5 py-2 font-medium hover:bg-violet-700 transition-colors">Browse Jobs</a>
</div>

<?php else: ?>

<?php if (!empty($pendingOffers)): ?>
<!-- Pending Offers -->
<div class="mb-8">
  <div class="flex items-center gap-2 mb-4">
    <div class="w-2 h-2 bg-violet-600 rounded-full animate-pulse"></div>
    <h2 class="text-base font-semibold text-gray-900">Pending Review</h2>
    <span class="bg-violet-100 text-violet-700 text-xs font-bold rounded-full px-2 py-0.5"><?= count($pendingOffers) ?></span>
  </div>

  <div class="space-y-4">
    <?php foreach ($pendingOffers as $offer):
      $days = daysUntil($offer['deadline'] ?? $offer['expires_at'] ?? null);
      $salary = formatSalary($offer['salary'] ?? $offer['salary_offered'] ?? null);
    ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow"
      id="offer-card-<?= (int)($offer['id'] ?? 0) ?>">
      <div class="p-5 sm:p-6">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-base flex-shrink-0">
            <?= strtoupper(substr($offer['company_name'] ?? 'C', 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div>
                <h3 class="text-base font-bold text-gray-900"><?= htmlspecialchars($offer['job_title'] ?? '') ?></h3>
                <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($offer['company_name'] ?? '') ?><?= !empty($offer['location']) ? ' · ' . htmlspecialchars($offer['location']) : '' ?></p>
              </div>
              <?= offerStatusBadge($offer['status'] ?? 'pending') ?>
            </div>

            <!-- Key details row -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4">
              <div class="bg-emerald-50 rounded-xl p-3">
                <div class="text-xs text-gray-400 mb-0.5">Salary</div>
                <div class="text-sm font-bold text-emerald-700"><?= $salary ?></div>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <div class="text-xs text-gray-400 mb-0.5">Start Date</div>
                <div class="text-sm font-semibold text-gray-900">
                  <?= !empty($offer['start_date']) ? date('M j, Y', strtotime($offer['start_date'])) : 'To be discussed' ?>
                </div>
              </div>
              <?php if ($days !== null): ?>
              <div class="<?= $days <= 3 ? 'bg-red-50' : 'bg-amber-50' ?> rounded-xl p-3">
                <div class="text-xs text-gray-400 mb-0.5">Deadline</div>
                <div class="text-sm font-bold <?= $days <= 3 ? 'text-red-600' : 'text-amber-700' ?>">
                  <?= $days > 0 ? "{$days} day" . ($days !== 1 ? 's' : '') . ' left' : 'Expired' ?>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-3 mt-4 pt-4 border-t border-gray-50">
              <button onclick="viewOfferDetails(<?= htmlspecialchars(json_encode($offer)) ?>)"
                class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-800 border border-gray-200 hover:border-gray-300 rounded-full px-3 py-2 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                View Full Offer
              </button>
              <button onclick="respondOffer(<?= (int)($offer['id'] ?? 0) ?>, 'accept')"
                class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-full text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Accept Offer
              </button>
              <button onclick="openCounterModal(<?= (int)($offer['id'] ?? 0) ?>, <?= (int)($offer['salary'] ?? $offer['salary_offered'] ?? 0) ?>)"
                class="flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-full text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Negotiate
              </button>
              <button onclick="respondOffer(<?= (int)($offer['id'] ?? 0) ?>, 'decline')"
                class="flex items-center gap-1.5 text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-300 rounded-full px-3 py-2 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Decline
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($acceptedOffers)): ?>
<!-- Accepted Offers -->
<div class="mb-8">
  <h2 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    Accepted Offers
  </h2>
  <div class="space-y-4">
    <?php foreach ($acceptedOffers as $offer): ?>
    <div class="bg-white rounded-2xl border border-emerald-200 shadow-sm p-5 sm:p-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-base flex-shrink-0">
          <?= strtoupper(substr($offer['company_name'] ?? 'C', 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <h3 class="text-sm font-bold text-gray-900"><?= htmlspecialchars($offer['job_title'] ?? '') ?></h3>
            <?= offerStatusBadge('accepted') ?>
          </div>
          <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($offer['company_name'] ?? '') ?> · <?= formatSalary($offer['salary'] ?? $offer['salary_offered'] ?? null) ?></p>
          <?php if (!empty($offer['start_date'])): ?>
          <p class="text-xs text-emerald-600 font-medium mt-1">Starting <?= date('M j, Y', strtotime($offer['start_date'])) ?></p>
          <?php endif; ?>
        </div>
        <button onclick="viewOfferDetails(<?= htmlspecialchars(json_encode($offer)) ?>)"
          class="flex-shrink-0 text-xs text-gray-500 hover:text-gray-800 border border-gray-200 hover:border-gray-300 rounded-full px-3 py-2 transition-colors">
          View
        </button>
      </div>

      <!-- Onboarding next steps -->
      <div class="mt-4 pt-4 border-t border-gray-50">
        <p class="text-xs font-semibold text-gray-700 mb-3">Next Steps</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <?php
          $steps = [
            ['Sign your offer letter', 'Check your email for the contract to sign electronically.', 'bg-violet-100 text-violet-600'],
            ['Complete paperwork', 'HR will send you onboarding documents to fill out.', 'bg-blue-100 text-blue-600'],
            ['Prepare for day one', 'You\'ll receive your onboarding schedule soon!', 'bg-emerald-100 text-emerald-600'],
          ];
          foreach ($steps as [$title, $desc, $cls]):
          ?>
          <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-3">
            <div class="w-7 h-7 <?= $cls ?> rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
              <p class="text-xs font-semibold text-gray-800"><?= $title ?></p>
              <p class="text-xs text-gray-500 mt-0.5 leading-relaxed"><?= $desc ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($declinedOffers)): ?>
<!-- Declined/Negotiating Offers -->
<div>
  <h2 class="text-base font-semibold text-gray-900 mb-4 text-gray-400">Past Offers</h2>
  <div class="space-y-3">
    <?php foreach ($declinedOffers as $offer): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 opacity-70 hover:opacity-100 transition-opacity">
      <div class="flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-500 font-bold flex-shrink-0">
          <?= strtoupper(substr($offer['company_name'] ?? 'C', 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <span class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($offer['job_title'] ?? '') ?></span>
            <?= offerStatusBadge($offer['status'] ?? 'declined') ?>
          </div>
          <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($offer['company_name'] ?? '') ?> · <?= formatSalary($offer['salary'] ?? $offer['salary_offered'] ?? null) ?></p>
        </div>
        <button onclick="viewOfferDetails(<?= htmlspecialchars(json_encode($offer)) ?>)"
          class="flex-shrink-0 text-xs text-gray-400 hover:text-gray-700 border border-gray-200 rounded-full px-3 py-1.5 transition-colors">
          View
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  const inner = document.getElementById('toast-inner');
  document.getElementById('toast-msg').textContent = msg;
  inner.className = `${type === 'error' ? 'bg-red-600' : 'bg-emerald-600'} text-white text-sm px-5 py-3 rounded-xl shadow-xl flex items-center gap-3`;
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3500);
}

function viewOfferDetails(offer) {
  document.getElementById('modal-offer-title').textContent = offer.job_title || 'Offer Details';
  document.getElementById('modal-position').textContent  = offer.job_title || '—';
  document.getElementById('modal-salary').textContent    = offer.salary ? '$' + parseInt(offer.salary).toLocaleString() + ' / year' : (offer.salary_offered ? '$' + parseInt(offer.salary_offered).toLocaleString() + ' / year' : 'Not specified');
  document.getElementById('modal-start').textContent     = offer.start_date ? new Date(offer.start_date).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
  document.getElementById('modal-deadline').textContent  = (offer.deadline || offer.expires_at) ? new Date(offer.deadline || offer.expires_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
  document.getElementById('modal-company').textContent   = offer.company_name || '—';
  document.getElementById('modal-location').textContent  = offer.location || '—';
  document.getElementById('modal-offer-letter').textContent = offer.offer_letter || offer.details || 'Please review the full offer letter sent to your email.';

  const actions = document.getElementById('modal-actions');
  const status  = offer.status || 'pending';
  if (['pending','sent',''].includes(status)) {
    actions.innerHTML = `
      <button onclick="respondOffer(${offer.id},'decline');closeOfferModal()" class="px-4 py-2 text-sm text-red-500 hover:text-red-700 border border-red-200 rounded-full font-medium transition-colors">Decline</button>
      <button onclick="openCounterModal(${offer.id},${offer.salary||offer.salary_offered||0});closeOfferModal()" class="px-4 py-2 text-sm text-amber-600 hover:text-amber-700 border border-amber-300 rounded-full font-medium transition-colors">Negotiate</button>
      <button onclick="respondOffer(${offer.id},'accept');closeOfferModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-full text-sm font-semibold transition-colors">Accept Offer</button>`;
  } else {
    actions.innerHTML = `<button onclick="closeOfferModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 font-medium">Close</button>`;
  }
  document.getElementById('offer-modal').classList.remove('hidden');
}

function closeOfferModal() { document.getElementById('offer-modal').classList.add('hidden'); }
function closeCounterModal() { document.getElementById('counter-modal').classList.add('hidden'); }

function openCounterModal(offerId, currentSalary) {
  document.getElementById('counter-offer-id').value = offerId;
  document.getElementById('counter-salary').value = currentSalary > 0 ? Math.round(currentSalary * 1.1) : '';
  document.getElementById('counter-message').value = '';
  document.getElementById('counter-modal').classList.remove('hidden');
}

async function respondOffer(offerId, action) {
  if (action === 'decline' && !confirm('Are you sure you want to decline this offer?')) return;
  try {
    const res = await fetch('/api/v1/candidate/offers/' + offerId + '/' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ offer_id: offerId })
    });
    const data = await res.json();
    if (data.success) {
      showToast(action === 'accept' ? 'Offer accepted! Congratulations!' : 'Offer declined.');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(data.message || 'Action failed', 'error');
    }
  } catch(e) {
    showToast('Request failed. Please try again.', 'error');
  }
}

async function submitCounter() {
  const offerId = document.getElementById('counter-offer-id').value;
  const salary  = document.getElementById('counter-salary').value;
  const message = document.getElementById('counter-message').value;
  if (!salary) { showToast('Please enter a counter salary', 'error'); return; }
  try {
    const res = await fetch('/api/v1/candidate/offers/' + offerId + '/negotiate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ offer_id: offerId, counter_salary: salary, message })
    });
    const data = await res.json();
    if (data.success) {
      closeCounterModal();
      showToast('Counter offer sent to the recruiter!');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(data.message || 'Failed to send', 'error');
    }
  } catch(e) {
    showToast('Request failed. Please try again.', 'error');
  }
}

// Close modals on backdrop click
document.getElementById('offer-modal').addEventListener('click', (e) => { if (e.target === e.currentTarget) closeOfferModal(); });
document.getElementById('counter-modal').addEventListener('click', (e) => { if (e.target === e.currentTarget) closeCounterModal(); });
</script>
