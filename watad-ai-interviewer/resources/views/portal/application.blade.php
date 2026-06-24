@extends('portal.layout')
@section('title', 'Application · Watad Careers')
@section('content')
@php
    $steps = [
        'applied'        => 'Applied',
        'ai_screening'   => 'AI Screening',
        'qualified'      => 'Qualified',
        'tech_interview' => 'Interview',
        'final_review'   => 'Final Review',
        'offer'          => 'Offer',
        'hired'          => 'Hired',
    ];
    // Map the granular status onto the candidate-facing step.
    $stepFor = [
        'applied' => 'applied', 'ai_screening' => 'ai_screening', 'qualified' => 'qualified',
        'tech_interview' => 'tech_interview', 'manager_interview' => 'tech_interview',
        'final_review' => 'final_review', 'offer' => 'offer', 'hired' => 'hired',
    ];
    $order   = array_keys($steps);
    $status  = $application->status->value;
    $current = array_search($stepFor[$status] ?? 'applied', $order, true);
    $terminalBad = in_array($status, ['rejected', 'withdrawn', 'disqualified'], true);

    $next = [
        'applied'           => 'Your application has been received. The next step is a short AI screening interview.',
        'ai_screening'      => 'Please complete your AI interview. Our team reviews the result right after.',
        'qualified'         => 'You passed the AI screening 🎉 Our recruiters are reviewing your profile for the next interview.',
        'tech_interview'    => 'You have been shortlisted for an interview. Check below for scheduling details.',
        'manager_interview' => 'You are progressing through interviews. Watch this page and your email for details.',
        'final_review'      => 'Your interviews are complete and the team is making a final decision.',
        'offer'             => 'Congratulations — there is an offer waiting for you! Check the Offers page.',
        'hired'             => 'Welcome aboard! 🎉 Our team will be in touch with onboarding details.',
        'rejected'          => 'Thank you for your interest. We will not be moving forward this time, but we encourage you to apply again.',
        'withdrawn'         => 'This application has been withdrawn.',
        'disqualified'      => 'This application did not meet the requirements for this role.',
    ];
@endphp

<a href="{{ route('portal.applications') }}" class="text-sm text-brand">‹ My applications</a>
<h1 class="mb-1 mt-2 text-xl font-semibold">{{ $application->jobPosition?->title }}</h1>
<p class="mb-5 text-sm text-slate-500">
    Status:
    <span class="badge-soft {{ $terminalBad ? 'bg-slate-100 text-slate-600' : 'bg-brand-light text-brand' }}">{{ $application->status->label() }}</span>
</p>

{{-- Journey stepper --}}
@unless($terminalBad)
<div class="card p-5">
    <div class="flex items-center">
        @foreach($steps as $key => $label)
            @php($idx = array_search($key, $order, true))
            @php($done = $current !== false && $idx < $current)
            @php($isNow = $idx === $current)
            <div class="flex flex-1 flex-col items-center text-center">
                <span class="grid h-8 w-8 place-items-center rounded-full text-xs font-semibold
                    {{ $done ? 'bg-emerald-500 text-white' : ($isNow ? 'bg-brand text-white ring-4 ring-brand/20' : 'bg-slate-100 text-slate-400') }}">
                    {{ $done ? '✓' : $idx + 1 }}
                </span>
                <span class="mt-1.5 text-[11px] {{ $isNow ? 'font-semibold text-brand' : 'text-slate-500' }}">{{ $label }}</span>
            </div>
            @if(!$loop->last)
                <div class="mb-4 h-0.5 flex-1 {{ $done ? 'bg-emerald-500' : 'bg-slate-100' }}"></div>
            @endif
        @endforeach
    </div>
</div>
@endunless

{{-- What's next --}}
<div class="card mt-4 p-5 {{ $terminalBad ? '' : 'border-brand/20 bg-brand-light/40' }}">
    <h2 class="mb-1 text-sm font-semibold {{ $terminalBad ? 'text-slate-700' : 'text-brand' }}">
        {{ $terminalBad ? 'Update' : "What's next" }}
    </h2>
    <p class="text-sm text-slate-600">{{ $next[$status] ?? 'Your application is being processed.' }}</p>

    @if($status === 'offer')
        <a href="{{ route('portal.offers') }}" class="btn-primary mt-3 inline-block">Review your offer →</a>
    @endif
</div>

{{-- Scheduled interviews --}}
@if($application->humanInterviews->isNotEmpty())
<div class="card mt-4 p-5 text-sm">
    <h2 class="mb-2 font-semibold">Scheduled interviews</h2>
    @foreach($application->humanInterviews as $iv)
        <div class="flex items-center justify-between border-b border-slate-50 py-2 last:border-0">
            <span>{{ ucfirst($iv->type->value) }} · {{ $iv->scheduled_at?->format('M j, H:i') }} · {{ $iv->mode->value }}</span>
            @if($iv->meeting_url && $iv->status->value === 'scheduled')
                <a href="{{ $iv->meeting_url }}" class="btn-primary" target="_blank">Join</a>
            @endif
        </div>
    @endforeach
</div>
@endif
@endsection
