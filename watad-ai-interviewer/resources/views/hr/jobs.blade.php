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
                        <th class="px-5 text-end font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($jobs as $job)
                    <tr class="border-b border-slate-50 hover:bg-slate-50" x-data="{ editing: false }">
                        <td class="px-5 py-3 font-medium text-slate-700">{{ $job->title }}</td>
                        <td class="text-slate-600">{{ $job->department?->name ?? '—' }}</td>
                        <td class="capitalize text-slate-600">{{ $job->seniority }}</td>
                        <td>
                            <span @class([
                                'badge-soft capitalize',
                                'bg-emerald-50 text-emerald-600' => $job->status === 'open',
                                'bg-amber-50 text-amber-600'      => $job->status === 'paused',
                                'bg-slate-100 text-slate-500'     => in_array($job->status, ['closed', 'draft']),
                            ])>{{ $job->status }}</span>
                        </td>
                        <td class="px-5 text-end">
                            <div class="flex items-center justify-end gap-3">
                                @can('invitations.create')
                                    <form method="POST" action="{{ route('hr.jobs.invitations.create', $job) }}">
                                        @csrf
                                        <button class="text-xs font-medium text-brand hover:underline">Generate link</button>
                                    </form>
                                @endcan
                                @can('jobs.update')
                                    <button @click="editing = !editing" class="text-xs font-medium text-slate-500 hover:underline">Edit</button>
                                    @if($job->status === 'open')
                                        <form method="POST" action="{{ route('hr.jobs.status', $job) }}"
                                              onsubmit="return confirm('Archive this job? It will stop accepting candidates.')">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="closed">
                                            <button class="text-xs font-medium text-amber-600 hover:underline">Archive</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('hr.jobs.status', $job) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="open">
                                            <button class="text-xs font-medium text-emerald-600 hover:underline">Re-open</button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @can('jobs.update')
                    <tr x-show="editing" x-cloak class="border-b border-slate-100 bg-slate-50/60">
                        <td colspan="5" class="px-5 py-4">
                            <form method="POST" action="{{ route('hr.jobs.update', $job) }}" class="grid gap-4 sm:grid-cols-2">
                                @csrf @method('PUT')
                                <div class="sm:col-span-2"><label class="label">Title</label>
                                    <input name="title" value="{{ $job->title }}" required class="input"></div>
                                <div><label class="label">Seniority</label>
                                    <select name="seniority" class="input">
                                        @foreach(['intern','junior','mid','senior','lead','manager','director','executive'] as $s)
                                            <option value="{{ $s }}" @selected($job->seniority === $s)>{{ ucfirst($s) }}</option>
                                        @endforeach
                                    </select></div>
                                <div><label class="label">Status</label>
                                    <select name="status" class="input">
                                        @foreach(['draft','open','paused','closed'] as $st)
                                            <option value="{{ $st }}" @selected($job->status === $st)>{{ ucfirst($st) }}</option>
                                        @endforeach
                                    </select></div>
                                <div><label class="label">Currency</label>
                                    <input name="currency" value="{{ $job->currency }}" maxlength="3" class="input"></div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><label class="label">Salary min</label>
                                        <input name="salary_min" type="number" value="{{ $job->salary_min }}" class="input"></div>
                                    <div><label class="label">Salary max</label>
                                        <input name="salary_max" type="number" value="{{ $job->salary_max }}" class="input"></div>
                                </div>
                                <div class="sm:col-span-2"><label class="label">Description</label>
                                    <textarea name="description" rows="3" class="input">{{ $job->description }}</textarea></div>
                                <div class="sm:col-span-2 flex gap-2">
                                    <button class="btn-primary">Save changes</button>
                                    <button type="button" @click="editing = false" class="btn-outline">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endcan
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
