@extends('layouts.candidate')
@section('title', 'Apply · '.$invitation->jobPosition->title)
@section('content')
<div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
    <h1 class="text-xl font-semibold">You're invited to interview</h1>
    <p class="text-slate-600 mt-1">
        Position: <strong>{{ $invitation->jobPosition->title }}</strong>
        · ~{{ $invitation->template?->max_duration_min ?? 20 }} min
        @if($invitation->avatar) · with {{ $invitation->avatar->name }} (AI {{ $invitation->avatar->role_label }})@endif
    </p>

    @if($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc ps-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('candidate.invitation.intake', $invitation->token) }}"
          enctype="multipart/form-data" class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        @csrf
        <div class="sm:col-span-2">
            <label class="block text-sm mb-1">Full name *</label>
            <input name="full_name" required value="{{ old('full_name') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Email *</label>
            <input name="email" type="email" required value="{{ old('email') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Mobile</label>
            <input name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm mb-1">LinkedIn</label>
            <input name="linkedin_url" type="url" value="{{ old('linkedin_url') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Country</label>
            <input name="country" value="{{ old('country') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Years of experience</label>
            <input name="years_experience" type="number" step="0.5" min="0" value="{{ old('years_experience') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Expected salary ({{ $invitation->jobPosition->currency }})</label>
            <input name="expected_salary" type="number" step="100" value="{{ old('expected_salary') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <input type="hidden" name="salary_currency" value="{{ $invitation->jobPosition->currency }}">
        </div>
        <div>
            <label class="block text-sm mb-1">Notice period</label>
            <input name="notice_period" placeholder="e.g. 1 month" value="{{ old('notice_period') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm mb-1">CV (PDF / DOCX) *</label>
            <input name="cv" type="file" accept=".pdf,.doc,.docx" required class="w-full rounded-lg border border-slate-300 px-3 py-2 bg-white">
        </div>
        <label class="sm:col-span-2 flex items-start gap-2 text-sm text-slate-600">
            <input type="checkbox" name="consent" value="1" required class="mt-1">
            I consent to the processing and recording of this interview for hiring purposes.
        </label>
        <div class="sm:col-span-2">
            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-white font-medium hover:bg-indigo-700">
                Start interview →
            </button>
        </div>
    </form>
</div>
@endsection
