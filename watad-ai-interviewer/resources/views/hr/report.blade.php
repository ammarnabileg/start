@extends('layouts.app')
@section('title', 'Decision Center · '.$interview->candidate?->full_name)
@section('heading', 'HR Decision Center')
@section('content')
@php
    $report = $interview->report;
    $b      = $interview->behavioralAnalysis;
    $cv     = $interview->candidate?->latestCvAnalysis;
    $bySeq  = $interview->messages->keyBy('seq');
    $rec    = $interview->recommendation;
    $scores = $interview->competencyScores->sortByDesc('score');
    $hasAnalysis = $interview->competencyScores->isNotEmpty();
    $overall = $interview->overall_score;

    $approved = $rec && in_array($rec->value, ['strong_hire', 'hire']);
    $criticalFlags = $interview->redFlags->where('severity', 'high')->count();

    // Tailwind-safe literal classes (no runtime-built class names).
    $barClass  = fn ($s) => $s >= 68 ? 'bg-emerald-500' : ($s >= 50 ? 'bg-amber-500' : 'bg-red-500');
    $textClass = fn ($s) => $s >= 68 ? 'text-emerald-600' : ($s >= 50 ? 'text-amber-600' : 'text-red-600');
    $ringClass = $overall === null ? 'text-slate-300'
        : ($overall >= 68 ? 'text-emerald-500' : ($overall >= 50 ? 'text-amber-500' : 'text-red-500'));

    $sevMeta = [
        'high'   => ['label' => 'High',   'box' => 'border-red-500 bg-red-50',     'chip' => 'bg-red-100 text-red-700'],
        'medium' => ['label' => 'Medium', 'box' => 'border-amber-500 bg-amber-50', 'chip' => 'bg-amber-100 text-amber-700'],
        'low'    => ['label' => 'Low',    'box' => 'border-slate-300 bg-slate-50', 'chip' => 'bg-slate-100 text-slate-600'],
    ];

    $tabs = [
        'overview'     => 'Executive Summary',
        'competencies' => 'Competencies',
        'behavioral'   => 'Behavioral',
        'risk'         => 'Risk Analysis',
        'resume'       => 'Resume',
        'evidence'     => 'Transcript',
        'timeline'     => 'Timeline',
    ];
@endphp

