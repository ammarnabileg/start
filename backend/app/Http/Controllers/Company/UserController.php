<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::where('tenant_id', auth()->user()->tenant_id)
            ->with('roles')->whereNot('user_type', 'candidate')->get();
        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required', 'email' => 'required|email|unique:users', 'password' => 'required|min:6', 'role' => 'required|string']);
        $user = User::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name, 'email' => $request->email,
            'password' => Hash::make($request->password), 'user_type' => 'hr',
        ]);
        $user->assignRole($request->role);
        return response()->json($user->load('roles'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->tenant_id === auth()->user()->tenant_id, 403);
        $user->update($request->only(['name', 'phone', 'is_active', 'locale']));
        if ($request->role) { $user->syncRoles([$request->role]); }
        return response()->json($user->fresh('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        abort_unless($user->tenant_id === auth()->user()->tenant_id, 403);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
