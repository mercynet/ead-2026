<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

it('RED_CONFIRMED registers the canonical Student S-01 routes', function (): void {
    $studentRoutes = collect(Route::getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/student/'));

    expect($studentRoutes)->not->toBeEmpty()
        ->and($studentRoutes->pluck('uri')->all())->toContain('api/v1/student/courses');
});

it('keeps the Student surface on its exact tenant-scoped middleware stack', function (): void {
    $expectedUris = [
        'api/v1/student/courses',
        'api/v1/student/courses/{courseId}',
        'api/v1/student/courses/{courseId}/modules',
        'api/v1/student/courses/{courseId}/modules/{moduleId}/lessons',
        'api/v1/student/lessons/{lessonId}',
        'api/v1/student/lessons/{lessonId}/media',
        'api/v1/student/lessons/{lessonId}/progress',
        'api/v1/student/courses/{courseId}/materials',
        'api/v1/student/courses/{courseId}/materials/{materialId}/downloads',
    ];

    foreach ($expectedUris as $uri) {
        $routes = collect(Route::getRoutes())->filter(fn (RoutingRoute $route): bool => $route->uri() === $uri);

        expect($routes)->not->toBeEmpty("Missing Student route {$uri}");

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            expect($middleware)->toContain('auth:sanctum')
                ->toContain('api.context')
                ->toContain('resolve.tenant.optional')
                ->toContain('area.guard:student')
                ->toContain('tenant.access')
                ->not->toContain('area.guard:admin')
                ->not->toContain('area.guard:instructor')
                ->not->toContain('area.guard:mzrt');
        }
    }
});

it('keeps Student controllers and Resources independent from other area and module internals', function (): void {
    $controllerFiles = glob(base_path('app/Modules/Learning/Http/Controllers/Student/*.php')) ?: [];
    $resourceFiles = glob(base_path('app/Modules/Learning/Http/Resources/Student/*.php')) ?: [];

    expect($controllerFiles)->not->toBeEmpty()->and($resourceFiles)->not->toBeEmpty();

    foreach (array_merge($controllerFiles, $resourceFiles) as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toContain('Http\\Controllers\\Admin')
            ->not->toContain('Http\\Controllers\\Instructor')
            ->not->toContain('Assessment\\')
            ->not->toContain('Financial\\');
    }
});
