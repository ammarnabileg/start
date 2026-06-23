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
            <div x-data="{ edit: false }" class="card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-slate-800">{{ $template->name }}</span>
                        <span class="text-xs text-slate-500">· {{ $template->mode->value }} · {{ strtoupper($template->language) }} · {{ $template->avatar?->name }}</span>
                    </div>
                    <button @click="edit = !edit" class="text-sm text-brand">Edit weights</button>
                </div>
                <form x-show="edit" x-cloak method="POST" action="{{ route('hr.templates.update', $template) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf @method('PUT')
                    @foreach(['name'=>$template->name,'mode'=>$template->mode->value,'language'=>$template->language,'min_questions'=>$template->min_questions,'max_questions'=>$template->max_questions,'max_duration_min'=>$template->max_duration_min,'follow_up_depth'=>$template->follow_up_depth] as $k=>$v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    @php($weights = $template->competencies->keyBy('competency'))
                    @foreach($competencies as $c)
                        @php($tc = $weights[$c->value] ?? null)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="enabled[]" value="{{ $c->value }}" @checked(!$tc || $tc->is_enabled) class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand">
                            <span class="w-40 text-slate-600">{{ $c->label() }}</span>
                            <input type="number" step="0.5" name="weights[{{ $c->value }}]" value="{{ $tc->weight ?? $c->defaultWeight() }}" class="input w-20">
                        </label>
                    @endforeach
                    <div class="sm:col-span-2"><button class="btn-primary">Save weights</button></div>
                </form>
            </div>
        @empty
            <div class="card"><x-empty-state title="No templates yet" /></div>
        @endforelse
    </div>
</div>
@endsection
