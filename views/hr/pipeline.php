<?php
/**
 * Kanban Pipeline board. Full-width, horizontal-scrolling columns with
 * drag & drop (kanban-board.js + SortableJS). Controller may inject:
 *   $columns      => ['applied'=>[card,...], ...] keyed by stage enum
 *   $jobsList     => [['id'=>..,'title'=>..], ...] for the job filter
 *   $activeJobId  => int|null
 */
require_once __DIR__ . '/../partials/helpers.php';

$stages = ['applied','ai_screening','qualified','disqualified','tech_interview','manager_interview','final_review','offer','hired','rejected','withdrawn'];

if (!isset($columns)) {
    $columns = array_fill_keys($stages, []);
    foreach (demo_candidates() as $c) {
        $columns[$c['stage']][] = $c;
    }
    // Add a couple more cards so busy columns look realistic.
    $columns['applied'][] = ['id'=>11,'full_name'=>'Hana Yamamoto','job'=>'Data Analyst','score'=>0,'rec'=>null,'stage'=>'applied','applied'=>'-3 hours'];
    $columns['applied'][] = ['id'=>12,'full_name'=>'Marco Bianchi','job'=>'Frontend Engineer','score'=>0,'rec'=>null,'stage'=>'applied','applied'=>'-5 hours'];
    $columns['ai_screening'][] = ['id'=>13,'full_name'=>'Yuki Tanaka','job'=>'Senior Backend Engineer','score'=>58,'rec'=>'possible','stage'=>'ai_screening','applied'=>'-1 days'];
}

$jobsList = $jobsList ?? [
    ['id'=>1,'title'=>'Senior Backend Engineer'],
    ['id'=>2,'title'=>'Product Designer'],
    ['id'=>3,'title'=>'Frontend Engineer'],
    ['id'=>4,'title'=>'Data Analyst'],
];

$pageTitle   = 'Pipeline';
$activeNav   = 'pipeline';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Pipeline']];
$csrf = $_SESSION['_csrf'] ?? '';

ob_start();
?>
<meta name="csrf-token" content="<?= e($csrf) ?>">

<!-- Top bar -->
<div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-3 flex-1">
        <select id="pipelineJobFilter" class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-violet-500/40 outline-none shadow-sm">
            <option value="">All Jobs</option>
            <?php foreach ($jobsList as $j): ?><option value="<?= (int)$j['id'] ?>"><?= e($j['title']) ?></option><?php endforeach; ?>
        </select>
        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-full px-3.5 py-2 w-56 focus-within:ring-2 focus-within:ring-violet-500/40 transition shadow-sm">
            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input id="pipelineSearch" type="text" placeholder="Search candidates…" class="bg-transparent text-sm w-full outline-none" oninput="filterPipeline()">
        </div>
        <button onclick="rankAllAI()" class="inline-flex items-center gap-1.5 bg-violet-50 hover:bg-violet-100 text-violet-700 rounded-full px-4 py-2 text-sm font-semibold transition-colors shadow-sm">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
            AI Rank All
        </button>
    </div>
    <div class="flex items-center gap-2">
        <div class="flex items-center bg-gray-100 rounded-full p-0.5">
            <button class="px-3 py-1.5 rounded-full bg-white shadow-sm text-sm font-medium text-violet-600">Kanban</button>
            <a href="/candidates" class="px-3 py-1.5 rounded-full text-sm font-medium text-gray-500 hover:text-gray-700">List</a>
        </div>
    </div>
</div>

<!-- Bulk action bar -->
<div id="bulkBar" class="hidden mb-4 flex items-center gap-3 bg-violet-600 text-white rounded-xl px-4 py-2.5 shadow-sm">
    <span class="text-sm font-medium"><span id="bulkCount">0</span> selected</span>
    <div class="relative" data-dropdown>
        <button data-dropdown-trigger class="inline-flex items-center gap-1.5 bg-white/15 hover:bg-white/25 rounded-full px-3 py-1.5 text-sm font-medium transition-colors">
            Move to stage
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div data-dropdown-menu class="hidden absolute left-0 mt-2 w-52 bg-white rounded-xl border border-gray-100 shadow-lg overflow-hidden z-30 text-sm text-gray-700 animate-fade-in">
            <?php foreach ($stages as $st): [$label] = stage_meta($st); ?>
                <button data-bulk-stage="<?= e($st) ?>" class="w-full text-left px-4 py-2 hover:bg-gray-50"><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <button onclick="document.querySelectorAll('[data-card-select]').forEach(c=>{c.checked=false;c.dispatchEvent(new Event('change',{bubbles:true}))})" class="ml-auto text-sm text-white/80 hover:text-white">Clear</button>
