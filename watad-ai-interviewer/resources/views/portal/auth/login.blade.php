@extends('portal.layout')
@section('title', 'Sign in · Watad Careers')
@section('content')
<div class="mx-auto max-w-sm">
    <div class="mb-6 flex items-center gap-2"><span class="grid h-9 w-9 place-items-center rounded-lg bg-brand font-bold text-white">W</span><span class="font-semibold">Watad Careers</span></div>
    <div class="card p-6">
        <h1 class="mb-1 text-xl font-semibold">Candidate sign in</h1>
        <p class="mb-5 text-sm text-slate-500">Access your applications, interviews and offers.</p>
        @if($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('portal.login') }}" class="space-y-4">
            @csrf
            <div><label class="label">Email</label><input name="email" type="email" required autofocus class="input"></div>
            <div><label class="label">Password</label><input name="password" type="password" required class="input"></div>
            <button class="btn-primary w-full">Sign in</button>
        </form>
        <p class="mt-4 text-center text-sm text-slate-500">No account? <a href="{{ route('portal.register') }}" class="text-brand">Register</a></p>
    </div>
</div>
@endsection
