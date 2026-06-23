@extends('layouts.app')
@section('title', 'Dashboard · Watad')
@section('heading', 'Dashboard')
@section('content')
<x-page-header title="Overview" />

<div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
    <x-stat-card label="Total candidates" :value="$metrics['total_candidates']" icon="👤" />
    <x-stat-card label="Interviews today" :value="$metrics['interviews_today']" icon="🎙️" />
    <x-stat-card label="Hired" :value="$metrics['hired']" icon="✅" />
    <x-stat-card label="Rejected" :value="$metrics['rejected']" icon="🚫" />
    <x-stat-card label="Conversion" :value="$metrics['conversion'].'%'" icon="📈" />
    <x-stat-card label="Avg score" :value="$metrics['avg_score']" icon="⭐" />
</div>

<div class="card mt-6 p-5" x-data="interviewChart(@js($chart))">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Interview volume</h2>
        <div class="flex gap-1 text-xs">
            <template x-for="g in ['daily','weekly','monthly']" :key="g">
                <button @click="grouping = g; render()"
                        :class="grouping === g ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'"
                        class="rounded-md px-2.5 py-1 capitalize" x-text="g"></button>
            </template>
        </div>
    </div>
    <canvas x-ref="canvas" height="80"></canvas>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card p-5 lg:col-span-1">
        <h2 class="mb-4 font-semibold text-slate-800">Hiring funnel</h2>
        @php($max = max(1, $funnel['applied']))
        @foreach($funnel as $stage => $count)
            <div class="mb-3">
                <div class="mb-1 flex justify-between text-sm">
                    <span class="capitalize text-slate-600">{{ $stage }}</span>
                    <span class="text-slate-400">{{ $count }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-brand" style="width: {{ round($count / $max * 100) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card overflow-hidden lg:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold text-slate-800">Recent results</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="text-slate-500">
                <tr class="border-b border-slate-100">
                    <th class="px-5 py-2.5 text-start font-medium">Candidate</th>
                    <th class="text-start font-medium">Position</th>
                    <th class="text-start font-medium">Score</th>
                    <th class="px-5 text-start font-medium">Recommendation</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recent as $interview)
                <tr class="border-b border-slate-50 hover:bg-slate-50">
                    <td class="px-5 py-2.5">
                        <a href="{{ route('hr.interviews.show', $interview->public_id) }}" class="text-brand hover:underline">
                            {{ $interview->candidate?->full_name }}
                        </a>
                    </td>
                    <td class="text-slate-600">{{ $interview->jobPosition?->title }}</td>
                    <td class="font-medium">{{ $interview->overall_score }}</td>
                    <td class="px-5">@include('components.reco-badge', ['recommendation' => $interview->recommendation])</td>
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
                data: { labels, datasets: [{ label: 'Interviews', data, borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.1)', fill: true, tension: .3 }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
        },
    };
}
</script>
@endsection