</div>

<!-- Board -->
<div class="overflow-x-auto pb-4 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8" style="scroll-snap-type: x proximity;">
    <div class="flex gap-4 min-w-max">
        <?php foreach ($stages as $stage): [$label, $badgeCls, $hex] = stage_meta($stage); $cards = $columns[$stage] ?? []; ?>
            <section data-stage="<?= e($stage) ?>" data-stage-label="<?= e($label) ?>"
                     class="w-[300px] shrink-0 bg-gray-100/70 rounded-2xl flex flex-col max-h-[calc(100vh-13rem)]" style="scroll-snap-align: start;">
                <!-- Column header (sticky) -->
                <div class="sticky top-0 z-10 bg-gray-100/95 backdrop-blur rounded-t-2xl px-3 py-3 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full" style="background: <?= e($hex) ?>"></span>
                    <h3 class="text-sm font-semibold text-gray-700"><?= e($label) ?></h3>
                    <span data-count class="ml-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $badgeCls ?>"><?= count($cards) ?></span>
                    <button onclick="App.toast('Add candidate to <?= e($label) ?>','info')" class="ml-auto p-1 rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-700 transition-colors" title="Add to <?= e($label) ?>" aria-label="Add">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </div>

                <!-- Cards -->
                <div data-kanban-list class="flex-1 overflow-y-auto px-3 pb-3 space-y-2.5 min-h-[80px] rounded-lg transition-colors">
                    <?php foreach ($cards as $card): $sc = score_color((float)($card['score'] ?? 0)); [$recLabel,$recCls] = recommendation_badge($card['rec'] ?? null); ?>
                        <article data-card data-application-id="<?= (int)$card['id'] ?>" data-name="<?= e(strtolower($card['full_name'])) ?>"
                                 class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-3 cursor-grab active:cursor-grabbing">
                            <div class="flex items-start gap-2">
                                <input type="checkbox" data-card-select class="mt-1 rounded border-gray-300 text-violet-600 focus:ring-violet-500 w-3.5 h-3.5">
                                <span class="w-9 h-9 rounded-full bg-violet-100 text-violet-700 text-xs font-semibold flex items-center justify-center shrink-0"><?= e(initials($card['full_name'])) ?></span>
                                <div class="min-w-0 flex-1">
                                    <a href="/candidates/<?= (int)$card['id'] ?>" class="text-sm font-semibold text-gray-900 hover:text-violet-600 truncate block leading-tight"><?= e($card['full_name']) ?></a>
                                    <div class="text-xs text-gray-400 truncate"><?= e($card['job']) ?></div>
                                </div>
                                <span data-drag-handle class="text-gray-300 hover:text-gray-500 shrink-0 cursor-grab" title="Drag">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg>
                                </span>
                            </div>
                            <div class="mt-2.5 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <?php if (($card['score'] ?? 0) > 0): ?>
                                        <span class="inline-flex items-center justify-center px-1.5 h-5 rounded text-[11px] font-bold <?= $sc['soft'] ?>"><?= (int)$card['score'] ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($card['rec'])): ?>
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold ring-1 <?= $recCls ?>"><?= e($recLabel) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[11px] text-gray-400 whitespace-nowrap"><?= e(time_ago($card['applied'] ?? null)) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>
<script src="<?= e(asset('js/kanban-board.js')) ?>" defer></script>
<script>
function filterPipeline() {
    var q = (document.getElementById('pipelineSearch').value || '').toLowerCase();
    document.querySelectorAll('[data-card]').forEach(function (c) {
        c.style.display = c.getAttribute('data-name').includes(q) ? '' : 'none';
    });
}
function rankAllAI() {
    App.toast('AI is ranking all candidates…', 'info');
    setTimeout(function(){ App.toast('Candidates re-ranked by AI score', 'success'); }, 1600);
}
document.getElementById('pipelineJobFilter')?.addEventListener('change', function(){
    App.toast(this.value ? 'Filtered pipeline by job' : 'Showing all jobs', 'info');
});
</script>
<?php require __DIR__ . '/../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
