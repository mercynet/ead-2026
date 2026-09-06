<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Guard the public surface of the API.
 *
 * Every api/v1 route must carry auth:sanctum unless it is deliberately
 * public and listed here. Adding a public route is an explicit act:
 * it must be added to this allowlist in the same PR. No DB required.
 */
it('requires auth:sanctum on every api/v1 route not deliberately public', function (): void {
    $publicAllowlist = [
        'POST api/v1/auth/login',
        'POST api/v1/auth/password/forgot',
        'POST api/v1/auth/password/reset',
        'POST api/v1/core/auth/login',
        'POST api/v1/core/auth/password/forgot',
        'POST api/v1/core/auth/password/reset',
        'POST api/v1/core/invitations/accept',
        'GET api/v1/learning/catalog/courses',
        'GET api/v1/learning/catalog/courses/{slug}',
        'GET api/v1/assessment/certificates/verify/{certificateNumber}',
    ];

    /** @var array<string> $unprotected */
    $unprotected = [];

    /** @var array<string> $apiRoutesSeen */
    $apiRoutesSeen = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1')) {
            continue;
        }

        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            $signature = "{$method} {$route->uri()}";
            $apiRoutesSeen[] = $signature;

            $isProtected = in_array('auth:sanctum', $route->gatherMiddleware(), true);

            if (! $isProtected && ! in_array($signature, $publicAllowlist, true)) {
                $unprotected[] = $signature;
            }
        }
    }

    expect($apiRoutesSeen)->not->toBeEmpty('No api/v1 routes found — the route scan is probably broken.');

    expect($unprotected)->toBeEmpty(
        'These api/v1 routes have no auth:sanctum and are not in the public allowlist: '
        .implode(', ', $unprotected)
    );
});

it('keeps the public allowlist free of stale entries', function (): void {
    $publicAllowlist = [
        'POST api/v1/auth/login',
        'POST api/v1/auth/password/forgot',
        'POST api/v1/auth/password/reset',
        'POST api/v1/core/auth/login',
        'POST api/v1/core/auth/password/forgot',
        'POST api/v1/core/auth/password/reset',
        'POST api/v1/core/invitations/accept',
        'GET api/v1/learning/catalog/courses',
        'GET api/v1/learning/catalog/courses/{slug}',
        'GET api/v1/assessment/certificates/verify/{certificateNumber}',
    ];

    /** @var array<string> $actualSignatures */
    $actualSignatures = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        foreach ($route->methods() as $method) {
            $actualSignatures[] = "{$method} {$route->uri()}";
        }
    }

    $stale = array_values(array_diff($publicAllowlist, $actualSignatures));

    expect($stale)->toBeEmpty(
        'These allowlist entries no longer match any route (remove them): '.implode(', ', $stale)
    );
});

it('uses the named rate limiters on invitation routes (no shared anonymous bucket)', function (): void {
    $middlewareFor = function (string $method, string $uri): array {
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route->gatherMiddleware();
            }
        }

        return [];
    };

    expect($middlewareFor('POST', 'api/v1/core/invitations/accept'))
        ->toContain('throttle:invitation-accept')
        ->and($middlewareFor('POST', 'api/v1/core/invitations'))
        ->toContain('throttle:invitation-create');
});

it('keeps canonical and legacy auth routes on identical middleware and throttling', function (): void {
    $middlewareFor = function (string $method, string $uri): array {
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route->gatherMiddleware();
            }
        }

        return [];
    };

    foreach (['login', 'password/forgot', 'password/reset'] as $path) {
        $canonical = $middlewareFor('POST', "api/v1/auth/{$path}");
        $legacy = $middlewareFor('POST', "api/v1/core/auth/{$path}");

        sort($canonical);
        sort($legacy);

        expect($canonical)->toBe($legacy);
    }

    foreach ([['POST', 'logout'], ['GET', 'me']] as [$method, $path]) {
        $canonical = $middlewareFor($method, "api/v1/auth/{$path}");
        $legacy = $middlewareFor($method, "api/v1/core/auth/{$path}");

        sort($canonical);
        sort($legacy);

        expect($canonical)->toBe($legacy);
    }
});
