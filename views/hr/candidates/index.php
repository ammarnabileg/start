<?php
/**
 * Candidates list with a collapsible advanced-filter panel, a sortable table
 * with expandable skill previews, bulk actions, and simple pagination.
 * Controller may inject: $candidates, $jobsList, $total, $page, $perPage.
 */
require_once __DIR__ . '/../../partials/helpers.php';

$db = Database::getInstance();
$tid     = Auth::user()['tenant_id'] ?? 0;
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['search'] ?? '');
$filterJob   = (int)($_GET['job'] ?? 0);
$filterStage = trim($_GET['stage'] ?? '');
$filterMin   = (int)($_GET['score_min'] ?? 0);
$filterMax   = (int)($_GET['score_max'] ?? 100);

try {
    $where = "a.tenant_id = ?";
    $params = [$tid];
    if ($search) { $where .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)"; $s="%$search%"; $params[]=$s;$params[]=$s;$params[]=$s; }
    if ($filterJob)   { $where .= " AND a.job_id = ?"; $params[] = $filterJob; }
    if ($filterStage) { $where .= " AND a.current_stage = ?"; $params[] = $filterStage; }
    if ($filterMin > 0 || $filterMax < 100) { $where .= " AND a.ai_match_score BETWEEN ? AND ?"; $params[]=$filterMin; $params[]=$filterMax; }

    $total = (int)($db->fetch("SELECT COUNT(*) as n FROM applications a JOIN candidates c ON c.id=a.candidate_id WHERE $where", $params)['n'] ?? 0);

    $candidates = $db->fetchAll(
        "SELECT c.id, CONCAT(c.first_name,' ',c.last_name) as full_name,
                c.email, c.phone, c.current_title, c.current_company, c.location,
                c.skills, c.years_experience,
                a.id as application_id, a.current_stage as stage,
                a.ai_match_score as score, a.ai_recommendation as rec,
                a.applied_at,
                j.title as job,
                ie.overall_score
         FROM applications a
         JOIN candidates c ON c.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         LEFT JOIN interview_evaluations ie ON ie.interview_id = (
             SELECT id FROM interviews WHERE application_id = a.id ORDER BY created_at DESC LIMIT 1
         )
         WHERE $where
         ORDER BY a.applied_at DESC
         LIMIT $perPage OFFSET $offset",
        $params
    ) ?: [];
    foreach ($candidates as &$c) {
        $c['skills'] = is_string($c['skills']) ? (json_decode($c['skills'], true) ?: []) : ($c['skills'] ?: []);
    }
    unset($c);
    $jobsList = $db->fetchAll("SELECT id, title FROM jobs WHERE tenant_id = ? ORDER BY title", [$tid]) ?: [];
    $jobsList = array_column($jobsList, 'title');
} catch (\Exception $e) {
    $candidates = demo_candidates();
    $total = count($candidates);
    $jobsList = ['Senior Backend Engineer','Product Designer','Frontend Engineer','Data Analyst'];
}
$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle   = 'Candidates';
$activeNav   = 'candidates';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Candidates']];

ob_start();
?>
<!-- Top bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <p class="text-gray-500"><span class="font-semibold text-gray-900"><?= (int)$total ?></span> candidates across all jobs</p>
    <div class="flex items-center gap-2">
        <button onclick="toggleFilters()" id="filterToggle" class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-full px-4 py-2 text-sm font-medium transition-colors">
            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            Filters
        </button>
        <div class="relative" data-dropdown>
            <button data-dropdown-trigger class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-full px-4 py-2 text-sm font-semibold transition-colors">
                Bulk Actions
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div data-dropdown-menu class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl border border-gray-100 shadow-lg overflow-hidden z-30 text-sm animate-fade-in">
                <button onclick="bulkAction('export')" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700 flex items-center gap-2"><svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>Export to Excel</button>
                <button onclick="bulkAction('move')" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700 flex items-center gap-2"><svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M13 6l6 6-6 6"/></svg>Move Stage</button>
                <button onclick="bulkAction('pool')" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700 flex items-center gap-2"><svg class="w-4 h-4 text-violet-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1Z"/></svg>Add to Talent Pool</button>
                <button onclick="bulkAction('delete')" class="w-full text-left px-4 py-2.5 hover:bg-rose-50 text-rose-600 flex items-center gap-2 border-t border-gray-100"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Filter panel (collapsible) -->
