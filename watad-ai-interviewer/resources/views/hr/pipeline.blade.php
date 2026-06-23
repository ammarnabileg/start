@extends('layouts.app')
@section('title', 'Pipeline · Watad')
@section('heading', 'Pipeline')
@section('content')
<x-page-header title="Hiring pipeline" />
@php
    $stageOf = [];
    foreach ($placed as $stageId => $rows) {
        foreach ($rows as $row) { $stageOf[$row->candidate_id] = $stageId; }
    }
    $firstStageId = $stages->first()?->id;
@endphp

<div class="flex gap-4 overflow-x-auto pb-4">
    @foreach($stages as $stage)
        <div class="w-72 shrink-0">
            <div class="mb-2 flex items-center justify-between px-1">
                <h3 class="text-sm font-medium text-slate-700">{{ $stage->name }}</h3>
                <span class="badge-soft bg-slate-100 text-slate-500">
                    {{ $interviews->filter(fn ($i) => ($stageOf[$i->candidate_id] ?? $firstStageId) === $stage->id)->count() }}
                </span>
            </div>
            <div class="space-y-2">
                @foreach($interviews as $candidateId => $interview)
                    @php($current = $stageOf[$candidateId] ?? $firstStageId)
                    @if($current === $stage->id)
                        <div class="card p-3">
                            <a href="{{ route('hr.interviews.show', $interview->public_id) }}" class="text-sm font-medium text-brand hover:underline">
                                {{ $interview->candidate?->full_name }}
                            </a>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-xs text-slate-500">{{ $interview->overall_score ? $interview->overall_score.'/100' : '—' }}</span>
                                @include('components.reco-badge', ['recommendation' => $interview->recommendation])
                            </div>
                            <form method="POST" action="{{ route('hr.interviews.move_stage', $interview->public_id) }}" class="mt-2">
                                @csrf
                                <select name="stage_id" onchange="this.form.submit()" class="input text-xs">
                                    @foreach($stages as $s)<option value="{{ $s->id }}" @selected($s->id === $current)>{{ $s->name }}</option>@endforeach
                                </select>
                            </form>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
