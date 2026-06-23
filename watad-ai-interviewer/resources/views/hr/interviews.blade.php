@extends('layouts.app')
@section('title', 'Interviews · Watad')
@section('heading', 'Interviews')
@section('content')
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">All statuses</option>
        @foreach(['completed','processing','in_progress','abandoned','error'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
    </select>
    <select name="recommendation" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <option value="">All recommendations</option>
        @foreach(['strong_hire','hire','maybe','reject'] as $r)
            <option value="{{ $r }}" @selected(request('recommendation')===$r)>{{ ucwords(str_replace('_',' ',$r)) }}</option>
        @endforeach
    </select>
    <button class="rounded-lg bg-slate-800 px-4 py-2 text-white text-sm">Filter</button>
    @can('report.export')
        <a href="{{ url('/api/export/interviews.xlsx?'.http_build_query(request()->only('status','recommendation','job'))) }}"
           class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Export .xlsx</a>
    @endcan
</form>

<div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="text-slate-500 border-b border-slate-100 dark:border-slate-800">
            <tr>
                <th class="text-start font-medium p-3">Candidate</th>
                <th class="text-start font-medium">Position</th>
                <th class="text-start font-medium">Status</th>
                <th class="text-start font-medium">Score</th>
                <th class="text-start font-medium">Recommendation</th>
                <th class="text-start font-medium">Date</th>
            </tr>
        </thead>
        <tbody>
        @forelse($interviews as $interview)
            <tr class="border-b border-slate-50 dark:border-slate-800/50 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="p-3">
                    <a href="{{ route('hr.interviews.show', $interview->public_id) }}" class="text-indigo-600 hover:underline">
                        {{ $interview->candidate?->full_name ?? '—' }}
                    </a>
                </td>
                <td>{{ $interview->jobPosition?->title }}</td>
                <td><span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs capitalize">{{ str_replace('_',' ',$interview->status->value) }}</span></td>
                <td class="font-medium">{{ $interview->overall_score ?? '—' }}</td>
                <td>@include('components.reco-badge', ['recommendation' => $interview->recommendation])</td>
                <td class="text-slate-500">{{ $interview->completed_at?->diffForHumans() ?? $interview->created_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="p-6 text-center text-slate-400">No interviews found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $interviews->links() }}</div>
@endsection
