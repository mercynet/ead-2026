<?php

use Illuminate\Support\Facades\Route;

it('exposes the I-02 Instructor routes with the intended non-lifecycle surface', function (): void {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/instructor/'))
        ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($routes)->toContain(...[
        'GET|HEAD api/v1/instructor/enrollments',
        'POST api/v1/instructor/enrollments',
        'GET|HEAD api/v1/instructor/enrollments/{id}',
        'GET|HEAD api/v1/instructor/enrollments/{id}/progress',
        'GET|HEAD api/v1/instructor/lessons/{lessonId}/media',
        'POST api/v1/instructor/lessons/{lessonId}/media',
        'GET|HEAD api/v1/instructor/lessons/{lessonId}/media/{mediaId}',
        'PATCH api/v1/instructor/lessons/{lessonId}/media/{mediaId}',
        'DELETE api/v1/instructor/lessons/{lessonId}/media/{mediaId}',
        'GET|HEAD api/v1/instructor/courses/{courseId}/materials',
        'POST api/v1/instructor/courses/{courseId}/materials',
        'GET|HEAD api/v1/instructor/courses/{courseId}/materials/{materialId}',
        'PATCH api/v1/instructor/courses/{courseId}/materials/{materialId}',
        'DELETE api/v1/instructor/courses/{courseId}/materials/{materialId}',
        'POST api/v1/instructor/courses/{courseId}/materials/{materialId}/downloads',
    ]);

    expect($routes)->not->toContain('PATCH api/v1/instructor/enrollments/{id}')
        ->not->toContain('DELETE api/v1/instructor/enrollments/{id}')
        ->not->toContain('POST api/v1/instructor/enrollments/{id}/cancel')
        ->not->toContain('POST api/v1/instructor/enrollments/{id}/confirm-payment');
});

it('keeps I-02 Instructor routes authenticated and guarded exactly by the Instructor area', function (): void {
    collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/instructor/'))
        ->each(function ($route): void {
            expect($route->gatherMiddleware())->toContain('auth:sanctum', 'area.guard:instructor', 'tenant.access');
        });
});

it('keeps Instructor I-02 projections free of forbidden PII and internal storage fields', function (): void {
    $rosterResource = file_get_contents(base_path('app/Modules/Learning/Http/Resources/Instructor/EnrollmentResource.php'));
    $mediaResource = file_get_contents(base_path('app/Modules/Learning/Http/Resources/Instructor/LessonMediaResource.php'));
    $materialResource = file_get_contents(base_path('app/Modules/Learning/Http/Resources/Instructor/CourseMaterialResource.php'));

    expect($rosterResource)->not->toBeFalse()
        ->and($rosterResource)->not->toContain("'email'")
        ->not->toContain("'tenant_id'")
        ->and($mediaResource)->not->toContain("'metadata'")
        ->and($materialResource)->not->toContain("'file_path'");
});

it('keeps Instructor I-02 implementation out of Financial controllers and Assessment models', function (): void {
    $directory = base_path('app/Modules/Learning/Http/Controllers/Instructor');

    foreach (glob($directory.'/*.php') ?: [] as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toBeFalse()
            ->and($contents)->not->toContain('App\\Modules\\Financial\\Models')
            ->and($contents)->not->toContain('App\\Modules\\Assessment\\Models');
    }
});
