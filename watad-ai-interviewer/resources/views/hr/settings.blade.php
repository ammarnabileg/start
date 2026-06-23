@extends('layouts.app')
@section('title', 'Settings · Watad')
@section('heading', 'Settings')
@section('content')
<x-page-header title="Settings & integrations" />

<div class="card max-w-2xl p-5">
    <h2 class="mb-4 font-semibold text-slate-800">Integration status</h2>
    <table class="w-full text-sm">
        @foreach($status as $label => $value)
            <tr class="border-b border-slate-50">
                <td class="py-2 text-slate-500">{{ $label }}</td>
                <td class="py-2 font-medium">
                    @php($bad = in_array($value, ['missing','disabled','none']))
                    <span class="badge-soft {{ $bad ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $value }}</span>
                </td>
            </tr>
        @endforeach
    </table>
    <p class="mt-4 text-xs text-slate-400">
        Secrets (API keys, Google credentials, provider keys) are set via environment variables or the
        installer (<code>install.php</code>) and never edited from the UI.
        See <code>docs/18-deployment.md</code> and <code>docs/13-security-architecture.md</code>.
    </p>
</div>
@endsection
