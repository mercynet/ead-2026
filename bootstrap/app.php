<?php

use App\Exceptions\AccessDeniedException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\TenantContextRequiredException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.context' => \App\Http\Middleware\InjectApiContext::class,
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

        $exceptions->render(function (InvalidCredentialsException $exception, Request $request) {
            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'invalid_credentials',
                        'message' => $exception->getMessage(),
                    ],
                ],
            ], 401);
        });

        $exceptions->render(function (ResourceNotFoundException $exception, Request $request) {
            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'not_found',
                        'message' => $exception->getMessage(),
                    ],
                ],
            ], 404);
        });

        $exceptions->render(function (AccessDeniedException $exception, Request $request) {
            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'access_denied',
                        'message' => $exception->getMessage(),
                    ],
                ],
            ], 403);
        });
    })->create();
