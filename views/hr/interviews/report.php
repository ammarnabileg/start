<?php
/**
 * Full AI Interview Evaluation Report — print-friendly professional report.
 * PHP vars: $interview, $evaluation, $candidate, $job
 */
require_once __DIR__ . '/../../partials/helpers.php';

// Unpack flat $report row from InterviewController/InterviewRepository
if (isset($report) && is_array($report)) {
    $candidate  = $candidate ?? [
        'id'       => $report['candidate_id'] ?? 0,
        'full_name'=> $report['candidate_name'] ?? '',
        'email'    => $report['candidate_email'] ?? '',
        'phone'    => $report['candidate_phone'] ?? '',
        'location' => $report['location'] ?? '',
    ];
    $job        = $job ?? [
        'id'         => $report['job_id'] ?? 0,
        'title'      => $report['job_title'] ?? '',
        'department' => $report['seniority'] ?? '',
    ];
    $interview  = $interview ?? array_filter([
        'id'               => $report['id'] ?? 0,
        'started_at'       => $report['started_at'] ?? '',
        'completed_at'     => $report['completed_at'] ?? '',
        'duration_minutes' => $report['duration_minutes'] ?? 0,
        'status'           => $report['status'] ?? '',
        'ai_model'         => $report['ai_model'] ?? '',
        'tokens_used'      => $report['ai_tokens_used'] ?? $report['tokens_used'] ?? 0,
    ], fn($v) => $v !== null);
    if (!isset($evaluation) && !empty($report['evaluation'])) {
        $ev = $report['evaluation'];
        $transcript = [];
        foreach ($report['messages'] ?? [] as $m) {
            $transcript[] = ['role' => $m['role'] ?? 'ai', 'content' => $m['content'] ?? ''];
        }
        $evaluation = [
            'overall_score'         => $ev['overall_score'] ?? 0,
            'recommendation'        => $ev['recommendation'] ?? 'possible',
            'executive_summary'     => $ev['executive_summary'] ?? '',
            'ai_verdict'            => $ev['ai_verdict'] ?? $ev['executive_summary'] ?? '',
            'strengths'             => $ev['strengths'] ?? [],
            'areas_for_development' => $ev['weaknesses'] ?? $ev['areas_for_development'] ?? [],
            'skills'                => array_map(fn($s) => ['name'=>$s['name']??'','score'=>$s['score']??0,'color'=>'bg-blue-500'], $ev['skills_analysis'] ?? []),
            'disc'                  => array_map(fn($k, $v) => ['label'=>ucfirst($k),'value'=>$v,'color'=>'bg-blue-500','soft'=>'bg-blue-50','text'=>'text-blue-700','desc'=>''], array_keys($ev['disc_profile'] ?? []), array_values($ev['disc_profile'] ?? [])),
            'big_five'              => array_map(fn($k, $v) => ['trait'=>$k,'score'=>$v,'desc'=>''], array_keys($ev['big_five'] ?? []), array_values($ev['big_five'] ?? [])),
            'red_flags'             => $ev['red_flags'] ?? [],
            'transcript'            => $transcript,
        ];
    }
}

