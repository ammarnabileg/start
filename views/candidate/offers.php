<?php
$pageTitle = 'My Offers';
$db = Database::getInstance();
$cid = Auth::user()['id'];
$offers = $db->fetchAll(
    "SELECT o.*, j.title as job_title, t.name as company_name
     FROM offers o
     JOIN jobs j ON j.id = o.job_id
     JOIN tenants t ON t.id = j.tenant_id
     WHERE o.candidate_id = ? ORDER BY o.created_at DESC",
    [$cid]
) ?: [];

// ── Helpers ───────────────────────────────────────────────────────────────
function offerStatusBadge(string $status): string {
    return match($status) {
        'pending'  => '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>Pending</span>',
        'accepted' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>Accepted</span>',
        'declined' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700"><span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>Declined</span>',
        'rejected' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700"><span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>Declined</span>',
        default    => '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">' . htmlspecialchars(ucfirst($status)) . '</span>',
    };
}

function formatSalary(float $amount, string $currency, string $type): string {
    $sym  = match(strtoupper($currency)) { 'GBP' => '£', 'EUR' => '€', default => '$' };
    $fmt  = number_format($amount);
    $freq = $type === 'annual' ? '/year' : '/month';
    return "{$sym}{$fmt}{$freq}";
}

function expiryCountdown(?string $expiresAt): array {
    // Returns ['label' => '...', 'warn' => bool]
    if (!$expiresAt) return ['label' => 'No expiry', 'warn' => false];
    $diff = (strtotime($expiresAt) - time()) / 86400;
    if ($diff < 0)   return ['label' => 'Expired', 'warn' => true];
    if ($diff < 1)   return ['label' => 'Expires today', 'warn' => true];
    $days = (int) ceil($diff);
    $warn = $days <= 7;
    $label = $days === 1 ? 'Expires in 1 day' : "Expires in {$days} days";
    return ['label' => $label, 'warn' => $warn];
}

function companyInitials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    $init  = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
    return $init ?: '?';
}

// Palette pool for company avatars
$avatarColors = [
    'bg-violet-500', 'bg-blue-500', 'bg-emerald-500', 'bg-amber-500',
    'bg-pink-500', 'bg-indigo-500', 'bg-teal-500', 'bg-rose-500',
];
?>

<!-- ══════════════════════ PAGE HEADER ══════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">My Offers</h1>
    <p class="text-sm text-gray-500 mt-0.5">
      <?php if (empty($offers)): ?>
        Accepted or pending offers from employers will appear here.
      <?php else: ?>
        You have <?= count($offers) ?> offer<?= count($offers) !== 1 ? 's' : '' ?>.
        <?php $pendingCount = count(array_filter($offers, fn($o) => ($o['status'] ?? '') === 'pending')); ?>
        <?php if ($pendingCount > 0): ?>
          <span class="text-amber-600 font-medium"><?= $pendingCount ?> pending your response.</span>
        <?php endif; ?>
      <?php endif; ?>
    </p>
  </div>
  <?php if (!empty($offers)): ?>
  <div class="flex items-center gap-2">
    <span class="text-xs text-gray-500">Filter:</span>
    <div class="flex gap-1.5">
      <button onclick="filterOffers('all')" data-filter="all"
        class="filter-btn active px-3 py-1.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700 border border-violet-200 transition-colors">
        All (<?= count($offers) ?>)
      </button>
      <?php
      $statuses = ['pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Declined', 'negotiating' => 'Negotiating'];
      foreach ($statuses as $s => $l):
        $cnt = count(array_filter($offers, fn($o) => ($o['status'] ?? '') === $s));
        if ($cnt === 0) continue;
      ?>
      <button onclick="filterOffers('<?= $s ?>')" data-filter="<?= $s ?>"
        class="filter-btn px-3 py-1.5 rounded-full text-xs font-medium bg-white text-gray-600 border border-gray-200 hover:border-gray-300 transition-colors">
        <?= $l ?> (<?= $cnt ?>)
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════ OFFERS LIST ══════════════════════ -->
<?php if (empty($offers)): ?>
<!-- Empty State -->
<div class="flex flex-col items-center justify-center py-20 px-6">
  <div class="w-20 h-20 bg-gray-100 rounded-3xl flex items-center justify-center mb-5">
    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
  </div>
  <h3 class="text-lg font-semibold text-gray-700 mb-2">No Offers Yet</h3>
  <p class="text-sm text-gray-400 text-center max-w-sm mb-6">
    When an employer sends you a job offer, it will appear here. Keep applying and acing those interviews!
  </p>
  <a href="/candidate/jobs"
    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    Browse Jobs
  </a>
