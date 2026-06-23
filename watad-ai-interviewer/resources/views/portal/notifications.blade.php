@extends('portal.layout')
@section('title', 'Notifications · Watad Careers')
@section('content')
<h1 class="mb-5 text-xl font-semibold">Notifications</h1>
<div class="card divide-y divide-slate-50 p-2">
    @forelse($notifications as $n)
        <div class="px-3 py-3 text-sm">
            <div class="text-slate-700">{{ $n->payload['subject'] ?? ucfirst(str_replace('_',' ',$n->event)) }}</div>
            <div class="text-xs text-slate-400">{{ $n->created_at?->diffForHumans() }}</div>
        </div>
    @empty
        <div class="p-6"><x-empty-state title="No notifications yet" /></div>
    @endforelse
</div>
@endsection
