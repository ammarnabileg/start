@extends('portal.layout')
@section('title', 'Your offer · Watad Careers')
@section('content')
<a href="{{ route('portal.offers') }}" class="text-sm text-brand">‹ My offers</a>
<div class="card mt-2 max-w-2xl p-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Your offer — {{ $offer->application?->jobPosition?->title }}</h1>
        <span class="badge-soft bg-slate-100 text-slate-600">{{ $offer->status->label() }}</span>
    </div>
    @if($offer->expires_at)<p class="mt-1 text-sm text-amber-600">Expires {{ $offer->expires_at->diffForHumans() }}</p>@endif

    <dl class="mt-4 grid grid-cols-2 gap-2 text-sm text-slate-600">
        <div>Role: <strong>{{ $offer->title ?? $offer->application?->jobPosition?->title }}</strong></div>
        <div>Salary: {{ $offer->salary ? $offer->salary.' '.$offer->currency : 'As discussed' }}</div>
        <div>Start date: {{ optional($offer->start_date)->toFormattedDateString() ?? 'To be agreed' }}</div>
    </dl>

    <div class="mt-4"><a href="{{ route('hr.offers.letter', $offer) }}" class="btn-ghost" target="_blank">View offer letter (PDF)</a></div>

    @if(in_array($offer->status->value, ['sent','viewed']))
        <form method="POST" action="{{ route('portal.offers.accept', $offer) }}" class="mt-5 border-t border-slate-100 pt-4">
            @csrf
            <label class="flex items-start gap-2 text-sm text-slate-600"><input type="checkbox" name="agree" value="1" required class="mt-1"> I have read and accept this offer.</label>
            <label class="label mt-3">Type your full name to sign</label>
            <input name="signature" required class="input" placeholder="{{ $offer->application?->candidate?->full_name }}">
            <div class="mt-3 flex gap-2">
                <button class="btn-primary">Accept &amp; sign</button>
                <button formaction="{{ route('portal.offers.decline', $offer) }}" class="btn-ghost">Decline</button>
            </div>
        </form>
    @elseif($offer->status->value === 'accepted')
        <p class="mt-4 text-emerald-600">✓ You accepted this offer on {{ $offer->signed_at?->toFormattedDateString() }}. Welcome aboard!</p>
    @endif
</div>
@endsection
