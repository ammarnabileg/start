<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\TalentPool;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $applications = Application::where('tenant_id', $tenantId)
            ->with(['candidate.primaryCv', 'job', 'aiEvaluation.skillScores', 'aiEvaluation.riskFlags'])
            ->when($request->job_id, fn($q) => $q->where('recruitment_job_id', $request->job_id))
            ->when($request->stage, fn($q) => $q->where('pipeline_stage', $request->stage))
            ->when($request->recommendation, fn($q) => $q->where('ai_recommendation', $request->recommendation))
            ->when($request->min_score, fn($q) => $q->where('overall_score', '>=', $request->min_score))
            ->when($request->search, fn($q) => $q->whereHas('candidate', fn($c) => $c->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%")))
            ->latest()->paginate(20);

        return response()->json($applications);
    }

    public function show(Application $application): JsonResponse
    {
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);

        return response()->json($application->load([
            'candidate.cvs', 'candidate.talentPools',
            'job.criteria', 'cv',
            'aiEvaluation.skillScores', 'aiEvaluation.behavioralAnalysis', 'aiEvaluation.riskFlags',
            'interviewSessions.messages',
            'humanInterviews.evaluations.evaluator',
            'offer',
        ]));
    }

    public function updateStage(Request $request, Application $application): JsonResponse
    {
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);
        $request->validate(['pipeline_stage' => 'required|in:' . implode(',', Application::PIPELINE_STAGES)]);

        $application->update(['pipeline_stage' => $request->pipeline_stage, 'status' => $request->pipeline_stage]);
        \App\Models\AuditLog::record('application.stage_changed', $application, [], ['stage' => $request->pipeline_stage]);

        return response()->json($application);
    }

    public function bulkUpdateStage(Request $request): JsonResponse
    {
        $request->validate([
            'application_ids' => 'required|array',
            'pipeline_stage' => 'required|in:' . implode(',', Application::PIPELINE_STAGES),
        ]);

        $tenantId = auth()->user()->tenant_id;
        Application::whereIn('id', $request->application_ids)
            ->where('tenant_id', $tenantId)
            ->update(['pipeline_stage' => $request->pipeline_stage, 'status' => $request->pipeline_stage]);

        return response()->json(['message' => 'Updated successfully']);
    }

    public function addNote(Request $request, Application $application): JsonResponse
    {
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);
        $request->validate(['note' => 'required|string']);
        $notes = $application->hr_notes ? $application->hr_notes . "\n\n[" . now()->toDateTimeString() . " - " . auth()->user()->name . "]\n" . $request->note : $request->note;
        $application->update(['hr_notes' => $notes]);
        return response()->json($application);
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate(['application_ids' => 'required|array|min:2|max:5', 'question' => 'nullable|string']);
        $tenantId = auth()->user()->tenant_id;

        $applications = Application::whereIn('id', $request->application_ids)
            ->where('tenant_id', $tenantId)
            ->with(['candidate', 'aiEvaluation.skillScores', 'aiEvaluation.behavioralAnalysis', 'aiEvaluation.riskFlags', 'job'])
            ->get();

        $comparison = null;
        if ($request->question || $request->ai_comparison) {
            $service = new AIService(auth()->user()->tenant);
            $comparison = $service->compareCandidates($applications->toArray(), $request->question);
        }

        return response()->json(['applications' => $applications, 'ai_comparison' => $comparison]);
    }

    public function addToTalentPool(Request $request, Candidate $candidate): JsonResponse
    {
        $request->validate(['pool_ids' => 'required|array', 'pool_ids.*' => 'exists:talent_pools,id']);
        $tenantId = auth()->user()->tenant_id;

        foreach ($request->pool_ids as $poolId) {
            $pool = TalentPool::where('id', $poolId)->where('tenant_id', $tenantId)->firstOrFail();
            $pool->candidates()->syncWithoutDetaching([$candidate->id => ['added_by' => auth()->id(), 'notes' => $request->notes]]);
        }

        return response()->json(['message' => 'Added to talent pool(s)']);
    }

    public function candidateProfile(Candidate $candidate): JsonResponse
    {
        return response()->json($candidate->load([
            'cvs', 'applications.job', 'applications.aiEvaluation',
            'talentPools', 'skillScores', 'behavioralAnalyses',
        ]));
    }

    public function reEvaluate(Application $application): JsonResponse
    {
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);

        $session = $application->interviewSessions()->where('status', 'completed')->latest()->first();
        if (!$session) return response()->json(['message' => 'No completed interview session found'], 422);

        $service = app(\App\Services\InterviewService::class);
        dispatch(fn() => $service->evaluateInterview($session))->afterResponse();

        return response()->json(['message' => 'Re-evaluation queued']);
    }

    public function export(Application $application): JsonResponse
    {
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);
        $application->load(['candidate', 'job', 'aiEvaluation.skillScores', 'aiEvaluation.behavioralAnalysis', 'aiEvaluation.riskFlags']);
        $url = route('applications.export.pdf', $application->id);
        return response()->json(['url' => $url, 'message' => 'PDF generation not fully set up — data available']);
    }
}
