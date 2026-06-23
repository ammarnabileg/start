@extends('portal.layout')
@section('title', 'Offers · Watad Careers')
@section('content')
<h1 class="mb-5 text-xl font-semibold">My offers</h1>
<div class="space-y-3">
    @forelse($offers as $offer)
        <a href="{{ route('portal.offers.show', $offer) }}" class="card flex items-center justify-between p-4 hover:bg-slate-50">
            <div>
                <div class="font-medium text-slate-800">{{ $offer->application?->jobPosition?->title }}</div>
                <div class="text-xs text-slate-500">{{ $offer->salary ? $offer->salary.' '.$offer->currency : '' }}</div>
            </div>
            <span class="badge-soft bg-slate-100 text-slate-600">{{ $offer->status->label() }}</span>
        </a>
    @empty
        <div class="card"><x-empty-state title="No offers yet" /></div>
    @endforelse
</div>
@endsection
