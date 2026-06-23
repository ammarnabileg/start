@extends('layouts.candidate')
@section('title', 'Apply · '.$invitation->jobPosition->title)
@section('content')
<div class="card p-6">
    <h1 class="text-xl font-semibold text-slate-800">You're invited to interview</h1>
    <p class="mt-1 text-slate-600">
        Position: <strong>{{ $invitation->jobPosition->title }}</strong>
        · ~{{ $invitation->template?->max_duration_min ?? 20 }} min
        @if($invitation->avatar) · with {{ $invitation->avatar->name }} (AI {{ $invitation->avatar->role_label }})@endif
    </p>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc ps-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('candidate.invitation.intake', $invitation->token) }}"
          enctype="multipart/form-data" class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @csrf
        <div class="sm:col-span-2"><label class="label">Full name *</label><input name="full_name" required value="{{ old('full_name') }}" class="input"></div>
        <div><label class="label">Email *</label><input name="email" type="email" required value="{{ old('email') }}" class="input"></div>
        <div><label class="label">Mobile</label><input name="phone" value="{{ old('phone') }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">LinkedIn</label><input name="linkedin_url" type="url" value="{{ old('linkedin_url') }}" class="input"></div>
        <div><label class="label">Country</label><input name="country" value="{{ old('country') }}" class="input"></div>
        <div><label class="label">Years of experience</label><input name="years_experience" type="number" step="0.5" min="0" value="{{ old('years_experience') }}" class="input"></div>
        <div><label class="label">Expected salary ({{ $invitation->jobPosition->currency }})</label>
            <input name="expected_salary" type="number" step="100" value="{{ old('expected_salary') }}" class="input">
            <input type="hidden" name="salary_currency" value="{{ $invitation->jobPosition->currency }}"></div>
        <div><label class="label">Notice period</label><input name="notice_period" placeholder="e.g. 1 month" value="{{ old('notice_period') }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">CV (PDF / DOCX) *</label><input name="cv" type="file" accept=".pdf,.doc,.docx" required class="input"></div>
        <label class="flex items-start gap-2 text-sm text-slate-600 sm:col-span-2">
            <input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand">
            I consent to the processing and recording of this interview for hiring purposes.
        </label>
        <div class="sm:col-span-2"><button class="btn-primary w-full">Start interview →</button></div>
    </form>
</div>
@endsection
