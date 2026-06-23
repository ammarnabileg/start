@extends('portal.layout')
@section('title', 'My Applications · Watad Careers')
@section('content')
<h1 class="mb-5 text-xl font-semibold">My applications</h1>
<div class="space-y-3">
    @forelse($applications as $app)
        <a href="{{ route('portal.applications.show', $app) }}" class="card flex items-center justify-between p-4 hover:bg-slate-50">
            <div>
                <div class="font-medium text-slate-800">{{ $app->jobPosition?->title }}</div>
                <div class="text-xs text-slate-500">Applied {{ $app->applied_at?->diffForHumans() }}</div>
            </div>
            <span class="badge-soft bg-slate-100 text-slate-600">{{ $app->status->label() }}</span>
        </a>
    @empty
        <div class="card"><x-empty-state title="You haven't applied to any jobs yet" /></div>
    @endforelse
</div>
@endsection
