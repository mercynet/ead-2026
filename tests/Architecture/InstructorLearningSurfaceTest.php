<?php

use Illuminate\Support\Facades\Route;

it('exposes the canonical Instructor Learning authoring surface with no lifecycle routes', function (): void {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/instructor/'))
        ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($routes)->toContain(...[
        'GET|HEAD api/v1/instructor/courses',
        'POST api/v1/instructor/courses',
        'GET|HEAD api/v1/instructor/courses/{id}',
        'GET|HEAD api/v1/instructor/courses/{id}/preview',
        'PATCH api/v1/instructor/courses/{id}',
        'DELETE api/v1/instructor/courses/{id}',
        'GET|HEAD api/v1/instructor/courses/{courseId}/modules',
        'POST api/v1/instructor/modules',
        'GET|HEAD api/v1/instructor/modules/{id}',
        'PATCH api/v1/instructor/modules/{id}',
        'DELETE api/v1/instructor/modules/{id}',
        'PATCH api/v1/instructor/modules/reorder',
        'GET|HEAD api/v1/instructor/modules/{moduleId}/lessons',
        'POST api/v1/instructor/lessons',
        'GET|HEAD api/v1/instructor/lessons/{id}',
        'PATCH api/v1/instructor/lessons/{id}',
        'DELETE api/v1/instructor/lessons/{id}',
        'PATCH api/v1/instructor/lessons/reorder',
    ]);

    expect($routes)->not->toContain('POST api/v1/instructor/courses/{id}/publish')
        ->not->toContain('POST api/v1/instructor/courses/{id}/unpublish')
        ->not->toContain('POST api/v1/instructor/lessons/{id}/publish')
        ->not->toContain('POST api/v1/instructor/lessons/{id}/unpublish');
});

it('keeps every Instructor Learning route authenticated and in the Instructor area', function (): void {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/instructor/'));

    expect($routes)->not->toBeEmpty();

    $routes->each(function ($route): void {
        expect($route->middleware())->toContain('auth:sanctum')
            ->and($route->middleware())->toContain('tenant.access')
            ->and($route->middleware())->toContain('area.guard:instructor');
    });
});

it('keeps Instructor controllers isolated from Admin controller namespaces', function (): void {
    $directory = base_path('app/Modules/Learning/Http/Controllers/Instructor');
    $files = glob($directory.'/*.php');

    expect($files)->not->toBeFalse()->not->toBeEmpty();

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toBeFalse()
            ->and($contents)->not->toContain('Http\\Controllers\\Admin');
    }
});
