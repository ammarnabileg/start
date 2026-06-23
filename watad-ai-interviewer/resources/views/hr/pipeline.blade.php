@extends('layouts.app')
@section('title', 'Pipeline · Watad')
@section('heading', 'Hiring pipeline')
@section('content')
@php
    // Map candidate_id → current stage_id from placed rows.
    $stageOf = [];
    foreach ($placed as $stageId => $rows) {
        foreach ($rows as $row) { $stageOf[$row->candidate_id] = $stageId; }
    }
    $firstStageId = $stages->first()?->id;
@endphp

<div class="flex gap-4 overflow-x-auto pb-4">
    @foreach($stages as $stage)
        <div class="w-72 shrink-0">
            <div class="flex items-center justify-between mb-2 px-1">
                <h3 class="font-medium text-sm">{{ $stage->name }}</h3>
            </div>
            <div class="space-y-2">
                @foreach($interviews as $candidateId => $interview)
                    @php($current = $stageOf[$candidateId] ?? $firstStageId)
                    @if($current === $stage->id)
                        <div class="rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-3">
                            <a href="{{ route('hr.interviews.show', $interview->public_id) }}" class="font-medium text-sm text-indigo-600 hover:underline">
                                {{ $interview->candidate?->full_name }}
                            </a>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-slate-500">{{ $interview->overall_score ? $interview->overall_score.'/100' : '—' }}</span>
                                @include('components.reco-badge', ['recommendation' => $interview->recommendation])
                            </div>
                            <form method="POST" action="{{ route('hr.interviews.move_stage', $interview->public_id) }}" class="mt-2">
                                @csrf
                                <select name="stage_id" onchange="this.form.submit()" class="w-full text-xs rounded border border-slate-300 px-2 py-1">
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
