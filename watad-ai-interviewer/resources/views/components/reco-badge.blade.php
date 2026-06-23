@php
    /** @var \App\Enums\Recommendation|null $recommendation */
    $map = [
        'green' => 'bg-emerald-100 text-emerald-700',
        'teal'  => 'bg-teal-100 text-teal-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'red'   => 'bg-red-100 text-red-700',
    ];
    $color = $recommendation?->color() ?? 'amber';
@endphp
@if($recommendation)
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $map[$color] }}">
        {{ $recommendation->label() }}
    </span>
@else
    <span class="text-slate-400 text-xs">—</span>
@endif
