@extends('layouts.app')
@section('title', 'Schedule interview · Watad')
@section('heading', 'Schedule interview')
@section('content')
<x-page-header title="Schedule a human interview" />

@if($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('hr.human-interviews.store') }}" class="card grid gap-4 p-5 sm:grid-cols-2">
    @csrf
    <div class="sm:col-span-2"><label class="label">Application</label>
        <select name="application_id" required class="input">
            @foreach($applications as $app)
                <option value="{{ $app->id }}" @selected($preselect===$app->id)>{{ $app->candidate?->full_name }} — {{ $app->jobPosition?->title }} ({{ $app->status->label() }})</option>
            @endforeach
        </select></div>
    <div><label class="label">Type</label><select name="type" class="input"><option value="technical">Technical</option><option value="manager">Manager</option><option value="department">Department</option><option value="panel">Panel</option></select></div>
    <div><label class="label">Mode</label><select name="mode" class="input"><option value="online">Online</option><option value="onsite">Onsite</option></select></div>
    <div><label class="label">Meeting provider</label><select name="meeting_provider" class="input"><option value="manual">Manual link</option><option value="zoom">Zoom</option><option value="google_meet">Google Meet</option><option value="ms_teams">MS Teams</option><option value="onsite">Onsite</option></select></div>
    <div><label class="label">Meeting URL / location</label><input name="meeting_url" type="url" placeholder="https://…" class="input"></div>
    <div><label class="label">Date & time</label><input name="scheduled_at" type="datetime-local" required class="input"></div>
    <div><label class="label">Duration (min)</label><input name="duration_min" type="number" value="45" required class="input"></div>
    <div class="sm:col-span-2"><label class="label">Panelists (any department)</label>
        <select name="panelists[]" multiple size="6" required class="input">
            @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>@endforeach
        </select>
        <p class="mt-1 text-xs text-slate-400">Hold Ctrl/⌘ to select multiple. First selected = lead.</p></div>
    <div class="sm:col-span-2"><button class="btn-primary">Schedule & assign</button></div>
</form>
@endsection
