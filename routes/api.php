<?php

use App\Http\Controllers\Api\V1\Core\AuthController;
use App\Http\Controllers\Api\V1\Core\UserController;
use App\Http\Controllers\Api\V1\Learning\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Learning\Catalog\CourseController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.links')->group(function (): void {
    Route::prefix('v1/core')
        ->group(function (): void {
            Route::prefix('auth')
                ->controller(AuthController::class)
                ->group(function (): void {
                    Route::post('/login', 'login')->middleware('resolve.tenant.optional');

                    Route::middleware([
                        'resolve.tenant.optional',
                        'tenant.required.unless.developer',
                        'auth:sanctum',
                        'tenant.access',
                    ])->group(function (): void {
                        Route::post('/logout', 'logout');
                        Route::get('/me', 'me');
                    });
                });

            Route::prefix('users')
                ->controller(UserController::class)
                ->middleware(['resolve.tenant.optional', 'tenant.required.unless.developer'])
                ->group(function (): void {
                    Route::post('/', 'store');

                    Route::middleware(['auth:sanctum', 'tenant.access'])->group(function (): void {
                        Route::get('/', 'index');
                        Route::get('/{user}', 'show');
                        Route::patch('/me', 'updateMe');
                        Route::patch('/me/password', 'updatePassword');
                    });
                });
        });

    Route::prefix('v1/learning')
        ->middleware(['resolve.tenant.optional', 'tenant.required.unless.developer'])
        ->group(function (): void {
            Route::prefix('catalog')->group(function (): void {
                Route::controller(CourseController::class)
                    ->middleware('tenant.access')
                    ->group(function (): void {
                        Route::get('/courses', 'index');
                        Route::get('/courses/{slug}', 'show');
                    });

                Route::controller(CategoryController::class)
                    ->middleware(['auth:sanctum', 'tenant.access'])
                    ->group(function (): void {
                        Route::get('/categories', 'index');
                        Route::post('/categories', 'store');
                    });
            });
        });
});
