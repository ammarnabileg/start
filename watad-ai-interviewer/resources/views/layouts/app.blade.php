<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Watad AI Interviewer')</title>
    @include('partials.theme')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-screen overflow-hidden bg-slate-50 font-sans text-slate-800">
@php
    $groups = [
        ['Recruiting', [
            ['hr.dashboard', 'Dashboard', null, false],
            ['hr.jobs.index', 'Jobs', 'jobs.view', true],
            ['hr.interviews.index', 'AI Interviews', 'ai_interviews.view', false],
            ['hr.pipeline.index', 'Pipeline', 'pipelines.view', false],
        ]],
        ['Setup', [
            ['hr.templates.index', 'Templates', 'templates.view', true],
            ['hr.avatars.index', 'Avatars', 'avatars.view', true],
            ['hr.questions.index', 'Question Libraries', 'questions.view', true],
        ]],
        ['Administration', [
            ['hr.users.index', 'Users', 'users.view', true],
            ['hr.roles.index', 'Roles & Permissions', 'roles.view', false],
            ['hr.settings.index', 'Settings', 'settings.view', false],
        ]],
    ];
@endphp

<div class="flex h-screen">
    {{-- Icon rail --}}
    <aside class="flex w-[68px] shrink-0 flex-col items-center gap-1 border-e border-slate-200 bg-white py-3">
        <a href="{{ route('hr.dashboard') }}" class="mb-2 grid h-10 w-10 place-items-center rounded-xl bg-brand text-lg font-bold text-white">W</a>
        <a href="{{ route('hr.dashboard') }}"
           class="flex w-14 flex-col items-center gap-1 rounded-xl bg-brand-light px-1 py-2 text-brand">
            <span class="text-lg">👥</span>
            <span class="text-[10px] leading-tight text-center">Hiring</span>
        </a>
        <div class="flex-1"></div>
        @can('settings.view')
            <a href="{{ route('hr.settings.index') }}" class="util-btn" title="Settings">⚙️</a>
        @endcan
    </aside>

    {{-- Section panel --}}
    <aside class="flex w-64 shrink-0 flex-col border-e border-slate-200 bg-white">
        <div class="flex items-center justify-between px-5 py-4">
            <h2 class="text-lg font-semibold text-brand">Watad Hiring</h2>
        </div>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4">
            @foreach($groups as [$title, $items])
                @php($visible = collect($items)->filter(fn ($i) => $i[2] === null || auth()->user()?->can($i[2])))
                @if($visible->isNotEmpty())
                    <div>
                        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $title }}</div>
                        @foreach($visible as [$route, $label, $perm, $canAdd])
                            <a href="{{ Route::has($route) ? route($route) : '#' }}"
                               class="nav-link {{ request()->routeIs($route) ? 'nav-active' : '' }}">
                                <span>{{ $label }}</span>
                                @if($canAdd)
                                    <span class="nav-add">+</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Top bar --}}
        <header class="flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4">
            <div class="flex items-center gap-1">
                <span class="rounded-t-lg border-b-2 border-brand bg-slate-50 px-4 py-1.5 text-sm font-medium text-slate-700">
                    @yield('heading', 'Dashboard')
                </span>
            </div>
            <div class="flex-1"></div>
            <button class="util-btn" title="Search">🔍</button>
            <button class="util-btn" title="Help">❔</button>
            <button class="util-btn" title="Notifications">🔔</button>
            @can('settings.view')<a href="{{ route('hr.settings.index') }}" class="util-btn" title="Settings">⚙️</a>@endcan
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-1">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-brand text-sm font-semibold text-white">
                        {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                    </span>
                    <span class="text-slate-400">▾</span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute end-0 mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    <div class="border-b border-slate-100 px-3 py-2 text-sm">
                        <div class="font-medium text-slate-700">{{ auth()->user()?->name }}</div>
                        <div class="text-xs text-slate-400">{{ auth()->user()?->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full px-3 py-2 text-start text-sm text-slate-600 hover:bg-slate-50">Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 overflow-y-auto p-6">
            @if(session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
