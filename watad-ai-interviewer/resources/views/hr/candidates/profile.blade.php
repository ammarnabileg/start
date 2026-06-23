@extends('layouts.app')
@section('title', $candidate->full_name.' · Watad')
@section('heading', 'Candidate')
@section('content')
<div x-data="{ tab: 'overview' }" class="space-y-6">
    <div class="card flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ $candidate->full_name }}</h2>
            <p class="text-sm text-slate-500">{{ $candidate->email }} · {{ $candidate->phone }} · {{ $candidate->country }}</p>
            <div class="mt-1">@foreach($candidate->tags as $tag)<span class="badge-soft bg-blue-50 text-brand">#{{ $tag->name }}</span> @endforeach</div>
        </div>
        <form method="POST" action="{{ route('hr.candidates.tags.store', $candidate) }}" class="flex items-end gap-2">
            @csrf
            <input name="name" placeholder="add tag" class="input w-32">
            <button class="btn-ghost">Tag</button>
        </form>
    </div>

    <div class="flex flex-wrap gap-2 border-b border-slate-200 text-sm">
        @foreach(['overview'=>'Overview','applications'=>'Applications','ai'=>'AI Interviews','human'=>'Human Reviews','documents'=>'Documents','notes'=>'Notes','timeline'=>'Timeline','offers'=>'Offers'] as $k=>$label)
            <button @click="tab='{{ $k }}'" :class="tab==='{{ $k }}' ? 'border-brand text-brand' : 'border-transparent text-slate-500'" class="-mb-px border-b-2 px-3 py-2">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Overview --}}
    <div x-show="tab==='overview'" class="card p-5 text-sm text-slate-600">
        <div class="grid gap-2 sm:grid-cols-2">
            <div>Experience: {{ $candidate->years_experience ?? '—' }} yrs</div>
            <div>Expected salary: {{ $candidate->expected_salary ?? '—' }} {{ $candidate->salary_currency }}</div>
            <div>Notice period: {{ $candidate->notice_period ?? '—' }}</div>
            <div>LinkedIn: <a href="{{ $candidate->linkedin_url }}" class="text-brand">{{ $candidate->linkedin_url ? 'profile' : '—' }}</a></div>
        </div>
        @if($cv = $candidate->latestCvAnalysis)
            <p class="mt-3"><span class="font-medium text-slate-700">AI CV summary:</span> {{ $cv->summary }}</p>
        @endif
    </div>

    {{-- Applications + decisions --}}
    <div x-show="tab==='applications'" x-cloak class="space-y-4">
        @forelse($candidate->applications as $app)
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-slate-800">{{ $app->jobPosition?->title }}</span>
                        <span class="badge-soft bg-slate-100 text-slate-600">{{ $app->status->label() }}</span>
                    </div>
                    @if($ai = $app->aiInterview)
                        <span class="text-sm text-slate-500">AI: {{ $ai->overall_score ?? '—' }} ({{ $ai->recommendation?->label() }})</span>
                    @endif
                </div>

                @can('decisions.advance')
                <form method="POST" action="{{ route('hr.applications.decision', $app) }}" class="mt-3 flex flex-wrap items-center gap-2">
                    @csrf
                    <select name="decision" class="input w-auto">
                        <option value="advance">Advance</option>
                        <option value="reject">Reject</option>
                        @can('decisions.approve')<option value="approve">Approve (→ offer)</option>@endcan
                        <option value="hold">Hold</option>
                    </select>
                    <input name="reason" placeholder="reason (required for override)" class="input w-64">
                    @can('decisions.override_ai')
                        <label class="flex items-center gap-1 text-xs text-slate-500"><input type="checkbox" name="override" value="1"> Override AI</label>
                    @endcan
                    <button class="btn-primary">Record</button>
                    @can('interviews.schedule')
                        <a href="{{ route('hr.human-interviews.create', ['application' => $app->id]) }}" class="btn-ghost">Schedule interview</a>
                    @endcan
                </form>
                @endcan

                @can('decisions.make_offer')
                    @if(in_array($app->status->value, ['final_review','offer']))
                    <form method="POST" action="{{ route('hr.offers.store', $app) }}" class="mt-2 flex flex-wrap items-end gap-2">
                        @csrf
                        <input name="salary" type="number" placeholder="salary" class="input w-28">
                        <input name="currency" value="{{ $app->jobPosition?->currency }}" class="input w-20">
                        <input name="start_date" type="date" class="input w-40">
                        <button class="btn-ghost">Make offer</button>
                    </form>
                    @endif
                @endcan

                @if($app->humanInterviews->count())
                    <div class="mt-3 text-xs text-slate-500">Interviews:
                        @foreach($app->humanInterviews as $hi)
                            <a href="{{ route('hr.human-interviews.show', $hi) }}" class="text-brand">{{ ucfirst($hi->type->value) }} ({{ $hi->status->value }})</a>@if(!$loop->last) · @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="card"><x-empty-state title="No applications yet" /></div>
        @endforelse
    </div>

    {{-- AI interviews --}}
    <div x-show="tab==='ai'" x-cloak class="card p-5 text-sm">
        @forelse($candidate->interviews as $iv)
            <div class="mb-2 flex items-center justify-between border-b border-slate-50 py-2">
                <span>{{ $iv->created_at?->toDateString() }} · {{ $iv->status->value }}</span>
                <span>{{ $iv->overall_score ?? '—' }} @if($iv->status->value==='completed')<a href="{{ route('hr.interviews.show', $iv->public_id) }}" class="text-brand ms-2">open</a>@endif</span>
            </div>
        @empty <p class="text-slate-400">No AI interviews.</p> @endforelse
    </div>

    {{-- Human reviews --}}
    <div x-show="tab==='human'" x-cloak class="card p-5 text-sm">
        @php($humans = $candidate->applications->flatMap->humanInterviews)
        @forelse($humans as $hi)
            <div class="mb-2 border-b border-slate-50 py-2">
                <a href="{{ route('hr.human-interviews.show', $hi) }}" class="font-medium text-brand">{{ ucfirst($hi->type->value) }} interview</a>
                <span class="text-slate-500"> · {{ $hi->status->value }} · rating {{ $hi->aggregate_rating ?? '—' }}/5 · {{ $hi->evaluations->count() }} eval(s)</span>
            </div>
        @empty <p class="text-slate-400">No human interviews.</p> @endforelse
    </div>

    {{-- Documents --}}
    <div x-show="tab==='documents'" x-cloak class="card p-5 text-sm">
        <form method="POST" action="{{ route('hr.candidates.documents.store', $candidate) }}" enctype="multipart/form-data" class="mb-4 flex flex-wrap items-end gap-2">
            @csrf
            <select name="type" class="input w-auto"><option>cv</option><option>portfolio</option><option>certificate</option><option>attachment</option></select>
            <input type="file" name="document" required class="input w-64">
            <button class="btn-ghost">Upload</button>
        </form>
        @forelse($candidate->documents as $doc)
            <div class="flex items-center justify-between border-b border-slate-50 py-1.5">
                <span>{{ ucfirst($doc->type) }} v{{ $doc->version }} — {{ $doc->original_name }}</span>
                <a href="{{ route('hr.candidates.documents.download', [$candidate, $doc->id]) }}" class="text-brand">download</a>
            </div>
        @empty <p class="text-slate-400">No documents.</p> @endforelse
    </div>

    {{-- Notes --}}
    <div x-show="tab==='notes'" x-cloak class="card p-5 text-sm">
        <form method="POST" action="{{ route('hr.candidates.notes.store', $candidate) }}" class="mb-4 flex items-end gap-2">
            @csrf
            <textarea name="body" rows="2" required placeholder="Add internal note…" class="input"></textarea>
            <button class="btn-primary">Add</button>
        </form>
        @forelse($candidate->notes as $note)
            <div class="mb-2 border-b border-slate-50 py-2">
                <div class="text-slate-700">{{ $note->body }}</div>
                <div class="text-xs text-slate-400">{{ $note->author?->name }} · {{ $note->created_at?->diffForHumans() }}</div>
            </div>
        @empty <p class="text-slate-400">No notes.</p> @endforelse
    </div>

    {{-- Timeline --}}
    <div x-show="tab==='timeline'" x-cloak class="card p-5 text-sm">
        @forelse($candidate->activities as $act)
            <div class="flex items-start gap-3 py-1.5">
                <span class="w-32 shrink-0 text-xs text-slate-400">{{ $act->occurred_at?->format('Y-m-d H:i') }}</span>
                <span class="text-slate-600">{{ $act->summary }}</span>
            </div>
        @empty <p class="text-slate-400">No activity yet.</p> @endforelse
    </div>

    {{-- Offers --}}
    <div x-show="tab==='offers'" x-cloak class="card p-5 text-sm">
        @php($offers = $candidate->applications->flatMap->offers)
        @forelse($offers as $offer)
            <div class="flex items-center justify-between border-b border-slate-50 py-2">
                <span>{{ $offer->title }} · {{ $offer->salary }} {{ $offer->currency }}</span>
                <span><span class="badge-soft bg-slate-100 text-slate-600">{{ $offer->status->label() }}</span>
                    <a href="{{ route('hr.offers.show', $offer) }}" class="text-brand ms-2">open</a></span>
            </div>
        @empty <p class="text-slate-400">No offers.</p> @endforelse
    </div>
</div>
@endsection
