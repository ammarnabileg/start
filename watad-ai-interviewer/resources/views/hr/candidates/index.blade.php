@extends('layouts.app')
@section('title', 'Candidates · Watad')
@section('heading', 'Candidates')
@section('content')
<x-page-header title="Candidates" />

<div class="mb-6 grid grid-cols-3 gap-4">
    <x-stat-card label="Total" :value="$stats['total']" icon="👤" />
    <x-stat-card label="In pipeline" :value="$stats['in_pipeline']" icon="🔄" />
    <x-stat-card label="Hired" :value="$stats['hired']" icon="✅" />
</div>

<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Search name or email" class="input w-64">
    <select name="status" class="input w-auto">
        <option value="">Any stage</option>
        @foreach(\App\Enums\ApplicationStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected(request('status')===$s->value)>{{ $s->label() }}</option>
        @endforeach
    </select>
    <button class="btn-primary">Search</button>
</form>

<div class="card overflow-hidden">
    @if($candidates->count())
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-start font-medium">Name</th>
                    <th class="text-start font-medium">Applications</th>
                    <th class="text-start font-medium">Tags</th>
                    <th class="px-5 text-start font-medium">Email</th>
                </tr>
            </thead>
            <tbody>
            @foreach($candidates as $candidate)
                <tr class="border-b border-slate-50 hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <a href="{{ route('hr.candidates.show', $candidate) }}" class="font-medium text-brand hover:underline">{{ $candidate->full_name }}</a>
                    </td>
                    <td class="text-slate-600">
                        @foreach($candidate->applications as $app)
                            <span class="badge-soft bg-slate-100 text-slate-600">{{ $app->jobPosition?->title }} · {{ $app->status->label() }}</span>
                        @endforeach
                    </td>
                    <td>@foreach($candidate->tags as $tag)<span class="badge-soft bg-blue-50 text-brand">#{{ $tag->name }}</span> @endforeach</td>
                    <td class="px-5 text-slate-500">{{ $candidate->email }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <x-empty-state title="No candidates yet" />
    @endif
</div>
<div class="mt-4">{{ $candidates->links() }}</div>
@endsection
