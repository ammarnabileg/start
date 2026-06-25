<?php
/**
 * Jobs list page with status tabs, search, department filter, card grid /
 * table toggle, and a slide-over "New Job" panel (AI builder + manual tabs).
 * Controller may inject: $jobs, $departments, $counts, $activeStatus.
 */
require_once __DIR__ . '/../../partials/helpers.php';

$activeStatus = $activeStatus ?? ($_GET['status'] ?? 'all');

$db = Database::getInstance();
$tid = Auth::user()['tenant_id'] ?? 0;
try {
    $jobs = $db->fetchAll(
        "SELECT j.*,
                COUNT(DISTINCT a.id) as applications,
                COUNT(DISTINCT i.id) as interviews,
                SUM(CASE WHEN a.current_stage='hired' THEN 1 ELSE 0 END) as hired,
                DATE_FORMAT(j.created_at,'%d %b') as posted
         FROM jobs j
         LEFT JOIN applications a ON a.job_id = j.id
         LEFT JOIN interviews i ON i.application_id = a.id
         WHERE j.tenant_id = ?
         GROUP BY j.id
         ORDER BY j.created_at DESC",
        [$tid]
    ) ?: [];
    foreach ($jobs as &$j) {
        $j['seniority']      = $j['seniority'] ?? '';
        $j['interview_type'] = $j['interview_type'] ?? 'video';
    }
    unset($j);
} catch (\Exception $e) { $jobs = []; }

$departments = $departments ?? array_values(array_unique(array_filter(array_column($jobs, 'department'))));
if (empty($departments)) $departments = $db->fetchAll('SELECT DISTINCT department FROM jobs WHERE tenant_id=? AND department IS NOT NULL ORDER BY department', [$tid]);

$counts = [
    'all'       => count($jobs),
    'published' => count(array_filter($jobs, fn($j)=>$j['status']==='published')),
    'draft'     => count(array_filter($jobs, fn($j)=>$j['status']==='draft')),
    'archived'  => count(array_filter($jobs, fn($j)=>$j['status']==='archived')),
    'closed'    => count(array_filter($jobs, fn($j)=>$j['status']==='closed')),
];

$visibleJobs = $activeStatus === 'all' ? $jobs : array_values(array_filter($jobs, fn($j)=>$j['status']===$activeStatus));

$itTypeIcon = [
    'text'  => '<path d="M8 9h8M8 13h6"/><rect x="3" y="4" width="18" height="14" rx="2"/><path d="m8 18-2 3"/>',
    'voice' => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    'video' => '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m16 10 6-3v10l-6-3"/>',
];

$pageTitle   = 'Jobs';
$activeNav   = 'jobs';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Jobs']];

ob_start();
?>
<!-- Top bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <p class="text-gray-500">Manage your open roles and AI interview pipelines.</p>
    </div>
    <button data-modal-open="newJobPanel" class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-500 text-gray-900 rounded-full px-5 py-2.5 font-semibold text-sm shadow-sm transition-all self-start">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        New Job
    </button>
</div>

