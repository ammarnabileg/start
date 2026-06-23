<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Watad Interview')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-4 py-4 flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white font-bold">W</span>
            <span class="font-semibold">Watad</span>
            <span class="text-slate-400">· AI Interview</span>
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-8">
        @yield('content')
    </main>
    <footer class="mx-auto max-w-3xl px-4 py-8 text-center text-xs text-slate-400">
        Your responses are processed by an AI assistant to support Watad's hiring decisions.
    </footer>
</body>
</html>
