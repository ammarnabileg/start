@extends('layouts.app')
@section('title', 'Pipeline · Watad')
@section('heading', 'Pipeline')
@section('content')
@php
    $recoMeta = [
        'strong_hire' => ['Strong Hire', 'bg-emerald-100 text-emerald-700'],
        'hire'        => ['Hire',        'bg-teal-100 text-teal-700'],
        'maybe'       => ['Maybe',       'bg-amber-100 text-amber-700'],
        'reject'      => ['Reject',      'bg-red-100 text-red-700'],
    ];
@endphp

<div x-data="pipelineBoard(@js($apps), @js($board))" class="space-y-4">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[12rem]">
            <input x-model="search" type="search" placeholder="{{ __('Search candidates…') }}"
                   class="input ps-9" />
            <svg class="pointer-events-none absolute start-3 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3m1.8-5.2a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" /></svg>
        </div>
        <select x-model="jobFilter" class="input w-auto">
            <option value="">All jobs</option>
            @foreach($jobs as $job)<option value="{{ $job }}">{{ $job }}</option>@endforeach
        </select>
        <select x-model="recoFilter" class="input w-auto">
            <option value="">All recommendations</option>
            <option value="strong_hire">Strong Hire</option>
            <option value="hire">Hire</option>
            <option value="maybe">Maybe</option>
            <option value="reject">Reject</option>
        </select>
        <span class="text-xs text-slate-400" x-text="`${filtered().length} candidate(s)`"></span>
    </div>

    {{-- Board --}}
    <div class="flex gap-4 overflow-x-auto pb-4">
        <template x-for="col in board" :key="col.value">
            <div class="w-72 shrink-0">
                <div class="mb-2 flex items-center justify-between px-1">
                    <h3 class="text-sm font-medium text-slate-700" x-text="col.label"></h3>
                    <span class="badge-soft bg-slate-100 text-slate-500" x-text="itemsFor(col.value).length"></span>
                </div>
                <div class="min-h-[6rem] space-y-2 rounded-xl p-1 transition-colors"
                     :class="dragOver === col.value ? 'bg-brand-light/60 ring-2 ring-brand/30' : ''"
                     @dragover.prevent="dragOver = col.value" @dragleave="dragOver = null"
                     @drop="onDrop(col.value)">
                    <template x-for="item in itemsFor(col.value)" :key="item.id">
                        <div class="card cursor-grab p-3 active:cursor-grabbing"
                             draggable="true" @dragstart="dragId = item.id" @dragend="dragOver = null">
                            <div class="flex items-start gap-2">
                                <input type="checkbox" :value="item.id" x-model="selected"
                                       class="mt-1 h-3.5 w-3.5 rounded border-slate-300 text-brand" @click.stop />
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-light text-xs font-bold text-brand" x-text="item.initial"></span>
                                <div class="min-w-0 flex-1">
                                    <a :href="item.profileUrl" class="block truncate text-sm font-medium text-slate-700 hover:text-brand" x-text="item.name"></a>
                                    <div class="truncate text-xs text-slate-400" x-text="item.job"></div>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5 ps-9">
                                <template x-if="item.score !== null">
                                    <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold"
                                          :class="item.score >= 68 ? 'bg-emerald-50 text-emerald-600' : (item.score >= 50 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600')"
                                          x-text="item.score"></span>
                                </template>
                                <template x-if="item.recoLabel">
                                    <span class="rounded px-1.5 py-0.5 text-[11px] font-medium" :class="recoClass(item.reco)" x-text="item.recoLabel"></span>
                                </template>
                                <template x-if="item.interviewStatus">
                                    <span class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="statusDot(item.interviewStatus)"></span>
                                        <span x-text="item.interviewStatus.replace('_',' ')"></span>
                                    </span>
                                </template>
                                <template x-if="item.days !== null && item.days > 7">
                                    <span class="text-[11px] text-amber-500" :title="`${item.days} days since activity`">⏳<span x-text="item.days"></span>d</span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p x-show="!itemsFor(col.value).length" class="px-1 text-xs text-slate-300">—</p>
                </div>
            </div>
        </template>
    </div>

    {{-- Bulk action bar --}}
    <div x-show="selected.length" x-cloak
         class="fixed inset-x-0 bottom-4 z-20 mx-auto flex w-fit items-center gap-3 rounded-full border border-slate-200 bg-white px-5 py-2.5 shadow-lg">
        <span class="text-sm text-slate-600"><span class="font-semibold" x-text="selected.length"></span> selected</span>
        <select x-model="bulkTarget" class="input w-auto py-1 text-sm">
            <option value="">Move to…</option>
            <template x-for="col in board" :key="col.value"><option :value="col.value" x-text="col.label"></option></template>
        </select>
        <button @click="applyBulk()" :disabled="!bulkTarget" class="btn-primary py-1.5 disabled:opacity-50">Apply</button>
        <button @click="selected = []" class="text-sm text-slate-400 hover:text-slate-600">Clear</button>
    </div>
</div>

<script>
function pipelineBoard(apps, board) {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const move = (id, status) => fetch(`/hr/applications/${id}/move-stage`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ status }),
    });

    return {
        items: apps, board,
        search: '', jobFilter: '', recoFilter: '',
        dragId: null, dragOver: null,
        selected: [], bulkTarget: '',

        filtered() {
            const q = this.search.trim().toLowerCase();
            return this.items.filter(i =>
                (!q || i.name.toLowerCase().includes(q) || (i.email && i.email.toLowerCase().includes(q)) || i.job.toLowerCase().includes(q)) &&
                (!this.jobFilter || i.job === this.jobFilter) &&
                (!this.recoFilter || i.reco === this.recoFilter)
            );
        },
        itemsFor(status) { return this.filtered().filter(i => i.status === status); },

        onDrop(status) {
            this.dragOver = null;
            const item = this.items.find(i => i.id === this.dragId);
            this.dragId = null;
            if (!item || item.status === status) return;
            item.status = status;        // optimistic
            move(item.id, status).catch(() => {});
        },

        applyBulk() {
            if (!this.bulkTarget) return;
            const target = this.bulkTarget;
            this.items.filter(i => this.selected.includes(i.id)).forEach(i => {
                if (i.status !== target) { i.status = target; move(i.id, target).catch(() => {}); }
            });
            this.selected = []; this.bulkTarget = '';
        },

        recoClass(reco) {
            return {
                strong_hire: 'bg-emerald-100 text-emerald-700',
                hire: 'bg-teal-100 text-teal-700',
                maybe: 'bg-amber-100 text-amber-700',
                reject: 'bg-red-100 text-red-700',
            }[reco] || 'bg-slate-100 text-slate-500';
        },
        statusDot(s) {
            return {
                completed: 'bg-emerald-500', in_progress: 'bg-amber-500',
                processing: 'bg-blue-500', scheduled: 'bg-slate-300',
                abandoned: 'bg-slate-400', error: 'bg-red-500',
            }[s] || 'bg-slate-300';
        },
    };
}
</script>
@endsection