</div>

<?php else: ?>
<div id="offers-list" class="space-y-4">
  <?php foreach ($offers as $idx => $o):
    $status    = $o['status'] ?? 'pending';
    $expiry    = expiryCountdown($o['expires_at'] ?? null);
    $startDate = !empty($o['start_date']) ? date('M j, Y', strtotime($o['start_date'])) : 'TBD';
    $salaryFmt = formatSalary((float)($o['salary_amount'] ?? 0), $o['salary_currency'] ?? 'USD', $o['salary_type'] ?? 'monthly');
    $initials  = companyInitials($o['company_name'] ?? '');
    $avatarBg  = $avatarColors[crc32($o['company_name'] ?? '') % count($avatarColors)];
    $offerId   = (int)$o['id'];
    $jobTitle  = htmlspecialchars($o['job_title'] ?? '');
    $company   = htmlspecialchars($o['company_name'] ?? '');
  ?>
  <div class="offer-card bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow"
       data-status="<?= $status ?>" data-offer-id="<?= $offerId ?>">
    <div class="flex flex-col sm:flex-row sm:items-start gap-5">

      <!-- Company logo/initials -->
      <div class="flex-shrink-0">
        <div class="w-14 h-14 <?= $avatarBg ?> rounded-2xl flex items-center justify-center text-white text-lg font-bold shadow-sm select-none">
          <?= htmlspecialchars($initials) ?>
        </div>
      </div>

      <!-- Main content -->
      <div class="flex-1 min-w-0">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1">
              <h2 class="text-base font-bold text-gray-900 truncate"><?= $jobTitle ?></h2>
              <?= offerStatusBadge($status) ?>
            </div>
            <p class="text-sm text-gray-600 font-medium"><?= $company ?></p>
          </div>

          <!-- Salary -->
          <div class="flex-shrink-0 text-right">
            <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars($salaryFmt) ?></p>
            <?php if (!empty($o['negotiated_salary']) && $status === 'pending'): ?>
            <p class="text-xs text-violet-600 font-medium mt-0.5">Negotiated: <?= formatSalary((float)$o['negotiated_salary'], $o['salary_currency'] ?? 'USD', $o['salary_type'] ?? 'monthly') ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Meta row -->
        <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-500">
          <span class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Start: <?= $startDate ?>
          </span>
          <?php if (!empty($o['expires_at'])): ?>
          <span class="flex items-center gap-1.5 <?= $expiry['warn'] && $status === 'pending' ? 'text-amber-600 font-semibold' : '' ?>">
            <?php if ($expiry['warn'] && $status === 'pending'): ?>
            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <?php else: ?>
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php endif; ?>
            <?= htmlspecialchars($expiry['label']) ?>
          </span>
          <?php endif; ?>
          <span class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Received <?= !empty($o['created_at']) ? date('M j, Y', strtotime($o['created_at'])) : 'Recently' ?>
          </span>
        </div>

        <?php if (!empty($o['negotiation_message'])): ?>
        <div class="mt-3 bg-violet-50 border border-violet-100 rounded-xl px-3 py-2 text-xs text-violet-700">
          <span class="font-semibold">Your negotiation note:</span> <?= htmlspecialchars($o['negotiation_message']) ?>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div class="flex flex-wrap items-center gap-2 mt-4">
          <!-- View offer letter (always visible) -->
          <?php if (!empty($o['offer_letter'])): ?>
          <button
            onclick="openOfferLetter(<?= $offerId ?>, <?= json_encode($jobTitle) ?>, <?= json_encode($company) ?>, <?= json_encode($o['offer_letter']) ?>)"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-violet-700 border border-violet-300 hover:border-violet-500 hover:bg-violet-50 px-4 py-2 rounded-full transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            View Offer Letter
          </button>
          <?php endif; ?>

          <?php if ($status === 'pending'): ?>
          <!-- Accept -->
          <button
            onclick="openAcceptModal(<?= $offerId ?>, <?= json_encode($jobTitle) ?>, <?= json_encode($company) ?>)"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-white bg-emerald-500 hover:bg-emerald-600 px-4 py-2 rounded-full transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Accept
          </button>
          <!-- Decline -->
          <button
            onclick="openDeclineModal(<?= $offerId ?>, <?= json_encode($jobTitle) ?>, <?= json_encode($company) ?>)"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-red-600 border border-red-300 hover:bg-red-50 hover:border-red-500 px-4 py-2 rounded-full transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Decline
          </button>
          <!-- Negotiate -->
          <button
            onclick="openNegotiateModal(<?= $offerId ?>, <?= json_encode($jobTitle) ?>, <?= json_encode($company) ?>, <?= (float)($o['salary_amount'] ?? 0) ?>, <?= json_encode($o['salary_currency'] ?? 'USD') ?>, <?= json_encode($salaryFmt) ?>)"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 border border-gray-300 hover:bg-gray-50 hover:border-gray-400 px-4 py-2 rounded-full transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            Negotiate
          </button>
          <?php elseif ($status === 'accepted'): ?>
          <span class="inline-flex items-center gap-1.5 text-sm text-emerald-700 bg-emerald-50 border border-emerald-100 px-4 py-2 rounded-full font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Offer Accepted
          </span>
          <?php elseif (in_array($status, ['declined', 'rejected'])): ?>
          <span class="inline-flex items-center gap-1.5 text-sm text-red-600 bg-red-50 border border-red-100 px-4 py-2 rounded-full font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Offer Declined
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     MODALS
═══════════════════════════════════════════════════════════ -->

