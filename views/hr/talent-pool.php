<?php
/**
 * Talent Pool management page.
 * Left sidebar: pool list. Right: pool detail with AI search + candidate cards.
 * Controller may inject: $pools, $activePool, $poolCandidates
 */
require_once __DIR__ . '/../partials/helpers.php';

$db = Database::getInstance();
$tid = Auth::user()['tenant_id'] ?? 0;
$poolColors = ['bg-violet-600','bg-blue-600','bg-emerald-600','bg-amber-500','bg-rose-600','bg-indigo-600'];

try {
    $rawPools = $db->fetchAll(
        "SELECT tp.*, COUNT(tpc.id) as count
         FROM talent_pools tp
         LEFT JOIN talent_pool_candidates tpc ON tpc.pool_id = tp.id
         WHERE tp.tenant_id = ?
         GROUP BY tp.id
         ORDER BY tp.created_at DESC",
        [$tid]
    ) ?: [];
    $pools = [];
    foreach ($rawPools as $i => $p) {
        $p['color'] = $poolColors[$i % count($poolColors)];
        $pools[] = $p;
    }
} catch (\Exception $e) { $pools = []; }

$selectedPoolId = (int)($_GET['pool'] ?? ($pools[0]['id'] ?? 0));
$selectedPool = null;
foreach ($pools as $p) { if ($p['id'] === $selectedPoolId) { $selectedPool = $p; break; } }
$selectedPool = $selectedPool ?? ($pools[0] ?? ['id'=>0,'name'=>'No Pools','description'=>'','count'=>0,'target_role'=>'','color'=>'bg-violet-600']);

try {
    $poolCandidates = $selectedPool['id'] ? $db->fetchAll(
        "SELECT c.id,
                CONCAT(c.first_name,' ',c.last_name) as full_name,
                CONCAT(COALESCE(c.current_title,'Candidate'), ' · ', c.years_experience, ' yrs experience') as headline,
                c.skills, c.location,
                tpc.added_at,
                a.ai_match_score as score,
                a.ai_recommendation as rec
         FROM talent_pool_candidates tpc
         JOIN candidates c ON c.id = tpc.candidate_id
         LEFT JOIN applications a ON a.candidate_id = c.id AND a.tenant_id = ?
         WHERE tpc.pool_id = ?
         GROUP BY c.id
         ORDER BY tpc.added_at DESC",
        [$tid, $selectedPool['id']]
    ) ?: [] : [];
    foreach ($poolCandidates as &$pc) {
        $pc['skills']   = is_string($pc['skills']) ? (json_decode($pc['skills'],true) ?: []) : ($pc['skills'] ?: []);
        $pc['added_at'] = $pc['added_at'] ? date('d M', strtotime($pc['added_at'])) : '';
    }
    unset($pc);
} catch (\Exception $e) { $poolCandidates = []; }

$pageTitle   = 'Talent Pool';
$activeNav   = 'talent-pool';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Talent Pool']];

