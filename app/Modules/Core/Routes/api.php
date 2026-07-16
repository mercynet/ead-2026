<?php

use App\Modules\Core\Http\Controllers\AuthController;
use App\Modules\Core\Http\Controllers\InvitationController;
use App\Modules\Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/core')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::prefix('auth')
            ->controller(AuthController::class)
            ->group(function (): void {
                Route::post('/login', 'login')->middleware('throttle:5,1');
                Route::post('/password/forgot', 'forgotPassword')->middleware('throttle:5,1');
                Route::post('/password/reset', 'resetPassword')->middleware('throttle:5,1');

                Route::middleware([
                    'tenant.required.unless.developer',
                    'auth:sanctum',
                    'tenant.access',
                ])->group(function (): void {
                    Route::post('/logout', 'logout');
                    Route::get('/me', 'me');
                });
            });

        Route::prefix('invitations')
            ->controller(InvitationController::class)
            ->group(function (): void {
                Route::post('/accept', 'accept')->middleware('throttle:10,1');

                Route::middleware([
                    'tenant.required.unless.developer',
                    'auth:sanctum',
                    'tenant.access',
                ])->group(function (): void {
                    Route::post('/', 'store')->middleware('throttle:60,1');
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