<div id="filterPanel" class="hidden bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-5">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Score Range</label>
            <div class="flex items-center gap-2">
                <input type="range" min="0" max="100" value="0" oninput="document.getElementById('scoreMin').textContent=this.value" class="flex-1 accent-violet-600">
                <span id="scoreMin" class="text-sm font-semibold text-gray-700 w-8 text-right">0</span>
            </div>
            <div class="text-[11px] text-gray-400 mt-1">Minimum AI score</div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Recommendation</label>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach (['strong'=>'Strong','suitable'=>'Suitable','possible'=>'Possible','not_recommended'=>'Not Rec.'] as $val=>$lbl): ?>
                    <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-gray-200 text-xs font-medium text-gray-600 cursor-pointer hover:bg-gray-50 has-[:checked]:bg-violet-50 has-[:checked]:border-violet-300 has-[:checked]:text-violet-700">
                        <input type="checkbox" class="hidden" value="<?= e($val) ?>"><?= e($lbl) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Job</label>
            <select class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <option value="">All Jobs</option>
                <?php foreach ($jobsList as $j): ?><option><?= e($j) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Stage</label>
            <select class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <option value="">All Stages</option>
                <?php foreach (['applied','ai_screening','qualified','tech_interview','manager_interview','final_review','offer','hired'] as $s): [$l]=stage_meta($s); ?><option><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Skills</label>
            <input type="text" placeholder="e.g. PHP, React…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Experience (years)</label>
            <div class="flex items-center gap-2">
                <input type="number" placeholder="Min" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <span class="text-gray-300">–</span>
                <input type="number" placeholder="Max" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Language</label>
            <select class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                <option value="">Any</option><option>English</option><option>Arabic</option><option>Both</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button onclick="applyFilters()" class="flex-1 bg-violet-600 hover:bg-violet-700 text-white rounded-lg px-4 py-2 text-sm font-semibold transition-colors">Apply</button>
            <button onclick="location.href='/candidates'" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">Reset</button>
        </div>
    </div>
</div>

<!-- Search + table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="p-3 border-b border-gray-100 flex items-center gap-2">
        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-full px-3.5 py-2 flex-1 focus-within:ring-2 focus-within:ring-violet-500/40 transition">
            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input id="candSearch" type="text" placeholder="Search by name or email…" class="bg-transparent text-sm w-full outline-none" oninput="filterCands()">
        </div>
        <a href="/candidates/compare" class="inline-flex items-center gap-1.5 text-sm font-medium text-violet-600 hover:bg-violet-50 rounded-full px-3 py-2 transition-colors whitespace-nowrap">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 3v18M15 3v18M3 9h18M3 15h18" /></svg>
            Compare
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 w-10"><input type="checkbox" onchange="toggleAll(this)" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500"></th>
                    <th class="px-4 py-3">Name &amp; Email</th>
                    <th class="px-4 py-3">Applied For</th>
                    <th class="px-4 py-3">Score</th>
                    <th class="px-4 py-3">Recommendation</th>
                    <th class="px-4 py-3">Stage</th>
                    <th class="px-4 py-3">Applied</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($candidates as $c):
                    $sc = score_color((float)$c['score']); [$recLabel,$recCls]=recommendation_badge($c['rec']); [$stLabel,$stCls]=stage_meta($c['stage']); ?>
                    <tr class="cand-row hover:bg-gray-50 transition-colors" data-search="<?= e(strtolower($c['full_name'].' '.$c['email'])) ?>">
                        <td class="px-4 py-3.5"><input type="checkbox" data-cand class="rounded border-gray-300 text-violet-600 focus:ring-violet-500"></td>
                        <td class="px-4 py-3.5">
                            <button onclick="toggleExpand(<?= (int)$c['id'] ?>)" class="flex items-center gap-3 text-left group">
                                <span class="w-9 h-9 rounded-full bg-violet-100 text-violet-700 text-xs font-semibold flex items-center justify-center shrink-0"><?= e(initials($c['full_name'])) ?></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 group-hover:text-violet-600 truncate"><?= e($c['full_name']) ?></span>
                                    <span class="block text-xs text-gray-400 truncate"><?= e($c['email']) ?></span>
                                </span>
                            </button>
                        </td>
                        <td class="px-4 py-3.5 text-sm text-gray-600"><?= e($c['job']) ?></td>
                        <td class="px-4 py-3.5"><span class="inline-flex items-center justify-center w-10 h-7 rounded-lg text-sm font-bold <?= $sc['soft'] ?>"><?= (int)$c['score'] ?></span></td>
                        <td class="px-4 py-3.5"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ring-1 <?= $recCls ?>"><?= e($recLabel) ?></span></td>
                        <td class="px-4 py-3.5"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?= $stCls ?>"><?= e($stLabel) ?></span></td>
                        <td class="px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap"><?= e(time_ago($c['applied'])) ?></td>
                        <td class="px-4 py-3.5 text-right"><a href="/candidates/<?= (int)$c['id'] ?>" class="text-sm font-medium text-violet-600 hover:text-violet-700">View</a></td>
                    </tr>
                    <tr id="expand-<?= (int)$c['id'] ?>" class="hidden bg-gray-50/60">
                        <td colspan="8" class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-4 pl-12">
                                <div class="text-xs text-gray-400 uppercase font-semibold tracking-wider">Skills</div>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach (($c['skills'] ?? []) as $sk): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-white border border-gray-200 text-xs font-medium text-gray-600"><?= e($sk) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (empty($c['skills'])): ?><span class="text-xs text-gray-400">No skills parsed yet</span><?php endif; ?>
                                </div>
                                <div class="ml-auto flex items-center gap-3 text-xs text-gray-500">
                                    <span><?= (int)($c['years'] ?? 0) ?> yrs exp</span>
                                    <span class="text-gray-300">·</span>
                                    <span><?= e($c['location'] ?? '—') ?></span>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
        <span class="text-sm text-gray-500">Showing <span class="font-medium text-gray-700">1–<?= min($perPage,$total) ?></span> of <?= (int)$total ?></span>
        <div class="flex items-center gap-1">
            <a href="?page=<?= max(1,$page-1) ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-100 transition-colors <?= $page<=1?'pointer-events-none opacity-40':'' ?>">Previous</a>
            <?php for ($p=1; $p<=min($totalPages,5); $p++): ?>
                <a href="?page=<?= $p ?>" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-sm font-medium transition-colors <?= $p===$page?'bg-violet-600 text-white':'text-gray-600 hover:bg-gray-100' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages,$page+1) ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-100 transition-colors <?= $page>=$totalPages?'pointer-events-none opacity-40':'' ?>">Next</a>
        </div>
    </div>
