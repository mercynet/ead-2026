<?php

use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Core\Http\Controllers\InvitationController;
use App\Modules\Core\Http\Controllers\MzrtTenantCreateController;
use App\Modules\Core\Http\Controllers\MzrtTenantStatusController;
use App\Modules\Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/**
 * The neutral auth surface is canonical. The domain-first surface remains a
 * compatibility alias for existing v1 consumers and intentionally shares the
 * same controller and middleware registration.
 */
$registerAuthRoutes = function (string $prefix): void {
    Route::prefix($prefix)
        ->middleware(['resolve.tenant.optional', 'api.context'])
        ->group(function (): void {
            Route::prefix('auth')
                ->controller(AuthController::class)
                ->group(function (): void {
                    Route::post('/login', 'login')->middleware('throttle:login');
                    Route::post('/password/forgot', 'forgotPassword')->middleware('throttle:password-forgot');
                    Route::post('/password/reset', 'resetPassword')->middleware('throttle:password-reset');

                    Route::middleware([
                        'tenant.required.unless.developer',
                        'auth:sanctum',
                        'tenant.access',
                    ])->group(function (): void {
                        Route::post('/logout', 'logout');
                        Route::get('/me', 'me');
                    });
                });
        });
};

$registerAuthRoutes('v1');

// Legacy compatibility surface: keep it during v1 with identical behavior.
$registerAuthRoutes('v1/core');

Route::prefix('v1/core')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {

        Route::prefix('invitations')
            ->controller(InvitationController::class)
            ->group(function (): void {
                Route::post('/accept', 'accept')->middleware('throttle:invitation-accept');

                Route::middleware([
                    'tenant.required.unless.developer',
                    'auth:sanctum',
                    'tenant.access',
                ])->group(function (): void {
                    Route::post('/', 'store')->middleware('throttle:invitation-create');
                });
            });

        Route::prefix('users')
            ->controller(UserController::class)
            ->middleware([
                'tenant.required.unless.developer',
                'auth:sanctum',
                'tenant.access',
            ])
            ->group(function (): void {
                Route::get('/', 'index');
                Route::get('/{user}', 'show');
                Route::patch('/me', 'updateMe');
                Route::patch('/me/password', 'updatePassword');
            });
    });

Route::prefix('v1/mzrt')
    ->middleware(['auth:sanctum', 'area.guard:mzrt', 'api.context'])
    ->group(function (): void {
        Route::post('/tenants', [MzrtTenantCreateController::class, 'store']);
        Route::patch('/tenants/{tenant}/status', [MzrtTenantStatusController::class, 'update']);
    });
