<?php

use App\Http\Controllers\Api\V1\Core\Auth\LoginController;
use App\Http\Controllers\Api\V1\Core\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Core\Auth\MeController;
use App\Http\Controllers\Api\V1\Core\Users\ListUsersController;
use App\Http\Controllers\Api\V1\Core\Users\RegisterUserController;
use App\Http\Controllers\Api\V1\Core\Users\ShowUserController;
use App\Http\Controllers\Api\V1\Core\Users\UpdateMeController;
use App\Http\Controllers\Api\V1\Core\Users\UpdatePasswordController;
use App\Http\Controllers\Api\V1\Learning\Catalog\ListCoursesController;
use App\Http\Controllers\Api\V1\Learning\Catalog\ShowCourseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/core')
    ->middleware('resolve.tenant')
    ->group(function (): void {
        Route::prefix('auth')->group(function (): void {
            Route::post('/login', LoginController::class);
            Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
            Route::get('/me', MeController::class)->middleware('auth:sanctum');
        });

        Route::post('/users', RegisterUserController::class);
        Route::get('/users', ListUsersController::class)->middleware('auth:sanctum');
        Route::get('/users/{id}', ShowUserController::class)->middleware('auth:sanctum');
        Route::patch('/users/me', UpdateMeController::class)->middleware('auth:sanctum');
        Route::patch('/users/me/password', UpdatePasswordController::class)->middleware('auth:sanctum');
    });

Route::prefix('v1/learning')
    ->middleware('resolve.tenant')
    ->group(function (): void {
        Route::prefix('catalog')->group(function (): void {
            Route::get('/courses', ListCoursesController::class);
            Route::get('/courses/{slug}', ShowCourseController::class);
        });
    });
