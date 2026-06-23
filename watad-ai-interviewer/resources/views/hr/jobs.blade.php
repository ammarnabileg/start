@extends('layouts.app')
@section('title', 'Jobs · Watad')
@section('heading', 'Jobs')
@section('content')
<div x-data="{ create: false }">
    <x-page-header title="Job positions">
        @can('jobs.create')
            <button @click="create = !create" class="btn-primary">＋ New job</button>
        @endcan
    </x-page-header>

    @if(session('invitation_link'))
        <div class="mb-4 rounded-lg border border-brand/20 bg-brand-light px-4 py-3 text-sm text-brand">
            Invitation link created:
            <a href="{{ session('invitation_link') }}" class="font-medium underline">{{ session('invitation_link') }}</a>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-3 gap-4">
        <x-stat-card label="Open" :value="$stats['open']" icon="🟢" />
        <x-stat-card label="Draft" :value="$stats['draft']" icon="📝" />
        <x-stat-card label="Closed / Paused" :value="$stats['closed']" icon="⏸️" />
    </div>

    @can('jobs.create')
    <form x-show="create" x-cloak method="POST" action="{{ route('hr.jobs.store') }}"
          class="card mb-6 grid gap-4 p-5 sm:grid-cols-2">
        @csrf
        <div class="sm:col-span-2"><label class="label">Title</label><input name="title" required class="input"></div>
        <div><label class="label">Seniority</label>
            <select name="seniority" class="input">
                @foreach(['intern','junior','mid','senior','lead','manager','director','executive'] as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
            </select></div>
        <div><label class="label">Currency</label><input name="currency" value="EGP" maxlength="3" class="input"></div>
        <div><label class="label">Salary min</label><input name="salary_min" type="number" class="input"></div>
        <div><label class="label">Salary max</label><input name="salary_max" type="number" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Description</label><textarea name="description" rows="3" class="input"></textarea></div>
        <div class="sm:col-span-2"><button class="btn-primary">Create job</button></div>
    </form>
    @endcan

    <div class="card overflow-hidden">
        @if($jobs->count())
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-slate-500">
                    <tr>
                        <th class="px-5 py-3 text-start font-medium">Title</th>
                        <th class="text-start font-medium">Department</th>
                        <th class="text-start font-medium">Seniority</th>
                        <th class="text-start font-medium">Status</th>
                        <th class="px-5 text-end font-medium">Invite</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($jobs as $job)
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-700">{{ $job->title }}</td>
                        <td class="text-slate-600">{{ $job->department?->name ?? '—' }}</td>
                        <td class="capitalize text-slate-600">{{ $job->seniority }}</td>
                        <td><span class="badge-soft bg-slate-100 capitalize text-slate-600">{{ $job->status }}</span></td>
                        <td class="px-5 text-end">
                            @can('invitations.create')
                                <form method="POST" action="{{ route('hr.jobs.invitations.create', $job) }}">
                                    @csrf
                                    <button class="text-xs font-medium text-brand hover:underline">Generate link</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <x-empty-state title="You haven't created a job position yet">
                @can('jobs.create')<button @click="create = true" class="btn-outline">Create your first job</button>@endcan
            </x-empty-state>
        @endif
    </div>
    <div class="mt-4">{{ $jobs->links() }}</div>
</div>
@endsection