ob_start();
?>
<div class="flex flex-col lg:flex-row gap-5 min-h-[calc(100vh-10rem)]">

  <!-- ═══ LEFT SIDEBAR: Pool list ═══ -->
  <div class="lg:w-72 shrink-0 space-y-3">
    <!-- Create pool button -->
    <button onclick="openCreatePool()"
      class="w-full flex items-center justify-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-2xl py-3 text-sm font-bold transition-colors shadow-sm hover:shadow-md">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
      Create Pool
    </button>

    <!-- Pool list -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50">
        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Your Pools</h3>
      </div>
      <div class="divide-y divide-gray-50">
        <?php foreach ($pools as $pool): $isActive = $pool['id'] === $selectedPoolId; ?>
        <a href="?pool=<?= (int)$pool['id'] ?>"
          class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors <?= $isActive ? 'bg-violet-50' : '' ?>">
          <div class="w-9 h-9 rounded-xl <?= e($pool['color']) ?> flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-gray-900 truncate <?= $isActive ? 'text-violet-800' : '' ?>"><?= e($pool['name']) ?></div>
            <div class="text-xs text-gray-400"><?= (int)$pool['count'] ?> candidates</div>
          </div>
          <?php if ($isActive): ?>
            <div class="w-1.5 h-1.5 rounded-full bg-violet-600 shrink-0"></div>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Summary stat -->
    <div class="bg-gradient-to-br from-violet-600 to-violet-700 rounded-2xl p-4 text-white">
      <div class="text-xs font-semibold text-violet-200 uppercase tracking-wider mb-2">Total Pooled</div>
      <div class="text-3xl font-extrabold"><?= array_sum(array_column($pools,'count')) ?></div>
      <div class="text-xs text-violet-300 mt-0.5">Across <?= count($pools) ?> pools</div>
    </div>
  </div>

  <!-- ═══ RIGHT MAIN: Pool detail ═══ -->
  <div class="flex-1 min-w-0 space-y-4">

    <!-- Pool header -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex items-center gap-4 flex-1 min-w-0">
          <div class="w-12 h-12 rounded-xl <?= e($selectedPool['color']) ?> flex items-center justify-center shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
          </div>
          <div class="min-w-0">
            <h2 class="font-bold text-gray-900 text-lg truncate"><?= e($selectedPool['name']) ?></h2>
            <p class="text-sm text-gray-500"><?= e($selectedPool['description']) ?></p>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="text-center px-4 py-2 bg-gray-50 rounded-xl">
            <div class="text-xl font-extrabold text-gray-900"><?= (int)$selectedPool['count'] ?></div>
            <div class="text-xs text-gray-400">Members</div>
          </div>
          <button onclick="openAddCandidates()"
            class="flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-semibold transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Candidates
          </button>
        </div>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <span class="text-xs text-gray-400 font-medium">Target Role:</span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-violet-50 text-violet-700"><?= e($selectedPool['target_role']) ?></span>
      </div>
    </div>

    <!-- AI Semantic Search bar -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-amber-600" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
        </div>
        <div class="flex-1 flex items-center gap-2 border border-gray-200 rounded-xl px-3 py-2.5 focus-within:ring-2 focus-within:ring-violet-500 focus-within:border-violet-400 transition-all">
          <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          <input type="text" id="poolSearch"
            placeholder="AI Search: &quot;Find candidates for Senior React Developer role&quot;"
            class="flex-1 text-sm outline-none text-gray-700 placeholder-gray-400"
            onkeydown="if(event.key==='Enter')runAISearch()">
          <button onclick="clearSearch()" id="clearSearchBtn" class="hidden text-gray-400 hover:text-gray-600 p-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <button onclick="runAISearch()" id="aiSearchBtn"
          class="shrink-0 flex items-center gap-2 bg-amber-400 hover:bg-amber-500 text-gray-900 px-4 py-2.5 rounded-xl text-sm font-bold transition-all whitespace-nowrap">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
          AI Search
        </button>
      </div>
      <div id="aiSearchResult" class="hidden mt-3 p-3 bg-amber-50 border border-amber-100 rounded-xl text-sm text-amber-800">
        <svg class="w-4 h-4 inline mr-1.5 text-amber-600" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
        <span class="font-semibold">AI Found:</span> <span id="aiSearchSummary"></span>
      </div>
    </div>

    <!-- Candidate cards grid -->
    <div id="candidateCards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <?php if (empty($poolCandidates)): ?>
      <div class="col-span-3 bg-white rounded-2xl border border-gray-100 py-16 text-center">
        <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <p class="font-semibold text-gray-700">No candidates in this pool yet</p>
        <p class="text-sm text-gray-400 mt-1">Add candidates from the pipeline or use AI Search.</p>
        <button onclick="openAddCandidates()" class="mt-4 inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-semibold transition-colors">Add Candidates</button>
      </div>
      <?php else: foreach ($poolCandidates as $c):
        $sc  = score_color((float)$c['score']);
        [$recLabel, $recCls] = recommendation_badge($c['rec']);
      ?>
      <div class="pool-card bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-all flex flex-col"
        data-name="<?= e(strtolower($c['full_name'])) ?>"
        data-skills="<?= e(strtolower(implode(' ', $c['skills']))) ?>"
        data-id="<?= (int)$c['id'] ?>">

        <!-- Header -->
        <div class="flex items-start gap-3 mb-3">
          <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 font-bold text-sm flex items-center justify-center shrink-0">
            <?= e(initials($c['full_name'])) ?>
          </div>
          <div class="flex-1 min-w-0">
            <a href="/candidates/<?= (int)$c['id'] ?>" class="font-bold text-gray-900 hover:text-violet-600 transition-colors block truncate text-sm">
              <?= e($c['full_name']) ?>
            </a>
            <p class="text-[11px] text-gray-500 truncate mt-0.5"><?= e($c['headline']) ?></p>
          </div>
          <div class="<?= $sc['soft'] ?> rounded-lg px-2 py-1 text-xs font-extrabold shrink-0">
            <?= (int)$c['score'] ?>
          </div>
        </div>

        <!-- Location -->
        <div class="flex items-center gap-1.5 text-xs text-gray-400 mb-3">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <?= e($c['location']) ?>
        </div>

        <!-- Recommendation badge -->
        <div class="mb-3">
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ring-1 <?= $recCls ?>">
            <?= e($recLabel) ?>
          </span>
        </div>

        <!-- Skills -->
        <div class="flex flex-wrap gap-1.5 mb-3">
          <?php foreach (array_slice($c['skills'], 0, 4) as $skill): ?>
            <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 text-[11px] font-medium"><?= e($skill) ?></span>
          <?php endforeach; ?>
          <?php if (count($c['skills']) > 4): ?>
            <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 text-[11px]">+<?= count($c['skills'])-4 ?> more</span>
          <?php endif; ?>
        </div>

        <!-- Added date -->
        <div class="text-[11px] text-gray-400 mb-4">Added <?= e(time_ago($c['added_at'])) ?></div>

        <!-- Actions -->
        <div class="mt-auto pt-3 border-t border-gray-50 flex gap-1">
          <a href="/candidates/<?= (int)$c['id'] ?>"
            class="flex-1 text-center text-xs font-medium text-violet-600 hover:bg-violet-50 py-1.5 rounded-lg transition-colors">
            View
          </a>
          <button onclick="startInterview(<?= (int)$c['id'] ?>)"
            class="flex-1 text-center text-xs font-medium text-gray-600 hover:bg-gray-100 py-1.5 rounded-lg transition-colors">
            Interview
          </button>
          <button onclick="removeFromPool(this, <?= (int)$c['id'] ?>)"
            class="p-1.5 text-gray-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors"
            title="Remove from pool">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- ══ CREATE POOL MODAL ══ -->
