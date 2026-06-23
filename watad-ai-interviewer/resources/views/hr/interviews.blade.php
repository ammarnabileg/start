@extends('layouts.app')
@section('title', 'Interviews · Watad')
@section('heading', 'Interviews')
@section('content')
<x-page-header title="Interviews">
    @can('reports.export')
        <a href="{{ url('/api/export/interviews.xlsx?'.http_build_query(request()->only('status','recommendation','job'))) }}"
           class="btn-ghost">⬇ Export .xlsx</a>
    @endcan
</x-page-header>

<div class="mb-6 grid grid-cols-3 gap-4">
    <x-stat-card label="Total" :value="$stats['total']" icon="🗂️" />
    <x-stat-card label="Completed" :value="$stats['completed']" icon="✅" />
    <x-stat-card label="Shortlisted" :value="$stats['shortlisted']" icon="⭐" />
</div>

<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
    <span class="chip">🔎 Filter</span>
    <select name="status" class="input w-auto">
        <option value="">All statuses</option>
        @foreach(['completed','processing','in_progress','abandoned','error'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
    </select>
    <select name="recommendation" class="input w-auto">
        <option value="">All recommendations</option>
        @foreach(['strong_hire','hire','maybe','reject'] as $r)
            <option value="{{ $r }}" @selected(request('recommendation')===$r)>{{ ucwords(str_replace('_',' ',$r)) }}</option>
        @endforeach
    </select>
    <button class="btn-primary">Apply</button>
</form>

<div class="card overflow-hidden">
    @if($interviews->count())
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-start font-medium">Candidate</th>
                    <th class="text-start font-medium">Position</th>
                    <th class="text-start font-medium">Status</th>
                    <th class="text-start font-medium">Score</th>
                    <th class="text-start font-medium">Recommendation</th>
                    <th class="px-5 text-start font-medium">Date</th>
                </tr>
            </thead>
            <tbody>
            @foreach($interviews as $interview)
                <tr class="border-b border-slate-50 hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <a href="{{ route('hr.interviews.show', $interview->public_id) }}" class="text-brand hover:underline">
                            {{ $interview->candidate?->full_name ?? '—' }}
                        </a>
                    </td>
                    <td class="text-slate-600">{{ $interview->jobPosition?->title }}</td>
                    <td><span class="badge-soft bg-slate-100 capitalize text-slate-600">{{ str_replace('_',' ',$interview->status->value) }}</span></td>
                    <td class="font-medium">{{ $interview->overall_score ?? '—' }}</td>
                    <td>@include('components.reco-badge', ['recommendation' => $interview->recommendation])</td>
                    <td class="px-5 text-slate-500">{{ $interview->completed_at?->diffForHumans() ?? $interview->created_at->diffForHumans() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <x-empty-state title="No interviews match your filters yet" />
    @endif
</div>
<div class="mt-4">{{ $interviews->links() }}</div>
@endsection
