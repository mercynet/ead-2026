<?php

namespace App\Modules\Assessment\Providers;

use App\Modules\Assessment\Listeners\IssueCertificateOnCourseCompletedListener;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Policies\InstructorAssessmentPolicy;
use App\Modules\Assessment\Policies\QuizAttemptPolicy;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
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
        Gate::define('assessment.instructor.questionnaires.view-check', function (User $user, ?Tenant $tenant = null, ?Questionnaire $questionnaire = null): bool {
            return app(InstructorAssessmentPolicy::class)->questionnaire($user, $tenant, $questionnaire);
        });
        Gate::define('assessment.instructor.questionnaires.update-check', function (User $user, ?Tenant $tenant = null, ?Questionnaire $questionnaire = null): bool {
            return app(InstructorAssessmentPolicy::class)->questionnaire($user, $tenant, $questionnaire, 'assessment.questionnaires.update');
        });
        Gate::define('assessment.instructor.questionnaires.delete-check', function (User $user, ?Tenant $tenant = null, ?Questionnaire $questionnaire = null): bool {
            return app(InstructorAssessmentPolicy::class)->questionnaire($user, $tenant, $questionnaire, 'assessment.questionnaires.delete');
        });
        Gate::define('assessment.instructor.questions.view-check', function (User $user, ?Tenant $tenant = null, ?QuizQuestion $question = null): bool {
            return app(InstructorAssessmentPolicy::class)->question($user, $tenant, $question);
        });
        Gate::define('assessment.instructor.questions.update-check', function (User $user, ?Tenant $tenant = null, ?QuizQuestion $question = null): bool {
            return app(InstructorAssessmentPolicy::class)->question($user, $tenant, $question, 'assessment.questions.update');
        });
        Gate::define('assessment.instructor.questions.delete-check', function (User $user, ?Tenant $tenant = null, ?QuizQuestion $question = null): bool {
            return app(InstructorAssessmentPolicy::class)->question($user, $tenant, $question, 'assessment.questions.delete');
        });
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/admin.php');
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/instructor.php');
    }
}
