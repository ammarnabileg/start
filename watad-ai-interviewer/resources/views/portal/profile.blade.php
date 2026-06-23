@extends('portal.layout')
@section('title', 'Profile · Watad Careers')
@section('content')
<h1 class="mb-5 text-xl font-semibold">My profile</h1>
<form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" class="card grid gap-4 p-5 sm:grid-cols-2">
    @csrf @method('PUT')
    <div class="sm:col-span-2"><label class="label">Full name</label><input name="full_name" value="{{ $candidate->full_name }}" required class="input"></div>
    <div><label class="label">Phone</label><input name="phone" value="{{ $candidate->phone }}" class="input"></div>
    <div><label class="label">LinkedIn</label><input name="linkedin_url" type="url" value="{{ $candidate->linkedin_url }}" class="input"></div>
    <div><label class="label">Country</label><input name="country" value="{{ $candidate->country }}" class="input"></div>
    <div><label class="label">Years of experience</label><input name="years_experience" type="number" step="0.5" value="{{ $candidate->years_experience }}" class="input"></div>
    <div><label class="label">Expected salary</label><input name="expected_salary" type="number" value="{{ $candidate->expected_salary }}" class="input"></div>
    <div><label class="label">Notice period</label><input name="notice_period" value="{{ $candidate->notice_period }}" class="input"></div>
    <div class="sm:col-span-2"><label class="label">Upload new CV (PDF/DOCX)</label><input name="cv" type="file" accept=".pdf,.doc,.docx" class="input"></div>
    <div class="sm:col-span-2"><button class="btn-primary">Save profile</button></div>
</form>

@if($candidate->documents->isNotEmpty())
<div class="card mt-4 p-5 text-sm">
    <h2 class="mb-2 font-semibold">My documents</h2>
    @foreach($candidate->documents as $doc)
        <div class="border-b border-slate-50 py-1.5">{{ ucfirst($doc->type) }} v{{ $doc->version }} — {{ $doc->original_name }}</div>
    @endforeach
</div>
@endif
@endsection