<!-- 1. Offer Letter Modal -->
<div id="modal-offer-letter" class="modal-backdrop hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col animate-scale-in">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <div>
        <h3 class="text-base font-bold text-gray-900" id="offer-letter-title">Offer Letter</h3>
        <p class="text-xs text-gray-500 mt-0.5" id="offer-letter-meta"></p>
      </div>
      <div class="flex items-center gap-2">
        <button onclick="printOfferLetter()"
          class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 border border-gray-200 hover:border-gray-400 hover:bg-gray-50 px-3 py-1.5 rounded-full transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Print
        </button>
        <button onclick="closeModal('modal-offer-letter')"
          class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
          <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>
    <!-- Body -->
    <div class="flex-1 overflow-y-auto px-6 py-5">
      <div id="offer-letter-body"
        class="prose prose-sm max-w-none text-gray-800 whitespace-pre-wrap leading-relaxed font-mono text-sm bg-gray-50 rounded-xl p-5 border border-gray-100 min-h-[200px]"></div>
    </div>
  </div>
</div>

<!-- 2. Accept Modal -->
<div id="modal-accept" class="modal-backdrop hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md animate-scale-in">
    <div class="px-6 pt-6 pb-4">
      <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      </div>
      <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Accept Offer</h3>
      <p class="text-sm text-gray-600 text-center">
        You are accepting the offer for <strong id="accept-position" class="text-gray-900"></strong>
        at <strong id="accept-company" class="text-gray-900"></strong>.
      </p>
      <p class="text-xs text-red-500 text-center mt-2 font-medium">This action cannot be undone.</p>
    </div>
    <div class="px-6 pb-6 flex gap-3">
      <button onclick="closeModal('modal-accept')"
        class="flex-1 py-2.5 rounded-full border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
        Cancel
      </button>
      <button onclick="confirmAccept()" id="accept-confirm-btn"
        class="flex-1 py-2.5 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold transition-colors disabled:opacity-60">
        Confirm Accept
      </button>
    </div>
  </div>
</div>

