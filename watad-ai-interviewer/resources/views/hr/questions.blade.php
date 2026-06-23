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
            <div class="card p-4">
                <h3 class="font-semibold text-slate-800">{{ $lib->name }} <span class="text-xs text-slate-400">({{ $lib->questions->count() }})</span></h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach($lib->questions as $q)
                        <li class="flex gap-2"><span class="badge-soft bg-slate-100 text-slate-500">{{ $q->competency }}</span><span class="text-slate-600">{{ $q->text }}</span></li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="card"><x-empty-state title="No libraries yet — the AI also generates questions adaptively" /></div>
        @endforelse
    </div>
</div>
@endsection
