<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\JobCriteria;
use App\Models\RecruitmentJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobCriteriaController extends Controller
{
    public function index(RecruitmentJob $job): JsonResponse
    {
        abort_unless($job->tenant_id === auth()->user()->tenant_id, 403);
        return response()->json($job->criteria()->orderBy('order')->get());
    }

    public function store(Request $request, RecruitmentJob $job): JsonResponse
    {
        abort_unless($job->tenant_id === auth()->user()->tenant_id, 403);
        $request->validate(['criterion' => 'required|string', 'criterion_ar' => 'nullable|string', 'weight' => 'required|integer|min:1|max:100', 'target_score' => 'required|integer|min:1|max:5', 'description' => 'nullable|string']);
        $criteria = $job->criteria()->create($request->validated());
        return response()->json($criteria, 201);
    }

    public function update(Request $request, JobCriteria $criteria): JsonResponse
    {
        abort_unless($criteria->job->tenant_id === auth()->user()->tenant_id, 403);
        $criteria->update($request->validated());
        return response()->json($criteria);
    }

    public function destroy(JobCriteria $criteria): JsonResponse
    {
        abort_unless($criteria->job->tenant_id === auth()->user()->tenant_id, 403);
        $criteria->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
