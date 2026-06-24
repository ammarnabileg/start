<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (!$token = auth()->attempt($request->only('email', 'password'))) {
            return response()->json(['message' => __('auth.failed')], 401);
        }

        $user = auth()->user();

        if (!$user->is_active) {
            auth()->logout();
            return response()->json(['message' => 'Account suspended. Contact administrator.'], 403);
        }

        $user->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);

        AuditLog::record('user.login');

        return $this->respondWithToken($token, $user);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user()->load('tenant');
        return response()->json(['user' => $user, 'permissions' => $user->getAllPermissions()->pluck('name')]);
    }

    public function logout(): JsonResponse
    {
        AuditLog::record('user.logout');
        auth()->logout();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh(), auth()->user());
    }

    private function respondWithToken(string $token, User $user): JsonResponse
    {
        $redirect = match($user->user_type) {
            'super_admin' => '/super-admin/dashboard',
            'candidate' => '/candidate/portal',
            default => '/dashboard',
        };

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->load('tenant'),
            'redirect' => $redirect,
        ]);
    }
}
