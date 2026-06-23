@extends('layouts.app')
@section('title', 'Jobs · Watad')
@section('heading', 'Jobs')
@section('content')
<div x-data="{ open: false }" class="space-y-6">

    @if(session('invitation_link'))
        <div class="rounded-lg bg-indigo-50 text-indigo-800 px-4 py-3 text-sm">
            Invitation link created:
            <a href="{{ session('invitation_link') }}" class="font-medium underline break-all">{{ session('invitation_link') }}</a>
        </div>
    @endif

    <div class="flex justify-between items-center">
        <p class="text-sm text-slate-500">{{ $jobs->total() }} positions</p>
        @can('job.create')
            <button @click="open = !open" class="rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium">New job</button>
        @endcan
    </div>

    @can('job.create')
    <form x-show="open" x-cloak method="POST" action="{{ route('hr.jobs.store') }}"
          class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 grid sm:grid-cols-2 gap-4">
        @csrf
        <div class="sm:col-span-2"><label class="block text-sm mb-1">Title</label>
            <input name="title" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Seniority</label>
            <select name="seniority" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @foreach(['intern','junior','mid','senior','lead','manager','director','executive'] as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
            </select></div>
        <div><label class="block text-sm mb-1">Currency</label>
            <input name="currency" value="EGP" maxlength="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Salary min</label>
            <input name="salary_min" type="number" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Salary max</label>
            <input name="salary_max" type="number" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div class="sm:col-span-2"><label class="block text-sm mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></div>
        <div class="sm:col-span-2"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium">Create job</button></div>
    </form>
    @endcan

    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-slate-500 border-b border-slate-100 dark:border-slate-800">
                <tr><th class="text-start font-medium p-3">Title</th><th class="text-start font-medium">Department</th>
                    <th class="text-start font-medium">Seniority</th><th class="text-start font-medium">Status</th>
                    <th class="text-end font-medium p-3">Invite</th></tr>
            </thead>
            <tbody>
            @forelse($jobs as $job)
                <tr class="border-b border-slate-50 dark:border-slate-800/50">
                    <td class="p-3 font-medium">{{ $job->title }}</td>
                    <td>{{ $job->department?->name ?? '—' }}</td>
                    <td class="capitalize">{{ $job->seniority }}</td>
                    <td><span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs capitalize">{{ $job->status }}</span></td>
                    <td class="p-3 text-end">
                        @can('invitation.create')
                        <form method="POST" action="{{ route('hr.jobs.invitations.create', $job) }}">
                            @csrf
                            <button class="text-indigo-600 hover:underline text-xs">Generate link</button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-400">No jobs yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $jobs->links() }}
</div>
@endsection
