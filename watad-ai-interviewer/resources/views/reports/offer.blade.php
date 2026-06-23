<!DOCTYPE html>
<html @if(($offer->application?->candidate?->salary_currency ?? '') === 'ar') dir="rtl" @endif>
<head><meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; color:#1e293b; }
    body { font-size: 13px; line-height: 1.6; padding: 10px 20px; }
    .head { border-bottom: 3px solid #2563eb; padding-bottom: 10px; margin-bottom: 18px; }
    h1 { font-size: 20px; margin: 0; } .muted { color:#64748b; }
    table { width:100%; border-collapse: collapse; margin: 16px 0; }
    td { padding: 6px 4px; } .label { color:#64748b; width:35%; }
    .sign { margin-top: 40px; border-top: 1px solid #cbd5e1; padding-top: 8px; width: 50%; }
</style></head>
<body>
@php($c = $offer->application?->candidate)
<div class="head">
    <h1>Offer of Employment</h1>
    <div class="muted">Watad — {{ $offer->title ?? $offer->application?->jobPosition?->title }}</div>
</div>

<p>Dear {{ $c?->full_name }},</p>
<p>We are delighted to offer you the position of
   <strong>{{ $offer->title ?? $offer->application?->jobPosition?->title }}</strong> at Watad.
   Please find the key terms below.</p>

<table>
    <tr><td class="label">Position</td><td>{{ $offer->title ?? $offer->application?->jobPosition?->title }}</td></tr>
    <tr><td class="label">Compensation</td><td>{{ $offer->salary ? number_format((float)$offer->salary).' '.$offer->currency : 'As discussed' }}</td></tr>
    <tr><td class="label">Start date</td><td>{{ optional($offer->start_date)->toFormattedDateString() ?? 'To be agreed' }}</td></tr>
    <tr><td class="label">Offer expires</td><td>{{ optional($offer->expires_at)->toFormattedDateString() ?? '—' }}</td></tr>
</table>

@if($offer->notes)<p>{{ $offer->notes }}</p>@endif

<p>We look forward to welcoming you to the team.</p>
<p class="muted">Warm regards,<br>The Watad Hiring Team</p>

<div class="sign">
    @if($offer->signed_at)
        Accepted &amp; signed: <strong>{{ $offer->signature_path }}</strong><br>
        <span class="muted">{{ $offer->signed_at->toFormattedDateString() }}</span>
    @else
        Candidate signature: ____________________________
    @endif
</div>
</body>
</html>
