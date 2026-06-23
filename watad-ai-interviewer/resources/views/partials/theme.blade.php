{{-- Shared theme: Tailwind config + reusable component classes. Included by every layout so all
     interfaces share one simple, light, blue-accent style (matches the product UI). --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: { DEFAULT: '#2563eb', light: '#eff6ff', dark: '#1d4ed8' },
                },
                fontFamily: { sans: ['Inter', 'Cairo', 'system-ui', 'sans-serif'] },
            },
        },
    };
</script>
<style type="text/tailwindcss">
    @layer components {
        .btn-primary  { @apply inline-flex items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-dark disabled:opacity-50; }
        .btn-ghost    { @apply inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600 transition hover:bg-slate-50; }
        .btn-outline  { @apply inline-flex items-center justify-center gap-2 rounded-lg border border-brand px-4 py-2 text-sm font-medium text-brand transition hover:bg-brand-light; }
        .card         { @apply rounded-xl border border-slate-200 bg-white; }
        .stat-card    { @apply flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-5; }
        .chip         { @apply inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600; }
        .input        { @apply w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-brand focus:ring-2 focus:ring-brand/20; }
        .label        { @apply mb-1 block text-sm text-slate-600; }
        .nav-link     { @apply flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50; }
        .nav-active   { @apply bg-brand-light font-medium text-brand; }
        .nav-add      { @apply grid h-5 w-5 shrink-0 place-items-center rounded-full border border-slate-300 text-xs text-slate-400 transition hover:border-brand hover:text-brand; }
        .util-btn     { @apply grid h-9 w-9 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600; }
        .badge-soft   { @apply inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium; }
    }
</style>
<style>[x-cloak]{display:none!important}</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
