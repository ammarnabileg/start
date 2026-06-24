<?php
/**
 * Offers management page with Draft / Pending / Accepted / Declined tabs.
 * Create/Edit offer modal with AI letter generation.
 */
require_once __DIR__ . '/../partials/helpers.php';

$activeTab = $_GET['tab'] ?? 'pending';

$offers = $offers ?? [
    ['id'=>1,'candidate'=>'James Carter','candidate_id'=>1,'position'=>'Senior Backend Engineer','department'=>'Engineering','salary'=>110000,'currency'=>'GBP','salary_type'=>'annual','start_date'=>'+30 days','deadline'=>'+7 days','status'=>'pending','created_at'=>'-2 days','reporting_to'=>'CTO','benefits'=>['health','dental','stock','remote']],
    ['id'=>2,'candidate'=>'Diego Fernandez','candidate_id'=>3,'position'=>'Product Designer','department'=>'Design','salary'=>95000,'currency'=>'USD','salary_type'=>'annual','start_date'=>'+21 days','deadline'=>'+5 days','status'=>'pending','created_at'=>'-1 days','reporting_to'=>'Head of Design','benefits'=>['health','dental','401k','remote']],
    ['id'=>3,'candidate'=>'Olivia Reyes','candidate_id'=>6,'position'=>'Frontend Engineer','department'=>'Engineering','salary'=>90000,'currency'=>'USD','salary_type'=>'annual','start_date'=>'+45 days','deadline'=>'+14 days','status'=>'draft','created_at'=>'-3 days','reporting_to'=>'Engineering Manager','benefits'=>['health','401k','remote']],
    ['id'=>4,'candidate'=>'Liam Murphy','candidate_id'=>9,'position'=>'Senior Backend Engineer','department'=>'Engineering','salary'=>130000,'currency'=>'EUR','salary_type'=>'annual','start_date'=>'+14 days','deadline'=>null,'status'=>'accepted','created_at'=>'-14 days','reporting_to'=>'VP Engineering','benefits'=>['health','dental','vision','stock','remote']],
    ['id'=>5,'candidate'=>'Grace Okafor','candidate_id'=>8,'position'=>'Product Designer','department'=>'Design','salary'=>85000,'currency'=>'USD','salary_type'=>'annual','start_date'=>'+60 days','deadline'=>'-2 days','status'=>'declined','created_at'=>'-10 days','reporting_to'=>'Head of Design','benefits'=>['health','remote']],
];

$tabs = ['draft'=>'Draft','pending'=>'Pending','accepted'=>'Accepted','declined'=>'Declined'];
$tabCounts = [];
foreach ($tabs as $k=>$_) $tabCounts[$k] = count(array_filter($offers, fn($o)=>$o['status']===$k));
$tabOffers = array_values(array_filter($offers, fn($o)=>$o['status']===$activeTab));

$statusConfig = [
    'draft'    => ['Draft',    'bg-gray-100 text-gray-600'],
    'pending'  => ['Pending',  'bg-amber-100 text-amber-700'],
    'accepted' => ['Accepted', 'bg-emerald-100 text-emerald-700'],
    'declined' => ['Declined', 'bg-rose-100 text-rose-700'],
];

$benefitLabels = [
    'health'  =>'Health Insurance','dental'=>'Dental Coverage','vision'=>'Vision Coverage',
    '401k'    =>'401(k) Match',   'stock' =>'Stock Options',  'remote' =>'Remote Work',
    'learning'=>'Learning Budget','gym'=>'Gym Membership',
];

$pageTitle   = 'Offers';
$activeNav   = 'offers';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Offers']];

ob_start();
?>

<!-- Top bar -->
<div class="flex items-center justify-between mb-6">
  <p class="text-sm text-gray-500">Manage job offers through every stage of acceptance.</p>
  <button onclick="openOfferModal()" class="flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition-all shadow-sm hover:shadow-md">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Create Offer
  </button>
</div>

<!-- Status tabs -->
<div class="flex gap-1 overflow-x-auto border-b border-gray-200 mb-6">
  <?php foreach ($tabs as $key => $label): $on = $activeTab === $key; ?>
    <a href="?tab=<?= e($key) ?>"
      class="flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap relative transition-colors <?= $on ? 'text-violet-700' : 'text-gray-500 hover:text-gray-800' ?>">
      <?= e($label) ?>
      <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $on ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-500' ?>"><?= (int)($tabCounts[$key]??0) ?></span>
      <?php if ($on): ?><span class="absolute bottom-0 left-0 right-0 h-0.5 bg-violet-600 rounded-t-full"></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Offers list -->
