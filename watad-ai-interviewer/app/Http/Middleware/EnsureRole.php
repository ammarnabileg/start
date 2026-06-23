<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: aborts 403 unless the authenticated user has one of the listed roles.
 * Usage: ->middleware('role:hr_manager,super_admin'). Fine-grained checks use Laravel's
 * `can:` middleware backed by the permission gates (see AppServiceProvider). docs/14.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if(! $user, 401);
        abort_unless(
            collect($roles)->contains(fn (string $role) => $user->hasRole($role)),
            403,
            'You do not have access to this area.'
        );

        return $next($request);
    }
}
