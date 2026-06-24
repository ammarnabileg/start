<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Services\Hiring\ApplicationWorkflow;
use Illuminate\View\View;

/**
 * Kanban board over job_applications, grouped by application status (docs/23 tab 6).
 * The board is rendered client-side (Alpine) so search, filters, drag-and-drop and bulk
 * actions work without a round-trip per interaction.
 */
class PipelineController extends Controller
{
    public function index(): View
    {
        $apps = JobApplication::with(['candidate', 'jobPosition', 'aiInterview'])
            ->latest('last_activity_at')
            ->get()
            ->map(fn (JobApplication $a) => [
                'id'              => $a->public_id,
                'name'            => $a->candidate?->full_name ?? '—',
                'email'           => $a->candidate?->email ?? '',
                'initial'         => mb_strtoupper(mb_substr($a->candidate?->full_name ?? '?', 0, 1)),
                'job'             => $a->jobPosition?->title ?? '—',
                'status'          => $a->status->value,
                'score'           => $a->aiInterview?->overall_score !== null ? round($a->aiInterview->overall_score) : null,
                'reco'            => $a->aiInterview?->recommendation?->value,
                'recoLabel'       => $a->aiInterview?->recommendation?->label(),
                'interviewStatus' => $a->aiInterview?->status?->value,
                'days'            => $a->last_activity_at ? (int) $a->last_activity_at->diffInDays(now()) : null,
                'profileUrl'      => $a->candidate ? route('hr.candidates.show', $a->candidate) : '#',
            ])
            ->values();

        return view('hr.pipeline', [
            'board' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], ApplicationWorkflow::BOARD),
            'apps'  => $apps,
            'jobs'  => $apps->pluck('job')->unique()->filter()->sort()->values(),
        ]);
    }
}
