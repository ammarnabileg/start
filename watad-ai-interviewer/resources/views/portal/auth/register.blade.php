@extends('portal.layout')
@section('title', 'Register · Watad Careers')
@section('content')
<div class="mx-auto max-w-sm">
    <div class="mb-6 flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-lg bg-brand font-bold text-white">W</span><span class="font-semibold">Watad Careers</span></div>
    <div class="card p-6">
        <h1 class="mb-1 text-xl font-semibold">Create your account</h1>
        @if($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="list-disc ps-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('portal.register') }}" class="space-y-4">
            @csrf
            <div><label class="label">Full name</label><input name="full_name" required class="input" value="{{ old('full_name') }}"></div>
            <div><label class="label">Email</label><input name="email" type="email" required class="input" value="{{ old('email') }}"></div>
            <div><label class="label">Password</label><input name="password" type="password" required class="input"></div>
            <div><label class="label">Confirm password</label><input name="password_confirmation" type="password" required class="input"></div>
            <button class="btn-primary w-full">Register</button>
        </form>
        <p class="mt-4 text-center text-sm text-slate-500">Have an account? <a href="{{ route('portal.login') }}" class="text-brand">Sign in</a></p>
    </div>
</div>
@endsection
