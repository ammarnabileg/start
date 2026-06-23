<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
      x-data="{ dark: localStorage.theme === 'dark' }" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Watad AI Interviewer')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-900 dark:text-slate-100 antialiased">
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="hidden md:flex w-60 flex-col border-e border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
        <div class="px-5 py-4 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white font-bold">W</span>
            <span class="font-semibold">Watad</span>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
            @php($nav = [['hr.dashboard','Dashboard'],['hr.jobs.index','Jobs'],['hr.interviews.index','Interviews']])
            @foreach($nav as [$route,$label])
                <a href="{{ Route::has($route) ? route($route) : '#' }}"
                   class="block rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 {{ request()->routeIs($route) ? 'bg-slate-100 dark:bg-slate-800 font-medium' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="p-3 border-t border-slate-100 dark:border-slate-800">
            @csrf
            <button class="w-full text-start rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">Sign out</button>
        </form>
    </aside>

    <div class="flex-1 flex flex-col">
        <header class="h-14 flex items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-6">
            <h1 class="font-semibold">@yield('heading', 'Dashboard')</h1>
            <div class="flex items-center gap-3">
                <button @click="dark = !dark; localStorage.theme = dark ? 'dark' : 'light'"
                        class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Toggle theme">
                    <span x-text="dark ? '☀️' : '🌙'"></span>
                </button>
                <span class="text-sm text-slate-500">{{ auth()->user()?->name }}</span>
            </div>
        </header>
        <main class="flex-1 p-6">
            @if(session('status'))
                <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
