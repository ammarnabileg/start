<?php
/**
 * Side-by-side candidate comparison (up to 4). Best value per row highlighted
 * green. Includes an "Ask AI" chat at the bottom.
 * Controller may inject: $candidates (each with score, rec, skills{name=>val},
 * disc, red_flags count).
 */
require_once __DIR__ . '/../../partials/helpers.php';

$candidates = $candidates ?? [
    ['id'=>1,'full_name'=>'James Carter','job'=>'Senior Backend Engineer','score'=>88,'rec'=>'strong','disc'=>'C/D','flags'=>2,'years'=>7,'salary'=>110000,'cur'=>'GBP',
        'skills'=>['Backend'=>92,'System Design'=>88,'Databases'=>74,'Cloud'=>85,'Communication'=>89,'Leadership'=>72]],
    ['id'=>9,'full_name'=>'Liam Murphy','job'=>'Senior Backend Engineer','score'=>85,'rec'=>'strong','disc'=>'D/I','flags'=>1,'years'=>10,'salary'=>125000,'cur'=>'GBP',
        'skills'=>['Backend'=>86,'System Design'=>90,'Databases'=>82,'Cloud'=>88,'Communication'=>80,'Leadership'=>84]],
    ['id'=>2,'full_name'=>'Aisha Khan','job'=>'Senior Backend Engineer','score'=>76,'rec'=>'suitable','disc'=>'S/C','flags'=>0,'years'=>5,'salary'=>95000,'cur'=>'GBP',
        'skills'=>['Backend'=>80,'System Design'=>72,'Databases'=>85,'Cloud'=>70,'Communication'=>78,'Leadership'=>60]],
];

// Collect the full skill set across candidates.
$skillNames = [];
foreach ($candidates as $c) foreach (array_keys($c['skills'] ?? []) as $s) $skillNames[$s] = true;
$skillNames = array_keys($skillNames);

// Compute best value per metric for green highlighting.
$best = ['score'=>max(array_map(fn($c)=>$c['score'],$candidates)), 'years'=>max(array_map(fn($c)=>$c['years'],$candidates)), 'flags'=>min(array_map(fn($c)=>$c['flags'],$candidates)), 'salary'=>min(array_map(fn($c)=>$c['salary'],$candidates))];
$bestSkill = [];
foreach ($skillNames as $s) { $bestSkill[$s] = max(array_map(fn($c)=>$c['skills'][$s] ?? 0, $candidates)); }

$cols = count($candidates);
$gridCls = ['1'=>'grid-cols-2','2'=>'grid-cols-3','3'=>'grid-cols-4','4'=>'grid-cols-5'][$cols] ?? 'grid-cols-4';

$pageTitle   = 'Compare Candidates';
$activeNav   = 'candidates';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Candidates','url'=>'/candidates'],['label'=>'Compare']];

$hl = fn(bool $on) => $on ? 'bg-emerald-50 text-emerald-700 font-semibold ring-1 ring-emerald-200' : 'text-gray-700';