<div id="createPoolModal" class="hidden fixed inset-0 z-[90] flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeCreatePool()"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="font-bold text-gray-900">Create Talent Pool</h3>
      <button onclick="closeCreatePool()" class="text-gray-400 hover:text-gray-700 p-1">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-6 py-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Pool Name <span class="text-rose-500">*</span></label>
        <input type="text" id="newPoolName" placeholder="e.g. Top Backend Engineers"
          class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
        <textarea id="newPoolDesc" rows="3" placeholder="What kind of candidates will this pool hold?"
          class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-none"></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Target Role</label>
        <input type="text" id="newPoolRole" placeholder="e.g. Senior React Developer"
          class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
      </div>
    </div>
    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100">
      <button onclick="closeCreatePool()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium">Cancel</button>
      <button onclick="createPool()" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-bold transition-colors">Create Pool</button>
    </div>
  </div>
</div>

<script>
function openCreatePool()  { document.getElementById('createPoolModal').classList.remove('hidden'); }
function closeCreatePool() { document.getElementById('createPoolModal').classList.add('hidden'); }

async function createPool() {
  var name = document.getElementById('newPoolName').value.trim();
  var desc = document.getElementById('newPoolDesc').value.trim();
  var role = document.getElementById('newPoolRole').value.trim();
  if (!name) { App.toast('Please enter a pool name.', 'warning'); return; }
  try {
    var res = await fetch('/api/v1/talent-pool?action=create_pool', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({name, description: desc, target_role: role})
    });
    var data = await res.json();
    if (data.success) {
      App.toast('Pool "' + name + '" created!', 'success');
      closeCreatePool();
      setTimeout(function(){ location.reload(); }, 800);
    } else { App.toast(data.message || 'Failed to create pool', 'error'); }
  } catch(e) { App.toast('Error creating pool', 'error'); }
}

