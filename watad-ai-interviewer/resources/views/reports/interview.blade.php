@php
    $report = $interview->report;
    $b = $interview->behavioralAnalysis;
    $colors = ['strong_hire' => '#059669', 'hire' => '#0d9488', 'maybe' => '#d97706', 'reject' => '#dc2626'];
    $recColor = $colors[$interview->recommendation?->value] ?? '#64748b';
@endphp
<!DOCTYPE html>
<html @if($interview->language === 'ar') dir="rtl" @endif>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1e293b; font-size: 12px; line-height: 1.5; }
    h1 { font-size: 20px; margin: 0; }
    h2 { font-size: 14px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; margin: 18px 0 8px; color: #334155; }
    .muted { color: #64748b; }
    .header { border-bottom: 3px solid #4f46e5; padding-bottom: 12px; margin-bottom: 14px; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; color: #fff; font-weight: bold; background: {{ $recColor }}; }
    .score-big { font-size: 34px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    td, th { text-align: start; padding: 4px 6px; }
    .bar-bg { background: #e2e8f0; height: 8px; border-radius: 4px; width: 100%; }
    .bar { background: #4f46e5; height: 8px; border-radius: 4px; }
    .flag { border-inline-start: 4px solid #f59e0b; padding-inline-start: 8px; margin-bottom: 6px; }
    .flag.high { border-color: #dc2626; }
    .pagebreak { page-break-before: always; }
    .footer { position: fixed; bottom: -20px; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 9px; }
    ul { margin: 4px 0; padding-inline-start: 18px; }
</style>
</head>
<body>
<div class="footer">Watad AI Interview Report · generated {{ optional($report?->generated_at)->toDayDateTimeString() }} · model {{ $report?->model }} · confidential — internal hiring use</div>

<div class="header">
    <table>
        <tr>
            <td>
                <h1>AI Interview Report</h1>
                <div class="muted">{{ $interview->candidate?->full_name }} — {{ $interview->jobPosition?->title }}
                    ({{ $interview->jobPosition?->department?->name }})</div>
                <div class="muted">{{ optional($interview->completed_at)->toFormattedDateString() }} ·
                    {{ $interview->mode->value }} · {{ $interview->question_count }} questions ·
                    {{ $interview->duration_seconds ? gmdate('i:s', $interview->duration_seconds) : '—' }}
                    @if($interview->avatar) · {{ $interview->avatar->name }} ({{ $interview->avatar->role_label }})@endif
                </div>
            </td>
            <td style="text-align: end; width: 160px;">
                <div class="score-big">{{ $interview->overall_score ?? '—' }}<span class="muted" style="font-size:14px;">/100</span></div>
                <span class="badge">{{ $interview->recommendation?->label() ?? 'Pending' }}</span>
            </td>
        </tr>
    </table>
</div>

<h2>1. Candidate Information</h2>
<table>
    <tr><td class="muted">Email</td><td>{{ $interview->candidate?->email }}</td>
        <td class="muted">Phone</td><td>{{ $interview->candidate?->phone }}</td></tr>
    <tr><td class="muted">LinkedIn</td><td>{{ $interview->candidate?->linkedin_url }}</td>
        <td class="muted">Country</td><td>{{ $interview->candidate?->country }}</td></tr>
    <tr><td class="muted">Experience</td><td>{{ $interview->candidate?->years_experience }} yrs</td>
        <td class="muted">Expected salary</td><td>{{ $interview->candidate?->expected_salary }} {{ $interview->candidate?->salary_currency }}</td></tr>
    <tr><td class="muted">Notice period</td><td colspan="3">{{ $interview->candidate?->notice_period }}</td></tr>
</table>

<h2>2. Resume Summary</h2>
<p>{{ $report?->resume_summary ?? $interview->candidate?->latestCvAnalysis?->summary ?? '—' }}</p>

<h2>3. Interview Summary</h2>
<p>{{ $report?->interview_summary ?? '—' }}</p>

<h2>4. Strengths</h2>
<ul>@forelse($report?->strengths ?? [] as $s)<li>{{ $s }}</li>@empty<li class="muted">—</li>@endforelse</ul>

<h2>5. Weaknesses</h2>
<ul>@forelse($report?->weaknesses ?? [] as $w)<li>{{ $w }}</li>@empty<li class="muted">—</li>@endforelse</ul>

<h2>6. Technical Assessment</h2>
<p>{{ $report?->technical_assessment ?? '—' }}</p>
<table>
    @foreach($interview->competencyScores as $score)
        <tr>
            <td style="width: 35%;">{{ \App\Enums\Competency::tryFrom($score->competency)?->label() ?? $score->competency }}</td>
            <td style="width: 50%;"><div class="bar-bg"><div class="bar" style="width: {{ (int)$score->score }}%;"></div></div></td>
            <td style="width: 15%; text-align: end;">{{ (int)$score->score }}/100</td>
        </tr>
    @endforeach
</table>

<h2>7. Behavioral Assessment</h2>
@if($b)
    <p><strong>Personality:</strong> {{ $b->personality_type }} ·
       Growth mindset {{ (int)$b->growth_mindset_score }} · Stress handling {{ (int)$b->stress_handling_score }}</p>
    <table>
        <tr><td style="width:50%"><strong>DISC</strong>:
            @foreach(($b->disc ?? []) as $k=>$v) {{ $k }} {{ $v }} @endforeach</td>
            <td><strong>Big Five</strong>:
            @foreach(($b->big_five ?? []) as $k=>$v) {{ ucfirst(substr($k,0,4)) }} {{ $v }} @endforeach</td></tr>
    </table>
    <p>{{ $report?->behavioral_assessment ?? $b->observations }}</p>
@else
    <p class="muted">Not available.</p>
@endif

<h2>8. AI Analysis</h2>
<p>{{ $report?->ai_analysis ?? '—' }}</p>

<div class="pagebreak"></div>
<h2>9. Red Flags</h2>
@forelse($interview->redFlags as $flag)
    <div class="flag {{ $flag->severity }}">
        <strong>{{ ucwords(str_replace('_',' ',$flag->type)) }}</strong> ({{ $flag->severity }})<br>
        {{ $flag->description }}
    </div>
@empty
    <p style="color:#059669;">No red flags detected.</p>
@endforelse

<h2>10. Hiring Recommendation</h2>
<p><span class="badge">{{ $interview->recommendation?->label() }}</span></p>
<p>{{ $report?->hiring_recommendation ?? '—' }}</p>

@if($interview->events->count())
    <h2>Key Moments</h2>
    <table>
        @foreach($interview->events->sortBy('ms_offset')->take(12) as $e)
            <tr><td style="width: 50px;" class="muted">{{ gmdate('i:s', (int)($e->ms_offset/1000)) }}</td><td>{{ $e->label }}</td></tr>
        @endforeach
    </table>
@endif
</body>
</html>
