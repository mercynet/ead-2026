<?php

use App\Http\Controllers\Api\V1\Core\AuthController;
use App\Http\Controllers\Api\V1\Core\UserController;
use App\Http\Controllers\Api\V1\Learning\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Learning\Catalog\CourseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/core')
    ->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('/login', [AuthController::class, 'login'])->middleware('resolve.tenant.optional');
            Route::middleware(['resolve.tenant', 'auth:sanctum', 'tenant.access'])->group(function (): void {
                Route::post('/logout', [AuthController::class, 'logout']);
                Route::get('/me', [AuthController::class, 'me']);
            });
        });

        Route::middleware('resolve.tenant')->group(function (): void {
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users', [UserController::class, 'index'])->middleware(['auth:sanctum', 'tenant.access']);
            Route::get('/users/{user}', [UserController::class, 'show'])->middleware(['auth:sanctum', 'tenant.access']);
            Route::patch('/users/me', [UserController::class, 'updateMe'])->middleware(['auth:sanctum', 'tenant.access']);
            Route::patch('/users/me/password', [UserController::class, 'updatePassword'])->middleware(['auth:sanctum', 'tenant.access']);
        });
    });

Route::prefix('v1/learning')
    ->middleware('resolve.tenant')
    ->group(function (): void {
        Route::prefix('catalog')->group(function (): void {
            Route::get('/courses', [CourseController::class, 'index'])->middleware('tenant.access');
            Route::get('/courses/{slug}', [CourseController::class, 'show'])->middleware('tenant.access');
            Route::get('/categories', [CategoryController::class, 'index'])->middleware(['auth:sanctum', 'tenant.access']);
            Route::post('/categories', [CategoryController::class, 'store'])->middleware(['auth:sanctum', 'tenant.access']);
        });
    });