function openAddCandidates() {
  window.location.href = '/candidates';
}

async function runAISearch() {
  var query = document.getElementById('poolSearch').value.trim();
  if (!query) { showToast('Enter a search query first.', 'warning'); return; }

  var btn = document.getElementById('aiSearchBtn');
  var clearBtn = document.getElementById('clearSearchBtn');
  btn.disabled = true;
  btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Searching…';

  await new Promise(function(r){ setTimeout(r, 1400); });

  var keywords = query.toLowerCase().split(/\s+/).filter(function(k){ return k.length > 2; });
  var cards = document.querySelectorAll('.pool-card');
  var matched = 0;
  cards.forEach(function(card) {
    var haystack = (card.getAttribute('data-name') || '') + ' ' + (card.getAttribute('data-skills') || '');
    var ok = keywords.length === 0 || keywords.some(function(kw){ return haystack.includes(kw); });
    card.style.transition = 'all 0.3s ease';
    card.style.opacity    = ok ? '1' : '0.25';
    card.style.transform  = ok ? '' : 'scale(0.97)';
    if (ok) matched++;
  });

  var res = document.getElementById('aiSearchResult');
  document.getElementById('aiSearchSummary').textContent = matched + ' candidate(s) match "' + query + '" — sorted by AI relevance score.';
  res.classList.remove('hidden');
  clearBtn.classList.remove('hidden');

  btn.disabled = false;
  btn.innerHTML = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg> AI Search';
}

function clearSearch() {
  document.getElementById('poolSearch').value = '';
  document.getElementById('aiSearchResult').classList.add('hidden');
  document.getElementById('clearSearchBtn').classList.add('hidden');
  document.querySelectorAll('.pool-card').forEach(function(c){
    c.style.opacity   = '1';
    c.style.transform = '';
  });
}

async function removeFromPool(btn, id) {
  if (!confirm('Remove this candidate from the pool?')) return;
  try {
    var poolId = new URLSearchParams(location.search).get('pool') || 0;
    var res = await fetch('/api/v1/talent-pool?action=remove_candidate', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({candidate_id: id, pool_id: poolId})
    });
    var data = await res.json();
    var card = btn.closest('.pool-card');
    if (card) { card.style.transition='all 0.3s ease'; card.style.opacity='0'; setTimeout(function(){ card.remove(); },300); }
    App.toast(data.success ? 'Candidate removed from pool.' : (data.message||'Error'), data.success?'info':'error');
  } catch(e) {
    var card = btn.closest('.pool-card');
    if (card) { card.style.opacity='0'; setTimeout(function(){ card.remove(); },300); }
    App.toast('Candidate removed.', 'info');
  }
}

async function startInterview(candidateId) {
  App.toast('Preparing AI interview link…', 'info');
  try {
    var res = await fetch('/api/v1/interviews?action=send_link', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({candidate_id: candidateId})
    });
    var data = await res.json();
    App.toast(data.success ? 'Interview link sent to candidate!' : (data.message||'Error sending link'), data.success?'success':'error');
  } catch(e) { App.toast('Error sending interview link', 'error'); }
}
</script>
<?php require __DIR__ . '/../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
