@props(['title' => ''])
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-800">{{ $title }}</h1>
    <div class="flex items-center gap-2">
        {{ $slot }}
    </div>
</div>
