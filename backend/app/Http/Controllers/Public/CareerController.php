<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentJob;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class CareerController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)->where('status', 'active')->firstOrFail();
        return response()->json([
            'company' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'logo' => $tenant->career_page_logo ?? $tenant->logo,
                'title' => $tenant->career_page_title ?? $tenant->name,
                'description' => $tenant->career_page_description,
                'primary_color' => $tenant->primary_color,
            ],
            'jobs_count' => RecruitmentJob::where('tenant_id', $tenant->id)->where('status', 'active')->count(),
        ]);
    }

    public function jobs(string $slug): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $jobs = RecruitmentJob::where('tenant_id', $tenant->id)->where('status', 'active')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('department')->latest('published_at')->get();
        return response()->json($jobs);
    }
}
