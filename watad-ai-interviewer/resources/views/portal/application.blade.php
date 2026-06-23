@extends('portal.layout')
@section('title', 'Application · Watad Careers')
@section('content')
@php
    $steps = ['applied'=>'Applied','ai_screening'=>'AI Screening','qualified'=>'Qualified','tech_interview'=>'Interviews','manager_interview'=>'Interviews','final_review'=>'Final Review','offer'=>'Offer','hired'=>'Hired'];
    $order = array_keys($steps);
    $current = array_search($application->status->value, $order, true);
@endphp
<a href="{{ route('portal.applications') }}" class="text-sm text-brand">‹ My applications</a>
<h1 class="mb-1 mt-2 text-xl font-semibold">{{ $application->jobPosition?->title }}</h1>
<p class="mb-5 text-sm text-slate-500">Status: <span class="font-medium text-slate-700">{{ $application->status->label() }}</span></p>

<div class="card p-5">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        @foreach($steps as $key=>$label)
            @php($idx = array_search($key, $order, true))
            <span class="rounded-full px-3 py-1 {{ $current !== false && $idx <= $current ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500' }}">{{ $label }}</span>
            @if(!$loop->last)<span class="text-slate-300">→</span>@endif
        @endforeach
    </div>
</div>

@if($application->humanInterviews->isNotEmpty())
<div class="card mt-4 p-5 text-sm">
    <h2 class="mb-2 font-semibold">Scheduled interviews</h2>
    @foreach($application->humanInterviews as $iv)
        <div class="flex items-center justify-between border-b border-slate-50 py-2">
            <span>{{ ucfirst($iv->type->value) }} · {{ $iv->scheduled_at?->format('M j, H:i') }} · {{ $iv->mode->value }}</span>
            @if($iv->meeting_url && $iv->status->value==='scheduled')<a href="{{ $iv->meeting_url }}" class="text-brand" target="_blank">Join</a>@endif
        </div>
    @endforeach
</div>
@endif
@endsection
