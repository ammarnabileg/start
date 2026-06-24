<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Department::where('tenant_id', auth()->user()->tenant_id)->withCount('jobs')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string', 'name_ar' => 'nullable|string', 'manager_id' => 'nullable|exists:users,id']);
        $dept = Department::create(array_merge($request->validated(), ['tenant_id' => auth()->user()->tenant_id]));
        return response()->json($dept, 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        abort_unless($department->tenant_id === auth()->user()->tenant_id, 403);
        $department->update($request->validated());
        return response()->json($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        abort_unless($department->tenant_id === auth()->user()->tenant_id, 403);
        $department->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
