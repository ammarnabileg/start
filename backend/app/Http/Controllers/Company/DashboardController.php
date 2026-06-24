<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Application;
use App\Models\InterviewSession;
use App\Models\RecruitmentJob;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        return response()->json([
            'stats' => [
                'active_jobs' => RecruitmentJob::where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'total_applications' => Application::where('tenant_id', $tenantId)->count(),
                'interviews_today' => InterviewSession::whereHas('application', fn($q) => $q->where('tenant_id', $tenantId))->whereDate('created_at', today())->count(),
                'pending_review' => Application::where('tenant_id', $tenantId)->where('pipeline_stage', 'qualified')->count(),
                'hired_this_month' => Application::where('tenant_id', $tenantId)->where('pipeline_stage', 'hired')->whereMonth('updated_at', now()->month)->count(),
                'ai_tokens_month' => AiUsageLog::where('tenant_id', $tenantId)->whereMonth('created_at', now()->month)->sum('total_tokens'),
            ],
            'pipeline_overview' => Application::where('tenant_id', $tenantId)->selectRaw('pipeline_stage, COUNT(*) as count')->groupBy('pipeline_stage')->get()->pluck('count', 'pipeline_stage'),
            'recent_applications' => Application::where('tenant_id', $tenantId)->with(['candidate', 'job'])->latest()->take(5)->get(),
            'attention_needed' => Application::where('tenant_id', $tenantId)->where('pipeline_stage', 'qualified')->with(['candidate', 'job', 'aiEvaluation'])->latest()->take(10)->get(),
        ]);
    }

    public function aiAnalytics(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        return response()->json([
            'usage_by_feature' => AiUsageLog::where('tenant_id', $tenantId)->selectRaw('feature, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as calls')->groupBy('feature')->get(),
            'usage_by_day' => AiUsageLog::where('tenant_id', $tenantId)->selectRaw('DATE(created_at) as date, SUM(total_tokens) as tokens, SUM(cost_usd) as cost')->where('created_at', '>=', now()->subDays(30))->groupBy('date')->orderBy('date')->get(),
            'total_this_month' => ['tokens' => AiUsageLog::where('tenant_id', $tenantId)->whereMonth('created_at', now()->month)->sum('total_tokens'), 'cost' => AiUsageLog::where('tenant_id', $tenantId)->whereMonth('created_at', now()->month)->sum('cost_usd')],
        ]);
    }

    public function copilot(Request $request): JsonResponse
    {
        $request->validate(['question' => 'required|string|max:500']);
        $tenant = auth()->user()->tenant;
        $tenantId = auth()->user()->tenant_id;

        $context = [
            'candidates' => Application::where('tenant_id', $tenantId)->with(['candidate', 'aiEvaluation.skillScores'])->get()->toArray(),
            'jobs' => RecruitmentJob::where('tenant_id', $tenantId)->where('status', 'active')->get()->toArray(),
        ];

        $service = new AIService($tenant);
        $answer = $service->recruitmentCopilot($request->question, $context);

        return response()->json(['answer' => $answer]);
    }
}
