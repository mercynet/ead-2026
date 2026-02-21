<?php

use App\Exceptions\TenantContextRequiredException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.links' => \App\Http\Middleware\EnsureApiLinks::class,
            'resolve.tenant' => \App\Http\Middleware\ResolveTenant::class,
            'resolve.tenant.optional' => \App\Http\Middleware\ResolveTenantOptional::class,
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'tenant.required.unless.developer' => \App\Http\Middleware\EnsureTenantRequiredForNonDeveloper::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantContextRequiredException $exception, Request $request) {
            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'tenant_not_resolved',
                        'message' => $exception->getMessage(),
                    ],
                ],
            ], 422);
        });
    })->create();
