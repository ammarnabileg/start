<?php
$pageTitle = 'Human Interviews';
$db = Database::getInstance();
$tid = Auth::user()['tenant_id'];
$interviews = $db->fetchAll(
    "SELECT hi.*, c.full_name as candidate_name, j.title as job_title, a.current_stage
     FROM human_interviews hi
     JOIN applications a ON a.id = hi.application_id
     JOIN candidates c ON c.id = a.candidate_id
     JOIN jobs j ON j.id = a.job_id
     WHERE hi.tenant_id = ? ORDER BY hi.scheduled_at DESC LIMIT 50",
    [$tid]
) ?: [];
$teamMembers = $db->fetchAll("SELECT id, full_name FROM users WHERE tenant_id=? AND status='active' AND role != 'candidate' ORDER BY full_name", [$tid]) ?: [];
$jobsList    = $db->fetchAll("SELECT id, title FROM jobs WHERE tenant_id=? AND status='published' ORDER BY title", [$tid]) ?: [];
?>

<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Human Interviews</h1>
      <p class="text-sm text-gray-500 mt-1">Schedule and track interviews with your team</p>
    </div>
    <button onclick="openModal('schedule-modal')" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Schedule Interview
    </button>
  </div>

  <!-- Filter tabs -->
  <div class="flex gap-2 mb-4 border-b border-gray-200">
    <?php foreach (['upcoming'=>'Upcoming','today'=>'Today','past'=>'Past','all'=>'All'] as $k=>$v): ?>
    <button onclick="filterTab('<?=$k?>')" data-tab="<?=$k?>"
      class="px-4 py-2 text-sm font-medium border-b-2 -mb-px <?=$k==='upcoming'?'border-violet-600 text-violet-700':'border-transparent text-gray-500 hover:text-gray-700'?> transition-colors tab-btn">
      <?=$v?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Interviews Table -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($interviews)): ?>
    <div class="py-16 text-center">
      <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      <p class="text-gray-500 font-medium">No interviews scheduled yet</p>
      <p class="text-sm text-gray-400 mt-1">Schedule your first human interview above</p>
    </div>
    <?php else: ?>
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Candidate</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
          <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100" id="interviews-tbody">
        <?php foreach ($interviews as $iv):
        $statusColors = ['scheduled'=>'bg-blue-100 text-blue-700','completed'=>'bg-emerald-100 text-emerald-700','cancelled'=>'bg-red-100 text-red-700','pending'=>'bg-amber-100 text-amber-700'];
        $sc = $statusColors[$iv['status']??'pending'] ?? 'bg-gray-100 text-gray-700';
        $dt = $iv['scheduled_at'] ? date('M j, Y g:i A', strtotime($iv['scheduled_at'])) : '—';
        ?>
        <tr class="hover:bg-gray-50 interview-row" data-status="<?= $iv['status'] ?>" data-time="<?= $iv['scheduled_at'] ?>">
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-bold">
                <?= strtoupper(substr($iv['candidate_name']??'?',0,1)) ?>
              </div>
              <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($iv['candidate_name']??'') ?></span>
            </div>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($iv['job_title']??'') ?></td>
          <td class="px-4 py-3">
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full capitalize">
              <?= htmlspecialchars(str_replace('_', ' ', $iv['interview_type']??'interview')) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600"><?= $dt ?></td>
          <td class="px-4 py-3">
            <span class="text-xs font-medium px-2 py-1 rounded-full <?= $sc ?>"><?= ucfirst($iv['status']??'pending') ?></span>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              <?php if (($iv['status']??'') === 'scheduled'): ?>
              <button onclick="markComplete(<?= $iv['id'] ?>)" class="text-xs text-emerald-600 hover:text-emerald-800 font-medium">Complete</button>
              <button onclick="cancelInterview(<?= $iv['id'] ?>)" class="text-xs text-red-600 hover:text-red-800 font-medium">Cancel</button>
              <?php endif; ?>
              <?php if ($iv['meeting_link']??''): ?>
              <a href="<?= htmlspecialchars($iv['meeting_link']) ?>" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Join</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Schedule Modal -->
