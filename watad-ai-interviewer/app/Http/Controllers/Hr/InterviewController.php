<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\PipelineStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InterviewController extends Controller
{
    public function index(Request $request): View
    {
        $interviews = Interview::with(['candidate', 'jobPosition'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('recommendation'), fn ($q) => $q->where('recommendation', $request->string('recommendation')))
            ->when($request->filled('job'), fn ($q) => $q->where('job_position_id', $request->integer('job')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total'       => Interview::count(),
            'completed'   => Interview::where('status', 'completed')->count(),
            'shortlisted' => Interview::whereIn('recommendation', ['strong_hire', 'hire'])->count(),
        ];

        return view('hr.interviews', compact('interviews', 'stats'));
    }

    public function show(Interview $interview): View
    {
        $interview->load([
            'candidate.latestCvAnalysis', 'jobPosition', 'avatar', 'competencyScores',
            'behavioralAnalysis', 'redFlags', 'videoAnalysis', 'report', 'events', 'messages',
        ]);

        return view('hr.report', compact('interview'));
    }

    public function reportPdf(Interview $interview): StreamedResponse
    {
        abort_unless($interview->report?->pdf_path && Storage::exists($interview->report->pdf_path), 404);

        return Storage::download(
            $interview->report->pdf_path,
            "watad-interview-{$interview->public_id}.pdf"
        );
    }

    public function moveStage(Request $request, Interview $interview): RedirectResponse
    {
        $request->validate(['stage_id' => ['required', 'exists:pipeline_stages,id']]);

        $interview->candidate->load([]);
        \App\Models\CandidatePipeline::updateOrCreate(
            ['candidate_id' => $interview->candidate_id, 'job_position_id' => $interview->job_position_id],
            ['stage_id' => $request->integer('stage_id'), 'moved_by' => $request->user()->id, 'moved_at' => now()],
        );

        return back()->with('status', 'Candidate moved to '.PipelineStage::find($request->integer('stage_id'))?->name);
    }
}
