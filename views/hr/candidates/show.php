<?php
/**
 * HR Decision Center — full candidate 360 view with 9 tabs.
 * Controller may inject: $candidate, $application, $evaluation, $job,
 * $transcript, $timeline, $criteria, $humanInterviews.
 * Rich demo fallbacks below keep every tab populated standalone.
 */
require_once __DIR__ . '/../../partials/helpers.php';

$candidate = $candidate ?? [
    'id' => 1, 'first_name' => 'James', 'last_name' => 'Carter', 'full_name' => 'James Carter', 'email' => 'james.carter@mail.com', 'phone' => '+44 7700 900123',
    'location' => 'London, UK', 'years_experience' => 7, 'expected_salary' => 110000, 'salary_currency' => 'GBP',
    'languages_spoken' => ['English (Native)', 'Spanish (B1)'], 'nationality' => 'British',
    'linkedin_url' => 'https://linkedin.com/in/jamescarter',
];
$job = $job ?? ['title' => 'Senior Backend Engineer', 'department' => 'Engineering'];
$application = $application ?? ['current_stage' => 'tech_interview', 'final_score' => 88, 'ai_recommendation' => 'strong', 'applied_at' => '-2 days'];

$evaluation = $evaluation ?? [
    'overall_score' => 88,
    'recommendation' => 'strong',
    'executive_summary' => 'James is a strong senior backend engineer with deep expertise in PHP, distributed systems, and cloud infrastructure. He communicates with clarity, demonstrates sound architectural judgement, and shows genuine ownership over outcomes. His responses revealed mature trade-off thinking and a collaborative, mentorship-oriented style. Minor gaps appear around front-end familiarity and formal data-modelling, but these are not blockers for the role.',
    'strengths' => ['Distributed systems design', 'Clear technical communication', 'Strong ownership mindset', 'Mentorship & collaboration'],
    'weaknesses' => ['Limited front-end depth', 'Light on formal data modelling', 'Few examples of large-team leadership'],
    'risk_level' => 'low',
    'skills_analysis' => [
        ['name' => 'PHP / Backend', 'score' => 92, 'weight' => 20, 'confidence' => 'High', 'evidence' => 'Walked through an event-driven refactor that cut p95 latency by 40%.'],
        ['name' => 'System Design', 'score' => 88, 'weight' => 18, 'confidence' => 'High', 'evidence' => 'Designed a sharded queue with backpressure under load.'],
        ['name' => 'Databases (SQL)', 'score' => 74, 'weight' => 12, 'confidence' => 'Medium', 'evidence' => 'Solid on indexing; less confident on normalization trade-offs.'],
        ['name' => 'Cloud / AWS', 'score' => 85, 'weight' => 12, 'confidence' => 'High', 'evidence' => 'Described multi-AZ deployment and cost-aware autoscaling.'],
        ['name' => 'Testing & QA', 'score' => 80, 'weight' => 8, 'confidence' => 'High', 'evidence' => 'Advocated contract tests between services.'],
        ['name' => 'Problem Solving', 'score' => 90, 'weight' => 10, 'confidence' => 'High', 'evidence' => 'Decomposed an ambiguous scaling problem methodically.'],
        ['name' => 'Communication', 'score' => 89, 'weight' => 8, 'confidence' => 'High', 'evidence' => 'Explained complex ideas without jargon.'],
        ['name' => 'Front-end', 'score' => 52, 'weight' => 4, 'confidence' => 'Medium', 'evidence' => 'Comfortable with APIs; limited UI framework depth.'],
        ['name' => 'Leadership', 'score' => 72, 'weight' => 4, 'confidence' => 'Medium', 'evidence' => 'Mentors juniors; less formal team-lead experience.'],
        ['name' => 'DevOps / CI', 'score' => 83, 'weight' => 2, 'confidence' => 'High', 'evidence' => 'Owns pipelines and blue/green releases.'],
        ['name' => 'Domain Knowledge', 'score' => 78, 'weight' => 2, 'confidence' => 'Medium', 'evidence' => 'Relevant fintech background.'],
    ],
    'disc' => ['D' => 70, 'I' => 45, 'S' => 55, 'C' => 80],
    'big_five' => ['Openness' => 82, 'Conscientiousness' => 88, 'Extraversion' => 54, 'Agreeableness' => 71, 'Neuroticism' => 28],
    'growth_score' => 84, 'stress_score' => 30,
    'leadership_style' => 'Servant Leader', 'learning_style' => 'Hands-on / Experiential',
    'red_flags' => [
        ['severity' => 'low', 'title' => 'Short tenure at one role', 'desc' => 'Spent 11 months at a previous company.', 'evidence' => 'Mentioned a relocation as the reason for leaving.'],
        ['severity' => 'medium', 'title' => 'Salary expectation at top of band', 'desc' => 'Expected salary is at the upper edge of the posted range.', 'evidence' => 'Stated £110k expectation versus £90–120k band.'],
    ],
    'criteria_scores' => [
        ['name' => 'Technical proficiency', 'target' => 3.0, 'score' => 4.6, 'weight' => 30],
        ['name' => 'System design', 'target' => 3.0, 'score' => 4.4, 'weight' => 25],
        ['name' => 'Communication', 'target' => 3.0, 'score' => 4.5, 'weight' => 20],
        ['name' => 'Culture fit', 'target' => 3.0, 'score' => 3.8, 'weight' => 15],
        ['name' => 'Leadership', 'target' => 3.0, 'score' => 2.9, 'weight' => 10],
    ],
    'cv_match' => 86,
    'cv_skills_found' => ['PHP', 'MySQL', 'AWS', 'Docker', 'Redis', 'REST APIs', 'CI/CD'],
    'cv_skills_missing' => ['Kubernetes', 'GraphQL', 'React'],
    'cv_companies' => [
        ['name' => 'FinPay Ltd', 'role' => 'Senior Backend Engineer', 'period' => '2021 — Present'],
        ['name' => 'DataForge', 'role' => 'Backend Engineer', 'period' => '2018 — 2021'],
        ['name' => 'WebCraft', 'role' => 'Junior Developer', 'period' => '2016 — 2018'],
    ],
    'cv_gaps' => ['3-month gap in 2021 (relocation)'],
];

