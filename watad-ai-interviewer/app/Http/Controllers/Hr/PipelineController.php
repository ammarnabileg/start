<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\CandidatePipeline;
use App\Models\HiringPipeline;
use App\Models\Interview;
use Illuminate\View\View;

/**
 * Kanban board of candidates by pipeline stage. Candidates not yet placed appear under the first
 * stage by default; moves use InterviewController@moveStage.
 */
class PipelineController extends Controller
{
    public function index(): View
    {
        $pipeline = HiringPipeline::with('stages')->where('is_default', true)->first()
            ?? HiringPipeline::with('stages')->first();

        $stages = $pipeline?->stages ?? collect();

        // Placed candidates grouped by stage id.
        $placed = CandidatePipeline::with(['candidate', 'stage'])
            ->get()
            ->groupBy('stage_id');

        // Latest completed interview per candidate, for score/recommendation chips.
        $interviews = Interview::with('candidate')
            ->whereIn('status', ['completed', 'processing'])
            ->latest('completed_at')
            ->get()
            ->unique('candidate_id')
            ->keyBy('candidate_id');

        // Candidates with a completed interview but no pipeline row yet → bucket under first stage.
        $placedCandidateIds = $placed->flatten()->pluck('candidate_id')->all();
        $unplaced = $interviews->reject(fn ($i) => in_array($i->candidate_id, $placedCandidateIds, true));

        return view('hr.pipeline', compact('stages', 'placed', 'interviews', 'unplaced'));
    }
}