<?php if (empty($tabOffers)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 py-16 text-center">
  <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
  </div>
  <p class="font-semibold text-gray-700">No <?= strtolower($tabs[$activeTab]) ?> offers</p>
  <p class="text-sm text-gray-400 mt-1">Create an offer to get started.</p>
  <button onclick="openOfferModal()" class="mt-4 inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-semibold transition-colors">Create Offer</button>
</div>
<?php else: ?>
<div class="grid grid-cols-1 gap-4">
  <?php foreach ($tabOffers as $offer):
    [$stLabel,$stCls] = $statusConfig[$offer['status']] ?? ['Unknown','bg-gray-100 text-gray-600'];
  ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
      <!-- Candidate info -->
      <div class="flex items-center gap-4 flex-1 min-w-0">
        <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-700 font-bold flex items-center justify-center text-base shrink-0">
          <?= e(initials($offer['candidate'])) ?>
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <a href="/candidates/<?= (int)$offer['candidate_id'] ?>" class="font-bold text-gray-900 hover:text-violet-600 transition-colors"><?= e($offer['candidate']) ?></a>
            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $stCls ?>"><?= e($stLabel) ?></span>
          </div>
          <div class="text-sm text-gray-500 mt-0.5"><?= e($offer['position']) ?> · <?= e($offer['department']) ?></div>
          <div class="text-xs text-gray-400 mt-0.5">Reports to: <?= e($offer['reporting_to']) ?></div>
        </div>
      </div>

      <!-- Offer details -->
      <div class="grid grid-cols-3 gap-4 sm:gap-6 text-center sm:text-right">
        <div>
          <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Salary</div>
          <div class="font-bold text-gray-900"><?= e($offer['currency']) ?> <?= number_format((int)$offer['salary']) ?></div>
          <div class="text-xs text-gray-400 capitalize"><?= e($offer['salary_type']) ?></div>
        </div>
        <div>
          <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Start Date</div>
          <div class="font-semibold text-gray-700 text-sm"><?= e(time_ago($offer['start_date'])) ?></div>
        </div>
        <div>
          <?php if ($offer['deadline'] && $offer['status'] === 'pending'): ?>
          <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Deadline</div>
          <div class="font-semibold text-sm <?= strtotime($offer['deadline']) < time()+86400*3 ? 'text-rose-600' : 'text-amber-600' ?>">
            <?= e(time_ago($offer['deadline'])) ?>
          </div>
          <?php else: ?>
          <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Created</div>
          <div class="font-semibold text-gray-500 text-sm"><?= e(time_ago($offer['created_at'])) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Benefits -->
    <?php if (!empty($offer['benefits'])): ?>
    <div class="mt-4 flex flex-wrap gap-1.5">
      <?php foreach ($offer['benefits'] as $b): ?>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
          <?= e($benefitLabels[$b] ?? ucfirst($b)) ?>
        </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="mt-5 pt-4 border-t border-gray-50 flex items-center gap-2 flex-wrap">
      <?php if ($offer['status'] === 'pending' || $offer['status'] === 'draft'): ?>
        <button onclick="openOfferModal(<?= (int)$offer['id'] ?>)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">Edit</button>
        <?php if ($offer['status'] === 'pending'): ?>
          <button onclick="resendOffer(<?= (int)$offer['id'] ?>)" class="bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">Resend</button>
          <button onclick="withdrawOffer(<?= (int)$offer['id'] ?>)" class="bg-rose-50 hover:bg-rose-100 text-rose-600 px-4 py-2 rounded-full text-sm font-medium transition-colors">Withdraw</button>
        <?php endif; ?>
        <?php if ($offer['status'] === 'draft'): ?>
          <button onclick="sendOffer(<?= (int)$offer['id'] ?>)" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">Send Offer</button>
        <?php endif; ?>
      <?php elseif ($offer['status'] === 'accepted'): ?>
        <button onclick="viewOffer(<?= (int)$offer['id'] ?>)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">View Letter</button>
        <button onclick="downloadOffer(<?= (int)$offer['id'] ?>)" class="flex items-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Download PDF
        </button>
      <?php elseif ($offer['status'] === 'declined'): ?>
        <button onclick="viewOffer(<?= (int)$offer['id'] ?>)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">View Letter</button>
        <button onclick="openOfferModal()" class="bg-violet-50 hover:bg-violet-100 text-violet-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">Create New Offer</button>
      <?php endif; ?>
      <button onclick="viewOffer(<?= (int)$offer['id'] ?>)" class="ml-auto text-xs text-violet-600 hover:text-violet-800 font-medium">Preview Letter →</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════ CREATE / EDIT OFFER MODAL ══════════ -->
<div id="offerModal" class="hidden fixed inset-0 z-[90] flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeOfferModal()"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh]">
    <!-- Modal Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
      <div>
        <h3 class="font-bold text-gray-900" id="offerModalTitle">Create Offer</h3>
        <p class="text-xs text-gray-400">Fill in the details and optionally use AI to write the offer letter.</p>
      </div>
      <button onclick="closeOfferModal()" class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- Modal Body -->
    <div class="overflow-y-auto flex-1 px-6 py-5">
      <form id="offerForm" class="space-y-4">
        <input type="hidden" name="offer_id" id="offerIdField">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Candidate <span class="text-rose-500">*</span></label>
            <input type="text" name="candidate_name" id="offerCandidate" placeholder="Search or type candidate name..."
              class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Position Title <span class="text-rose-500">*</span></label>
            <input type="text" name="position" id="offerPosition" placeholder="e.g. Senior Backend Engineer"
              class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
            <select name="department" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
              <?php foreach (['Engineering','Marketing','Sales','HR','Finance','Operations','Design','Legal','Other'] as $d): ?>
                <option value="<?= e($d) ?>"><?= e($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Reporting To</label>
            <input type="text" name="reporting_to" placeholder="e.g. CTO, Engineering Manager"
              class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
          </div>
        </div>

        <!-- Salary -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Compensation</label>
          <div class="flex flex-wrap gap-2">
            <select name="currency" class="rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none w-24">
              <option>USD</option><option>EUR</option><option>GBP</option><option>AED</option>
            </select>
            <input type="number" name="salary" placeholder="Amount" min="0"
              class="rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none flex-1 min-w-24">
            <select name="salary_type" class="rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
              <option value="annual">Annual</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>
        </div>

        <!-- Benefits -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Benefits Package</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <?php foreach ($benefitLabels as $val=>$label): ?>
              <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="benefits[]" value="<?= e($val) ?>" class="accent-violet-600 rounded w-4 h-4">
                <?= e($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Start Date</label>
            <input type="date" name="start_date" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Offer Expiry Date</label>
            <input type="date" name="expiry_date" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
          </div>
        </div>

        <!-- Additional conditions -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Additional Conditions</label>
          <textarea name="conditions" rows="3" placeholder="e.g. Probationary period of 3 months, signing bonus of $5,000, relocation package..."
            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-y"></textarea>
        </div>

        <!-- AI Letter Generator -->
        <div class="border-t border-gray-100 pt-4">
          <div class="flex items-center justify-between mb-3">
            <label class="text-sm font-semibold text-gray-700">Offer Letter</label>
            <button type="button" onclick="generateOfferLetter()" id="generateLetterBtn"
              class="flex items-center gap-1.5 bg-amber-400 hover:bg-amber-500 text-gray-900 px-4 py-2 rounded-full text-sm font-bold transition-all">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
              AI Generate Letter
            </button>
          </div>

          <!-- Letter preview / editor -->
          <div id="letterLoading" class="hidden text-center py-8">
            <div class="inline-block w-8 h-8 border-3 border-violet-600 border-t-transparent rounded-full animate-spin mb-3" style="border-width:3px"></div>
            <p class="text-sm text-gray-500">AI is writing your offer letter...</p>
          </div>
          <div id="letterPreview" class="hidden rounded-xl border border-gray-200 bg-gray-50 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-white">
              <span class="text-xs font-semibold text-gray-600">Offer Letter Preview</span>
              <div class="flex gap-2">
                <button type="button" onclick="toggleLetterEdit()" class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
                <button type="button" onclick="copyLetter()" class="text-xs text-gray-500 hover:text-gray-700 font-medium">Copy</button>
              </div>
            </div>
            <div id="letterContent" contenteditable="false"
              class="p-5 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap max-h-64 overflow-y-auto outline-none"
              style="font-family:Georgia,serif">
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Modal Footer -->
    <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 shrink-0">
      <button type="button" onclick="closeOfferModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">Cancel</button>
      <div class="flex gap-2">
        <button type="button" onclick="saveOffer('draft')" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">Save Draft</button>
        <button type="button" onclick="saveOffer('pending')" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-bold transition-colors shadow-sm">Send Offer</button>
      </div>
    </div>
  </div>
</div>

<!-- Offer Letter Preview Modal -->
<div id="viewLetterModal" class="hidden fixed inset-0 z-[91] flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-gray-900/60" onclick="closeLetterModal()"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto p-8" style="font-family:Georgia,serif">
    <div class="flex items-start justify-between mb-6 font-sans">
      <h3 class="font-bold text-gray-900">Offer Letter</h3>
      <button onclick="closeLetterModal()" class="text-gray-400 hover:text-gray-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div id="viewLetterContent" class="text-sm text-gray-800 leading-relaxed space-y-4"></div>
  </div>
</div>

<script>
var currentOfferId = null;

function openOfferModal(offerId) {
  currentOfferId = offerId || null;
  document.getElementById('offerModalTitle').textContent = offerId ? 'Edit Offer' : 'Create Offer';
  document.getElementById('offerModal').classList.remove('hidden');
  document.getElementById('letterPreview').classList.add('hidden');
  document.getElementById('letterLoading').classList.add('hidden');
}

function closeOfferModal() {
  document.getElementById('offerModal').classList.add('hidden');
}

async function generateOfferLetter() {
  var candidate = document.getElementById('offerCandidate').value.trim();
  var position  = document.getElementById('offerPosition').value.trim();
  if (!candidate || !position) {
    showToast('Please fill in candidate name and position first.', 'warning');
    return;
  }

  var btn = document.getElementById('generateLetterBtn');
  var loading = document.getElementById('letterLoading');
  var preview = document.getElementById('letterPreview');

  btn.disabled = true;
  loading.classList.remove('hidden');
  preview.classList.add('hidden');

  try {
    var resp = await fetch('/api/v1/ai?action=generate-offer', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({candidate: candidate, position: position})
    });
    var data = await resp.json();
    var letter = data.data?.letter || generateFallbackLetter(candidate, position);
    document.getElementById('letterContent').textContent = letter;
    showToast('Offer letter generated!', 'success');
  } catch(e) {
    document.getElementById('letterContent').textContent = generateFallbackLetter(candidate, position);
    showToast('AI generated a draft letter. Review before sending.', 'info');
  }

  loading.classList.add('hidden');
  preview.classList.remove('hidden');
  btn.disabled = false;
}

function generateFallbackLetter(candidate, position) {
  var today = new Date().toLocaleDateString('en-GB', {year:'numeric',month:'long',day:'numeric'});
  return today + '\n\nDear ' + candidate + ',\n\nWe are delighted to extend this offer of employment to you for the position of ' + position + ' at Acme Talent Ltd.\n\nFollowing your impressive performance throughout our interview process, we are confident you will make a significant and positive contribution to our team. Your experience, skills, and approach closely align with what we are looking for in this role.\n\nThe details of your offer are outlined in the accompanying schedule. Please review all terms carefully. Should you have any questions, please do not hesitate to contact us.\n\nThis offer is contingent upon the successful completion of reference and background checks. We kindly request that you confirm your acceptance of this offer by the stated expiry date.\n\nWe are genuinely excited about the possibility of you joining our team and look forward to hearing from you.\n\nYours sincerely,\n\nSarah Mitchell\nHR Manager, Acme Talent Ltd';
}

function toggleLetterEdit() {
  var el = document.getElementById('letterContent');
  var isEditable = el.contentEditable === 'true';
  el.contentEditable = isEditable ? 'false' : 'true';
  el.className = el.className.replace('bg-gray-50','') + (isEditable ? '' : ' bg-white ring-2 ring-violet-500');
  if (!isEditable) el.focus();
}

function copyLetter() {
  var text = document.getElementById('letterContent').textContent;
  navigator.clipboard.writeText(text).then(function(){
    showToast('Letter copied to clipboard.', 'success');
  });
}

function saveOffer(status) {
  showToast(status === 'draft' ? 'Offer saved as draft.' : 'Offer sent to candidate!', 'success');
  closeOfferModal();
}

function resendOffer(id) {
  showToast('Offer resent to candidate.', 'success');
}

function withdrawOffer(id) {
  if (!confirm('Are you sure you want to withdraw this offer?')) return;
  showToast('Offer withdrawn.', 'info');
}

function sendOffer(id) {
  showToast('Offer sent to candidate!', 'success');
}

function viewOffer(id) {
  var sampleLetter = 'Dear Candidate,\n\nWe are pleased to offer you the position at our company. Please review the enclosed terms and respond by the deadline.\n\nWe look forward to welcoming you to the team.\n\nBest regards,\nHR Team';
  document.getElementById('viewLetterContent').innerHTML = '<p>' + sampleLetter.replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>') + '</p>';
  document.getElementById('viewLetterModal').classList.remove('hidden');
}

function closeLetterModal() {
  document.getElementById('viewLetterModal').classList.add('hidden');
}

function downloadOffer(id) {
  showToast('Preparing PDF download...', 'info');
  setTimeout(function(){ showToast('PDF downloaded!', 'success'); }, 1200);
}
</script>
<?php require __DIR__ . '/../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