<div x-data="{ tab: 'overview' }" class="space-y-6">

    {{-- ============ Header ============ --}}
    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-light text-xl font-bold text-brand">
                    {{ mb_substr($interview->candidate?->full_name ?? '?', 0, 1) }}
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">{{ $interview->candidate?->full_name ?? '—' }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ $interview->jobPosition?->title }}
                        · <span class="uppercase">{{ $interview->mode->value }}</span>
                        · {{ strtoupper($interview->language ?? 'en') }}
                        · {{ $interview->duration_seconds ? gmdate('i:s', $interview->duration_seconds) : '—' }}
                        · {{ $interview->question_count ?? 0 }} Q
                    </p>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ $interview->candidate?->email }}
                        @if($interview->candidate?->phone) · {{ $interview->candidate->phone }} @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-5">
                {{-- Score ring --}}
                <div class="relative grid h-20 w-20 place-items-center">
                    <svg class="absolute inset-0 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="16" fill="none" stroke="currentColor" class="text-slate-100" stroke-width="3.5"/>
                        <circle cx="18" cy="18" r="16" fill="none" stroke="currentColor" class="{{ $ringClass }}"
                                stroke-width="3.5" stroke-linecap="round"
                                stroke-dasharray="{{ round(($overall ?? 0) / 100 * 100.5, 1) }} 100.5"/>
                    </svg>
                    <div class="text-center">
                        <div class="text-xl font-bold text-slate-800">{{ $overall !== null ? round($overall) : '—' }}</div>
                        <div class="text-[9px] uppercase tracking-wide text-slate-400">/100</div>
                    </div>
                </div>
                <div class="text-end">
                    @include('components.reco-badge', ['recommendation' => $rec])
                    <div class="mt-2">
                        @if(! $hasAnalysis)
                            <span class="badge-soft bg-slate-100 text-slate-500">Analysis pending</span>
                        @elseif($criticalFlags)
                            <span class="badge-soft bg-red-100 text-red-700">⚑ HR attention required</span>
                        @elseif($approved)
                            <span class="badge-soft bg-emerald-100 text-emerald-700">✓ Auto-advanced</span>
                        @else
                            <span class="badge-soft bg-amber-100 text-amber-700">⏳ Pending review</span>
                        @endif
                    </div>
                    @if($report?->pdf_path)
                        <a href="{{ route('hr.interviews.report.pdf', $interview->public_id) }}"
                           class="mt-2 inline-block text-xs font-medium text-brand hover:underline">⬇ Download PDF</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ Decision bar (Final Decision Center) ============ --}}
        @if($application)
            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                <span class="me-auto text-xs text-slate-500">
                    Current stage: <span class="font-medium text-slate-700">{{ $application->status->label() }}</span>
                </span>
                @can('decisions.advance')
                    <form method="POST" action="{{ route('hr.applications.decision', $application) }}">
                        @csrf <input type="hidden" name="decision" value="advance">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            ✓ Advance to next stage
                        </button>
                    </form>
                @endcan
                @can('decisions.advance')
                    <form method="POST" action="{{ route('hr.applications.decision', $application) }}">
                        @csrf <input type="hidden" name="decision" value="hold">
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            ⏳ Hold for review
                        </button>
                    </form>
                @endcan
                @can('decisions.reject')
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                                class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            ✕ Reject
                        </button>
                        <form x-show="open" x-cloak @click.outside="open = false"
                              method="POST" action="{{ route('hr.applications.decision', $application) }}"
                              class="absolute end-0 z-10 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                            @csrf <input type="hidden" name="decision" value="reject">
                            <label class="label">Reason</label>
                            <textarea name="reason" rows="2" required class="input" placeholder="Why is this candidate being rejected?"></textarea>
                            <button class="btn-primary mt-2 w-full !bg-red-600 hover:!bg-red-700">Confirm rejection</button>
                        </form>
                    </div>
                @endcan
                <a href="{{ route('hr.candidates.show', $interview->candidate_id) }}"
                   class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:text-slate-700 hover:underline">Full profile →</a>
            </div>
        @endif
    </div>

    {{-- ============ Tabs ============ --}}
    <div class="flex flex-wrap gap-1 border-b border-slate-200 text-sm">
        @foreach($tabs as $key => $label)
            <button @click="tab='{{ $key }}'"
                    :class="tab==='{{ $key }}' ? 'border-brand text-brand' : 'border-transparent text-slate-500 hover:text-slate-700'"
                    class="-mb-px border-b-2 px-3.5 py-2 font-medium">{{ $label }}
                @if($key==='risk' && $interview->redFlags->count())
                    <span class="badge-soft ms-1 bg-red-100 text-red-700">{{ $interview->redFlags->count() }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ============ 1. Executive Summary ============ --}}
    <div x-show="tab==='overview'" class="space-y-6">
        @unless($hasAnalysis)
            <div class="card p-8 text-center">
                <p class="text-sm text-slate-500">Analysis is still being generated. It runs automatically a moment after the interview ends.</p>
                <p class="mt-1 text-xs text-slate-400">Status: {{ ucfirst($interview->status->value) }}</p>
            </div>
        @else
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Recommendation card --}}
            <div class="card p-5">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Hiring recommendation</h3>
                <div class="mb-2">@include('components.reco-badge', ['recommendation' => $rec])</div>
                <p class="text-sm text-slate-600">{{ $report?->hiring_recommendation ?? '—' }}</p>
            </div>
            {{-- Quick stats --}}
            <div class="card grid grid-cols-2 gap-4 p-5">
                <div><div class="text-2xl font-bold {{ $textClass($overall ?? 0) }}">{{ $overall !== null ? round($overall) : '—' }}</div><div class="text-xs text-slate-400">Overall score</div></div>
                <div><div class="text-2xl font-bold text-slate-700">{{ $cv?->jd_match_score !== null ? round($cv->jd_match_score) : '—' }}</div><div class="text-xs text-slate-400">JD match</div></div>
                <div><div class="text-2xl font-bold text-slate-700">{{ $interview->question_count ?? 0 }}</div><div class="text-xs text-slate-400">Questions</div></div>
                <div><div class="text-2xl font-bold {{ $criticalFlags ? 'text-red-600' : 'text-slate-700' }}">{{ $interview->redFlags->count() }}</div><div class="text-xs text-slate-400">Red flags</div></div>
            </div>
            {{-- Top / bottom competency --}}
            <div class="card p-5">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Signal</h3>
                @if($scores->isNotEmpty())
                    @php($top = $scores->first())@php($low = $scores->last())
                    <div class="mb-2 text-sm"><span class="text-emerald-600">▲ Strongest:</span> {{ \App\Enums\Competency::tryFrom($top->competency)?->label() ?? $top->competency }} ({{ round($top->score) }})</div>
                    <div class="text-sm"><span class="text-red-500">▼ Weakest:</span> {{ \App\Enums\Competency::tryFrom($low->competency)?->label() ?? $low->competency }} ({{ round($low->score) }})</div>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card p-5">
                <h3 class="mb-2 font-semibold text-slate-800">Interview summary</h3>
                <p class="text-sm leading-relaxed text-slate-600">{{ $report?->interview_summary ?? $report?->resume_summary ?? '—' }}</p>
            </div>
            <div class="grid gap-6">
                <div class="card p-5">
                    <h3 class="mb-2 font-semibold text-emerald-700">Strengths</h3>
                    <ul class="space-y-1 text-sm text-slate-600">
                        @forelse($report?->strengths ?? [] as $s)<li class="flex gap-2"><span class="text-emerald-500">✓</span>{{ $s }}</li>@empty<li class="text-slate-400">—</li>@endforelse
                    </ul>
                </div>
                <div class="card p-5">
                    <h3 class="mb-2 font-semibold text-amber-700">Areas of concern</h3>
                    <ul class="space-y-1 text-sm text-slate-600">
                        @forelse($report?->weaknesses ?? [] as $w)<li class="flex gap-2"><span class="text-amber-500">!</span>{{ $w }}</li>@empty<li class="text-slate-400">—</li>@endforelse
                    </ul>
                </div>
            </div>
        </div>
        @endunless
    </div>

    {{-- ============ 2. Competency Breakdown ============ --}}
    <div x-show="tab==='competencies'" x-cloak class="space-y-4">
        @forelse($scores as $score)
            @php
                $seqs = is_array($score->evidence) ? $score->evidence : [];
                $conf = $score->confidence !== null ? round($score->confidence * 100) : null;
            @endphp
            <div class="card p-5">
                <div class="mb-1 flex items-center justify-between">
                    <h4 class="font-semibold text-slate-800">{{ \App\Enums\Competency::tryFrom($score->competency)?->label() ?? $score->competency }}</h4>
                    <div class="flex items-center gap-3 text-sm">
                        @if($conf !== null)<span class="text-xs text-slate-400">confidence {{ $conf }}%</span>@endif
                        <span class="text-lg font-bold {{ $textClass($score->score) }}">{{ round($score->score) }}</span>
                    </div>
                </div>
                <div class="mb-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $barClass($score->score) }}" style="width: {{ (int) $score->score }}%"></div>
                </div>
                @if($score->rationale)
                    <p class="text-sm text-slate-600">{{ $score->rationale }}</p>
                @endif
                @if(count($seqs))
                    <div class="mt-3 space-y-1.5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Evidence</p>
                        @foreach($seqs as $seq)
                            @if($q = $bySeq[$seq] ?? null)
                                <blockquote class="border-s-2 border-slate-200 ps-3 text-xs text-slate-500">
                                    <span class="font-medium text-slate-400">[{{ $q->role === 'agent' ? 'Q' : 'A' }}{{ $seq }}]</span>
                                    {{ \Illuminate\Support\Str::limit($q->content, 220) }}
                                </blockquote>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-slate-400">No competency scores yet (analysis pending).</div>
        @endforelse
    </div>

    {{-- ============ 3. Behavioral & Personality ============ --}}
    <div x-show="tab==='behavioral'" x-cloak class="space-y-6">
        @if($b)
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card p-5">
                    <h3 class="mb-4 font-semibold text-slate-800">DISC profile</h3>
                    @foreach(($b->disc ?? []) as $k => $v)
                        <div class="mb-2">
                            <div class="mb-0.5 flex justify-between text-sm text-slate-600"><span>{{ ['D'=>'Dominance','I'=>'Influence','S'=>'Steadiness','C'=>'Compliance'][$k] ?? $k }}</span><span class="font-medium">{{ (int) $v }}</span></div>
                            <div class="h-1.5 rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand" style="width: {{ (int) $v }}%"></div></div>
                        </div>
                    @endforeach
                </div>
                <div class="card p-5">
                    <h3 class="mb-4 font-semibold text-slate-800">Big Five</h3>
                    @foreach(($b->big_five ?? []) as $k => $v)
                        <div class="mb-2">
                            <div class="mb-0.5 flex justify-between text-sm text-slate-600"><span class="capitalize">{{ $k }}</span><span class="font-medium">{{ (int) $v }}</span></div>
                            <div class="h-1.5 rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-400" style="width: {{ (int) $v }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <div class="card p-5 text-center">
                    <div class="text-2xl font-bold text-slate-800">{{ $b->growth_mindset_score !== null ? round($b->growth_mindset_score) : '—' }}</div>
                    <div class="text-xs text-slate-400">Growth mindset</div>
                </div>
                <div class="card p-5 text-center">
                    <div class="text-2xl font-bold text-slate-800">{{ $b->stress_handling_score !== null ? round($b->stress_handling_score) : '—' }}</div>
                    <div class="text-xs text-slate-400">Stress handling</div>
                </div>
                <div class="card p-5 text-center">
                    <div class="text-base font-semibold text-slate-800">{{ $b->personality_type ?? '—' }}</div>
                    <div class="text-xs text-slate-400">Personality type</div>
                </div>
            </div>

            @if($b->leadership_tendency)
                <div class="card p-5"><h3 class="mb-1 font-semibold text-slate-800">Leadership tendency</h3><p class="text-sm text-slate-600">{{ $b->leadership_tendency }}</p></div>
            @endif
            @if($b->observations)
                <div class="card p-5"><h3 class="mb-1 font-semibold text-slate-800">Observations</h3><p class="text-sm text-slate-600">{{ $b->observations }}</p></div>
            @endif
            <p class="text-xs text-slate-400">Interview-based approximation, not a clinical assessment.</p>
        @else
            <div class="card p-8 text-center text-sm text-slate-400">No behavioral analysis yet.</div>
        @endif
    </div>

    {{-- ============ 4. Risk Analysis ============ --}}
    <div x-show="tab==='risk'" x-cloak class="space-y-4">
        @forelse($interview->redFlags->sortByDesc(fn ($f) => ['high'=>3,'medium'=>2,'low'=>1][$f->severity] ?? 0) as $flag)
            @php($meta = $sevMeta[$flag->severity] ?? $sevMeta['low'])
            @php($seqs = is_array($flag->evidence ?? null) ? $flag->evidence : [])
            <div class="card border-s-4 p-5 {{ $meta['box'] }}">
                <div class="mb-1 flex items-center justify-between">
                    <h4 class="font-semibold text-slate-800">{{ ucwords(str_replace('_', ' ', $flag->type)) }}</h4>
                    <span class="badge-soft {{ $meta['chip'] }}">{{ $meta['label'] }}</span>
                </div>
                <p class="text-sm text-slate-600">{{ $flag->description }}</p>
                @if(count($seqs))
                    <div class="mt-2 space-y-1">
                        @foreach($seqs as $seq)
                            @if($q = $bySeq[$seq] ?? null)
                                <blockquote class="border-s-2 border-slate-300 ps-3 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($q->content, 200) }}</blockquote>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="card p-8 text-center text-emerald-600">✓ No red flags detected.</div>
        @endforelse
    </div>

    {{-- ============ 5. Resume Analysis ============ --}}
    <div x-show="tab==='resume'" x-cloak class="space-y-6">
        @if($cv)
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="card p-5 lg:col-span-2">
                    <h3 class="mb-2 font-semibold text-slate-800">Summary</h3>
                    <p class="text-sm leading-relaxed text-slate-600">{{ $cv->summary ?? '—' }}</p>
                </div>
                <div class="card p-5 text-center">
                    <div class="text-3xl font-bold {{ $textClass($cv->jd_match_score ?? 0) }}">{{ $cv->jd_match_score !== null ? round($cv->jd_match_score) : '—' }}</div>
                    <div class="text-xs text-slate-400">JD match score</div>
                    @if(($cv->extracted['total_years'] ?? null) !== null)
                        <div class="mt-3 text-lg font-semibold text-slate-700">{{ $cv->extracted['total_years'] }} yrs</div>
                        <div class="text-xs text-slate-400">Experience</div>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="card p-5">
                    <h3 class="mb-2 font-semibold text-slate-800">Skills</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($cv->extracted['skills'] ?? [] as $skill)
                            <span class="badge-soft bg-brand-light text-brand">{{ $skill }}</span>
                        @empty<span class="text-sm text-slate-400">—</span>@endforelse
                    </div>
                </div>
                <div class="card p-5">
                    <h3 class="mb-2 font-semibold text-slate-800">Companies & roles</h3>
                    <ul class="space-y-0.5 text-sm text-slate-600">
                        @forelse($cv->extracted['companies'] ?? [] as $c)<li>{{ $c }}</li>@empty<li class="text-slate-400">—</li>@endforelse
                    </ul>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-emerald-700">Highlights</h3>
                    <ul class="space-y-1 text-sm text-slate-600">@forelse($cv->highlights ?? [] as $h)<li>• {{ $h }}</li>@empty<li class="text-slate-400">—</li>@endforelse</ul>
                </div>
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-amber-700">Gaps vs requirements</h3>
                    <ul class="space-y-1 text-sm text-slate-600">@forelse($cv->gaps ?? [] as $g)<li>• {{ $g }}</li>@empty<li class="text-slate-400">—</li>@endforelse</ul>
                </div>
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-slate-700">Suggested focus</h3>
                    <ul class="space-y-1 text-sm text-slate-600">@forelse($cv->topics_to_probe ?? [] as $t)<li>• {{ $t }}</li>@empty<li class="text-slate-400">—</li>@endforelse</ul>
                </div>
            </div>
        @else
            <div class="card p-8 text-center text-sm text-slate-400">No resume analysis available.</div>
        @endif
    </div>

    {{-- ============ 6. Transcript (Evidence Explorer) ============ --}}
    <div x-show="tab==='evidence'" x-cloak class="card space-y-4 p-5">
        @foreach($interview->messages->whereNotIn('role', ['system'])->sortBy('seq') as $m)
            <div class="{{ $m->role === 'agent' ? '' : 'flex justify-end' }}">
                <div class="max-w-[85%]">
                    <span class="text-xs font-semibold {{ $m->role === 'agent' ? 'text-brand' : 'text-slate-400' }}">
                        [{{ $m->seq }}] {{ $m->role === 'agent' ? ($interview->avatar?->name ?? 'AI') : 'Candidate' }}
                    </span>
                    <p class="mt-0.5 whitespace-pre-wrap rounded-2xl px-4 py-2 text-sm {{ $m->role === 'agent' ? 'bg-slate-100 text-slate-700' : 'bg-brand text-white' }}">{{ $m->content }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============ 7. Timeline ============ --}}
    <div x-show="tab==='timeline'" x-cloak class="card p-5">
        @forelse($interview->events->sortBy('ms_offset') as $e)
            <div class="flex items-center gap-3 py-1.5">
                <span class="w-12 font-mono text-xs text-slate-400">{{ gmdate('i:s', (int) ($e->ms_offset / 1000)) }}</span>
                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ ['positive'=>'bg-emerald-500','warning'=>'bg-amber-500','critical'=>'bg-red-500'][$e->severity] ?? 'bg-slate-400' }}"></span>
                <span class="text-sm text-slate-600">{{ $e->label }}</span>
            </div>
        @empty
            <p class="text-center text-sm text-slate-400">No timeline events.</p>
        @endforelse
    </div>
</div>
@endsection
