@extends('portal.layout')
@section('title', 'Dashboard · Watad Careers')
@section('content')
<h1 class="mb-5 text-xl font-semibold">Welcome back, {{ explode(' ', $candidate->full_name)[0] }} 👋</h1>

<div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
    <x-stat-card label="Applications" :value="$candidate->applications->count()" icon="📨" />
    <x-stat-card label="In review" :value="$candidate->applications->whereNotIn('status',['hired','rejected','withdrawn'])->count()" icon="🔄" />
    <x-stat-card label="Upcoming IVs" :value="$upcoming->count()" icon="📅" />
    <x-stat-card label="Offers" :value="$offers->count()" icon="🎉" />
</div>

@if($upcoming->isNotEmpty())
<div class="card mt-6 p-5">
    <h2 class="mb-3 font-semibold">Upcoming interviews</h2>
    @foreach($upcoming as $iv)
        <div class="flex items-center justify-between border-b border-slate-50 py-2 text-sm">
            <span>{{ ucfirst($iv->type->value) }} interview · {{ $iv->scheduled_at?->format('M j, H:i') }}</span>
            @if($iv->meeting_url)<a href="{{ $iv->meeting_url }}" class="btn-primary" target="_blank">Join</a>@endif
        </div>
    @endforeach
</div>
@endif

@if($offers->isNotEmpty())
<div class="card mt-6 border-brand/30 bg-brand-light p-5">
    <h2 class="mb-2 font-semibold text-brand">You have an offer 🎉</h2>
    @foreach($offers as $offer)
        <a href="{{ route('portal.offers.show', $offer) }}" class="btn-primary">Review your offer — {{ $offer->application?->jobPosition?->title }}</a>
    @endforeach
</div>
@endif
@endsection
