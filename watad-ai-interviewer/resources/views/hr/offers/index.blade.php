@extends('layouts.app')
@section('title', 'Offers · Watad')
@section('heading', 'Offers')
@section('content')
<x-page-header title="Offers" />
<div class="card overflow-hidden">
    @if($offers->count())
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-slate-500">
                <tr><th class="px-5 py-3 text-start font-medium">Candidate</th><th class="text-start font-medium">Position</th>
                    <th class="text-start font-medium">Salary</th><th class="text-start font-medium">Status</th><th class="px-5 text-start font-medium"></th></tr>
            </thead>
            <tbody>
            @foreach($offers as $offer)
                <tr class="border-b border-slate-50 hover:bg-slate-50">
                    <td class="px-5 py-3 font-medium text-slate-700">{{ $offer->application?->candidate?->full_name }}</td>
                    <td class="text-slate-600">{{ $offer->application?->jobPosition?->title }}</td>
                    <td class="text-slate-600">{{ $offer->salary }} {{ $offer->currency }}</td>
                    <td><span class="badge-soft bg-slate-100 text-slate-600">{{ $offer->status->label() }}</span></td>
                    <td class="px-5"><a href="{{ route('hr.offers.show', $offer) }}" class="text-brand">open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else <x-empty-state title="No offers yet" /> @endif
</div>
<div class="mt-4">{{ $offers->links() }}</div>
@endsection