<!-- 3. Decline Modal -->
<div id="modal-decline" class="modal-backdrop hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md animate-scale-in">
    <div class="px-6 pt-6 pb-4">
      <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </div>
      <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Decline Offer</h3>
      <p class="text-sm text-gray-600 text-center mb-4">
        Declining the offer for <strong id="decline-position" class="text-gray-900"></strong>
        at <strong id="decline-company" class="text-gray-900"></strong>.
      </p>
      <label class="block text-xs font-medium text-gray-700 mb-1.5">Reason for declining <span class="text-gray-400 font-normal">(optional)</span></label>
      <textarea id="decline-reason" rows="3"
        placeholder="e.g. Accepted another offer, salary expectations not met..."
        class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-red-400 focus:ring-1 focus:ring-red-200 transition-colors resize-none"></textarea>
    </div>
    <div class="px-6 pb-6 flex gap-3">
      <button onclick="closeModal('modal-decline')"
        class="flex-1 py-2.5 rounded-full border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
        Cancel
      </button>
      <button onclick="confirmDecline()" id="decline-confirm-btn"
        class="flex-1 py-2.5 rounded-full bg-red-500 hover:bg-red-600 text-white text-sm font-semibold transition-colors disabled:opacity-60">
        Confirm Decline
      </button>
    </div>
  </div>
</div>

<!-- 4. Negotiate Modal -->
<div id="modal-negotiate" class="modal-backdrop hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
  <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md animate-scale-in">
    <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-100">
      <div>
        <h3 class="text-base font-bold text-gray-900">Negotiate Offer</h3>
        <p class="text-xs text-gray-500 mt-0.5" id="negotiate-meta"></p>
      </div>
      <button onclick="closeModal('modal-negotiate')"
        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-6 py-5 space-y-4">
      <!-- Current salary info -->
      <div class="bg-gray-50 rounded-xl p-3 flex items-center justify-between">
        <span class="text-xs text-gray-500 font-medium">Current offer</span>
        <span class="text-sm font-bold text-gray-900" id="negotiate-current-salary"></span>
      </div>

      <!-- Proposed salary -->
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1.5">Your proposed salary <span class="text-red-400">*</span></label>
        <div class="flex gap-2">
          <select id="negotiate-currency"
            class="w-28 border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
            <option value="USD">USD $</option>
            <option value="GBP">GBP £</option>
            <option value="EUR">EUR €</option>
            <option value="CAD">CAD $</option>
            <option value="AUD">AUD $</option>
          </select>
          <input type="number" id="negotiate-salary" min="0" step="100"
            placeholder="e.g. 5000"
            class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors">
        </div>
        <p class="text-xs text-gray-400 mt-1.5">Enter the amount per the same pay frequency as the offer.</p>
      </div>

      <!-- Message -->
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1.5">Message to recruiter <span class="text-gray-400 font-normal">(optional)</span></label>
        <textarea id="negotiate-message" rows="3"
          placeholder="Explain your reasoning — market rate data, experience, competing offers..."
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-200 transition-colors resize-none"></textarea>
      </div>
    </div>
    <div class="px-6 pb-6 flex gap-3 border-t border-gray-100 pt-4">
      <button onclick="closeModal('modal-negotiate')"
        class="flex-1 py-2.5 rounded-full border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
        Cancel
      </button>
      <button onclick="confirmNegotiate()" id="negotiate-confirm-btn"
        class="flex-1 py-2.5 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors disabled:opacity-60">
        Send Proposal
      </button>
    </div>
  </div>
</div>

<!-- ══════════════════════ TOAST CONTAINER ══════════════════════ -->
<div id="toast-container" class="fixed top-20 right-4 z-[60] flex flex-col gap-2 pointer-events-none max-w-sm w-full"></div>

<style>
@keyframes scaleIn {
  from { opacity: 0; transform: scale(0.96) translateY(8px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-scale-in { animation: scaleIn 0.2s cubic-bezier(.2,.8,.4,1) both; }
</style>

<script>
// ── State ──────────────────────────────────────────────────────────────────
let activeOfferId = null;

// ── Filter ─────────────────────────────────────────────────────────────────
function filterOffers(status) {
  // Update button styles
  document.querySelectorAll('.filter-btn').forEach(btn => {
    const isActive = btn.dataset.filter === status;
    btn.classList.toggle('bg-violet-100', isActive);
    btn.classList.toggle('text-violet-700', isActive);
    btn.classList.toggle('border-violet-200', isActive);
    btn.classList.toggle('bg-white', !isActive);
    btn.classList.toggle('text-gray-600', !isActive);
    btn.classList.toggle('border-gray-200', !isActive);
  });

  // Show/hide cards
  document.querySelectorAll('.offer-card').forEach(card => {
    const match = status === 'all' || card.dataset.status === status;
    card.style.display = match ? '' : 'none';
  });
}

// ── Modal helpers ──────────────────────────────────────────────────────────
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // Re-trigger animation
    const box = modal.querySelector('.animate-scale-in');
    if (box) { box.style.animation = 'none'; requestAnimationFrame(() => { box.style.animation = ''; }); }
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
  }
}

// Close on backdrop click
document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
  backdrop.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

// Escape key closes modals
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop:not(.hidden)').forEach(m => closeModal(m.id));
  }
});

