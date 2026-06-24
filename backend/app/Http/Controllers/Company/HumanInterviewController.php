<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\HumanInterview;
use App\Models\HumanInterviewEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HumanInterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $interviews = HumanInterview::whereHas('application', fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['application.candidate', 'application.job', 'scheduledBy', 'evaluations'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->where('scheduled_at', '>=', $request->from))
            ->latest('scheduled_at')->paginate(20);
        return response()->json($interviews);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'application_id' => 'required|exists:applications,id',
            'scheduled_at' => 'required|date|after:now',
            'interviewers' => 'required|array',
            'interviewers.*' => 'exists:users,id',
            'meeting_url' => 'nullable|url',
            'type' => 'nullable|in:technical,hr,manager,final',
            'duration_minutes' => 'nullable|integer|min:15',
            'notes' => 'nullable|string',
        ]);

        $application = Application::findOrFail($request->application_id);
        abort_unless($application->tenant_id === auth()->user()->tenant_id, 403);

        $interview = HumanInterview::create(array_merge($request->validated(), ['scheduled_by' => auth()->id()]));

        $application->update(['pipeline_stage' => 'tech_interview']);

        return response()->json($interview->load(['application.candidate', 'scheduledBy']), 201);
    }

    public function submitEvaluation(Request $request, HumanInterview $interview): JsonResponse
    {
        $request->validate([
            'technical_depth' => 'required|integer|min:1|max:5',
            'problem_solving' => 'required|integer|min:1|max:5',
            'communication' => 'required|integer|min:1|max:5',
            'culture_fit' => 'required|integer|min:1|max:5',
            'takes_ownership' => 'required|integer|min:1|max:5',
            'seniority_fit' => 'required|integer|min:1|max:5',
            'overall_rating' => 'required|integer|min:1|max:5',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'recommendation' => 'required|in:hire,reject,maybe,next_round',
            'notes' => 'nullable|string',
        ]);

        $evaluation = HumanInterviewEvaluation::updateOrCreate(
            ['human_interview_id' => $interview->id, 'evaluated_by' => auth()->id()],
            $request->validated()
        );

        return response()->json($evaluation);
    }

    public function update(Request $request, HumanInterview $interview): JsonResponse
    {
        $interview->update($request->only(['scheduled_at', 'meeting_url', 'status', 'notes', 'interviewers']));
        return response()->json($interview->fresh());
    }
}
