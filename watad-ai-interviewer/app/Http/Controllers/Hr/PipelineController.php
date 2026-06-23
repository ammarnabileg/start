<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Services\Hiring\ApplicationWorkflow;
use Illuminate\View\View;

/**
 * Kanban board over job_applications, grouped by application status (docs/23 tab 6).
 */
class PipelineController extends Controller
{
    public function index(): View
    {
        $applications = JobApplication::with(['candidate', 'jobPosition', 'aiInterview'])
            ->latest('last_activity_at')
            ->get()
            ->groupBy(fn (JobApplication $a) => $a->status->value);

        return view('hr.pipeline', [
            'board'        => ApplicationWorkflow::BOARD,
            'applications' => $applications,
        ]);
    }
}
