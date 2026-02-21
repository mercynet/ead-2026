<?php

use App\Http\Controllers\Api\V1\Core\Auth\LoginController;
use App\Http\Controllers\Api\V1\Core\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Core\Auth\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/core')
    ->middleware('resolve.tenant')
    ->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('/login', LoginController::class);
            Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
            Route::get('/me', MeController::class)->middleware('auth:sanctum');
        });
    });
