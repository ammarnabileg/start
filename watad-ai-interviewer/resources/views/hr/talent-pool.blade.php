@extends('layouts.app')
@section('title', 'Talent Pool · Watad')
@section('heading', 'Talent Pool')
@section('content')
<div x-data="{ create: false }">
    <x-page-header title="Talent pools">
        @can('talent_pool.create')<button @click="create=!create" class="btn-primary">＋ New pool</button>@endcan
    </x-page-header>

    @can('talent_pool.create')
    <form x-show="create" x-cloak method="POST" action="{{ route('hr.talent-pool.store') }}" class="card mb-6 flex flex-wrap items-end gap-3 p-5">
        @csrf
        <div><label class="label">Name</label><input name="name" required class="input"></div>
        <div class="flex-1"><label class="label">Description</label><input name="description" class="input"></div>
        <button class="btn-primary">Create</button>
    </form>
    @endcan

    <div class="grid gap-4 md:grid-cols-2">
        @forelse($pools as $pool)
            <div class="card p-5">
                <h3 class="font-semibold text-slate-800">{{ $pool->name }} <span class="text-xs text-slate-400">({{ $pool->candidates_count }})</span></h3>
                <p class="text-sm text-slate-500">{{ $pool->description }}</p>
                <div class="mt-2 space-y-1 text-sm">
                    @foreach($pool->candidates as $c)
                        <a href="{{ route('hr.candidates.show', $c) }}" class="block text-brand hover:underline">{{ $c->full_name }}</a>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="card md:col-span-2"><x-empty-state title="No talent pools yet" /></div>
        @endforelse
    </div>
</div>
@endsection
