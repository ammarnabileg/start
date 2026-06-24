<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\TalentPool;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TalentPoolController extends Controller
{
    public function index(): JsonResponse
    {
        $pools = TalentPool::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('candidates')->with('creator')->latest()->get();
        return response()->json($pools);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string', 'name_ar' => 'nullable|string', 'description' => 'nullable|string', 'color' => 'nullable|string']);
        $pool = TalentPool::create(array_merge($request->validated(), ['tenant_id' => auth()->user()->tenant_id, 'created_by' => auth()->id()]));
        return response()->json($pool, 201);
    }

    public function show(TalentPool $pool): JsonResponse
    {
        abort_unless($pool->tenant_id === auth()->user()->tenant_id, 403);
        return response()->json($pool->load(['candidates.cvs', 'candidates.applications.job', 'candidates.skillScores']));
    }

    public function destroy(TalentPool $pool): JsonResponse
    {
        abort_unless($pool->tenant_id === auth()->user()->tenant_id, 403);
        $pool->delete();
        return response()->json(['message' => 'Pool deleted']);
    }

    public function candidates(TalentPool $pool): JsonResponse
    {
        abort_unless($pool->tenant_id === auth()->user()->tenant_id, 403);
        $candidates = $pool->candidates()->with(['applications' => fn($q) => $q->where('tenant_id', $pool->tenant_id)])->get();
        return response()->json($candidates);
    }

    public function addCandidate(Request $request, TalentPool $pool): JsonResponse
    {
        abort_unless($pool->tenant_id === auth()->user()->tenant_id, 403);
        $request->validate(['candidate_id' => 'required|exists:candidates,id']);
        $pool->candidates()->syncWithoutDetaching([$request->candidate_id => ['added_by' => auth()->id()]]);
        return response()->json(['message' => 'Candidate added']);
    }

    public function removeCandidate(TalentPool $pool, int $candidateId): JsonResponse
    {
        abort_unless($pool->tenant_id === auth()->user()->tenant_id, 403);
        $pool->candidates()->detach($candidateId);
        return response()->json(['message' => 'Candidate removed']);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['query' => 'required|string', 'pool_id' => 'nullable|exists:talent_pools,id']);
        $tenantId = auth()->user()->tenant_id;

        $candidates = \App\Models\Candidate::whereHas('applications', fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['skillScores', 'applications.job'])->get()->toArray();

        $service = new AIService(auth()->user()->tenant);
        $results = $service->searchTalentPool($request->query, $candidates);

        return response()->json($results);
    }
}
