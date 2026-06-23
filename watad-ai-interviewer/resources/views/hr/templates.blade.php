@extends('layouts.app')
@section('title', 'Templates · Watad')
@section('heading', 'Interview templates')
@section('content')
<div x-data="{ create: false }" class="space-y-6">
    <div class="flex justify-end">
        <button @click="create = !create" class="rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium">New template</button>
    </div>

    <form x-show="create" x-cloak method="POST" action="{{ route('hr.templates.store') }}"
          class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 grid sm:grid-cols-3 gap-4">
        @csrf
        <div class="sm:col-span-3"><label class="block text-sm mb-1">Name</label>
            <input name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Mode</label>
            <select name="mode" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option>text</option><option>voice</option><option>video</option></select></div>
        <div><label class="block text-sm mb-1">Language</label>
            <select name="language" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="en">English</option><option value="ar">العربية</option></select></div>
        <div><label class="block text-sm mb-1">Avatar</label>
            <select name="avatar_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @foreach($avatars as $a)<option value="{{ $a->id }}">{{ $a->name }} — {{ $a->role_label }}</option>@endforeach
            </select></div>
        <div><label class="block text-sm mb-1">Min Q</label><input name="min_questions" type="number" value="6" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Max Q</label><input name="max_questions" type="number" value="12" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Duration (min)</label><input name="max_duration_min" type="number" value="20" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label class="block text-sm mb-1">Follow-up depth</label><input name="follow_up_depth" type="number" value="2" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div class="sm:col-span-3"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium">Create</button></div>
    </form>

    @foreach($templates as $template)
        <div x-data="{ edit: false }" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5">
            <div class="flex justify-between items-center">
                <div>
                    <span class="font-semibold">{{ $template->name }}</span>
                    <span class="text-xs text-slate-500">· {{ $template->mode->value }} · {{ strtoupper($template->language) }} · {{ $template->avatar?->name }}</span>
                </div>
                <button @click="edit = !edit" class="text-sm text-indigo-600">Edit weights</button>
            </div>
            <form x-show="edit" x-cloak method="POST" action="{{ route('hr.templates.update', $template) }}" class="mt-4 grid sm:grid-cols-2 gap-3">
                @csrf @method('PUT')
                <input type="hidden" name="name" value="{{ $template->name }}">
                <input type="hidden" name="mode" value="{{ $template->mode->value }}">
                <input type="hidden" name="language" value="{{ $template->language }}">
                <input type="hidden" name="min_questions" value="{{ $template->min_questions }}">
                <input type="hidden" name="max_questions" value="{{ $template->max_questions }}">
                <input type="hidden" name="max_duration_min" value="{{ $template->max_duration_min }}">
                <input type="hidden" name="follow_up_depth" value="{{ $template->follow_up_depth }}">
                @php($weights = $template->competencies->keyBy('competency'))
                @foreach($competencies as $c)
                    @php($tc = $weights[$c->value] ?? null)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="enabled[]" value="{{ $c->value }}" @checked(!$tc || $tc->is_enabled)>
                        <span class="w-40">{{ $c->label() }}</span>
                        <input type="number" step="0.5" name="weights[{{ $c->value }}]" value="{{ $tc->weight ?? $c->defaultWeight() }}"
                               class="w-20 rounded border border-slate-300 px-2 py-1">
                    </label>
                @endforeach
                <div class="sm:col-span-2"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-white text-sm font-medium">Save weights</button></div>
            </form>
        </div>
    @endforeach
</div>
@endsection
