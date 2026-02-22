<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CoursePolicy;
use App\Policies\QuizAttemptPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('core.users.list', [UserPolicy::class, 'list']);
        Gate::define('core.users.show', [UserPolicy::class, 'show']);
        Gate::define('core.users.update-self', [UserPolicy::class, 'updateSelf']);

        Gate::define('learning.catalog.courses.list', [CoursePolicy::class, 'list']);
        Gate::define('learning.catalog.courses.show', [CoursePolicy::class, 'show']);

        Gate::define('learning.categories.list', [CategoryPolicy::class, 'list']);

        Gate::define('learning.categories.create-category', function (User $user, ?Tenant $tenant = null, bool $isSystem = false): bool {
            $policy = app(CategoryPolicy::class);

            return $policy->create($user, $tenant, $isSystem);
        });

        Gate::define('learning.categories.tenant.create', [CategoryPolicy::class, 'createTenant']);
        Gate::define('learning.categories.system.manage', [CategoryPolicy::class, 'manageSystem']);

        Gate::define('learning.categories.tenant.update-check', [CategoryPolicy::class, 'update']);
        Gate::define('learning.categories.tenant.delete-check', [CategoryPolicy::class, 'delete']);

        Gate::define('assessment.attempts.create', [QuizAttemptPolicy::class, 'create']);
        Gate::define('assessment.attempts.view', [QuizAttemptPolicy::class, 'view']);
        Gate::define('assessment.attempts.answer', [QuizAttemptPolicy::class, 'answer']);
        Gate::define('assessment.attempts.finish', [QuizAttemptPolicy::class, 'finish']);
    }
}
