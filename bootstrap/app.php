<?php

use App\Modules\Core\Exceptions\TenantAlreadyExistsException;
use App\Modules\Core\Http\Middleware\EnsureAreaAccess;
use App\Modules\Financial\Exceptions\CheckoutConflictException;
use App\Modules\Financial\Exceptions\GatewayUnavailableException;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Exceptions\AreaAccessDeniedException;
use App\Shared\Exceptions\InvalidCredentialsException;
use App\Shared\Exceptions\InvitationInvalidException;
use App\Shared\Exceptions\ResourceNotFoundException;
use App\Shared\Exceptions\TenantContextRequiredException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    /*
     * Rotas da API vivem em app/Modules/<M>/Routes/api.php, carregadas pelo
     * service provider de cada módulo (prefixo `api` + middleware group `api`).
     */
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.context' => \App\Modules\Core\Http\Middleware\InjectApiContext::class,
            'resolve.tenant' => \App\Modules\Core\Http\Middleware\ResolveTenant::class,
            'resolve.tenant.optional' => \App\Modules\Core\Http\Middleware\ResolveTenantOptional::class,
            'tenant.access' => \App\Modules\Core\Http\Middleware\EnsureTenantAccess::class,
            'tenant.required.unless.developer' => \App\Modules\Core\Http\Middleware\EnsureTenantRequiredForNonDeveloper::class,
            'area.guard' => \App\Modules\Core\Http\Middleware\EnsureAreaAccess::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureAreaAccess::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantAlreadyExistsException $exception, Request $request) {
            return response()->json(['data' => null, 'errors' => [['code' => 'tenant_already_exists', 'message' => $exception->getMessage()]]], 409);
        });

        $exceptions->render(function (CheckoutConflictException $exception, Request $request) {
            return response()->json(['data' => null, 'errors' => [['code' => $exception->errorCode, 'message' => $exception->getMessage()]]], 409);
        });

        $exceptions->render(function (GatewayUnavailableException $exception, Request $request) {
            return response()->json(['data' => null, 'errors' => [['code' => 'gateway_unavailable', 'message' => 'Gateway de pagamento indisponível.']]], 503);
        });
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

        $exceptions->render(function (InvitationInvalidException $exception, Request $request) {
            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'invitation_invalid',
                        'message' => $exception->getMessage(),
                    ],
                ],
            ], 422);
        });

        $exceptions->render(function (\App\Shared\Exceptions\PasswordResetInvalidException $exception, Request $request) {
            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'password_reset_invalid',
                        'message' => $exception->getMessage(),
                    ],
                ],
            ], 422);
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

        $exceptions->render(function (AreaAccessDeniedException $exception, Request $request) {
            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'area_forbidden',
                        'message' => $exception->getMessage(),
                    ],
                ],
            ], 403);
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'too_many_requests',
                        'message' => 'Muitas requisições. Tente novamente em instantes.',
                    ],
                ],
            ], 429);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'unauthenticated',
                        'message' => 'Não autenticado.',
                    ],
                ],
            ], 401);
        });

        /*
         * O Handler converte AuthorizationException → AccessDeniedHttpException e
         * ModelNotFoundException → NotFoundHttpException ANTES dos render callbacks
         * (prepareException) — por isso os handlers abaixo miram as classes Symfony.
         */
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'access_denied',
                        'message' => 'Acesso negado.',
                    ],
                ],
            ], 403);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'not_found',
                        'message' => 'Recurso não encontrado.',
                    ],
                ],
            ], 404);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            return response()->json([
                'data' => null,
                'errors' => [
                    [
                        'code' => 'validation_error',
                        'message' => $message,
                    ],
                ],
            ], 422);
        });
    })->create();
