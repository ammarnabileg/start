<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\InterviewSession;
use App\Models\RecruitmentJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class SuperAdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'companies' => Tenant::count(),
                'users' => User::where('user_type', '!=', 'candidate')->count(),
                'candidates' => Candidate::count(),
                'jobs' => RecruitmentJob::count(),
                'interviews' => InterviewSession::count(),
                'total_tokens' => AiUsageLog::sum('total_tokens'),
                'total_cost' => AiUsageLog::sum('cost_usd'),
            ],
            'recent_companies' => Tenant::latest()->take(5)->withCount(['users', 'jobs'])->get(),
            'ai_usage_by_day' => AiUsageLog::selectRaw('DATE(created_at) as date, SUM(total_tokens) as tokens, SUM(cost_usd) as cost')
                ->where('created_at', '>=', now()->subDays(30))->groupBy('date')->orderBy('date')->get(),
        ]);
    }

    public function companies(Request $request): JsonResponse
    {
        $companies = Tenant::withCount(['users', 'jobs'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()->paginate(20);

        return response()->json($companies);
    }

    public function createCompany(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string', 'industry' => 'nullable|string',
            'country' => 'nullable|string', 'domain' => 'nullable|unique:tenants',
            'owner_name' => 'required|string', 'owner_email' => 'required|email|unique:users,email',
            'owner_password' => 'required|min:8',
        ]);

        $slug = \Str::slug($request->name) . '-' . uniqid();
        $tenant = Tenant::create([
            'name' => $request->name,
            'slug' => $slug,
            'domain' => $request->domain,
            'industry' => $request->industry,
            'country' => $request->country,
        ]);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->owner_name,
            'email' => $request->owner_email,
            'password' => Hash::make($request->owner_password),
            'user_type' => 'hr',
        ]);
        $owner->assignRole('company_owner');

        $this->seedDefaultRoles($tenant);

        return response()->json($tenant->load(['users']), 201);
    }

    public function updateCompany(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update($request->only(['name', 'industry', 'country', 'status', 'openai_api_key', 'heygen_api_key', 'primary_color']));
        return response()->json($tenant);
    }

    public function toggleCompanyStatus(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => $tenant->status === 'active' ? 'suspended' : 'active']);
        return response()->json($tenant);
    }

    public function impersonate(User $user): JsonResponse
    {
        $token = JWTAuth::fromUser($user);
        return response()->json(['access_token' => $token, 'user' => $user->load('tenant'), 'impersonated' => true]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'total_users' => User::where('user_type', '!=', 'candidate')->count(),
                'tokens_today' => AiUsageLog::whereDate('created_at', today())->sum('total_tokens'),
            ],
            'recent_tenants' => Tenant::latest()->take(10)->withCount('users')->get(),
        ]);
    }

    public function impersonateTenant(Tenant $tenant): JsonResponse
    {
        $user = User::where('tenant_id', $tenant->id)->whereHas('roles', fn($q) => $q->where('name', 'company_owner'))->first()
            ?? User::where('tenant_id', $tenant->id)->first();

        if (!$user) return response()->json(['message' => 'No users in this tenant'], 404);

        $token = JWTAuth::fromUser($user);
        return response()->json(['access_token' => $token, 'token_type' => 'bearer', 'token' => $token, 'user' => $user, 'impersonated' => true]);
    }

    public function globalSettings(): JsonResponse
    {
        return response()->json(SystemSetting::getAll());
    }

    public function saveGlobalSettings(Request $request): JsonResponse
    {
        foreach ($request->all() as $key => $value) {
            SystemSetting::set($key, $value);
        }
        return response()->json(['message' => 'Settings updated']);
    }

    public function aiUsage(): JsonResponse
    {
        return response()->json([
            'by_day' => AiUsageLog::selectRaw('DATE(created_at) as date, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as requests')
                ->where('created_at', '>=', now()->subDays(30))->groupBy('date')->orderBy('date')->get(),
            'by_feature' => AiUsageLog::selectRaw('feature, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as requests')
                ->groupBy('feature')->orderByDesc('tokens')->get(),
            'total' => AiUsageLog::selectRaw('SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as requests')->first(),
        ]);
    }

    public function terminal(Request $request): JsonResponse
    {
        $request->validate(['command' => 'required|string']);

        $allowed = ['cache:clear', 'config:clear', 'route:clear', 'view:clear', 'optimize', 'optimize:clear', 'queue:restart', 'migrate --force', 'storage:link'];

        $command = trim($request->command);
        $isAllowed = collect($allowed)->contains(fn($a) => str_starts_with($command, $a));

        if (!$isAllowed) {
            return response()->json(['error' => 'Command not allowed'], 403);
        }

        \Artisan::call($command);
        return response()->json(['output' => \Artisan::output()]);
    }

    private function seedDefaultRoles(Tenant $tenant): void
    {
        $roles = ['company_owner', 'hr_director', 'hr_manager', 'recruiter', 'technical_interviewer', 'department_manager', 'operations_manager', 'executive_reviewer', 'viewer'];
        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }
    }
}
