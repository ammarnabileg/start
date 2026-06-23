@props(['label' => '', 'value' => 0, 'icon' => '📦'])
<div class="stat-card">
    <div class="flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-light text-brand">{{ $icon }}</span>
        <span class="text-sm text-slate-500">{{ $label }}</span>
    </div>
    <span class="text-2xl font-semibold text-slate-800">{{ $value }}</span>
</div>
