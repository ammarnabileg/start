<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\RoleSlug;
use App\Models\User;
use App\Services\AI\LlmManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmManager::class, fn ($app) => new LlmManager($app));
    }

    public function boot(): void
    {
        // Authorization: super_admin passes everything; otherwise any dotted ability
        // (e.g. "job.create") is resolved against the user's permissions. Returning null
        // falls through so non-permission abilities / policies still apply.
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole(RoleSlug::SuperAdmin)) {
                return true;
            }
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability) ?: null;
            }
            return null;
        });
    }
}
