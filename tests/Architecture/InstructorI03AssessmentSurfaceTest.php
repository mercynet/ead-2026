<?php

use Illuminate\Support\Facades\Route;

it('exposes the complete canonical Instructor I-03 Assessment surface', function (): void {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/instructor/assessment/'))
        ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($routes)->toContain(...[
        'GET|HEAD api/v1/instructor/assessment/questionnaires',
        'POST api/v1/instructor/assessment/questionnaires',
        'GET|HEAD api/v1/instructor/assessment/questionnaires/{id}',
        'PATCH api/v1/instructor/assessment/questionnaires/{id}',
        'DELETE api/v1/instructor/assessment/questionnaires/{id}',
        'GET|HEAD api/v1/instructor/assessment/questionnaires/{questionnaireId}/questions',
        'POST api/v1/instructor/assessment/questionnaires/{questionnaireId}/questions',
        'PATCH api/v1/instructor/assessment/questionnaires/{questionnaireId}/questions/reorder',
        'DELETE api/v1/instructor/assessment/questionnaires/{questionnaireId}/questions/{questionId}',
        'GET|HEAD api/v1/instructor/assessment/questions',
        'POST api/v1/instructor/assessment/questions',
        'GET|HEAD api/v1/instructor/assessment/questions/{id}',
        'PATCH api/v1/instructor/assessment/questions/{id}',
        'DELETE api/v1/instructor/assessment/questions/{id}',
        'GET|HEAD api/v1/instructor/assessment/results',
        'GET|HEAD api/v1/instructor/assessment/results/{id}',
    ]);
});

it('keeps I-03 routes authenticated and guarded by the Instructor area', function (): void {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/instructor/assessment/'));

    expect($routes)->not->toBeEmpty();
    $routes->each(function ($route): void {
        expect($route->gatherMiddleware())->toContain(
            'auth:sanctum',
            'area.guard:instructor',
            'tenant.access',
        );
    });
});

it('keeps I-03 Instructor controllers and resources isolated from Admin and forbidden data', function (): void {
    foreach (glob(base_path('app/Modules/Assessment/Http/Controllers/Instructor/*.php')) ?: [] as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toBeFalse()
            ->and($contents)->not->toContain('Http\\Controllers\\Admin')
            ->and($contents)->not->toContain('Models\\Learning');
    }

    foreach (glob(base_path('app/Modules/Assessment/Http/Resources/Instructor/*.php')) ?: [] as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toBeFalse()
            ->and($contents)->not->toContain("'tenant_id'")
            ->and($contents)->not->toContain("'email'")
            ->and($contents)->not->toContain("'correct_options'");

        if (basename($file) !== 'ResultAnswerResource.php') {
            expect($contents)->not->toContain("'question_snapshot'");
        }
    }
});

it('proves I-03 ownership is enforced in the Assessment action scope and scoring stays server-side', function (): void {
    $scope = file_get_contents(base_path('app/Modules/Assessment/Services/InstructorAssessmentScope.php'));
    $results = file_get_contents(base_path('app/Modules/Assessment/Services/InstructorResultScope.php'));
    $scoring = file_get_contents(base_path('app/Modules/Assessment/Actions/Attempt/FinishAttemptAction.php'));

    expect($scope)->not->toBeFalse()
        ->and($scope)->toContain("where('instructor_id'")
        ->and($scope)->toContain('ownedParentIds')
        ->and($results)->not->toBeFalse()
        ->and($results)->toContain('questionnaireCourseMap')
        ->and($results)->toContain('enrolledUserIdsForCourses')
        ->and($scoring)->not->toBeFalse()
        ->and($scoring)->toContain('questions_snapshot');
});
