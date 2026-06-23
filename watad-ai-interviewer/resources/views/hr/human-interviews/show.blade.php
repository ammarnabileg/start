@extends('layouts.app')
@section('title', 'Interview · Watad')
@section('heading', 'Human Interview')
@section('content')
@php($app = $humanInterview->application)
<div class="space-y-6">
    <div class="card flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ $app?->candidate?->full_name }}</h2>
            <p class="text-sm text-slate-500">{{ ucfirst($humanInterview->type->value) }} · {{ $app?->jobPosition?->title }} ·
                {{ $humanInterview->scheduled_at?->format('Y-m-d H:i') }} · {{ $humanInterview->duration_min }}m</p>
        </div>
        <div class="text-end text-sm">
            <span class="badge-soft bg-slate-100 capitalize text-slate-600">{{ str_replace('_',' ',$humanInterview->status->value) }}</span>
            @if($humanInterview->meeting_url)<a href="{{ $humanInterview->meeting_url }}" class="btn-primary mt-2" target="_blank">Join meeting</a>@endif
        </div>
    </div>

    <div class="card p-5 text-sm">
        <h3 class="mb-2 font-semibold text-slate-800">Panel</h3>
        @foreach($humanInterview->panelists as $p)
            <span class="badge-soft bg-slate-100 text-slate-600">{{ $p->user?->name }}{{ $p->is_lead ? ' (lead)' : '' }} {{ $p->responded ? '✓' : '' }}</span>
        @endforeach
        <span class="ms-2 text-slate-500">Aggregate rating: {{ $humanInterview->aggregate_rating ?? '—' }}/5</span>
    </div>

    {{-- Dynamic evaluation form --}}
    @can('evaluations.create')
    <form method="POST" action="{{ route('hr.human-interviews.evaluate', $humanInterview) }}" class="card p-5">
        @csrf
        <h3 class="mb-4 font-semibold text-slate-800">Your evaluation @if($myEvaluation)<span class="text-xs text-emerald-600">(submitted — editing)</span>@endif</h3>

        @forelse($humanInterview->template?->criteria ?? [] as $c)
            @php($val = $myEvaluation->criteria_scores[$c->id] ?? null)
            <div class="mb-3">
                <label class="label">{{ $c->label }} <span class="text-xs text-slate-400">(weight {{ $c->weight }})</span></label>
                @switch($c->type)
                    @case('rating')
                        <select name="criteria_scores[{{ $c->id }}]" class="input w-32">@for($i=1;$i<=5;$i++)<option value="{{ $i }}" @selected($val==$i)>{{ $i }} ★</option>@endfor</select>
                        @break
                    @case('boolean')
                        <select name="criteria_scores[{{ $c->id }}]" class="input w-32"><option value="1" @selected($val=='1')>Yes</option><option value="0" @selected($val==='0')>No</option></select>
                        @break
                    @case('select')
                        <select name="criteria_scores[{{ $c->id }}]" class="input w-48">@foreach(($c->options ?? []) as $opt)<option @selected($val==$opt)>{{ $opt }}</option>@endforeach</select>
                        @break
                    @case('text')
                        <textarea name="criteria_scores[{{ $c->id }}]" rows="2" class="input">{{ $val }}</textarea>
                        @break
                    @default
                        <input type="number" name="criteria_scores[{{ $c->id }}]" value="{{ $val }}" class="input w-32">
                @endswitch
            </div>
        @empty
            <p class="mb-3 text-xs text-slate-400">No evaluation template resolved for this job — using the general fields below.</p>
        @endforelse

        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="label">Strengths (one per line)</label><textarea name="strengths" rows="3" class="input">{{ $myEvaluation ? implode("\n", $myEvaluation->strengths ?? []) : '' }}</textarea></div>
            <div><label class="label">Weaknesses (one per line)</label><textarea name="weaknesses" rows="3" class="input">{{ $myEvaluation ? implode("\n", $myEvaluation->weaknesses ?? []) : '' }}</textarea></div>
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div><label class="label">Overall rating (1–5)</label><input type="number" step="0.5" min="1" max="5" name="overall_rating" value="{{ $myEvaluation?->overall_rating }}" class="input w-32"></div>
            <div><label class="label">Recommendation</label>
                <select name="recommendation" class="input">
                    @foreach(['strong_yes'=>'Strong Yes','yes'=>'Yes','neutral'=>'Neutral','no'=>'No','strong_no'=>'Strong No'] as $v=>$l)
                        <option value="{{ $v }}" @selected($myEvaluation?->recommendation?->value===$v)>{{ $l }}</option>
                    @endforeach
                </select></div>
        </div>
        <div class="mt-3"><label class="label">Notes</label><textarea name="notes" rows="3" class="input">{{ $myEvaluation?->notes }}</textarea></div>
        <button class="btn-primary mt-4">Submit evaluation</button>
    </form>
    @endcan

    {{-- Submitted evaluations --}}
    <div class="card p-5 text-sm">
        <h3 class="mb-2 font-semibold text-slate-800">Submitted evaluations</h3>
        @forelse($humanInterview->evaluations->whereNotNull('submitted_at') as $ev)
            <div class="mb-2 border-b border-slate-50 py-2">
                <span class="font-medium text-slate-700">{{ $ev->user?->name }}</span> — {{ $ev->overall_rating }}/5 · {{ $ev->recommendation?->label() }}
                @if($ev->notes)<div class="text-slate-500">{{ $ev->notes }}</div>@endif
            </div>
        @empty <p class="text-slate-400">No evaluations submitted yet.</p> @endforelse
    </div>
</div>
@endsection