$transcript = $transcript ?? [
    ['role' => 'ai', 'q' => 1, 'content' => 'Welcome, James. To start, can you describe a backend system you designed end to end and the key trade-offs you made?', 'time' => '14:02'],
    ['role' => 'candidate', 'content' => 'Sure. At FinPay I designed an event-driven payments pipeline. The main trade-off was between strong consistency and throughput, so we used an outbox pattern with idempotent consumers to get reliable delivery without locking the write path.', 'time' => '14:03'],
    ['role' => 'ai', 'q' => 2, 'content' => 'How did you handle backpressure when a downstream consumer slowed down?', 'time' => '14:05'],
    ['role' => 'candidate', 'content' => 'We added a bounded queue with a dead-letter path and autoscaled consumers on queue depth. If depth crossed a threshold we shed non-critical work and alerted on-call.', 'time' => '14:06'],
    ['role' => 'ai', 'q' => 3, 'content' => 'Tell me about a time you disagreed with a teammate on an architectural decision.', 'time' => '14:08'],
    ['role' => 'candidate', 'content' => 'A colleague wanted a shared database across services. I advocated for separate stores with an API boundary. We prototyped both, measured coupling, and aligned on service-owned data. I focused on evidence rather than opinion.', 'time' => '14:09'],
];

$timeline = $timeline ?? [
    ['icon' => 'user', 'title' => 'Application received', 'desc' => 'Applied via career page', 'time' => '-2 days', 'color' => 'gray'],
    ['icon' => 'doc', 'title' => 'CV analyzed', 'desc' => 'Match score 86% computed by AI', 'time' => '-2 days', 'color' => 'blue'],
    ['icon' => 'play', 'title' => 'AI interview started', 'desc' => 'Video interview · 12 questions', 'time' => '-1 days', 'color' => 'violet'],
    ['icon' => 'check', 'title' => 'AI interview completed', 'desc' => 'Duration 18m · Score 88', 'time' => '-1 days', 'color' => 'emerald'],
    ['icon' => 'arrow', 'title' => 'Moved to Tech Interview', 'desc' => 'By Sarah Mitchell', 'time' => '-20 hours', 'color' => 'amber'],
];