ob_start();
?>
<div class="flex items-center justify-between mb-5">
    <p class="text-gray-500">Comparing <span class="font-semibold text-gray-900"><?= $cols ?></span> candidates. Best value in each row is highlighted.</p>
    <a href="/candidates" class="text-sm font-medium text-violet-600 hover:text-violet-700">← Back to candidates</a>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-x-auto">
    <table class="min-w-full border-collapse">
        <!-- Header: photos + names -->
        <thead>
            <tr>
                <th class="sticky left-0 bg-white z-10 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider px-5 py-4 w-44">Candidate</th>
                <?php foreach ($candidates as $c): ?>
                    <th class="px-5 py-4 text-center border-l border-gray-100 min-w-[180px]">
                        <div class="flex flex-col items-center gap-2">
                            <span class="w-12 h-12 rounded-2xl bg-violet-100 text-violet-700 font-bold flex items-center justify-center"><?= e(initials($c['full_name'])) ?></span>
                            <a href="/candidates/<?= (int)$c['id'] ?>" class="text-sm font-bold text-gray-900 hover:text-violet-600"><?= e($c['full_name']) ?></a>
                            <span class="text-[11px] text-gray-400 font-normal"><?= e($c['job']) ?></span>
                        </div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 text-sm">
            <!-- Score -->
            <tr>
                <td class="sticky left-0 bg-white z-10 px-5 py-3.5 font-medium text-gray-500">AI Score</td>
                <?php foreach ($candidates as $c): ?>
                    <td class="px-5 py-3.5 text-center border-l border-gray-100"><span class="inline-block px-3 py-1 rounded-lg <?= $hl($c['score']===$best['score']) ?>"><?= (int)$c['score'] ?></span></td>
                <?php endforeach; ?>
            </tr>
            <!-- Recommendation -->
            <tr>
                <td class="sticky left-0 bg-white z-10 px-5 py-3.5 font-medium text-gray-500">Recommendation</td>
                <?php foreach ($candidates as $c): [$rl,$rc]=recommendation_badge($c['rec']); ?>
                    <td class="px-5 py-3.5 text-center border-l border-gray-100"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ring-1 <?= $rc ?>"><?= e($rl) ?></span></td>
                <?php endforeach; ?>
            </tr>
            <!-- Experience -->
            <tr>
                <td class="sticky left-0 bg-white z-10 px-5 py-3.5 font-medium text-gray-500">Experience</td>
                <?php foreach ($candidates as $c): ?>
                    <td class="px-5 py-3.5 text-center border-l border-gray-100"><span class="inline-block px-3 py-1 rounded-lg <?= $hl($c['years']===$best['years']) ?>"><?= (int)$c['years'] ?> yrs</span></td>
                <?php endforeach; ?>
            </tr>
            <!-- Expected salary -->
            <tr>
                <td class="sticky left-0 bg-white z-10 px-5 py-3.5 font-medium text-gray-500">Expected Salary</td>
                <?php foreach ($candidates as $c): ?>
                    <td class="px-5 py-3.5 text-center border-l border-gray-100"><span class="inline-block px-3 py-1 rounded-lg <?= $hl($c['salary']===$best['salary']) ?>"><?= e(money($c['salary'],$c['cur'])) ?></span></td>
                <?php endforeach; ?>
            </tr>
            <!-- Skills section header -->
            <tr class="bg-gray-50/70"><td colspan="<?= $cols+1 ?>" class="sticky left-0 px-5 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Skills</td></tr>
            <?php foreach ($skillNames as $s): ?>
                <tr>
                    <td class="sticky left-0 bg-white z-10 px-5 py-3 font-medium text-gray-500"><?= e($s) ?></td>
                    <?php foreach ($candidates as $c): $v=$c['skills'][$s]??0; $isBest=$v===$bestSkill[$s] && $v>0; $skc=score_color((float)$v); ?>
                        <td class="px-5 py-3 border-l border-gray-100">
                            <div class="flex items-center gap-2 <?= $isBest?'rounded-lg ring-1 ring-emerald-200 bg-emerald-50 px-2 py-1 -mx-1':'' ?>">
                                <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full <?= $isBest?'bg-emerald-500':$skc['bg'] ?>" style="width:<?= (int)$v ?>%"></div></div>
                                <span class="text-xs font-bold <?= $isBest?'text-emerald-700':'text-gray-600' ?> w-7 text-right"><?= (int)$v ?></span>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <!-- DISC -->
            <tr>
                <td class="sticky left-0 bg-white z-10 px-5 py-3.5 font-medium text-gray-500">DISC Profile</td>
                <?php foreach ($candidates as $c): ?>
                    <td class="px-5 py-3.5 text-center border-l border-gray-100 text-gray-700 font-semibold"><?= e($c['disc']) ?></td>
                <?php endforeach; ?>
            </tr>
            <!-- Red flags -->
            <tr>
                <td class="sticky left-0 bg-white z-10 px-5 py-3.5 font-medium text-gray-500">Red Flags</td>
                <?php foreach ($candidates as $c): ?>
                    <td class="px-5 py-3.5 text-center border-l border-gray-100"><span class="inline-block px-3 py-1 rounded-lg <?= $hl($c['flags']===$best['flags']) ?>"><?= (int)$c['flags'] ?></span></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
