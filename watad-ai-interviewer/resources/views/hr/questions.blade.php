@extends('layouts.app')
@section('title', 'Question libraries · Watad')
@section('heading', 'Question Libraries')
@section('content')
<x-page-header title="Question libraries" />

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-1">
        <form method="POST" action="{{ route('hr.questions.libraries.store') }}" class="card space-y-2 p-4">
            @csrf
            <h3 class="text-sm font-semibold text-slate-800">New library</h3>
            <input name="name" placeholder="Library name" required class="input">
            <input name="description" placeholder="Description" class="input">
            <button class="btn-primary">Create</button>
        </form>

        <form method="POST" action="{{ route('hr.questions.store') }}" class="card space-y-2 p-4">
            @csrf
            <h3 class="text-sm font-semibold text-slate-800">Add question</h3>
            <select name="library_id" required class="input">@foreach($libraries as $lib)<option value="{{ $lib->id }}">{{ $lib->name }}</option>@endforeach</select>
            <select name="competency" class="input">@foreach($competencies as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach</select>
            <textarea name="text" rows="2" placeholder="Question (English)" required class="input"></textarea>
            <textarea name="text_ar" rows="2" placeholder="السؤال (بالعربية)" dir="rtl" class="input"></textarea>
            <select name="difficulty" class="input"><option>easy</option><option selected>standard</option><option>hard</option></select>
            <button class="btn-primary">Add</button>
        </form>
    </div>

    <div class="space-y-4 lg:col-span-2">
        @forelse($libraries as $lib)
            <div class="card p-4" x-data="{ editLib: false }">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-slate-800">{{ $lib->name }} <span class="text-xs text-slate-400">({{ $lib->questions->count() }})</span></h3>
                    @can('questions.update')
                        <button @click="editLib = !editLib" class="text-xs font-medium text-brand">Edit</button>
                    @endcan
                </div>
                @can('questions.update')
                    <form x-show="editLib" x-cloak method="POST" action="{{ route('hr.questions.libraries.update', $lib) }}" class="mt-3 space-y-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $lib->name }}" required class="input">
                        <input name="description" value="{{ $lib->description }}" placeholder="Description" class="input">
                        <button class="btn-primary">Save</button>
                    </form>
                @endcan
                <ul class="mt-2 space-y-1.5 text-sm">
                    @foreach($lib->questions as $q)
                        <li x-data="{ editQ: false }" class="rounded-lg {{ $q->is_active ? '' : 'opacity-50' }}">
                            <div class="flex items-start gap-2 py-1">
                                <span class="badge-soft bg-slate-100 text-slate-500">{{ $q->competency }}</span>
                                <span class="flex-1 text-slate-600">{{ $q->text }}</span>
                                @unless($q->is_active)<span class="badge-soft bg-slate-100 text-slate-400">Archived</span>@endunless
                                @can('questions.update')
                                    <button @click="editQ = !editQ" class="text-xs text-brand">Edit</button>
                                    <form method="POST" action="{{ route('hr.questions.toggle', $q) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="text-xs {{ $q->is_active ? 'text-amber-600' : 'text-emerald-600' }}">{{ $q->is_active ? 'Archive' : 'Restore' }}</button>
                                    </form>
                                @endcan
                            </div>
                            @can('questions.update')
                                <form x-show="editQ" x-cloak method="POST" action="{{ route('hr.questions.update', $q) }}" class="mb-2 space-y-2 rounded-lg bg-slate-50 p-3">
                                    @csrf @method('PUT')
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="competency" class="input">@foreach($competencies as $c)<option value="{{ $c->value }}" @selected($q->competency===$c->value)>{{ $c->label() }}</option>@endforeach</select>
                                        <select name="difficulty" class="input">@foreach(['easy','standard','hard'] as $d)<option @selected($q->difficulty===$d)>{{ $d }}</option>@endforeach</select>
                                    </div>
                                    <textarea name="text" rows="2" required class="input">{{ $q->text }}</textarea>
                                    <textarea name="text_ar" rows="2" dir="rtl" placeholder="بالعربية" class="input">{{ $q->text_ar }}</textarea>
                                    <button class="btn-primary">Save question</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="card"><x-empty-state title="No libraries yet — the AI also generates questions adaptively" /></div>
        @endforelse
    </div>
</div>
@endsection