$humanInterviews = $humanInterviews ?? [
    ['type' => 'technical', 'scheduled_at' => '+2 days', 'status' => 'scheduled', 'evaluator' => 'Raj Patel', 'platform' => 'Google Meet'],
];

$sc = score_color((float)$evaluation['overall_score']);
[$recLabel, $recCls] = recommendation_badge($evaluation['recommendation']);
[$stLabel, $stCls] = stage_meta($application['current_stage']);
$allStages = ['applied','ai_screening','qualified','disqualified','tech_interview','manager_interview','final_review','offer','hired','rejected','withdrawn'];

$pageTitle   = $candidate['full_name'];
$activeNav   = 'candidates';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'Candidates','url'=>'/candidates'],['label'=>$candidate['full_name']]];

// Helper: circular score ring (SVG).
$scoreRing = function (float $score, string $colorClass, int $size = 132) {
    $r = ($size / 2) - 10; $c = 2 * M_PI * $r; $off = $c * (1 - $score / 100);
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" class="-rotate-90">'
        .'<circle cx="'.($size/2).'" cy="'.($size/2).'" r="'.$r.'" fill="none" stroke="#f1f5f9" stroke-width="10"/>'
        .'<circle cx="'.($size/2).'" cy="'.($size/2).'" r="'.$r.'" fill="none" stroke="currentColor" class="'.$colorClass.'" stroke-width="10" stroke-linecap="round" stroke-dasharray="'.$c.'" stroke-dashoffset="'.$off.'" style="transition:stroke-dashoffset 1s ease"/>'
        .'</svg>';
};

ob_start();
?>
<!-- TOP BAR -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center gap-4">
        <a href="/candidates" class="hidden lg:flex w-9 h-9 rounded-full border border-gray-200 items-center justify-center text-gray-400 hover:bg-gray-50 transition-colors shrink-0" aria-label="Back">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="flex items-center gap-4 min-w-0">
            <span class="w-14 h-14 rounded-2xl bg-violet-100 text-violet-700 text-lg font-bold flex items-center justify-center shrink-0"><?= e(initials($candidate['full_name'])) ?></span>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-xl font-bold text-gray-900 truncate"><?= e($candidate['full_name']) ?></h2>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?= $stCls ?>"><?= e($stLabel) ?></span>
                </div>
                <p class="text-sm text-gray-500 truncate"><?= e($job['title']) ?> · <?= e($candidate['email']) ?></p>
            </div>
        </div>

        <div class="lg:ml-auto flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-2 bg-gray-50 rounded-full p-1">
                <select id="stageSelect" class="bg-transparent text-sm font-medium text-gray-700 px-3 py-1.5 outline-none cursor-pointer">
                    <?php foreach ($allStages as $st): [$l] = stage_meta($st); ?>
                        <option value="<?= e($st) ?>" <?= $st===$application['current_stage']?'selected':'' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
                <button onclick="updateStage()" class="bg-violet-600 hover:bg-violet-700 text-white rounded-full px-3 py-1.5 text-sm font-semibold transition-colors">Update</button>
            </div>
            <div class="relative" data-dropdown>
                <button data-dropdown-trigger class="inline-flex items-center gap-1.5 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-full px-4 py-2 text-sm font-medium transition-colors">
                    Actions
                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div data-dropdown-menu class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl border border-gray-100 shadow-lg overflow-hidden z-30 text-sm animate-fade-in">
                    <button onclick="scheduleHumanInterview()" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700">Schedule Human Interview</button>
                    <button onclick="location.href='/offers/create?application_id=<?= (int)($application['id']??0) ?>'" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700">Create Offer</button>
                    <button onclick="addToTalentPool()" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700">Add to Talent Pool</button>
                    <button onclick="window.print()" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700 border-t border-gray-100">Export PDF</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABS -->
