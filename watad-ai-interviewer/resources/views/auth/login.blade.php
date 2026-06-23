@extends('layouts.candidate')
@section('title', 'Sign in · Watad')
@section('content')
<div class="mx-auto max-w-sm">
    <h1 class="text-xl font-semibold mb-1">Sign in</h1>
    <p class="text-sm text-slate-500 mb-6">Watad AI Interviewer — HR console</p>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1">Email</label>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm mb-1">Password</label>
            <input name="password" type="password" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember"> Remember me
        </label>
        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-white font-medium hover:bg-indigo-700">Sign in</button>
    </form>
</div>
@endsection