<div id="schedule-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 modal-overlay">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-screen overflow-y-auto">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white">
      <h3 class="font-semibold text-gray-900">Schedule Interview</h3>
      <button data-modal-close class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="schedule-form" class="p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Candidate Search</label>
        <input type="text" id="candidate-search" placeholder="Search by name or email..." autocomplete="off"
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
        <input type="hidden" name="application_id" id="selected-app-id">
        <div id="candidate-results" class="mt-1 border border-gray-200 rounded-xl overflow-hidden hidden"></div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Interview Type</label>
        <select name="interview_type" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          <option value="technical_screening">Technical Screening</option>
          <option value="cultural_fit">Cultural Fit</option>
          <option value="management_round">Management Round</option>
          <option value="final_interview">Final Interview</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
          <input type="date" name="date" required min="<?= date('Y-m-d') ?>"
            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
          <input type="time" name="time" required
            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
        <select name="duration_minutes" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          <option value="30">30 minutes</option>
          <option value="45">45 minutes</option>
          <option value="60" selected>60 minutes</option>
          <option value="90">90 minutes</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Meeting Link (optional)</label>
        <input type="url" name="meeting_link" placeholder="https://meet.google.com/..."
          class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes for Interviewers</label>
        <textarea name="notes" rows="2" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-violet-500 focus:outline-none"
          placeholder="Topics to cover, specific areas to probe..."></textarea>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('schedule-modal')" class="flex-1 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-full hover:bg-gray-200">Cancel</button>
        <button type="submit" id="schedule-btn" class="flex-1 py-2 text-sm font-medium text-white bg-violet-600 rounded-full hover:bg-violet-700">Schedule</button>
      </div>
    </form>
  </div>
</div>

<!-- Complete Interview Modal -->
<div id="complete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 modal-overlay">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
    <h3 class="font-semibold text-gray-900 mb-4">Mark Interview Complete</h3>
    <input type="hidden" id="complete-iv-id">
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-2">Overall Rating</label>
      <div class="flex gap-2" id="star-rating">
        <?php for ($s=1;$s<=5;$s++): ?>
        <button type="button" data-star="<?=$s?>" onclick="setRating(<?=$s?>)"
          class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center text-lg hover:border-amber-400 star-btn">
          ⭐
        </button>
        <?php endfor; ?>
      </div>
      <input type="hidden" id="rating-value" value="0">
    </div>
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Feedback</label>
      <textarea id="complete-feedback" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-violet-500 focus:outline-none"
        placeholder="Key observations and recommendations..."></textarea>
    </div>
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-2">Recommendation</label>
      <div class="flex gap-2">
        <button onclick="setRec('hire')" data-rec="hire" class="rec-btn flex-1 py-2 text-sm rounded-full border border-emerald-300 text-emerald-700 hover:bg-emerald-50">Hire</button>
        <button onclick="setRec('no_hire')" data-rec="no_hire" class="rec-btn flex-1 py-2 text-sm rounded-full border border-red-300 text-red-700 hover:bg-red-50">No Hire</button>
        <button onclick="setRec('maybe')" data-rec="maybe" class="rec-btn flex-1 py-2 text-sm rounded-full border border-amber-300 text-amber-700 hover:bg-amber-50">Maybe</button>
      </div>
      <input type="hidden" id="rec-value" value="">
    </div>
    <div class="flex gap-3">
      <button onclick="closeModal('complete-modal')" class="flex-1 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-full">Cancel</button>
      <button onclick="submitComplete()" class="flex-1 py-2 text-sm font-medium text-white bg-emerald-600 rounded-full hover:bg-emerald-700">Save & Complete</button>
    </div>
  </div>
</div>