</div>

<!-- Ask AI -->
<div class="mt-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <div class="flex items-center gap-2 mb-3">
        <span class="w-8 h-8 rounded-lg bg-violet-600 flex items-center justify-center"><svg class="w-4 h-4 text-amber-300" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg></span>
        <h3 class="font-bold text-gray-900">Ask AI about these candidates</h3>
    </div>
    <div id="compareChat" class="space-y-3 mb-3 max-h-64 overflow-y-auto"></div>
    <form class="flex gap-2" onsubmit="event.preventDefault(); compareAsk();">
        <input id="compareInput" type="text" placeholder="e.g. Who is the best fit for a lead role and why?" class="flex-1 rounded-full border border-gray-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 outline-none">
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white rounded-full px-5 py-2.5 text-sm font-semibold transition-colors">Ask</button>
    </form>
    <div class="mt-3 flex flex-wrap gap-2">
        <?php foreach (['Who is the strongest overall?','Compare their system design skills','Who is the best value for money?'] as $sugg): ?>
            <button onclick="document.getElementById('compareInput').value=this.textContent; compareAsk();" class="text-xs px-3 py-1.5 rounded-full bg-violet-50 text-violet-700 hover:bg-violet-100 transition-colors"><?= e($sugg) ?></button>
        <?php endforeach; ?>
    </div>
</div>

<script>
function compareAsk() {
    var input = document.getElementById('compareInput');
    var box = document.getElementById('compareChat');
    var q = input.value.trim(); if (!q) return;
    box.insertAdjacentHTML('beforeend', '<div class="flex justify-end"><div class="max-w-[80%] bg-gray-100 text-gray-800 rounded-2xl rounded-br-md px-4 py-2.5 text-sm">' + App.escapeHtml(q) + '</div></div>');
    input.value=''; box.scrollTop = box.scrollHeight;
    var t = document.createElement('div'); t.className='flex justify-start';
    t.innerHTML='<div class="bg-violet-50 rounded-2xl rounded-bl-md px-4 py-3"><span class="flex gap-1"><span class="w-1.5 h-1.5 rounded-full bg-violet-400 animate-bounce"></span><span class="w-1.5 h-1.5 rounded-full bg-violet-400 animate-bounce" style="animation-delay:.15s"></span><span class="w-1.5 h-1.5 rounded-full bg-violet-400 animate-bounce" style="animation-delay:.3s"></span></span></div>';
    box.appendChild(t); box.scrollTop = box.scrollHeight;
    fetch('/api/v1/ai?action=compare', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            question: q,
            candidates: <?= json_encode(array_map(fn($c)=>['id'=>$c['id']??null,'name'=>$c['full_name'],'score'=>$c['score'],'years'=>$c['years'],'rec'=>$c['rec'],'skills'=>$c['skills']],$candidates)) ?>
        })
    }).then(r=>r.json()).then(function(d){
        t.remove();
        var ans = (d && ((d.data && d.data.answer) || d.message)) || 'Unable to compare at this time.';
        box.insertAdjacentHTML('beforeend','<div class="flex justify-start"><div class="max-w-[80%] bg-violet-50 text-gray-800 rounded-2xl rounded-bl-md px-4 py-2.5 text-sm">'+App.escapeHtml(ans)+'</div></div>');
        box.scrollTop = box.scrollHeight;
    }).catch(function(){
        t.remove();
        box.insertAdjacentHTML('beforeend','<div class="flex justify-start"><div class="max-w-[80%] bg-rose-50 text-rose-700 rounded-2xl rounded-bl-md px-4 py-2.5 text-sm">Error contacting AI — please try again.</div></div>');
        box.scrollTop = box.scrollHeight;
    });
}
</script>
<?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
