<?php

namespace App\Providers;

use App\Policies\CategoryPolicy;
use App\Policies\CoursePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('core.users.list', [UserPolicy::class, 'list']);
        Gate::define('core.users.show', [UserPolicy::class, 'show']);
        Gate::define('core.users.update-self', [UserPolicy::class, 'updateSelf']);

        Gate::define('learning.catalog.courses.list', [CoursePolicy::class, 'list']);
        Gate::define('learning.catalog.courses.show', [CoursePolicy::class, 'show']);

        Gate::define('learning.categories.list', [CategoryPolicy::class, 'list']);
        Gate::define('learning.categories.create', [CategoryPolicy::class, 'create']);
        Gate::define('learning.categories.tenant.create', [CategoryPolicy::class, 'createTenant']);
        Gate::define('learning.categories.system.manage', [CategoryPolicy::class, 'manageSystem']);

        Gate::define('learning.categories.tenant.update-check', [CategoryPolicy::class, 'update']);
        Gate::define('learning.categories.tenant.delete-check', [CategoryPolicy::class, 'delete']);
    }
}
