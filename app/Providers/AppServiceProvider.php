<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('core.users.list', function (User $authenticatedUser, Tenant $tenant): bool {
            if ($authenticatedUser->isDeveloper()) {
                return true;
            }

            return $authenticatedUser->isTenantAdmin() && $authenticatedUser->belongsToTenant($tenant);
        });

        Gate::define('core.users.show', function (User $authenticatedUser, Tenant $tenant, User $targetUser): bool {
            if ($authenticatedUser->isDeveloper()) {
                return true;
            }

            if ($authenticatedUser->isTenantAdmin() && $authenticatedUser->belongsToTenant($tenant)) {
                return $targetUser->belongsToTenant($tenant);
            }

            if ($authenticatedUser->isInstructor() || $authenticatedUser->isStudent()) {
                return $authenticatedUser->is($targetUser);
            }

            return false;
        });

        Gate::define('core.users.update-self', function (User $authenticatedUser, Tenant $tenant, User $targetUser): bool {
            if (! $authenticatedUser->is($targetUser)) {
                return false;
            }

            if ($authenticatedUser->isDeveloper()) {
                return true;
            }

            return $authenticatedUser->belongsToTenant($tenant);
        });

        Gate::define('learning.catalog.courses.list', function (User $authenticatedUser, Tenant $tenant): bool {
            if ($authenticatedUser->isDeveloper()) {
                return true;
            }

            return $authenticatedUser->belongsToTenant($tenant)
                && $authenticatedUser->getAllPermissions()->contains('name', 'learning.catalog.courses.list');
        });

        Gate::define('learning.catalog.courses.show', function (User $authenticatedUser, Tenant $tenant): bool {
            if ($authenticatedUser->isDeveloper()) {
                return true;
            }

            return $authenticatedUser->belongsToTenant($tenant)
                && $authenticatedUser->getAllPermissions()->contains('name', 'learning.catalog.courses.show');
        });
    }
}
