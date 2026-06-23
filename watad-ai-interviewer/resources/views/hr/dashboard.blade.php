@extends('layouts.app')
@section('title', 'Dashboard · Watad')
@section('heading', 'Dashboard')
@section('content')
@php
    $cards = [
        ['Total candidates', $metrics['total_candidates']],
        ['Interviews today', $metrics['interviews_today']],
        ['Hired', $metrics['hired']],
        ['Rejected', $metrics['rejected']],
        ['Conversion', $metrics['conversion'].'%'],
        ['Avg score', $metrics['avg_score']],
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
    @foreach($cards as [$label, $value])
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
            <div class="text-xs text-slate-500">{{ $label }}</div>
            <div class="text-2xl font-semibold mt-1">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 mt-6"
     x-data="interviewChart(@js($chart))">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold">Interview volume</h2>
        <div class="flex gap-1 text-xs">
            <template x-for="g in ['daily','weekly','monthly']" :key="g">
                <button @click="grouping = g; render()"
                        :class="grouping === g ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'"
                        class="rounded px-2 py-1 capitalize" x-text="g"></button>
            </template>
        </div>
    </div>
    <canvas x-ref="canvas" height="80"></canvas>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
    <div class="lg:col-span-1 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5">
        <h2 class="font-semibold mb-4">Hiring funnel</h2>
        @php($max = max(1, $funnel['applied']))
        @foreach($funnel as $stage => $count)
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1"><span class="capitalize">{{ $stage }}</span><span class="text-slate-500">{{ $count }}</span></div>
                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-2 rounded-full bg-indigo-500" style="width: {{ round($count / $max * 100) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="lg:col-span-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5">
        <h2 class="font-semibold mb-4">Recent results</h2>
        <table class="w-full text-sm">
            <thead class="text-slate-500 text-start">
                <tr class="border-b border-slate-100 dark:border-slate-800">
                    <th class="text-start font-medium py-2">Candidate</th>
                    <th class="text-start font-medium">Position</th>
                    <th class="text-start font-medium">Score</th>
                    <th class="text-start font-medium">Recommendation</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recent as $interview)
                <tr class="border-b border-slate-50 dark:border-slate-800/50 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <td class="py-2">
                        <a href="{{ route('hr.interviews.show', $interview->public_id) }}" class="text-indigo-600 hover:underline">
                            {{ $interview->candidate?->full_name }}
                        </a>
                    </td>
                    <td>{{ $interview->jobPosition?->title }}</td>
                    <td class="font-medium">{{ $interview->overall_score }}</td>
                    <td>
                        @include('components.reco-badge', ['recommendation' => $interview->recommendation])
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-slate-400">No completed interviews yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
function interviewChart(daily) {
    return {
        grouping: 'daily', chart: null,
        init() { this.$nextTick(() => this.render()); },
        aggregate() {
            if (this.grouping === 'daily') {
                return { labels: daily.map(d => d.date.slice(5)), data: daily.map(d => d.count) };
            }
            const size = this.grouping === 'weekly' ? 7 : 30, labels = [], data = [];
            for (let i = 0; i < daily.length; i += size) {
                const chunk = daily.slice(i, i + size);
                labels.push(chunk[0].date.slice(5));
                data.push(chunk.reduce((s, d) => s + d.count, 0));
            }
            return { labels, data };
        },
        render() {
            const { labels, data } = this.aggregate();
            if (this.chart) this.chart.destroy();
            this.chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: { labels, datasets: [{ label: 'Interviews', data, borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79,70,229,.1)', fill: true, tension: .3 }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
        },
    };
}
</script>
@endsection
