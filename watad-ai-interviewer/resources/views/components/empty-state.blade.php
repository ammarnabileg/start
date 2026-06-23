@props(['title' => 'Nothing here yet', 'cta' => null, 'href' => null])
<div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-slate-100 text-slate-400">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
             stroke="currentColor" class="h-8 w-8">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h4.5M4.5 4.5h15A1.5 1.5 0 0 1 21 6v12a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18V6a1.5 1.5 0 0 1 1.5-1.5Z" />
        </svg>
    </div>
    <p class="mb-3 text-sm text-slate-500">{{ $title }}</p>
    @if($cta && $href)
        <a href="{{ $href }}" class="btn-outline">{{ $cta }}</a>
    @endif
    {{ $slot }}
</div>
