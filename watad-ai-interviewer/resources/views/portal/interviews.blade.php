@extends('portal.layout')
@section('title', 'Interviews · Watad Careers')
@section('content')
<h1 class="mb-5 text-xl font-semibold">Interviews</h1>

<div class="card mb-4 p-5">
    <h2 class="mb-2 font-semibold">AI interviews</h2>
    @forelse($candidate->interviews as $iv)
        <div class="flex items-center justify-between border-b border-slate-50 py-2 text-sm">
            <span>{{ $iv->jobPosition?->title }} · {{ $iv->status->value }}</span>
            @if($iv->status->value === 'scheduled')
                <a href="{{ route('candidate.interview.room', $iv->public_id) }}" class="btn-primary">Start</a>
            @else <span class="text-slate-400">{{ $iv->completed_at?->diffForHumans() }}</span> @endif
        </div>
    @empty <p class="text-sm text-slate-400">No AI interviews yet.</p> @endforelse
</div>

<div class="card p-5">
    <h2 class="mb-2 font-semibold">Scheduled & past interviews</h2>
    @forelse($human as $iv)
        <div class="flex items-center justify-between border-b border-slate-50 py-2 text-sm">
            <span>{{ ucfirst($iv->type->value) }} · {{ $iv->application?->jobPosition?->title }} · {{ $iv->scheduled_at?->format('M j, H:i') }}</span>
            @if($iv->meeting_url && $iv->status->value==='scheduled')<a href="{{ $iv->meeting_url }}" class="text-brand" target="_blank">Join</a>
            @else <span class="badge-soft bg-slate-100 text-slate-500">{{ $iv->status->value }}</span> @endif
        </div>
    @empty <p class="text-sm text-slate-400">No scheduled interviews.</p> @endforelse
    <p class="mt-3 text-xs text-slate-400">Tip: test your microphone and camera, find a quiet space, and join a few minutes early.</p>
</div>
@endsection
