@extends('layouts.app')
@section('title', 'Pipeline · Watad')
@section('heading', 'Pipeline')
@section('content')
<x-page-header title="Hiring pipeline" />

<div class="flex gap-4 overflow-x-auto pb-4">
    @foreach($board as $status)
        @php($items = $applications[$status->value] ?? collect())
        <div class="w-72 shrink-0">
            <div class="mb-2 flex items-center justify-between px-1">
                <h3 class="text-sm font-medium text-slate-700">{{ $status->label() }}</h3>
                <span class="badge-soft bg-slate-100 text-slate-500">{{ $items->count() }}</span>
            </div>
            <div class="space-y-2">
                @foreach($items as $app)
                    <div class="card p-3">
                        <a href="{{ route('hr.candidates.show', $app->candidate) }}" class="text-sm font-medium text-brand hover:underline">
                            {{ $app->candidate?->full_name }}
                        </a>
                        <div class="text-xs text-slate-500">{{ $app->jobPosition?->title }}</div>
                        <div class="mt-2">
                            <form method="POST" action="{{ route('hr.applications.move_stage', $app) }}">
                                @csrf
                                <select name="status" onchange="this.form.submit()" class="input text-xs">
                                    @foreach($board as $s)<option value="{{ $s->value }}" @selected($s === $status)>{{ $s->label() }}</option>@endforeach
                                </select>
                            </form>
                        </div>
                    </div>
                @endforeach
                @if($items->isEmpty())<p class="px-1 text-xs text-slate-300">—</p>@endif
            </div>
        </div>
    @endforeach
</div>
@endsection