// ── Offer Letter ───────────────────────────────────────────────────────────
function openOfferLetter(offerId, jobTitle, company, letterText) {
  document.getElementById('offer-letter-title').textContent = 'Offer Letter — ' + jobTitle;
  document.getElementById('offer-letter-meta').textContent  = company;
  document.getElementById('offer-letter-body').textContent  = letterText;
  openModal('modal-offer-letter');
}

function printOfferLetter() {
  const title = document.getElementById('offer-letter-title').textContent;
  const body  = document.getElementById('offer-letter-body').textContent;
  const win   = window.open('', '_blank');
  win.document.write(`<!DOCTYPE html><html><head><title>${escapeHtml(title)}</title>
    <style>body{font-family:serif;max-width:700px;margin:40px auto;padding:0 20px;line-height:1.7;color:#111}
    h1{font-size:18px;margin-bottom:4px}pre{white-space:pre-wrap;font-family:inherit;font-size:14px}</style>
    </head><body><h1>${escapeHtml(title)}</h1><hr><pre>${escapeHtml(body)}</pre></body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 400);
}

// ── Accept ─────────────────────────────────────────────────────────────────
function openAcceptModal(offerId, jobTitle, company) {
  activeOfferId = offerId;
  document.getElementById('accept-position').textContent = jobTitle;
  document.getElementById('accept-company').textContent  = company;
  openModal('modal-accept');
}

async function confirmAccept() {
  const btn = document.getElementById('accept-confirm-btn');
  if (!activeOfferId) return;
  btn.disabled = true;
  btn.textContent = 'Accepting...';
  try {
    const res = await apiFetch(`/api/v1/offers/${activeOfferId}/accept`, 'POST', {});
    if (res.ok) {
      closeModal('modal-accept');
      showToast('Offer accepted! Congratulations!', 'success');
      updateCardStatus(activeOfferId, 'accepted');
    } else {
      const d = await res.json();
      throw new Error(d.error ?? 'Accept failed');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Confirm Accept';
  }
}

// ── Decline ────────────────────────────────────────────────────────────────
function openDeclineModal(offerId, jobTitle, company) {
  activeOfferId = offerId;
  document.getElementById('decline-position').textContent = jobTitle;
  document.getElementById('decline-company').textContent  = company;
  document.getElementById('decline-reason').value = '';
  openModal('modal-decline');
}

async function confirmDecline() {
  const btn    = document.getElementById('decline-confirm-btn');
  const reason = document.getElementById('decline-reason').value.trim();
  if (!activeOfferId) return;
  btn.disabled = true;
  btn.textContent = 'Declining...';
  try {
    const res = await apiFetch(`/api/v1/offers/${activeOfferId}/decline`, 'POST', { reason });
    if (res.ok) {
      closeModal('modal-decline');
      showToast('Offer declined.', 'info');
      updateCardStatus(activeOfferId, 'rejected');
    } else {
      const d = await res.json();
      throw new Error(d.error ?? 'Decline failed');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Confirm Decline';
  }
}

// ── Negotiate ──────────────────────────────────────────────────────────────
function openNegotiateModal(offerId, jobTitle, company, salaryAmount, currency, salaryFmt) {
  activeOfferId = offerId;
  document.getElementById('negotiate-meta').textContent           = `${jobTitle} at ${company}`;
  document.getElementById('negotiate-current-salary').textContent = salaryFmt;
  document.getElementById('negotiate-salary').value   = '';
  document.getElementById('negotiate-message').value  = '';

  // Pre-select currency
  const currencySelect = document.getElementById('negotiate-currency');
  if (currencySelect) {
    [...currencySelect.options].forEach(o => { o.selected = o.value === currency; });
  }

  openModal('modal-negotiate');
  setTimeout(() => document.getElementById('negotiate-salary')?.focus(), 150);
}

async function confirmNegotiate() {
  const btn      = document.getElementById('negotiate-confirm-btn');
  const salary   = parseFloat(document.getElementById('negotiate-salary')?.value ?? '');
  const currency = document.getElementById('negotiate-currency')?.value ?? 'USD';
  const message  = document.getElementById('negotiate-message')?.value?.trim() ?? '';

  if (!salary || salary <= 0) {
    showToast('Please enter a valid proposed salary.', 'error');
    document.getElementById('negotiate-salary')?.focus();
    return;
  }
  if (!activeOfferId) return;

  btn.disabled = true;
  btn.textContent = 'Sending...';
  try {
    const res = await apiFetch(`/api/v1/offers/${activeOfferId}/negotiate`, 'POST', {
      negotiated_salary: salary,
      currency:          currency,
      message:           message,
    });
    if (res.ok) {
      closeModal('modal-negotiate');
      showToast('Negotiation proposal sent to the recruiter!', 'success');
    } else {
      const d = await res.json();
      throw new Error(d.error ?? 'Negotiate failed');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Send Proposal';
  }
}

// ── Update card status in DOM ──────────────────────────────────────────────
function updateCardStatus(offerId, newStatus) {
  const card = document.querySelector(`.offer-card[data-offer-id="${offerId}"]`);
  if (!card) return;
  card.dataset.status = newStatus;

  // Replace status badge
  const badgeHTML = {
    accepted: '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>Accepted</span>',
    rejected: '<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700"><span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>Declined</span>',
  };
  const oldBadge = card.querySelector('[class*="rounded-full"][class*="text-amber"]');
  if (oldBadge && badgeHTML[newStatus]) {
    const span = document.createElement('span');
    span.innerHTML = badgeHTML[newStatus];
    oldBadge.replaceWith(span.firstElementChild);
  }

  // Replace action buttons area
  const actionRow = card.querySelector('.flex.flex-wrap.items-center.gap-2.mt-4');
  if (actionRow) {
    // Remove pending buttons (keep offer letter button)
    const pendingBtns = actionRow.querySelectorAll('button:not([onclick*="openOfferLetter"])');
    pendingBtns.forEach(b => b.remove());

    const statusLabel = document.createElement('span');
    if (newStatus === 'accepted') {
      statusLabel.className = 'inline-flex items-center gap-1.5 text-sm text-emerald-700 bg-emerald-50 border border-emerald-100 px-4 py-2 rounded-full font-medium';
      statusLabel.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Offer Accepted';
    } else {
      statusLabel.className = 'inline-flex items-center gap-1.5 text-sm text-red-600 bg-red-50 border border-red-100 px-4 py-2 rounded-full font-medium';
      statusLabel.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Offer Declined';
    }
    actionRow.appendChild(statusLabel);
  }
}

// ── API helper ─────────────────────────────────────────────────────────────
async function apiFetch(url, method, body) {
  return fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(body),
  });
}

// ── Toast notification ─────────────────────────────────────────────────────
function showToast(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const palette = {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    error:   'bg-red-50 border-red-200 text-red-800',
    info:    'bg-blue-50 border-blue-200 text-blue-800',
  };
  const iconPath = {
    success: 'M5 13l4 4L19 7',
    error:   'M6 18L18 6M6 6l12 12',
    info:    'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  };

  const toast = document.createElement('div');
  toast.className = `pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium ${palette[type] ?? palette.info}`;
  toast.style.animation = 'slideDown 0.2s ease';
  toast.innerHTML = `
    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${iconPath[type] ?? iconPath.info}"/>
    </svg>
    <span class="flex-1">${escapeHtml(msg)}</span>
    <button onclick="this.closest('div').remove()" class="flex-shrink-0 opacity-50 hover:opacity-100 transition-opacity -mt-0.5">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.transition = 'opacity 0.3s, transform 0.3s';
    toast.style.opacity    = '0';
    toast.style.transform  = 'translateX(20px)';
    setTimeout(() => toast.remove(), 300);
  }, 4500);
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
</script>
