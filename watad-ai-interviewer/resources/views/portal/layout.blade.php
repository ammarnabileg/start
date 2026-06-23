<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Watad Careers')</title>
    @include('partials.theme')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800">
@auth('candidate')
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-5xl items-center gap-4 px-4 py-3">
        <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand font-bold text-white">W</span>
            <span class="font-semibold">Watad Careers</span>
        </a>
        <nav class="ms-4 hidden gap-1 text-sm sm:flex">
            @foreach(['portal.dashboard'=>'Dashboard','portal.applications'=>'Applications','portal.interviews'=>'Interviews','portal.offers'=>'Offers','portal.profile'=>'Profile'] as $r=>$l)
                <a href="{{ route($r) }}" class="rounded-lg px-3 py-1.5 hover:bg-slate-100 {{ request()->routeIs($r) ? 'bg-brand-light text-brand font-medium' : 'text-slate-600' }}">{{ $l }}</a>
            @endforeach
        </nav>
        <div class="ms-auto flex items-center gap-3">
            <a href="{{ route('portal.notifications') }}" class="text-slate-400 hover:text-slate-600">🔔</a>
            <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="text-sm text-slate-500 hover:text-slate-700">Sign out</button></form>
        </div>
    </div>
</header>
@endauth
<main class="mx-auto max-w-5xl px-4 py-8">
    @if(session('status'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
