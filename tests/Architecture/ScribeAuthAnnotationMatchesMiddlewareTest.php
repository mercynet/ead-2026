<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Scribe's @unauthenticated annotation must mirror the real middleware.
 *
 * A route without `auth:sanctum` is public and its controller method must
 * carry @unauthenticated; a protected route must not. Keeps the generated
 * API docs honest about which endpoints need a token. No DB required.
 *
 * Current debt: annotations have not been audited against the router yet.
 * Assertion is present and hard-fails once `skip()` is removed.
 */
it('keeps @unauthenticated in sync with auth:sanctum on every api/v1 route', function (): void {
    /** @var array<string> $mismatches */
    $mismatches = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1')) {
            continue;
        }

        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            continue;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            continue;
        }

        $isProtected = in_array('auth:sanctum', $route->gatherMiddleware(), true);

        $docComment = (new ReflectionMethod($class, $method))->getDocComment() ?: '';
        $declaredPublic = str_contains($docComment, '@unauthenticated');

        if ($isProtected && $declaredPublic) {
            $mismatches[] = "{$route->uri()} is auth:sanctum but annotated @unauthenticated";
        }

        if (! $isProtected && ! $declaredPublic) {
            $mismatches[] = "{$route->uri()} is public but missing @unauthenticated";
        }
    }

    expect($mismatches)->toBeEmpty(
        'Scribe auth annotations drifted from middleware: '.implode('; ', $mismatches)
    );
})->skip('debt: @unauthenticated annotations not yet audited against the router');
