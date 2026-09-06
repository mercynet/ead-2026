<?php

namespace App\Modules\Learning\Providers;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Modules\Learning\Contracts\CourseCheckoutCatalog;
use App\Modules\Learning\Listeners\EnrollStudentFromOrderPaidListener;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Policies\CategoryPolicy;
use App\Modules\Learning\Policies\CourseModulePolicy;
use App\Modules\Learning\Policies\CoursePolicy;
use App\Modules\Learning\Policies\EnrollmentPolicy;
use App\Modules\Learning\Policies\LessonPolicy;
use App\Modules\Learning\Services\AssessmentCatalogResolver;
use App\Modules\Learning\Services\CourseCheckoutCatalogResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LearningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CourseCheckoutCatalog::class, CourseCheckoutCatalogResolver::class);
        $this->app->bind(AssessmentCatalog::class, AssessmentCatalogResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Relation::morphMap(['course' => Course::class]);
        $this->registerGates();
        $this->registerListeners();
        $this->registerRoutes();
    }

    private function registerGates(): void
    {
        Gate::define('learning.catalog.courses.list', [CoursePolicy::class, 'list']);
        Gate::define('learning.catalog.courses.show', [CoursePolicy::class, 'show']);
        Gate::define('learning.courses.preview-check', [CoursePolicy::class, 'preview']);
        Gate::define('learning.courses.view-check', [CoursePolicy::class, 'show']);
        Gate::define('learning.courses.create-check', [CoursePolicy::class, 'create']);
        Gate::define('learning.courses.update-check', [CoursePolicy::class, 'update']);
        Gate::define('learning.courses.publish-check', [CoursePolicy::class, 'publish']);
        Gate::define('learning.courses.delete-check', [CoursePolicy::class, 'delete']);

        Gate::define('learning.categories.list', [CategoryPolicy::class, 'list']);

        Gate::define('learning.categories.tenant.create', [CategoryPolicy::class, 'createTenant']);
        Gate::define('learning.categories.system.manage', [CategoryPolicy::class, 'manageSystem']);

        Gate::define('learning.categories.tenant.update-check', [CategoryPolicy::class, 'update']);
        Gate::define('learning.categories.tenant.delete-check', [CategoryPolicy::class, 'delete']);

        Gate::define('learning.modules.create-check', function (User $user, ?Tenant $tenant = null, ?int $courseId = null): bool {
            return app(CourseModulePolicy::class)->create($user, $tenant, $courseId);
        });

        Gate::define('learning.courses.list', [CoursePolicy::class, 'list']);

        Gate::define('learning.modules.list-check', function (User $user, ?Tenant $tenant = null, ?Course $course = null): bool {
            return app(CourseModulePolicy::class)->list($user, $tenant, $course);
        });

        Gate::define('learning.modules.view-check', function (User $user, ?Tenant $tenant = null, ?CourseModule $courseModule = null): bool {
            return app(CourseModulePolicy::class)->view($user, $tenant, $courseModule);
        });

        Gate::define('learning.modules.update-check', function (User $user, ?Tenant $tenant = null, ?CourseModule $courseModule = null): bool {
            return app(CourseModulePolicy::class)->update($user, $tenant, $courseModule);
        });

        Gate::define('learning.modules.delete-check', function (User $user, ?Tenant $tenant = null, ?CourseModule $courseModule = null): bool {
            return app(CourseModulePolicy::class)->delete($user, $tenant, $courseModule);
        });

        Gate::define('learning.modules.reorder-check', function (User $user, ?Tenant $tenant = null, ?int $courseId = null): bool {
            return app(CourseModulePolicy::class)->reorder($user, $tenant, $courseId);
        });

        Gate::define('learning.lessons.view', function (User $user, ?Tenant $tenant = null): bool {
            return app(LessonPolicy::class)->view($user, $tenant);
        });

        Gate::define('learning.lessons.list-check', function (User $user, ?Tenant $tenant = null, ?CourseModule $courseModule = null): bool {
            return app(LessonPolicy::class)->list($user, $tenant, $courseModule);
        });

        Gate::define('learning.lessons.create-check', function (User $user, ?Tenant $tenant = null, ?int $courseModuleId = null): bool {
            return app(LessonPolicy::class)->create($user, $tenant, $courseModuleId);
        });

        Gate::define('learning.progress.update', function (User $user, ?Tenant $tenant = null): bool {
            return app(LessonPolicy::class)->progress($user, $tenant);
        });

        Gate::define('learning.lessons.update-check', function (User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool {
            return app(LessonPolicy::class)->update($user, $tenant, $lesson);
        });

        Gate::define('learning.lessons.publish-check', function (User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool {
            return app(LessonPolicy::class)->publish($user, $tenant, $lesson);
        });

        Gate::define('learning.lessons.delete-check', function (User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool {
            return app(LessonPolicy::class)->delete($user, $tenant, $lesson);
        });

        Gate::define('learning.lessons.reorder-check', function (User $user, ?Tenant $tenant = null, ?int $courseModuleId = null): bool {
            return app(LessonPolicy::class)->reorder($user, $tenant, $courseModuleId);
        });

        Gate::define('learning.lessons.media.store-check', function (User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool {
            return app(LessonPolicy::class)->storeMedia($user, $tenant, $lesson);
        });

        Gate::define('learning.lessons.media.update-check', function (User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool {
            return app(LessonPolicy::class)->updateMedia($user, $tenant, $lesson);
        });

        Gate::define('learning.lessons.media.delete-check', function (User $user, ?Tenant $tenant = null, ?Lesson $lesson = null): bool {
            return app(LessonPolicy::class)->deleteMedia($user, $tenant, $lesson);
        });

        Gate::define('learning.enrollments.create', function (User $user, Tenant $tenant): bool {
            return app(EnrollmentPolicy::class)->create($user, $tenant);
        });

        Gate::define('learning.enrollments.list', function (User $user, ?Tenant $tenant = null): bool {
            return app(EnrollmentPolicy::class)->list($user, $tenant);
        });

        Gate::define('learning.enrollments.view', function (User $user, ?Tenant $tenant = null, ?\App\Modules\Learning\Models\Enrollment $enrollment = null): bool {
            return app(EnrollmentPolicy::class)->view($user, $tenant, $enrollment);
        });

        Gate::define('learning.enrollments.update', function (User $user, ?Tenant $tenant = null, ?\App\Modules\Learning\Models\Enrollment $enrollment = null): bool {
            return app(EnrollmentPolicy::class)->update($user, $tenant, $enrollment);
        });

        Gate::define('learning.enrollments.delete', function (User $user, ?Tenant $tenant = null, ?\App\Modules\Learning\Models\Enrollment $enrollment = null): bool {
            return app(EnrollmentPolicy::class)->delete($user, $tenant, $enrollment);
        });
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/admin.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/mzrt.php');
    }

    private function registerListeners(): void
    {
        Event::listen(OrderPaidEvent::class, EnrollStudentFromOrderPaidListener::class);
    }
}