<div data-tabs id="candTabs">
    <div class="border-b border-gray-200 mb-6 overflow-x-auto">
        <div class="flex gap-1 min-w-max">
            <?php
            $tabs = ['summary'=>'Executive Summary','skills'=>'Skills','behavior'=>'Behavior','risk'=>'Risk','cv'=>'CV Analysis','transcript'=>'Transcript','timeline'=>'Timeline','criteria'=>'Criteria','human'=>'Human Interviews'];
            $first = true;
            foreach ($tabs as $key=>$label): ?>
                <button data-tab="<?= e($key) ?>" aria-selected="<?= $first?'true':'false' ?>"
                        class="cand-tab relative px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors <?= $first?'tab-active':'text-gray-500 hover:text-gray-800' ?>">
                    <?= e($label) ?>
                </button>
            <?php $first=false; endforeach; ?>
        </div>
    </div>

    <!-- ===== TAB 1: Executive Summary ===== -->
    <div data-panel="summary" class="space-y-6 animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Score card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col items-center justify-center text-center">
                <div class="relative inline-flex items-center justify-center">
                    <?= $scoreRing((float)$evaluation['overall_score'], $sc['ring']) ?>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-extrabold <?= $sc['text'] ?>"><?= (int)$evaluation['overall_score'] ?></span>
                        <span class="text-[11px] text-gray-400 font-medium">/ 100</span>
                    </div>
                </div>
                <span class="mt-4 inline-flex px-3 py-1 rounded-full text-xs font-semibold ring-1 <?= $recCls ?>"><?= e($recLabel) ?></span>
                <p class="mt-2 text-xs text-gray-400">Overall AI Evaluation</p>
            </div>

            <!-- Strengths / Weaknesses / Risk -->
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center gap-2 text-emerald-600 mb-3"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 5 5L20 7"/></svg><span class="text-sm font-semibold">Top Strengths</span></div>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <?php foreach ($evaluation['strengths'] as $s): ?><li class="flex gap-2"><span class="text-emerald-400 mt-1">•</span><?= e($s) ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center gap-2 text-rose-500 mb-3"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01"/><circle cx="12" cy="12" r="9"/></svg><span class="text-sm font-semibold">Key Weaknesses</span></div>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <?php foreach ($evaluation['weaknesses'] as $w): ?><li class="flex gap-2"><span class="text-rose-300 mt-1">•</span><?= e($w) ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center gap-2 text-amber-500 mb-3"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 2 21h20L12 3Z"/><path d="M12 10v4m0 3h.01"/></svg><span class="text-sm font-semibold">Risk Level</span></div>
                    <?php $rl = $evaluation['risk_level']; $rlCls = ['low'=>'text-emerald-600','medium'=>'text-amber-600','high'=>'text-rose-600'][$rl] ?? 'text-gray-600'; ?>
                    <div class="text-2xl font-bold capitalize <?= $rlCls ?>"><?= e($rl) ?></div>
                    <p class="text-xs text-gray-400 mt-1"><?= count($evaluation['red_flags']) ?> flag(s) detected</p>
                </div>
            </div>
        </div>

        <!-- Narrative -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-violet-600" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
                <h3 class="font-bold text-gray-900">AI Narrative Summary</h3>
            </div>
            <p class="text-sm text-gray-600 leading-relaxed"><?= e($evaluation['executive_summary']) ?></p>
        </div>

        <!-- Quick stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php
            $quick = [
                ['Years experience', $candidate['years_experience'].' yrs'],
                ['Expected salary', money($candidate['expected_salary'], $candidate['salary_currency'])],
                ['Languages', implode(', ', array_map(fn($l)=>explode(' ',$l)[0], $candidate['languages_spoken']))],
                ['Location', $candidate['location']],
            ];
            foreach ($quick as [$l,$v]): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-1"><?= e($l) ?></div>
                    <div class="text-sm font-semibold text-gray-900"><?= e($v) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== TAB 2: Skills ===== -->
    <div data-panel="skills" class="hidden animate-fade-in">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-gray-900 mb-5">Skills Analysis</h3>
            <div class="space-y-5">
                <?php foreach ($evaluation['skills_analysis'] as $sk): $skc = score_color((float)$sk['score']); ?>
                    <div>
                        <div class="flex items-center justify-between mb-1.5 gap-3 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900"><?= e($sk['name']) ?></span>
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">weight <?= (int)$sk['weight'] ?>%</span>
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-violet-50 text-violet-600"><?= e($sk['confidence']) ?> confidence</span>
                            </div>
                            <span class="text-sm font-bold <?= $skc['text'] ?>"><?= (int)$sk['score'] ?></span>
                        </div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="skill-bar h-full rounded-full <?= $skc['bg'] ?>" data-width="<?= (int)$sk['score'] ?>" style="width:0%; transition:width 1s ease"></div>
                        </div>
                        <p class="mt-1.5 text-xs text-gray-500 italic">“<?= e($sk['evidence']) ?>”</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===== TAB 3: Behavior ===== -->
    <div data-panel="behavior" class="hidden space-y-6 animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- DISC -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-gray-900 mb-4">DISC Profile</h3>
                <div class="grid grid-cols-2 gap-3">
                    <?php
                    $disc = [
                        'D'=>['Dominance','bg-rose-500','bg-rose-50','text-rose-600'],
                        'I'=>['Influence','bg-amber-500','bg-amber-50','text-amber-600'],
                        'S'=>['Steadiness','bg-emerald-500','bg-emerald-50','text-emerald-600'],
                        'C'=>['Conscientiousness','bg-blue-500','bg-blue-50','text-blue-600'],
                    ];
                    foreach ($disc as $k=>[$label,$bar,$soft,$txt]): $val=(int)$evaluation['disc'][$k]; ?>
                        <div class="<?= $soft ?> rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-2xl font-extrabold <?= $txt ?>"><?= e($k) ?></span>
                                <span class="text-sm font-bold text-gray-700"><?= $val ?>%</span>
                            </div>
                            <div class="text-[11px] text-gray-500 mb-2"><?= e($label) ?></div>
                            <div class="h-1.5 rounded-full bg-white/70 overflow-hidden"><div class="h-full rounded-full <?= $bar ?>" style="width:<?= $val ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Big Five radar (CSS) -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-gray-900 mb-4">Big Five (OCEAN)</h3>
                <div class="space-y-3">
                    <?php foreach ($evaluation['big_five'] as $trait=>$val): ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span class="text-gray-600"><?= e($trait) ?></span><span class="font-semibold text-gray-900"><?= (int)$val ?></span></div>
                            <div class="h-2 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full bg-violet-500" style="width:<?= (int)$val ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Growth + Stress circles -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-gray-900 mb-4">Growth &amp; Stress</h3>
                <div class="flex items-center justify-around">
                    <?php
                    $circles = [['Growth Potential',(int)$evaluation['growth_score'],'text-emerald-500'],['Stress Tolerance',100-(int)$evaluation['stress_score'],'text-blue-500']];
                    foreach ($circles as [$label,$val,$ring]): ?>
                        <div class="text-center">
                            <div class="relative inline-flex items-center justify-center"><?= $scoreRing((float)$val,$ring,104) ?>
                                <span class="absolute text-xl font-bold text-gray-900"><?= $val ?></span>
                            </div>
                            <div class="text-xs text-gray-500 mt-2 font-medium"><?= e($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Styles -->
            <div class="grid grid-cols-1 gap-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-1">Leadership Style</div>
                    <div class="text-lg font-bold text-gray-900"><?= e($evaluation['leadership_style']) ?></div>
                    <p class="text-xs text-gray-500 mt-1">Empowers the team, leads by example, prioritizes others' growth.</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-1">Learning Style</div>
                    <div class="text-lg font-bold text-gray-900"><?= e($evaluation['learning_style']) ?></div>
                    <p class="text-xs text-gray-500 mt-1">Learns best by building and iterating on real problems.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TAB 4: Risk ===== -->
    <div data-panel="risk" class="hidden animate-fade-in">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-gray-900 mb-1">Risk Analysis</h3>
            <p class="text-xs text-gray-400 mb-5">Potential red flags surfaced from the interview and CV.</p>
            <div class="space-y-3">
                <?php
                $sevMeta = ['high'=>['🔴','border-rose-200 bg-rose-50','text-rose-700'],'medium'=>['🟠','border-amber-200 bg-amber-50','text-amber-700'],'low'=>['🟡','border-yellow-200 bg-yellow-50','text-yellow-700']];
                foreach ($evaluation['red_flags'] as $flag): [$dot,$box,$txt]=$sevMeta[$flag['severity']]??$sevMeta['low']; ?>
                    <div class="rounded-xl border <?= $box ?> p-4">
                        <div class="flex items-start gap-3">
                            <span class="text-base leading-none mt-0.5"><?= $dot ?></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900"><?= e($flag['title']) ?></span>
                                    <span class="text-[11px] px-1.5 py-0.5 rounded-full <?= $txt ?> bg-white/70 capitalize font-semibold"><?= e($flag['severity']) ?> severity</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-1"><?= e($flag['desc']) ?></p>
                                <p class="text-xs text-gray-500 mt-1.5 italic">Evidence: “<?= e($flag['evidence']) ?>”</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($evaluation['red_flags'])): ?>
                    <div class="text-center py-10 text-sm text-gray-400">No red flags detected. ✅</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== TAB 5: CV Analysis ===== -->
    <div data-panel="cv" class="hidden space-y-6 animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex flex-col items-center justify-center text-center">
                <?php $cvc = score_color((float)$evaluation['cv_match']); ?>
                <div class="relative inline-flex items-center justify-center"><?= $scoreRing((float)$evaluation['cv_match'],$cvc['ring'],120) ?>
                    <span class="absolute text-2xl font-extrabold <?= $cvc['text'] ?>"><?= (int)$evaluation['cv_match'] ?>%</span>
                </div>
                <p class="text-sm text-gray-500 mt-3 font-medium">CV–Job Match</p>
                <a href="#" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-violet-600 hover:text-violet-700"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>Download CV (PDF)</a>
            </div>
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Skills Found</h4>
                    <div class="space-y-2">
                        <?php foreach ($evaluation['cv_skills_found'] as $s): ?>
                            <div class="flex items-center gap-2 text-sm text-gray-700"><svg class="w-4 h-4 text-emerald-500 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 5 5L20 7"/></svg><?= e($s) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Missing / Desired</h4>
                    <div class="space-y-2">
                        <?php foreach ($evaluation['cv_skills_missing'] as $s): ?>
                            <div class="flex items-center gap-2 text-sm text-gray-500"><svg class="w-4 h-4 text-rose-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg><?= e($s) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h4 class="text-sm font-semibold text-gray-900 mb-4">Career History</h4>
            <ol class="relative border-l-2 border-gray-100 ml-2 space-y-5">
                <?php foreach ($evaluation['cv_companies'] as $co): ?>
                    <li class="ml-5">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full bg-violet-500 ring-4 ring-white"></span>
                        <div class="text-sm font-semibold text-gray-900"><?= e($co['role']) ?></div>
                        <div class="text-sm text-gray-500"><?= e($co['name']) ?> · <?= e($co['period']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($evaluation['cv_gaps'])): ?>
                <div class="mt-4 rounded-xl bg-amber-50 border border-amber-100 p-3">
                    <div class="text-xs font-semibold text-amber-700 mb-1">Career Gaps</div>
                    <?php foreach ($evaluation['cv_gaps'] as $g): ?><div class="text-sm text-amber-700">• <?= e($g) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== TAB 6: Transcript ===== -->
    <div data-panel="transcript" class="hidden animate-fade-in">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-gray-900 mb-5">Interview Transcript</h3>
            <div class="space-y-4 max-w-3xl mx-auto">
                <?php foreach ($transcript as $m): $isAI = $m['role']==='ai'; ?>
                    <div class="flex <?= $isAI?'justify-start':'justify-end' ?>">
                        <div class="max-w-[80%] rounded-2xl px-4 py-3 text-sm leading-relaxed <?= $isAI?'bg-violet-600 text-white rounded-bl-md':'bg-gray-100 text-gray-800 rounded-br-md' ?>">
                            <?php if ($isAI && !empty($m['q'])): ?><div class="text-[11px] font-semibold text-white/70 mb-1">Question <?= (int)$m['q'] ?></div><?php endif; ?>
                            <div><?= e($m['content']) ?></div>
                            <div class="text-[10px] mt-1 <?= $isAI?'text-white/60':'text-gray-400' ?>"><?= e($m['time']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===== TAB 7: Timeline ===== -->
    <div data-panel="timeline" class="hidden animate-fade-in">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-gray-900 mb-5">Activity Timeline</h3>
            <ol class="relative border-l-2 border-gray-100 ml-2 space-y-6">
                <?php
                $tlIcons = ['user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>','doc'=>'<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/>','play'=>'<polygon points="6 4 20 12 6 20"/>','check'=>'<path d="m5 12 5 5L20 7"/>','arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>'];
                $tlColors = ['gray'=>'bg-gray-100 text-gray-500','blue'=>'bg-blue-100 text-blue-600','violet'=>'bg-violet-100 text-violet-600','emerald'=>'bg-emerald-100 text-emerald-600','amber'=>'bg-amber-100 text-amber-600'];
                foreach ($timeline as $ev): $cls=$tlColors[$ev['color']]??'bg-gray-100 text-gray-500'; ?>
                    <li class="ml-6">
                        <span class="absolute -left-[15px] flex items-center justify-center w-7 h-7 rounded-full ring-4 ring-white <?= $cls ?>">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $tlIcons[$ev['icon']]??$tlIcons['check'] ?></svg>
                        </span>
                        <div class="text-sm font-semibold text-gray-900"><?= e($ev['title']) ?></div>
                        <div class="text-sm text-gray-500"><?= e($ev['desc']) ?></div>
                        <div class="text-xs text-gray-400 mt-0.5"><?= e(time_ago($ev['time'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>

    <!-- ===== TAB 8: Criteria ===== -->
    <div data-panel="criteria" class="hidden animate-fade-in">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-6 pb-3"><h3 class="font-bold text-gray-900">Criteria Scores</h3><p class="text-xs text-gray-400 mt-0.5">Measured against the job's target thresholds.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50"><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"><th class="px-6 py-3">Criterion</th><th class="px-6 py-3">Weight</th><th class="px-6 py-3 w-1/3">Score</th><th class="px-6 py-3">Target</th><th class="px-6 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($evaluation['criteria_scores'] as $cr): $met = $cr['score']>=$cr['target']; $pct=round($cr['score']/5*100); ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= e($cr['name']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?= (int)$cr['weight'] ?>%</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full <?= $met?'bg-emerald-500':'bg-amber-400' ?>" style="width:<?= $pct ?>%"></div></div>
                                        <span class="text-sm font-bold text-gray-700 w-8"><?= number_format($cr['score'],1) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500"><?= number_format($cr['target'],1) ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($met): ?><span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 5 5L20 7"/></svg>Met</span>
                                    <?php else: ?><span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-500"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>Not met</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== TAB 9: Human Interviews ===== -->
    <div data-panel="human" class="hidden animate-fade-in">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-bold text-gray-900">Human Interviews</h3>
                <button onclick="App.toast('Opening scheduler…','info')" class="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white rounded-full px-4 py-2 text-sm font-semibold transition-colors"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Schedule</button>
            </div>
            <div class="space-y-3">
                <?php foreach ($humanInterviews as $hi): ?>
                    <div class="rounded-xl border border-gray-100 p-4 flex flex-col sm:flex-row sm:items-center gap-3 hover:bg-gray-50 transition-colors">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/></svg></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-gray-900 capitalize"><?= e($hi['type']) ?> Interview</div>
                            <div class="text-xs text-gray-500"><?= e(time_ago($hi['scheduled_at'])) ?> · with <?= e($hi['evaluator']) ?> · <?= e($hi['platform']) ?></div>
                        </div>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 capitalize"><?= e($hi['status']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($humanInterviews)): ?>
                    <div class="text-center py-10 text-sm text-gray-400">No human interviews scheduled yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .cand-tab.tab-active { color:#6d28d9; }
    .cand-tab.tab-active::after { content:''; position:absolute; left:0; right:0; bottom:-1px; height:2px; background:#6d28d9; border-radius:2px; }
    @media print { aside, header, [data-dropdown], .cand-tab, #toastContainer { display:none !important; } main { padding:0 !important; } [data-panel]{ display:block !important; } .lg\:pl-64{ padding-left:0 !important; } }
</style>
<script>
var _appId = <?= (int)($application['id'] ?? 0) ?>;
var _candidateId = <?= (int)($candidate['id'] ?? 0) ?>;

async function updateStage() {
    var stage = document.getElementById('stageSelect').value;
    var btn = event.target;
    btn.disabled = true;
    try {
        var res = await fetch('/api/v1/pipeline?action=move', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({application_id: _appId, stage: stage})
        });
        var d = await res.json().catch(()=>({}));
        App.toast(d.success ? 'Stage updated to '+document.getElementById('stageSelect').selectedOptions[0].text : (d.message||'Update failed'), d.success?'success':'error');
    } catch(e) { App.toast('Error updating stage','error'); }
    finally { btn.disabled = false; }
}

async function scheduleHumanInterview() {
    var date = prompt('Interview date/time (YYYY-MM-DD HH:MM):');
    if (!date) return;
    try {
        var res = await fetch('/api/v1/human-interviews?action=schedule', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({application_id: _appId, candidate_id: _candidateId, scheduled_at: date})
        });
        var d = await res.json().catch(()=>({}));
        App.toast(d.success ? 'Interview scheduled' : (d.message||'Failed'), d.success?'success':'error');
    } catch(e) { App.toast('Error scheduling interview','error'); }
}

async function addToTalentPool() {
    try {
        var res = await fetch('/api/v1/talent-pool?action=add_candidate', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({candidate_id: _candidateId})
        });
        var d = await res.json().catch(()=>({}));
        App.toast(d.success ? 'Added to talent pool' : (d.message||'Failed'), d.success?'success':'error');
    } catch(e) { App.toast('Error adding to pool','error'); }
}

// Animate skill bars when the Skills tab opens; animate on first load too.
(function () {
    function fillBars(scope) {
        (scope || document).querySelectorAll('.skill-bar[data-width]').forEach(function (b) {
            requestAnimationFrame(function(){ b.style.width = b.getAttribute('data-width') + '%'; });
        });
    }
    var root = document.getElementById('candTabs');
    if (root) root.addEventListener('tab:change', function (e) { if (e.detail.name === 'skills') fillBars(); });
    window.addEventListener('load', function(){ setTimeout(fillBars, 200); });
})();
</script>
<?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
