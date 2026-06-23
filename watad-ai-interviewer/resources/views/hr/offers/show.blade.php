@extends('layouts.app')
@section('title', 'Offer · Watad')
@section('heading', 'Offer')
@section('content')
<div class="card max-w-2xl p-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-800">{{ $offer->application?->candidate?->full_name }}</h2>
        <span class="badge-soft bg-slate-100 text-slate-600">{{ $offer->status->label() }}</span>
    </div>
    <p class="mt-1 text-sm text-slate-500">{{ $offer->title ?? $offer->application?->jobPosition?->title }}</p>

    <dl class="mt-4 grid grid-cols-2 gap-2 text-sm text-slate-600">
        <div>Salary: <strong>{{ $offer->salary }} {{ $offer->currency }}</strong></div>
        <div>Start: {{ optional($offer->start_date)->toDateString() ?? '—' }}</div>
        <div>Expires: {{ optional($offer->expires_at)->toDateString() ?? '—' }}</div>
        <div>Sent: {{ optional($offer->sent_at)->diffForHumans() ?? '—' }}</div>
    </dl>

    <div class="mt-5 flex flex-wrap gap-2">
        <a href="{{ route('hr.offers.letter', $offer) }}" class="btn-ghost">⬇ Letter PDF</a>
        @can('offers.update')
            @if(in_array($offer->status->value, ['draft']))
                <form method="POST" action="{{ route('hr.offers.send', $offer) }}">@csrf<button class="btn-primary">Send to candidate</button></form>
            @endif
            @if(!in_array($offer->status->value, ['accepted','declined','withdrawn']))
                <form method="POST" action="{{ route('hr.offers.withdraw', $offer) }}">@csrf<button class="btn-ghost">Withdraw</button></form>
            @endif
        @endcan
    </div>
    @if($offer->signed_at)<p class="mt-3 text-sm text-emerald-600">✓ Signed by candidate ({{ $offer->signature_path }}) on {{ $offer->signed_at->toDayDateTimeString() }}</p>@endif
</div>
@endsection
