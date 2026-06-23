@extends('layouts.candidate')
@section('title', 'Sign in')
@section('content')
<div class="mx-auto max-w-sm">
    <div class="card p-6">
        <h1 class="mb-1 text-xl font-semibold text-slate-800">Sign in</h1>
        <p class="mb-6 text-sm text-slate-500">Watad AI Interviewer — HR console</p>

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div><label class="label">Email</label><input name="email" type="email" value="{{ old('email') }}" required autofocus class="input"></div>
            <div><label class="label">Password</label><input name="password" type="password" required class="input"></div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand"> Remember me</label>
            <button class="btn-primary w-full">Sign in</button>
        </form>
    </div>
</div>
@endsection