</div>

<script>
function toggleFilters(){ var p=document.getElementById('filterPanel'); p.classList.toggle('hidden'); document.getElementById('filterToggle').classList.toggle('bg-violet-50'); document.getElementById('filterToggle').classList.toggle('border-violet-300'); }
function toggleExpand(id){ document.getElementById('expand-'+id).classList.toggle('hidden'); }
function toggleAll(cb){ document.querySelectorAll('[data-cand]').forEach(c=>c.checked=cb.checked); }
function filterCands(){ var q=(document.getElementById('candSearch').value||'').toLowerCase(); document.querySelectorAll('.cand-row').forEach(r=>{ r.style.display = r.getAttribute('data-search').includes(q)?'':'none'; }); }
function applyFilters(){
    var url = new URL(location.href);
    var score = document.querySelector('#filterPanel input[type="range"]')?.value;
    if (score && score > 0) url.searchParams.set('score_min', score); else url.searchParams.delete('score_min');
    var job = document.querySelector('#filterPanel select:nth-of-type(1)')?.value;
    if (job) url.searchParams.set('job', job); else url.searchParams.delete('job');
    var stage = document.querySelector('#filterPanel select:nth-of-type(2)')?.value;
    if (stage) url.searchParams.set('stage', stage); else url.searchParams.delete('stage');
    url.searchParams.delete('page');
    location.href = url.toString();
}
async function bulkAction(type){
    var ids = Array.from(document.querySelectorAll('[data-cand]:checked')).map(c=>c.value);
    if(!ids.length){ App.toast('Select candidates first','warning'); return; }
    if(type==='delete'){
        App.confirm({title:'Delete '+ids.length+' candidate(s)?',message:'This cannot be undone.',confirmText:'Delete'}).then(async ok=>{
            if(!ok) return;
            var res = await fetch('/api/v1/candidates?action=bulk_delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids})});
            var d = await res.json().catch(()=>({}));
            App.toast(d.success?ids.length+' deleted':d.message||'Delete failed', d.success?'success':'error');
            if(d.success) setTimeout(()=>location.reload(),900);
        }); return;
    }
    if(type==='export'){
        var qs = ids.map(id=>'ids[]='+id).join('&');
        location.href='/api/v1/candidates?action=export&'+qs;
        return;
    }
    if(type==='move'){
        var stage = prompt('Move to stage (applied/ai_screening/qualified/tech_interview/hired):');
        if(!stage) return;
        var res = await fetch('/api/v1/candidates?action=bulk_move',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ids,stage})});
        var d = await res.json().catch(()=>({}));
        App.toast(d.success?ids.length+' moved to '+stage:d.message||'Failed','success');
        if(d.success) setTimeout(()=>location.reload(),900);
        return;
    }
    if(type==='pool'){
        var res = await fetch('/api/v1/talent-pool?action=bulk_add',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({candidate_ids:ids})});
        var d = await res.json().catch(()=>({}));
        App.toast(d.success?ids.length+' added to talent pool':d.message||'Failed', d.success?'success':'error');
    }
}
</script>
<?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
