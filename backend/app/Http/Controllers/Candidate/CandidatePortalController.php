<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateCv;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\RecruitmentJob;
use App\Services\CVAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class CandidatePortalController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:candidates',
            'phone' => 'nullable|string',
            'years_experience' => 'nullable|integer|min:0',
            'target_salary' => 'nullable|numeric',
            'target_salary_currency' => 'nullable|string|size:3',
            'password' => 'required|min:6',
        ]);

        $candidate = Candidate::create($request->only(['name', 'email', 'phone', 'years_experience', 'target_salary', 'target_salary_currency', 'preferred_language']));

        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'candidate',
        ]);

        $candidate->update(['user_id' => $user->id]);
        $token = JWTAuth::fromUser($user);

        return response()->json(['candidate' => $candidate, 'access_token' => $token], 201);
    }

    public function portal(): JsonResponse
    {
        $candidate = $this->getCandidate();
        $applications = $candidate->applications()->with(['job.department', 'aiEvaluation', 'offer', 'interviewSessions' => fn($q) => $q->latest()->take(1)])->latest()->get();
        return response()->json([
            'profile' => $candidate->load('cvs'),
            'applications' => $applications,
            'unread_notifications' => \App\Models\Notification::where('candidate_id', $candidate->id)->whereNull('read_at')->count(),
        ]);
    }

    public function profile(): JsonResponse
    {
        $candidate = $this->getCandidate();
        return response()->json($candidate->load(['cvs', 'applications.job.tenant', 'applications.aiEvaluation']));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $candidate = $this->getCandidate();
        $candidate->update($request->only(['name', 'phone', 'city', 'country', 'years_experience', 'target_salary', 'target_salary_currency', 'linkedin_url', 'bio', 'preferred_language']));
        return response()->json($candidate);
    }

    public function uploadCv(Request $request): JsonResponse
    {
        $request->validate(['cv' => 'required|file|mimes:pdf,doc,docx|max:10240']);
        $candidate = $this->getCandidate();

        $file = $request->file('cv');
        $path = $file->store("cvs/candidate-{$candidate->id}", 'local');

        $cv = CandidateCv::create([
            'candidate_id' => $candidate->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'is_primary' => !$candidate->cvs()->where('is_primary', true)->exists(),
        ]);

        return response()->json($cv, 201);
    }

    public function applications(): JsonResponse
    {
        $candidate = $this->getCandidate();
        return response()->json($candidate->applications()->with(['job.tenant', 'aiEvaluation', 'offer', 'humanInterviews'])->latest()->get());
    }

    public function availableJobs(Request $request): JsonResponse
    {
        $jobs = RecruitmentJob::where('status', 'active')
            ->whereNull('expires_at')->orWhere('expires_at', '>', now())
            ->with('tenant:id,name,logo,career_page_title')
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->latest('published_at')->paginate(20);
        return response()->json($jobs);
    }

    public function applyToJob(Request $request, RecruitmentJob $job): JsonResponse
    {
        $request->validate(['cv_id' => 'nullable|exists:candidate_cvs,id']);
        $candidate = $this->getCandidate();

        $existing = Application::where('recruitment_job_id', $job->id)->where('candidate_id', $candidate->id)->first();
        if ($existing) return response()->json(['message' => 'Already applied', 'application' => $existing], 409);

        $cvId = $request->cv_id ?? $candidate->primaryCv?->id;

        $application = Application::create([
            'tenant_id' => $job->tenant_id,
            'recruitment_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'candidate_cv_id' => $cvId,
            'applied_at' => now(),
        ]);

        if ($cvId) {
            $cv = CandidateCv::find($cvId);
            dispatch(function () use ($cv, $job, $application) {
                $service = new CVAnalysisService(new \App\Services\AIService($job->tenant));
                $analysis = $service->analyzeForJob($cv, $job->toArray());
                $application->update(['cv_analysis' => $analysis, 'cv_match_score' => $analysis['match_score'] ?? 0]);
            })->afterResponse();
        }

        return response()->json($application->load('job.tenant'), 201);
    }

    public function notifications(): JsonResponse
    {
        $candidate = $this->getCandidate();
        $notifs = Notification::where('candidate_id', $candidate->id)->latest()->take(50)->get();
        $notifs->where('read_at', null)->each(fn($n) => $n->update(['read_at' => now()]));
        return response()->json($notifs);
    }

    public function offers(): JsonResponse
    {
        $candidate = $this->getCandidate();
        return response()->json(Offer::whereHas('application', fn($q) => $q->where('candidate_id', $candidate->id))->with(['application.job.tenant'])->get());
    }

    private function getCandidate(): Candidate
    {
        return Candidate::where('user_id', auth()->id())->firstOrFail();
    }
}
