<?php

namespace App\Modules\Learning\Providers;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Policies\CategoryPolicy;
use App\Modules\Learning\Policies\CoursePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LearningServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerGates();
        $this->registerRoutes();
    }

    private function registerGates(): void
    {
        Gate::define('learning.catalog.courses.list', [CoursePolicy::class, 'list']);
        Gate::define('learning.catalog.courses.show', [CoursePolicy::class, 'show']);
        Gate::define('learning.courses.view-check', [CoursePolicy::class, 'show']);
        Gate::define('learning.courses.create-check', [CoursePolicy::class, 'create']);
        Gate::define('learning.courses.update-check', [CoursePolicy::class, 'update']);
        Gate::define('learning.courses.publish-check', [CoursePolicy::class, 'publish']);
        Gate::define('learning.courses.delete-check', [CoursePolicy::class, 'delete']);

        Gate::define('learning.categories.list', [CategoryPolicy::class, 'list']);

        Gate::define('learning.categories.create-category', function (User $user, ?Tenant $tenant = null, bool $isSystem = false): bool {
            $policy = app(CategoryPolicy::class);

            return $policy->create($user, $tenant, $isSystem);
        });

        Gate::define('learning.categories.tenant.create', [CategoryPolicy::class, 'createTenant']);
        Gate::define('learning.categories.system.manage', [CategoryPolicy::class, 'manageSystem']);

        Gate::define('learning.categories.tenant.update-check', [CategoryPolicy::class, 'update']);
        Gate::define('learning.categories.tenant.delete-check', [CategoryPolicy::class, 'delete']);
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/admin.php');
    }
}