$candidate = $candidate ?? [
    'id' => 1, 'full_name' => 'James Carter', 'email' => 'james.carter@mail.com',
    'phone' => '+44 7700 900123', 'location' => 'London, UK',
];
$job = $job ?? ['id' => 1, 'title' => 'Senior Backend Engineer', 'department' => 'Engineering'];
$interview = $interview ?? [
    'id' => 1, 'started_at' => '-1 days', 'completed_at' => '-1 days',
    'duration_minutes' => 18, 'status' => 'completed',
    'ai_model' => 'claude-3-5-sonnet-20241022', 'tokens_used' => 14280,
];
$evaluation = $evaluation ?? [
    'overall_score'     => 88,
    'recommendation'    => 'strong',
    'executive_summary' => 'James Carter demonstrates outstanding backend engineering capabilities with a deep command of distributed systems, cloud architecture, and engineering trade-offs. His communication is clear and precise throughout the interview, with well-structured reasoning on complex technical problems. He exhibits a collaborative, ownership-oriented mindset with genuine curiosity for system reliability and performance. Minor gaps exist in front-end exposure and formal team leadership experience, though these are non-blocking for this role.',
    'ai_verdict'        => 'Strongly recommended to advance to the technical interview stage. James\'s depth in distributed systems and AWS infrastructure aligns closely with the role requirements, and his communication skills suggest he would contribute well to cross-team collaboration.',
    'strengths'         => [
        'Deep expertise in distributed systems and event-driven architecture',
        'Strong communication — explains complex ideas without jargon',
        'Clear ownership mindset and proactive problem identification',
        'Solid AWS/cloud infrastructure knowledge with cost-awareness',
        'Effective advocate for engineering best practices within teams',
    ],
    'areas_for_development' => [
        'Limited formal front-end development exposure',
        'Formal team leadership experience could be strengthened',
        'Data modeling depth could be broader beyond transactional patterns',
    ],
    'skills' => [
        ['name'=>'Communication',        'score'=>89,'color'=>'bg-emerald-500'],
        ['name'=>'Problem Solving',      'score'=>90,'color'=>'bg-emerald-500'],
        ['name'=>'Technical Knowledge',  'score'=>92,'color'=>'bg-emerald-500'],
        ['name'=>'Leadership',           'score'=>72,'color'=>'bg-blue-500'],
        ['name'=>'Teamwork',             'score'=>85,'color'=>'bg-emerald-500'],
        ['name'=>'Adaptability',         'score'=>80,'color'=>'bg-emerald-500'],
        ['name'=>'Culture Fit',          'score'=>78,'color'=>'bg-blue-500'],
        ['name'=>'Motivation',           'score'=>91,'color'=>'bg-emerald-500'],
        ['name'=>'Analytical Thinking',  'score'=>88,'color'=>'bg-emerald-500'],
        ['name'=>'Creativity',           'score'=>74,'color'=>'bg-blue-500'],
        ['name'=>'Attention to Detail',  'score'=>87,'color'=>'bg-emerald-500'],
    ],
    'disc' => [
        'D'=>['label'=>'Dominant',       'value'=>70,'color'=>'bg-rose-500',   'soft'=>'bg-rose-50',   'text'=>'text-rose-700',   'desc'=>'Goal-oriented, decisive, direct communicator'],
        'I'=>['label'=>'Influential',    'value'=>45,'color'=>'bg-amber-500',  'soft'=>'bg-amber-50',  'text'=>'text-amber-700',  'desc'=>'Sociable, enthusiastic, optimistic outlook'],
        'S'=>['label'=>'Steady',         'value'=>55,'color'=>'bg-emerald-500','soft'=>'bg-emerald-50','text'=>'text-emerald-700','desc'=>'Patient, reliable, consistent and supportive'],
        'C'=>['label'=>'Conscientious',  'value'=>80,'color'=>'bg-blue-500',   'soft'=>'bg-blue-50',   'text'=>'text-blue-700',   'desc'=>'Analytical, precise, quality-focused thinker'],
    ],
    'big_five' => [
        ['trait'=>'Openness',          'score'=>82,'desc'=>'Highly creative and intellectually curious'],
        ['trait'=>'Conscientiousness', 'score'=>88,'desc'=>'Organised, dependable, goal-driven'],
        ['trait'=>'Extraversion',      'score'=>54,'desc'=>'Moderately outgoing, balanced introversion'],
        ['trait'=>'Agreeableness',     'score'=>71,'desc'=>'Cooperative and considerate of others'],
        ['trait'=>'Neuroticism',       'score'=>28,'desc'=>'Emotionally stable, calm under pressure'],
    ],
    'red_flags' => [
        ['severity'=>'low',    'title'=>'Short tenure (11 months)',          'desc'=>'One role had a below-average tenure. Candidate explained relocation as the reason.'],
        ['severity'=>'medium', 'title'=>'Salary at top of band',             'desc'=>'Expected salary of £110k sits at the upper end of the £90–120k posted range.'],
    ],
    'transcript' => [
        ['role'=>'ai',  'q'=>1,  'content'=>'Welcome, James. To start, can you describe a backend system you designed end to end and the key trade-offs you made?', 'timestamp'=>'00:00:15'],
        ['role'=>'cand','content'=>'Sure. At FinPay I designed an event-driven payments pipeline. The main trade-off was between strong consistency and throughput, so we used an outbox pattern with idempotent consumers to get reliable delivery without locking the write path.', 'timestamp'=>'00:01:03'],
        ['role'=>'ai',  'q'=>2,  'content'=>'How did you handle backpressure when a downstream consumer slowed down?', 'timestamp'=>'00:02:48'],
        ['role'=>'cand','content'=>'We added a bounded queue with a dead-letter path and autoscaled consumers on queue depth. If depth crossed a threshold we shed non-critical work and alerted on-call. This kept our SLA tight even during traffic spikes.', 'timestamp'=>'00:03:29'],
        ['role'=>'ai',  'q'=>3,  'content'=>'Tell me about a time you disagreed with a teammate on an architectural decision.', 'timestamp'=>'00:05:12'],
        ['role'=>'cand','content'=>'A colleague wanted a shared database across services. I advocated for separate data stores with an API boundary. We prototyped both approaches, measured coupling and blast radius, and aligned on service-owned data. I focused on evidence rather than opinion — that helped move the conversation forward without friction.', 'timestamp'=>'00:06:07'],
        ['role'=>'ai',  'q'=>4,  'content'=>'What does observability mean to you and how have you implemented it in production?', 'timestamp'=>'00:08:44'],
        ['role'=>'cand','content'=>'Observability means being able to ask arbitrary questions about a running system. I\'ve implemented structured logging with correlation IDs, distributed tracing via OpenTelemetry, and RED method dashboards in Grafana. The goal is shortening mean-time-to-understand, not just mean-time-to-detect.', 'timestamp'=>'00:09:22'],
        ['role'=>'ai',  'q'=>5,  'content'=>'How do you approach mentoring junior engineers?', 'timestamp'=>'00:12:10'],
        ['role'=>'cand','content'=>'I prefer pairing over lecturing. I give juniors meaningful ownership with a safety net — they drive, I navigate. I try to ask more questions than I answer so they develop reasoning skills rather than just pattern-matching to my solutions.', 'timestamp'=>'00:12:55'],
    ],
];

