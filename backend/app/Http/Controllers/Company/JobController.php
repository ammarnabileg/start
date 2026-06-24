<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RecruitmentJob;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function __construct(private AIService $aiService) {}

    public function index(Request $request): JsonResponse
    {
        $jobs = RecruitmentJob::where('tenant_id', auth()->user()->tenant_id)
            ->with(['department', 'avatar', 'creator'])
            ->withCount('applications')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->latest()->paginate(20);

        return response()->json($jobs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'avatar_id' => 'nullable|exists:avatars,id',
            'seniority' => 'required|in:' . implode(',', RecruitmentJob::SENIORITY_LEVELS),
            'salary_min' => 'nullable|numeric',
            'salary_max' => 'nullable|numeric',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'requirements' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'benefits' => 'nullable|string',
            'interview_type' => 'required|in:text,voice,video',
            'interview_language' => 'nullable|in:ar,en,both',
            'max_questions' => 'nullable|integer|min:5|max:20',
            'interview_duration' => 'nullable|integer',
            'status' => 'nullable|in:draft,active',
        ]);

        $job = RecruitmentJob::create(array_merge($validated, [
            'tenant_id' => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
        ]));

        AuditLog::record('job.created', $job);

        return response()->json($job->load(['department', 'avatar']), 201);
    }

    public function show(RecruitmentJob $job): JsonResponse
    {
        $this->authorizeJob($job);
        return response()->json($job->load(['department', 'avatar', 'creator', 'criteria', 'questionBank'])->append('applicationStats'));
    }

    public function update(Request $request, RecruitmentJob $job): JsonResponse
    {
        $this->authorizeJob($job);
        $old = $job->toArray();
        $job->update($request->validated());
        AuditLog::record('job.updated', $job, $old, $job->toArray());
        return response()->json($job->fresh(['department', 'avatar']));
    }

    public function destroy(RecruitmentJob $job): JsonResponse
    {
        $this->authorizeJob($job);
        AuditLog::record('job.deleted', $job);
        $job->delete();
        return response()->json(['message' => 'Job deleted']);
    }

    public function duplicate(RecruitmentJob $job): JsonResponse
    {
        $this->authorizeJob($job);
        $new = $job->replicate(['status', 'published_at']);
        $new->title = $job->title . ' (Copy)';
        $new->status = 'draft';
        $new->save();

        foreach ($job->criteria as $criterion) {
            $new->criteria()->create($criterion->except(['id', 'recruitment_job_id'])->toArray());
        }

        AuditLog::record('job.duplicated', $new);
        return response()->json($new, 201);
    }

    public function publish(RecruitmentJob $job): JsonResponse
    {
        $this->authorizeJob($job);
        $job->update(['status' => 'active', 'published_at' => now()]);
        return response()->json($job);
    }

    public function aiGenerate(Request $request): JsonResponse
    {
        $request->validate(['title' => 'required', 'seniority' => 'required', 'industry' => 'nullable']);
        $tenant = auth()->user()->tenant;
        $service = new AIService($tenant);
        $result = $service->generateJobDescription($request->all());
        return response()->json($result);
    }

    public function generateQuestions(RecruitmentJob $job): JsonResponse
    {
        $this->authorizeJob($job);
        $tenant = auth()->user()->tenant;
        $service = new AIService($tenant);
        $questions = $service->generateInterviewQuestions($job->toArray(), $job->criteria->toArray());
        return response()->json($questions);
    }

    public function generateLink(Request $request, RecruitmentJob $job): JsonResponse
    {
        $this->authorizeJob($job);
        $request->validate([
            'candidate_email' => 'required|email',
            'interview_type' => 'nullable|in:text,voice,video',
        ]);

        $candidate = \App\Models\Candidate::firstOrCreate(
            ['email' => $request->candidate_email],
            ['name' => $request->candidate_name ?? $request->candidate_email]
        );

        $application = \App\Models\Application::firstOrCreate(
            ['recruitment_job_id' => $job->id, 'candidate_id' => $candidate->id],
            ['tenant_id' => auth()->user()->tenant_id, 'applied_at' => now()]
        );

        $service = new \App\Services\InterviewService(new AIService(auth()->user()->tenant));
        $link = $service->generateInvitationLink($application, $request->interview_type ?? $job->interview_type);

        return response()->json([
            'link' => url('/interview/' . $link->token),
            'token' => $link->token,
            'expires_at' => $link->expires_at,
        ]);
    }

    private function authorizeJob(RecruitmentJob $job): void
    {
        abort_unless($job->tenant_id === auth()->user()->tenant_id, 403);
    }
}
