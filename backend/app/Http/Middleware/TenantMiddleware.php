<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();

        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        if ($user->user_type === 'super_admin') return $next($request);

        if (!$user->tenant_id) return response()->json(['message' => 'No company associated'], 403);

        if ($user->tenant && $user->tenant->status !== 'active') {
            return response()->json(['message' => 'Company account is suspended'], 403);
        }

        return $next($request);
    }
}
