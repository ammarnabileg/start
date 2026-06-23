@extends('layouts.app')
@section('title', 'Settings · Watad')
@section('heading', 'Settings & integrations')
@section('content')
<div class="max-w-2xl rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5">
    <h2 class="font-semibold mb-4">Integration status</h2>
    <table class="w-full text-sm">
        @foreach($status as $label => $value)
            <tr class="border-b border-slate-50 dark:border-slate-800/50">
                <td class="py-2 text-slate-500">{{ $label }}</td>
                <td class="py-2 font-medium">
                    @php($bad = in_array($value, ['missing','disabled','none']))
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $bad ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $value }}</span>
                </td>
            </tr>
        @endforeach
    </table>
    <p class="text-xs text-slate-400 mt-4">
        Secrets (API keys, Google credentials, provider keys) are configured via environment variables and never edited from the UI.
        See <code>docs/18-deployment.md</code> and <code>docs/13-security-architecture.md</code>.
    </p>
</div>
@endsection
