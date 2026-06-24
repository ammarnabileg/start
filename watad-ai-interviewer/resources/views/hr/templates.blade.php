@extends('layouts.app')
@section('title', 'Templates · Watad')
@section('heading', 'Templates')
@section('content')
<div x-data="{ create: false }">
    <x-page-header title="Interview templates">
        <button @click="create = !create" class="btn-primary">＋ New template</button>
    </x-page-header>

    <form x-show="create" x-cloak method="POST" action="{{ route('hr.templates.store') }}"
          class="card mb-6 grid gap-4 p-5 sm:grid-cols-3">
        @csrf
        <div class="sm:col-span-3"><label class="label">Name</label><input name="name" required class="input"></div>
        <div><label class="label">Mode</label><select name="mode" class="input"><option>text</option><option>voice</option><option>video</option></select></div>
        <div><label class="label">Language</label><select name="language" class="input"><option value="en">English</option><option value="ar">العربية</option></select></div>
        <div><label class="label">Avatar</label><select name="avatar_id" class="input">@foreach($avatars as $a)<option value="{{ $a->id }}">{{ $a->name }} — {{ $a->role_label }}</option>@endforeach</select></div>
        <div><label class="label">Min Q</label><input name="min_questions" type="number" value="6" class="input"></div>
        <div><label class="label">Max Q</label><input name="max_questions" type="number" value="12" class="input"></div>
        <div><label class="label">Duration (min)</label><input name="max_duration_min" type="number" value="20" class="input"></div>
        <div><label class="label">Follow-up depth</label><input name="follow_up_depth" type="number" value="2" class="input"></div>
        <div class="sm:col-span-3"><button class="btn-primary">Create</button></div>
    </form>

    <div class="space-y-4">
        @forelse($templates as $template)
            <div x-data="{ edit: false }" class="card p-5 {{ $template->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-slate-800">{{ $template->name }}</span>
                        <span class="text-xs text-slate-500">· {{ $template->mode->value }} · {{ strtoupper($template->language) }} · {{ $template->avatar?->name }}</span>
                        @unless($template->is_active)<span class="badge-soft ms-1 bg-slate-100 text-slate-500">Archived</span>@endunless
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="edit = !edit" class="text-sm font-medium text-brand">Edit</button>
                        <form method="POST" action="{{ route('hr.templates.update', $template) }}"
                              onsubmit="return {{ $template->is_active ? "confirm('Archive this template?')" : 'true' }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="is_active" value="{{ $template->is_active ? 0 : 1 }}">
                            <button class="text-sm font-medium {{ $template->is_active ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ $template->is_active ? 'Archive' : 'Restore' }}
                            </button>
                        </form>
                    </div>
                </div>
                <form x-show="edit" x-cloak method="POST" action="{{ route('hr.templates.update', $template) }}" class="mt-4 space-y-4">
                    @csrf @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-3"><label class="label">Name</label><input name="name" value="{{ $template->name }}" required class="input"></div>
                        <div><label class="label">Mode</label>
                            <select name="mode" class="input">@foreach(['text','voice','video'] as $m)<option @selected($template->mode->value===$m)>{{ $m }}</option>@endforeach</select></div>
                        <div><label class="label">Language</label>
                            <select name="language" class="input"><option value="en" @selected($template->language==='en')>English</option><option value="ar" @selected($template->language==='ar')>العربية</option></select></div>
                        <div><label class="label">Avatar</label>
                            <select name="avatar_id" class="input"><option value="">—</option>@foreach($avatars as $a)<option value="{{ $a->id }}" @selected($template->avatar_id===$a->id)>{{ $a->name }}</option>@endforeach</select></div>
                        <div><label class="label">Min Q</label><input name="min_questions" type="number" value="{{ $template->min_questions }}" class="input"></div>
                        <div><label class="label">Max Q</label><input name="max_questions" type="number" value="{{ $template->max_questions }}" class="input"></div>
                        <div><label class="label">Duration (min)</label><input name="max_duration_min" type="number" value="{{ $template->max_duration_min }}" class="input"></div>
                        <div><label class="label">Follow-up depth</label><input name="follow_up_depth" type="number" value="{{ $template->follow_up_depth }}" class="input"></div>
                    </div>
                    <div>
                        <p class="label mb-2">Competency weights</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @php($weights = $template->competencies->keyBy('competency'))
                            @foreach($competencies as $c)
                                @php($tc = $weights[$c->value] ?? null)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="enabled[]" value="{{ $c->value }}" @checked(!$tc || $tc->is_enabled) class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand">
                                    <span class="w-40 text-slate-600">{{ $c->label() }}</span>
                                    <input type="number" step="0.5" name="weights[{{ $c->value }}]" value="{{ $tc->weight ?? $c->defaultWeight() }}" class="input w-20">
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button class="btn-primary">Save changes</button>
                </form>
            </div>
        @empty
            <div class="card"><x-empty-state title="No templates yet" /></div>
        @endforelse
    </div>
</div>
@endsection