<script>
function filterTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    const active = b.dataset.tab === tab;
    b.classList.toggle('border-violet-600', active);
    b.classList.toggle('text-violet-700', active);
    b.classList.toggle('border-transparent', !active);
    b.classList.toggle('text-gray-500', !active);
  });
  const now = new Date();
  const today = now.toISOString().split('T')[0];
  document.querySelectorAll('.interview-row').forEach(row => {
    const status = row.dataset.status;
    const time   = row.dataset.time;
    let show = true;
    if (tab === 'upcoming') show = time > now.toISOString() && status === 'scheduled';
    else if (tab === 'today') show = time && time.startsWith(today);
    else if (tab === 'past')  show = time < now.toISOString() || status === 'completed' || status === 'cancelled';
    row.style.display = show ? '' : 'none';
  });
}
filterTab('upcoming');

let searchTimer;
document.getElementById('candidate-search').addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q = this.value.trim();
  if (q.length < 2) { document.getElementById('candidate-results').classList.add('hidden'); return; }
  searchTimer = setTimeout(async () => {
    const res = await fetch('/api/v1/candidates?search=' + encodeURIComponent(q) + '&page=1', { headers: {'X-Requested-With':'XMLHttpRequest'} });
    const data = await res.json();
    const results = document.getElementById('candidate-results');
    if (!data.ok || !data.data?.length) { results.classList.add('hidden'); return; }
    results.innerHTML = data.data.map(c => `
      <button type="button" class="w-full text-left px-4 py-3 hover:bg-violet-50 flex items-center gap-3"
        onclick="selectCandidate(${c.id}, '${c.full_name.replace(/'/g,"\\'")}', ${c.id})">
        <div class="w-8 h-8 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-bold">${c.full_name[0]}</div>
        <div><p class="font-medium text-sm">${c.full_name}</p><p class="text-xs text-gray-500">${c.job_title||''}</p></div>
      </button>`).join('');
    results.classList.remove('hidden');
  }, 300);
});

function selectCandidate(appId, name) {
  document.getElementById('selected-app-id').value = appId;
  document.getElementById('candidate-search').value = name;
  document.getElementById('candidate-results').classList.add('hidden');
}

document.getElementById('schedule-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd);
  body.scheduled_at = body.date + ' ' + body.time + ':00';
  delete body.date; delete body.time;
  if (!body.application_id) { showToast('Please select a candidate', 'warning'); return; }
  const btn = document.getElementById('schedule-btn');
  setLoading(btn, true, 'Scheduling...');
  try {
    const res = await ajax('/api/v1/interviews?action=schedule_human', { body });
    if (res.ok) { showToast('Interview scheduled', 'success'); closeModal('schedule-modal'); setTimeout(() => location.reload(), 800); }
    else throw new Error(res.message);
  } catch(e) { showToast(e.message || 'Failed', 'error'); }
  finally { setLoading(btn, false); }
});

function setRating(n) {
  document.getElementById('rating-value').value = n;
  document.querySelectorAll('.star-btn').forEach((b,i) => b.style.opacity = i < n ? '1' : '0.3');
}
function setRec(v) {
  document.getElementById('rec-value').value = v;
  document.querySelectorAll('.rec-btn').forEach(b => {
    b.classList.toggle('ring-2', b.dataset.rec === v);
    b.classList.toggle('ring-offset-1', b.dataset.rec === v);
  });
}
function markComplete(id) { document.getElementById('complete-iv-id').value = id; openModal('complete-modal'); }
async function submitComplete() {
  const id = document.getElementById('complete-iv-id').value;
  const body = { id, status:'completed', rating: document.getElementById('rating-value').value, feedback: document.getElementById('complete-feedback').value, recommendation: document.getElementById('rec-value').value };
  try {
    const res = await ajax('/api/v1/interviews?action=complete_human', { body });
    if (res.ok) { showToast('Interview completed', 'success'); closeModal('complete-modal'); setTimeout(() => location.reload(), 800); }
    else throw new Error(res.message);
  } catch(e) { showToast(e.message || 'Failed', 'error'); }
}
async function cancelInterview(id) {
  confirm2('Cancel this interview?', async () => {
    const res = await ajax('/api/v1/interviews?action=cancel_human', { body: { id } });
    if (res.ok) { showToast('Cancelled', 'success'); setTimeout(() => location.reload(), 800); }
  });
}
</script>