$sc = score_color((float)$evaluation['overall_score']);
[$recLabel, $recCls] = recommendation_badge($evaluation['recommendation']);

$r = 52; $c = 2 * M_PI * $r; $off = $c * (1 - (float)$evaluation['overall_score'] / 100);

$pageTitle   = 'Interview Report — ' . ($candidate['full_name'] ?? '');
$activeNav   = 'ai-interviews';
$breadcrumbs = [
  ['label'=>'Home','url'=>'/dashboard'],
  ['label'=>'AI Interviews','url'=>'/ai-interviews'],
  ['label'=>'Report'],
];

ob_start();
?>

<!-- Print / Screen header actions -->
<div class="flex items-center justify-between mb-6 print:hidden">
  <a href="/ai-interviews" class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6"/></svg>
    Back to Interviews
  </a>
  <div class="flex items-center gap-2">
    <a href="/candidates/<?= (int)$candidate['id'] ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">View Full Profile</a>
    <button onclick="window.print()" class="flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Print Report
    </button>
  </div>
</div>

<div class="max-w-4xl mx-auto space-y-5" id="reportContent">

  <!-- ── Report Header ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 print:shadow-none print:border-0">
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-violet-700 rounded-2xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
        </div>
        <div>
          <div class="text-xs font-semibold text-violet-600 uppercase tracking-widest mb-0.5">HireAI Platform</div>
          <h1 class="text-xl font-extrabold text-gray-900">AI Interview Evaluation Report</h1>
        </div>
      </div>
      <div class="text-right text-xs text-gray-400 hidden sm:block">
        <div>Report generated: <?= date('M j, Y · H:i') ?></div>
        <div>Interview ID: #<?= (int)$interview['id'] ?></div>
      </div>
    </div>
    <div class="mt-5 pt-5 border-t border-gray-100 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
      <div><div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Candidate</div><div class="font-bold text-gray-900"><?= e($candidate['full_name']) ?></div></div>
      <div><div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Position</div><div class="font-semibold text-gray-700"><?= e($job['title']) ?></div></div>
      <div><div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Interview Date</div><div class="text-gray-700"><?= e(time_ago($interview['completed_at'] ?? '')) ?></div></div>
      <div><div class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Duration</div><div class="text-gray-700"><?= (int)($interview['duration_minutes'] ?? 0) ?> minutes</div></div>
    </div>
  </div>

  <!-- ── Executive Summary ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
      <svg class="w-5 h-5 text-violet-600" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
      Executive Summary
    </h2>
    <div class="flex flex-col sm:flex-row items-start gap-6">
      <!-- Score ring -->
      <div class="flex flex-col items-center shrink-0">
        <div class="relative w-32 h-32">
          <svg width="128" height="128" viewBox="0 0 128 128" class="-rotate-90">
            <circle cx="64" cy="64" r="52" fill="none" stroke="#f1f5f9" stroke-width="10"/>
            <circle cx="64" cy="64" r="52" fill="none" stroke="currentColor" class="<?= $sc['ring'] ?>"
              stroke-width="10" stroke-linecap="round"
              stroke-dasharray="<?= number_format($c, 2) ?>"
              stroke-dashoffset="<?= number_format($off, 2) ?>"
              style="transition:stroke-dashoffset 1.2s ease"/>
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-3xl font-extrabold <?= $sc['text'] ?>"><?= (int)$evaluation['overall_score'] ?></span>
            <span class="text-[11px] text-gray-400">/ 100</span>
          </div>
        </div>
        <span class="mt-3 inline-flex px-3 py-1.5 rounded-full text-xs font-bold ring-1 <?= $recCls ?>"><?= e($recLabel) ?></span>
      </div>
      <!-- Verdict text -->
      <div class="flex-1">
        <p class="text-sm text-gray-600 leading-relaxed mb-4"><?= e($evaluation['executive_summary']) ?></p>
        <div class="bg-violet-50 border border-violet-100 rounded-xl p-4">
          <div class="text-xs font-bold text-violet-600 uppercase tracking-wider mb-2">AI Verdict</div>
          <p class="text-sm text-violet-900 leading-relaxed italic">"<?= e($evaluation['ai_verdict']) ?>"</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Skills Assessment ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-bold text-gray-900 mb-5 flex items-center gap-2">
      <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      Skills Assessment
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-4">
      <?php foreach ($evaluation['skills'] as $skill):
        $sc2 = score_color((float)$skill['score']); ?>
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-sm font-medium text-gray-700"><?= e($skill['name']) ?></span>
          <span class="text-sm font-bold <?= $sc2['text'] ?>"><?= (int)$skill['score'] ?></span>
        </div>
        <div class="h-3 rounded-full bg-gray-100 overflow-hidden">
          <div class="skill-bar h-full rounded-full <?= $sc2['bg'] ?>" data-width="<?= (int)$skill['score'] ?>" style="width:0%;transition:width 1s ease"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Personality Profile ── -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <!-- DISC -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        DISC Profile
      </h2>
      <div class="space-y-3">
        <?php foreach ($evaluation['disc'] as $key => $d): ?>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg <?= $d['soft'] ?> flex items-center justify-center shrink-0">
            <span class="text-sm font-extrabold <?= $d['text'] ?>"><?= e($key) ?></span>
          </div>
          <div class="flex-1">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-semibold text-gray-700"><?= e($d['label']) ?></span>
              <span class="text-xs font-bold <?= $d['text'] ?>"><?= (int)$d['value'] ?>%</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full <?= $d['color'] ?>" style="width:<?= (int)$d['value'] ?>%"></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5"><?= e($d['desc']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Big Five -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
        Big Five (OCEAN)
      </h2>
      <div class="space-y-3.5">
        <?php foreach ($evaluation['big_five'] as $b): ?>
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium text-gray-700"><?= e($b['trait']) ?></span>
            <span class="text-sm font-bold text-gray-900"><?= (int)$b['score'] ?></span>
          </div>
          <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full bg-violet-500" style="width:<?= (int)$b['score'] ?>%"></div>
          </div>
          <p class="text-[11px] text-gray-400 mt-0.5"><?= e($b['desc']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── Red Flags ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
      <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      Red Flags
    </h2>
    <?php if (empty($evaluation['red_flags'])): ?>
      <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <svg class="w-6 h-6 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm font-semibold text-emerald-700">No concerns detected in this interview.</span>
      </div>
    <?php else: ?>
      <div class="space-y-3">
        <?php
        $sevConfig = [
          'high'   => ['border-rose-200 bg-rose-50','text-rose-700','🔴'],
          'medium' => ['border-amber-200 bg-amber-50','text-amber-700','🟠'],
          'low'    => ['border-yellow-200 bg-yellow-50','text-yellow-700','🟡'],
        ];
        foreach ($evaluation['red_flags'] as $flag):
          [$boxCls, $txtCls, $dot] = $sevConfig[$flag['severity']] ?? $sevConfig['low'];
        ?>
        <div class="rounded-xl border <?= $boxCls ?> p-4">
          <div class="flex items-start gap-3">
            <span class="text-base"><?= $dot ?></span>
            <div>
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="font-semibold text-gray-900 text-sm"><?= e($flag['title']) ?></span>
                <span class="text-[11px] px-2 py-0.5 rounded-full <?= $txtCls ?> bg-white/60 font-semibold capitalize"><?= e($flag['severity']) ?> severity</span>
              </div>
              <p class="text-sm text-gray-600"><?= e($flag['desc']) ?></p>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Strengths & Areas for Development ── -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        Strengths
      </h2>
      <ul class="space-y-2.5">
        <?php foreach ($evaluation['strengths'] as $s): ?>
          <li class="flex items-start gap-2.5 text-sm text-gray-700">
            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 12 5 5L20 7"/></svg>
            <?= e($s) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Areas for Development
      </h2>
      <ul class="space-y-2.5">
        <?php foreach ($evaluation['areas_for_development'] as $a): ?>
          <li class="flex items-start gap-2.5 text-sm text-gray-700">
            <svg class="w-4 h-4 text-amber-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= e($a) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- ── Interview Transcript ── -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        Full Interview Transcript
      </h2>
      <button onclick="toggleTranscript()" id="transcriptToggleBtn"
        class="text-xs font-medium text-gray-500 hover:text-gray-800 flex items-center gap-1 print:hidden">
        <svg class="w-3.5 h-3.5" id="transcriptChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/></svg>
        Collapse
      </button>
    </div>
    <div id="transcriptBody" class="space-y-4 max-w-3xl mx-auto">
      <?php
      $qNum = 0;
      foreach ($evaluation['transcript'] as $msg):
        $isAI = $msg['role'] === 'ai';
        if ($isAI) $qNum++;
      ?>
      <div class="flex <?= $isAI ? 'justify-start' : 'justify-end' ?> gap-3">
        <?php if ($isAI): ?>
        <div class="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center shrink-0 mt-1">
          <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 1.8 4.6L18 9.4l-4.2 1.8L12 16l-1.8-4.8L6 9.4l4.2-1.8L12 3Z"/></svg>
        </div>
        <?php endif; ?>
        <div class="max-w-[80%]">
          <?php if ($isAI): ?>
            <div class="text-[10px] font-bold text-violet-500 uppercase tracking-wider mb-1">Q<?= $qNum ?> · AI Interviewer · <?= e($msg['timestamp']) ?></div>
          <?php else: ?>
            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 text-right"><?= e($candidate['full_name']) ?> · <?= e($msg['timestamp']) ?></div>
          <?php endif; ?>
          <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed <?= $isAI ? 'bg-violet-600 text-white rounded-bl-sm' : 'bg-gray-100 text-gray-800 rounded-br-sm' ?>">
            <?= e($msg['content']) ?>
          </div>
        </div>
        <?php if (!$isAI): ?>
        <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center shrink-0 mt-1 text-xs font-bold text-gray-600">
          <?= e(initials($candidate['full_name'])) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Appendix ── -->
  <div class="bg-gray-50 rounded-2xl border border-gray-200 p-5">
    <h2 class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wider">Appendix</h2>
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
      <div>
        <dt class="text-xs text-gray-400 font-semibold mb-0.5">AI Model</dt>
        <dd class="text-gray-700 font-mono text-xs"><?= e($interview['ai_model'] ?? '—') ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-400 font-semibold mb-0.5">Tokens Used</dt>
        <dd class="text-gray-700"><?= number_format((int)($interview['tokens_used'] ?? 0)) ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-400 font-semibold mb-0.5">Evaluation Timestamp</dt>
        <dd class="text-gray-700"><?= e(time_ago($interview['completed_at'] ?? '')) ?></dd>
      </div>
      <div>
        <dt class="text-xs text-gray-400 font-semibold mb-0.5">Report Version</dt>
        <dd class="text-gray-700">v2.1</dd>
      </div>
    </dl>
    <p class="text-xs text-gray-400 mt-4 border-t border-gray-200 pt-3">
      This report was generated automatically by the HireAI Platform using large language model analysis. Scores and recommendations are informational and intended to support — not replace — human judgment in hiring decisions. All data is processed in accordance with applicable data protection laws.
    </p>
  </div>

</div><!-- end reportContent -->

<style>
@media print {
  aside, header, #toastContainer, .print\:hidden, [data-dropdown], [onclick*="toggleSidebar"], .fixed { display: none !important; }
  .lg\:pl-64 { padding-left: 0 !important; }
  main { padding: 1rem !important; }
  body { background: white !important; }
  .bg-white, .bg-gray-50 { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
  .rounded-2xl { border-radius: 8px !important; }
  #transcriptBody { display: block !important; }
  #reportContent { max-width: 100% !important; }
}
</style>

<script>
// Animate skill bars
window.addEventListener('load', function() {
  setTimeout(function(){
    document.querySelectorAll('.skill-bar[data-width]').forEach(function(b){
      b.style.width = b.getAttribute('data-width') + '%';
    });
  }, 300);
});

function toggleTranscript() {
  var body   = document.getElementById('transcriptBody');
  var btn    = document.getElementById('transcriptToggleBtn');
  var chev   = document.getElementById('transcriptChevron');
  var isOpen = !body.classList.contains('hidden');
  body.classList.toggle('hidden', isOpen);
  btn.querySelector('span') && (btn.querySelector('span').textContent = isOpen ? 'Expand' : 'Collapse');
  chev.style.transform = isOpen ? 'rotate(-90deg)' : '';
  btn.childNodes[btn.childNodes.length-1].textContent = isOpen ? ' Expand' : ' Collapse';
}
</script>
<?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
