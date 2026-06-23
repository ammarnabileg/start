@extends('layouts.app')
@section('title', 'Question libraries · Watad')
@section('heading', 'Question libraries')
@section('content')
<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-4">
        <form method="POST" action="{{ route('hr.questions.libraries.store') }}"
              class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 space-y-2">
            @csrf
            <h3 class="font-semibold text-sm">New library</h3>
            <input name="name" placeholder="Library name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input name="description" placeholder="Description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-lg bg-indigo-600 px-3 py-2 text-white text-sm">Create</button>
        </form>

        <form method="POST" action="{{ route('hr.questions.store') }}"
              class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 space-y-2">
            @csrf
            <h3 class="font-semibold text-sm">Add question</h3>
            <select name="library_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($libraries as $lib)<option value="{{ $lib->id }}">{{ $lib->name }}</option>@endforeach
            </select>
            <select name="competency" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($competencies as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach
            </select>
            <textarea name="text" rows="2" placeholder="Question (English)" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <textarea name="text_ar" rows="2" placeholder="السؤال (بالعربية)" dir="rtl" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <select name="difficulty" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option>easy</option><option selected>standard</option><option>hard</option></select>
            <button class="rounded-lg bg-indigo-600 px-3 py-2 text-white text-sm">Add</button>
        </form>
    </div>

    <div class="lg:col-span-2 space-y-4">
        @forelse($libraries as $lib)
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
                <h3 class="font-semibold">{{ $lib->name }} <span class="text-xs text-slate-500">({{ $lib->questions->count() }})</span></h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach($lib->questions as $q)
                        <li class="flex gap-2"><span class="rounded bg-slate-100 dark:bg-slate-800 px-1.5 text-xs">{{ $q->competency }}</span><span>{{ $q->text }}</span></li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-slate-400 text-sm">No libraries yet. The AI engine generates questions adaptively; seeded questions act as anchors/few-shot.</p>
        @endforelse
    </div>
</div>
@endsection
