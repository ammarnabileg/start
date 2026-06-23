@extends('layouts.app')
@section('title', 'Report · '.$interview->candidate?->full_name)
@section('heading', 'Interview report')
@section('content')
@php($report = $interview->report)
<div x-data="{ tab: 'scores' }" class="space-y-6">

    {{-- Header --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold">{{ $interview->candidate?->full_name }}</h2>
            <p class="text-sm text-slate-500">{{ $interview->jobPosition?->title }} · {{ $interview->mode->value }} · {{ $interview->duration_seconds ? gmdate('i:s', $interview->duration_seconds) : '—' }}</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-center">
                <div class="text-3xl font-bold">{{ $interview->overall_score ?? '—' }}</div>
                <div class="text-xs text-slate-500">overall</div>
            </div>
            @include('components.reco-badge', ['recommendation' => $interview->recommendation])
            @if($report?->pdf_path)
                <a href="{{ route('hr.interviews.report.pdf', $interview->public_id) }}"
                   class="rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium">⬇ PDF</a>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-2 text-sm border-b border-slate-200 dark:border-slate-800">
        @foreach(['scores'=>'Scores','behavioral'=>'Behavioral','flags'=>'Red flags','transcript'=>'Transcript','timeline'=>'Timeline'] as $key=>$label)
            <button @click="tab='{{ $key }}'" :class="tab==='{{ $key }}' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500'"
                    class="px-3 py-2 border-b-2 -mb-px">{{ $label }}
                @if($key==='flags')<span class="ms-1 rounded-full bg-red-100 text-red-700 px-1.5 text-xs">{{ $interview->redFlags->count() }}</span>@endif
            </button>
        @endforeach
    </div>

    {{-- Scores --}}
    <div x-show="tab==='scores'" class="grid lg:grid-cols-2 gap-6">
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5">
            <h3 class="font-semibold mb-4">Competencies</h3>
            @forelse($interview->competencyScores as $score)
                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span>{{ \App\Enums\Competency::tryFrom($score->competency)?->label() ?? $score->competency }}</span>
                        <span class="font-medium">{{ (int)$score->score }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ (int)$score->score }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-slate-400 text-sm">No scores yet (analysis pending).</p>
            @endforelse
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 space-y-4 text-sm">
            <div><h4 class="font-semibold mb-1">Summary</h4><p class="text-slate-600 dark:text-slate-300">{{ $report?->interview_summary ?? '—' }}</p></div>
            <div><h4 class="font-semibold mb-1">Strengths</h4>
                <ul class="list-disc ps-5 text-slate-600 dark:text-slate-300">@forelse($report?->strengths ?? [] as $s)<li>{{ $s }}</li>@empty<li class="list-none text-slate-400">—</li>@endforelse</ul></div>
            <div><h4 class="font-semibold mb-1">Weaknesses</h4>
                <ul class="list-disc ps-5 text-slate-600 dark:text-slate-300">@forelse($report?->weaknesses ?? [] as $w)<li>{{ $w }}</li>@empty<li class="list-none text-slate-400">—</li>@endforelse</ul></div>
            <div><h4 class="font-semibold mb-1">Hiring recommendation</h4><p class="text-slate-600 dark:text-slate-300">{{ $report?->hiring_recommendation ?? '—' }}</p></div>
        </div>
    </div>

    {{-- Behavioral --}}
    <div x-show="tab==='behavioral'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 text-sm">
        @php($b = $interview->behavioralAnalysis)
        @if($b)
            <p class="mb-3"><span class="font-semibold">Personality:</span> {{ $b->personality_type }}</p>
            <div class="grid sm:grid-cols-2 gap-6">
                <div><h4 class="font-semibold mb-2">DISC</h4>
                    @foreach(($b->disc ?? []) as $k=>$v)
                        <div class="flex justify-between"><span>{{ $k }}</span><span>{{ $v }}</span></div>
                    @endforeach
                </div>
                <div><h4 class="font-semibold mb-2">Big Five</h4>
                    @foreach(($b->big_five ?? []) as $k=>$v)
                        <div class="flex justify-between"><span class="capitalize">{{ $k }}</span><span>{{ $v }}</span></div>
                    @endforeach
                </div>
            </div>
            <p class="mt-4 text-slate-600 dark:text-slate-300">{{ $b->observations }}</p>
            <p class="mt-2 text-xs text-slate-400">Interview-based approximation, not a clinical assessment.</p>
        @else
            <p class="text-slate-400">No behavioral analysis yet.</p>
        @endif
    </div>

    {{-- Red flags --}}
    <div x-show="tab==='flags'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 text-sm">
        @forelse($interview->redFlags as $flag)
            <div class="mb-3 border-s-4 ps-3 {{ $flag->severity==='high' ? 'border-red-500' : ($flag->severity==='medium' ? 'border-amber-500' : 'border-slate-300') }}">
                <div class="font-medium">{{ ucwords(str_replace('_',' ',$flag->type)) }} <span class="text-xs text-slate-400">({{ $flag->severity }})</span></div>
                <p class="text-slate-600 dark:text-slate-300">{{ $flag->description }}</p>
            </div>
        @empty
            <p class="text-emerald-600">✓ No red flags detected.</p>
        @endforelse
    </div>

    {{-- Transcript --}}
    <div x-show="tab==='transcript'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 space-y-3 text-sm">
        @foreach($interview->messages->whereNotIn('role',['system'])->sortBy('seq') as $m)
            <div>
                <span class="text-xs font-semibold {{ $m->role==='agent' ? 'text-indigo-600' : 'text-slate-500' }}">
                    [{{ $m->seq }}] {{ $m->role==='agent' ? ($interview->avatar?->name ?? 'AI') : 'Candidate' }}
                </span>
                <p class="text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $m->content }}</p>
            </div>
        @endforeach
    </div>

    {{-- Timeline --}}
    <div x-show="tab==='timeline'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 text-sm">
        @forelse($interview->events->sortBy('ms_offset') as $e)
            <div class="flex items-center gap-3 py-1">
                <span class="font-mono text-xs text-slate-400 w-12">{{ gmdate('i:s', (int)($e->ms_offset/1000)) }}</span>
                <span class="h-2 w-2 rounded-full {{ ['positive'=>'bg-emerald-500','warning'=>'bg-amber-500','critical'=>'bg-red-500'][$e->severity] ?? 'bg-slate-400' }}"></span>
                <span>{{ $e->label }}</span>
            </div>
        @empty
            <p class="text-slate-400">No timeline events.</p>
        @endforelse
    </div>
</div>
@endsection
