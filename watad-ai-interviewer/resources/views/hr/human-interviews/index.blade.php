@extends('layouts.app')
@section('title', 'Human Interviews · Watad')
@section('heading', 'Human Interviews')
@section('content')
<x-page-header title="Human interviews">
    @can('interviews.schedule')<a href="{{ route('hr.human-interviews.create') }}" class="btn-primary">＋ Schedule</a>@endcan
</x-page-header>

<div class="card overflow-hidden">
    @if($interviews->count())
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-start font-medium">Candidate</th>
                    <th class="text-start font-medium">Type</th>
                    <th class="text-start font-medium">When</th>
                    <th class="text-start font-medium">Panel</th>
                    <th class="text-start font-medium">Status</th>
                    <th class="px-5 text-start font-medium">Rating</th>
                </tr>
            </thead>
            <tbody>
            @foreach($interviews as $iv)
                <tr class="border-b border-slate-50 hover:bg-slate-50">
                    <td class="px-5 py-3"><a href="{{ route('hr.human-interviews.show', $iv) }}" class="text-brand hover:underline">{{ $iv->application?->candidate?->full_name }}</a><div class="text-xs text-slate-400">{{ $iv->application?->jobPosition?->title }}</div></td>
                    <td class="capitalize text-slate-600">{{ $iv->type->value }}</td>
                    <td class="text-slate-600">{{ $iv->scheduled_at?->format('Y-m-d H:i') }}</td>
                    <td class="text-slate-600">{{ $iv->panelists->count() }}</td>
                    <td><span class="badge-soft bg-slate-100 capitalize text-slate-600">{{ str_replace('_',' ',$iv->status->value) }}</span></td>
                    <td class="px-5 text-slate-600">{{ $iv->aggregate_rating ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <x-empty-state title="No human interviews scheduled yet" cta="Schedule one" :href="route('hr.human-interviews.create')" />
    @endif
</div>
<div class="mt-4">{{ $interviews->links() }}</div>
@endsection
