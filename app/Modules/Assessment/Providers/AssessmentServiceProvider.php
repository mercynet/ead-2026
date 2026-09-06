<?php

namespace App\Modules\Assessment\Providers;

use App\Modules\Assessment\Listeners\IssueCertificateOnCourseCompletedListener;
use App\Modules\Assessment\Policies\QuizAttemptPolicy;
use App\Modules\Learning\Events\CourseCompletedEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AssessmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerGates();
        $this->registerListeners();
        $this->registerRoutes();
    }

    private function registerListeners(): void
    {
        Event::listen(CourseCompletedEvent::class, IssueCertificateOnCourseCompletedListener::class);
    }

    private function registerGates(): void
    {
        Gate::define('assessment.attempts.create', [QuizAttemptPolicy::class, 'create']);
        Gate::define('assessment.attempts.view', [QuizAttemptPolicy::class, 'view']);
        Gate::define('assessment.attempts.answer', [QuizAttemptPolicy::class, 'answer']);
        Gate::define('assessment.attempts.finish', [QuizAttemptPolicy::class, 'finish']);
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/admin.php');
    }
}
