<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

it('requires exactly matching area guards on persona API routes', function (): void {
    /** @var array<string> $violations */
    $violations = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        if (! preg_match('#^api/v1/(mzrt|admin|instructor|student)(?:/|$)#', $route->uri(), $matches)) {
            continue;
        }

        $area = $matches[1];
        $areaGuards = array_values(array_filter(
            $route->middleware(),
            fn (string $middleware): bool => str_starts_with($middleware, 'area.guard:')
        ));
        $expectedGuard = "area.guard:{$area}";

        if ($areaGuards !== [$expectedGuard]) {
            $violations[] = "{$route->uri()} must carry exactly {$expectedGuard}; found ".implode(', ', $areaGuards ?: ['none']);
        }
    }

    expect($violations)->toBeEmpty(
        'Persona route area guards are missing or mismatched: '.implode('; ', $violations)
    );
});
