<?php

use App\Modules\Learning\Http\Controllers\Catalog\CategoryController;
use App\Modules\Learning\Http\Controllers\Catalog\CourseController as CatalogCourseController;
use App\Modules\Learning\Http\Controllers\Course\CourseController;
use App\Modules\Learning\Http\Controllers\Enrollment\EnrollmentController;
use App\Modules\Learning\Http\Controllers\Lesson\LessonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/learning')
    ->middleware(['resolve.tenant.optional', 'api.context', 'tenant.required.unless.developer'])
    ->group(function (): void {
        Route::prefix('catalog')->group(function (): void {
            Route::controller(CatalogCourseController::class)
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
                    Route::put('/categories/{id}', 'update');
                    Route::delete('/categories/{id}', 'destroy');
                });
        });

        Route::middleware(['auth:sanctum', 'tenant.access'])->group(function (): void {
            Route::controller(CourseController::class)
                ->group(function (): void {
                    Route::get('/courses/{courseId}/modules', 'modules');
                    Route::get('/courses/{id}', 'show');
                    Route::post('/courses', 'store');
                    Route::patch('/courses/{id}', 'update');
                    Route::delete('/courses/{id}', 'destroy');
                });

            Route::controller(EnrollmentController::class)
                ->group(function (): void {
                    Route::get('/courses/{courseId}/enrollment', 'show');
                });

            Route::controller(LessonController::class)
                ->group(function (): void {
                    Route::get('/lessons/{id}', 'show');
                    Route::post('/lessons/{id}/progress', 'progress');
                });
        });
    });