<!-- Filter bar -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3 mb-6 flex flex-col lg:flex-row gap-3 lg:items-center">
    <div class="flex items-center gap-1 overflow-x-auto">
        <?php
        $tabs = ['all'=>'All','published'=>'Published','draft'=>'Draft','archived'=>'Archived','closed'=>'Closed'];
        foreach ($tabs as $key=>$label):
            $on = $activeStatus === $key; ?>
            <a href="?status=<?= e($key) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors <?= $on ? 'bg-violet-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                <?= e($label) ?>
                <span class="px-1.5 py-0.5 rounded-full text-[11px] <?= $on ? 'bg-white/20' : 'bg-gray-100' ?>"><?= (int)($counts[$key] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="lg:ml-auto flex items-center gap-3">
        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-full px-3.5 py-2 flex-1 lg:w-56 focus-within:ring-2 focus-within:ring-violet-500/40 transition">
            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input id="jobSearch" type="text" placeholder="Search jobs…" class="bg-transparent text-sm w-full outline-none" oninput="filterJobs()">
        </div>
        <select id="deptFilter" onchange="filterJobs()" class="rounded-full border border-gray-200 px-3.5 py-2 text-sm text-gray-600 focus:ring-2 focus:ring-violet-500/40 outline-none">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
        </select>
        <div class="hidden sm:flex items-center bg-gray-100 rounded-full p-0.5">
            <button id="gridViewBtn" onclick="setJobView('grid')" class="p-2 rounded-full bg-white shadow-sm text-violet-600" aria-label="Grid view">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <button id="tableViewBtn" onclick="setJobView('table')" class="p-2 rounded-full text-gray-400" aria-label="Table view">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
        </div>
    </div>
</div>

<!-- Empty state -->
<?php if (empty($visibleJobs)): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-violet-50 flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-violet-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </div>
        <h3 class="font-bold text-gray-900">No <?= e($activeStatus === 'all' ? '' : $activeStatus) ?> jobs yet</h3>
        <p class="text-sm text-gray-500 mt-1 mb-5 max-w-sm mx-auto">Create your first job and let AI handle the first round of interviews.</p>
        <button data-modal-open="newJobPanel" class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-full px-5 py-2.5 font-semibold text-sm transition-all">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg> Create New Job
        </button>
    </div>
<?php else: ?>

<!-- GRID view -->
<div id="jobsGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php foreach ($visibleJobs as $job): [$stLabel,$stCls] = status_badge($job['status']); ?>
        <div class="job-card bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200 p-5 flex flex-col"
             data-title="<?= e(strtolower($job['title'])) ?>" data-dept="<?= e($job['department']) ?>">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <a href="/jobs/<?= (int)$job['id'] ?>" class="font-bold text-gray-900 hover:text-violet-600 transition-colors block truncate"><?= e($job['title']) ?></a>
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-violet-50 text-violet-700"><?= e($job['department']) ?></span>
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-gray-100 text-gray-600 capitalize"><?= e($job['seniority']) ?></span>
                    </div>
                </div>
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap <?= $stCls ?>"><?= e($stLabel) ?></span>
            </div>

            <div class="mt-4 space-y-1.5 text-sm text-gray-500">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <?= e($job['location']) ?> · <span class="capitalize"><?= e($job['work_type']) ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    Posted <?= e(time_ago($job['posted'])) ?>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-violet-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $itTypeIcon[$job['interview_type']] ?? $itTypeIcon['text'] ?></svg>
                    <span class="capitalize"><?= e($job['interview_type']) ?> interview</span>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-gray-50 py-2">
                    <div class="text-lg font-bold text-gray-900"><?= (int)$job['applications'] ?></div>
                    <div class="text-[11px] text-gray-400">Applicants</div>
                </div>
                <div class="rounded-xl bg-gray-50 py-2">
                    <div class="text-lg font-bold text-gray-900"><?= (int)$job['interviews'] ?></div>
                    <div class="text-[11px] text-gray-400">Interviews</div>
                </div>
                <div class="rounded-xl bg-gray-50 py-2">
                    <div class="text-lg font-bold text-emerald-600"><?= (int)$job['hired'] ?></div>
                    <div class="text-[11px] text-gray-400">Hired</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-1">
                <a href="/jobs/<?= (int)$job['id'] ?>" class="flex-1 text-center text-sm font-medium text-violet-600 hover:bg-violet-50 rounded-lg py-1.5 transition-colors">View</a>
                <a href="/jobs/<?= (int)$job['id'] ?>/edit" class="flex-1 text-center text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg py-1.5 transition-colors">Edit</a>
                <div class="relative" data-dropdown>
                    <button data-dropdown-trigger class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition-colors" aria-label="More actions">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                    </button>
                    <div data-dropdown-menu class="hidden absolute right-0 bottom-full mb-2 w-48 bg-white rounded-xl border border-gray-100 shadow-lg overflow-hidden z-20 text-sm animate-fade-in">
                        <button onclick="generateJobLink(<?= (int)$job['id'] ?>)" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-gray-700">Generate Link</button>
                        <button onclick="duplicateJob(<?= (int)$job['id'] ?>)" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-gray-700">Duplicate</button>
                        <button onclick="archiveJob(<?= (int)$job['id'] ?>)" class="w-full text-left px-4 py-2 hover:bg-rose-50 text-rose-600">Archive</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- TABLE view -->
<div id="jobsTable" class="hidden bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-5 py-3">Job</th><th class="px-5 py-3">Department</th><th class="px-5 py-3">Location</th>
                    <th class="px-5 py-3">Status</th><th class="px-5 py-3 text-center">Applicants</th><th class="px-5 py-3 text-center">Hired</th><th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($visibleJobs as $job): [$stLabel,$stCls] = status_badge($job['status']); ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5"><a href="/jobs/<?= (int)$job['id'] ?>" class="font-medium text-gray-900 hover:text-violet-600"><?= e($job['title']) ?></a><div class="text-xs text-gray-400 capitalize"><?= e($job['seniority']) ?></div></td>
                        <td class="px-5 py-3.5 text-sm text-gray-600"><?= e($job['department']) ?></td>
                        <td class="px-5 py-3.5 text-sm text-gray-600"><?= e($job['location']) ?></td>
                        <td class="px-5 py-3.5"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $stCls ?>"><?= e($stLabel) ?></span></td>
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-gray-900"><?= (int)$job['applications'] ?></td>
                        <td class="px-5 py-3.5 text-center text-sm font-semibold text-emerald-600"><?= (int)$job['hired'] ?></td>
                        <td class="px-5 py-3.5 text-right"><a href="/jobs/<?= (int)$job['id'] ?>" class="text-sm font-medium text-violet-600 hover:text-violet-700">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ===================== NEW JOB SLIDE-OVER ===================== -->
<div id="newJobPanel" data-modal class="hidden fixed inset-0 z-[90]">
    <div data-modal-overlay class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-xl bg-white shadow-xl flex flex-col animate-slide-in" data-modal-panel>
        <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Create New Job</h3>
                <p class="text-xs text-gray-400">Use AI to draft it, or fill it in manually.</p>
            </div>
            <button data-modal-close class="text-gray-400 hover:text-gray-700"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>

        <div data-tabs id="newJobTabs" class="flex-1 overflow-y-auto">
            <!-- Tab headers -->
            <div class="flex gap-1 p-2 m-4 bg-gray-100 rounded-xl">
                <button data-tab="ai" class="tab-active flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold transition-colors data-[active]:bg-white" aria-selected="true">
                    <svg class="w-4 h-4 text-violet-600" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
                    AI Job Builder
                </button>
                <button data-tab="manual" class="flex-1 px-3 py-2 rounded-lg text-sm font-semibold text-gray-600 transition-colors">Manual</button>
            </div>

            <!-- AI panel -->
            <div data-panel="ai" class="px-5 pb-5 space-y-4">
                <div class="rounded-xl bg-violet-50 border border-violet-100 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Title</label>
                            <input id="aiJobTitle" type="text" placeholder="e.g. Senior Backend Engineer" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Seniority</label>
                            <select id="aiSeniority" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <?php foreach (['intern','junior','mid','senior','lead','manager','director','executive'] as $s): ?><option value="<?= e($s) ?>" <?= $s==='senior'?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button onclick="generateJobAI()" class="mt-3 w-full inline-flex items-center justify-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-full px-4 py-2.5 font-semibold text-sm transition-all">
                        <svg class="w-4 h-4 text-amber-300" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
                        Generate with AI
                    </button>
                </div>
                <div id="aiJobResult" class="hidden"></div>
            </div>

            <!-- Manual panel -->
            <div data-panel="manual" class="hidden px-5 pb-5">
                <form class="space-y-4" data-validate onsubmit="event.preventDefault(); submitNewJob('published');">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-rose-500">*</span></label>
                        <input name="title" required type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
                            <input name="department" type="text" list="deptList" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                            <datalist id="deptList"><?php foreach ($departments as $d): ?><option value="<?= e($d) ?>"><?php endforeach; ?></datalist>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Seniority</label>
                            <select name="seniority" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <?php foreach (['intern','junior','mid','senior','lead','manager','director','executive'] as $s): ?><option value="<?= e($s) ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Location</label>
                            <input name="location" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Work Type</label>
                            <select name="work_type" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <option value="onsite">Onsite</option><option value="remote">Remote</option><option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Currency</label>
                            <select name="currency" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <option>USD</option><option>EUR</option><option>GBP</option><option>AED</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Salary Min</label>
                            <input name="salary_min" type="number" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Salary Max</label>
                            <input name="salary_max" type="number" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea name="description" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-y"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Requirements</label>
                        <textarea name="requirements" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-y"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Interview Type</label>
                            <select name="interview_type" id="interviewTypeSel" onchange="document.getElementById('avatarRow').classList.toggle('hidden', this.value!=='video')" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <option value="text">Text</option><option value="voice">Voice</option><option value="video">Video</option>
                            </select>
                        </div>
                        <div id="avatarRow" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Avatar</label>
                            <select name="avatar_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <option>Sophia (Professional)</option><option>Marcus (Friendly)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Criteria -->
                    <div class="pt-4 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-700">Evaluation Criteria</label>
                            <button type="button" onclick="addCriterion()" class="text-xs font-medium text-violet-600 hover:text-violet-700">+ Add criterion</button>
                        </div>
                        <div id="criteriaList" class="space-y-2">
                            <div class="flex gap-2 items-center">
                                <input type="text" placeholder="Criterion name" value="Technical proficiency" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <input type="number" placeholder="Weight" value="30" class="w-20 rounded-lg border border-gray-300 px-2 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
                                <span class="text-xs text-gray-400">%</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-100 p-4 flex justify-end gap-3 shrink-0">
            <button onclick="submitNewJob('draft')" class="rounded-full px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">Save as Draft</button>
            <button onclick="submitNewJob('published')" class="bg-violet-600 hover:bg-violet-700 text-white rounded-full px-5 py-2.5 font-semibold text-sm shadow-sm transition-all">Publish</button>
        </div>
    </div>
</div>

<style>
    [data-tab].tab-active { background:#fff; color:#6d28d9; box-shadow:0 1px 2px rgba(0,0,0,.06); }
</style>
<script>
function setJobView(v) {
    document.getElementById('jobsGrid')?.classList.toggle('hidden', v !== 'grid');
    document.getElementById('jobsTable')?.classList.toggle('hidden', v !== 'table');
    document.getElementById('gridViewBtn').className = v==='grid' ? 'p-2 rounded-full bg-white shadow-sm text-violet-600' : 'p-2 rounded-full text-gray-400';
    document.getElementById('tableViewBtn').className = v==='table' ? 'p-2 rounded-full bg-white shadow-sm text-violet-600' : 'p-2 rounded-full text-gray-400';
}
function filterJobs() {
    var q = (document.getElementById('jobSearch').value || '').toLowerCase();
    var dept = document.getElementById('deptFilter').value;
    document.querySelectorAll('.job-card').forEach(function (c) {
        var okQ = c.getAttribute('data-title').includes(q);
        var okD = !dept || c.getAttribute('data-dept') === dept;
        c.style.display = (okQ && okD) ? '' : 'none';
    });
}
function addCriterion() {
    var html = '<div class="flex gap-2 items-center"><input type="text" placeholder="Criterion name" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none"><input type="number" placeholder="Weight" class="w-20 rounded-lg border border-gray-300 px-2 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none"><span class="text-xs text-gray-400">%</span></div>';
    document.getElementById('criteriaList').insertAdjacentHTML('beforeend', html);
}
async function generateJobAI() {
    var title = document.getElementById('aiJobTitle').value.trim();
    var seniority = document.getElementById('aiSeniority')?.value || '';
    if (!title) { App.toast('Please enter a job title', 'error'); return; }
    var box = document.getElementById('aiJobResult');
    box.classList.remove('hidden');
    App.aiThinking(box, 'AI is drafting the job description…');
    try {
        var res = await fetch('/api/v1/ai/build-job', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({title: title, seniority: seniority})
        });
        var d = await res.json().catch(()=>({}));
        if (!d.ok) throw new Error(d.message || 'AI generation failed');
        var job = d.data || d;
        box.innerHTML =
            '<div class="rounded-xl border border-gray-200 overflow-hidden animate-fade-in">' +
              '<div class="bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 flex items-center gap-1.5"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg> AI-generated draft</div>' +
              '<div class="p-4 space-y-3 text-sm">' +
                '<div><div class="font-semibold text-gray-900 mb-1">About the role</div><p class="text-gray-600 leading-relaxed">' + App.escapeHtml(job.description || '') + '</p></div>' +
                '<div><div class="font-semibold text-gray-900 mb-1">Requirements</div><p class="text-gray-600 leading-relaxed">' + App.escapeHtml(job.requirements || '') + '</p></div>' +
              '</div>' +
              '<div class="px-4 py-3 bg-gray-50 flex gap-2 justify-end">' +
                '<button onclick="acceptAIDraft(this._job)" data-job=\'' + JSON.stringify(job).replace(/'/g,"&#39;") + '\' class="text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 rounded-full px-4 py-1.5" onmousedown="this._job=JSON.parse(this.dataset.job)">Accept &amp; Submit</button>' +
              '</div>' +
            '</div>';
    } catch(e) {
        box.innerHTML = '<p class="text-sm text-red-600 p-3">' + App.escapeHtml(e.message) + '</p>';
    }
}
async function acceptAIDraft(job) {
    if (!job) return;
    job.status = 'published';
    try {
        var res = await fetch('/api/v1/jobs?action=create', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify(job)
        });
        var d = await res.json().catch(()=>({}));
        App.toast(d.ok ? 'Job published!' : (d.message||'Failed'), d.ok?'success':'error');
        if (d.ok) { App.closeModal('newJobPanel'); setTimeout(()=>location.reload(), 900); }
    } catch(e) { App.toast('Error saving job','error'); }
}
async function generateJobLink(id) {
    try {
        var res = await fetch('/api/v1/jobs?action=generate_link', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({job_id: id})
        });
        var d = await res.json().catch(()=>({}));
        if (d.ok && d.data?.link) {
            navigator.clipboard?.writeText(d.data.link).catch(()=>{});
            App.toast('Interview link copied: ' + d.data.link, 'success');
        } else { App.toast(d.message || 'Failed to generate link', 'error'); }
    } catch(e) { App.toast('Error generating link','error'); }
}
async function duplicateJob(id) {
    try {
        var res = await fetch('/api/v1/jobs?action=duplicate', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({job_id: id})
        });
        var d = await res.json().catch(()=>({}));
        App.toast(d.ok ? 'Job duplicated' : (d.message||'Failed'), d.ok?'success':'error');
        if (d.ok) setTimeout(()=>location.reload(), 900);
    } catch(e) { App.toast('Error duplicating job','error'); }
}
async function archiveJob(id) {
    var ok = await App.confirm({title:'Archive job?',message:'This job will be archived and no longer accept applications.',confirmText:'Archive',danger:true});
    if (!ok) return;
    try {
        var res = await fetch('/api/v1/jobs?action=archive', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({job_id: id})
        });
        var d = await res.json().catch(()=>({}));
        App.toast(d.ok ? 'Job archived' : (d.message||'Failed'), d.ok?'success':'error');
        if (d.ok) setTimeout(()=>location.reload(), 900);
    } catch(e) { App.toast('Error archiving job','error'); }
}
async function submitNewJob(status) {
    var panel = document.getElementById('newJobPanel');
    var form = panel?.querySelector('form');
    if (!form) return;
    var data = Object.fromEntries(new FormData(form));
    data.status = status;
    var btn = panel.querySelector('[onclick*="submitNewJob(\''+status+'\')"]');
    if (btn) { btn.disabled = true; btn.textContent = status === 'draft' ? 'Saving…' : 'Publishing…'; }
    try {
        var res = await fetch('/api/v1/jobs?action=create', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        });
        var d = await res.json().catch(()=>({}));
        App.toast(d.ok ? (status === 'draft' ? 'Draft saved' : 'Job published!') : (d.message||'Failed'), d.ok?'success':'error');
        if (d.ok) { App.closeModal('newJobPanel'); setTimeout(()=>location.reload(), 900); }
    } catch(e) { App.toast('Error saving job','error'); }
    finally { if (btn) { btn.disabled = false; btn.textContent = status === 'draft' ? 'Save as Draft' : 'Publish'; } }
}
</script>
<?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
