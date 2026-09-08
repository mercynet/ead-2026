<?php

use App\Modules\Learning\Http\Controllers\Instructor\CourseController;
use App\Modules\Learning\Http\Controllers\Instructor\CourseMaterialController;
use App\Modules\Learning\Http\Controllers\Instructor\EnrollmentController;
use App\Modules\Learning\Http\Controllers\Instructor\LessonController;
use App\Modules\Learning\Http\Controllers\Instructor\LessonMediaController;
use App\Modules\Learning\Http\Controllers\Instructor\ModuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/instructor')
    ->middleware([
        'resolve.tenant.optional',
        'api.context',
        'auth:sanctum',
        'area.guard:instructor',
        'tenant.required.unless.developer',
        'tenant.access',
    ])
    ->group(function (): void {
        Route::controller(CourseController::class)->group(function (): void {
            Route::get('/courses', 'index');
            Route::post('/courses', 'store');
            Route::get('/courses/{id}/preview', 'preview');
            Route::get('/courses/{id}', 'show');
            Route::patch('/courses/{id}', 'update');
            Route::delete('/courses/{id}', 'destroy');
        });

        Route::controller(ModuleController::class)->group(function (): void {
            Route::get('/courses/{courseId}/modules', 'index');
            Route::post('/modules', 'store');
            Route::patch('/modules/reorder', 'reorder');
            Route::get('/modules/{id}', 'show');
            Route::patch('/modules/{id}', 'update');
            Route::delete('/modules/{id}', 'destroy');
        });

        Route::controller(LessonController::class)->group(function (): void {
            Route::get('/modules/{moduleId}/lessons', 'index');
            Route::post('/lessons', 'store');
            Route::patch('/lessons/reorder', 'reorder');
            Route::get('/lessons/{id}', 'show');
            Route::patch('/lessons/{id}', 'update');
            Route::delete('/lessons/{id}', 'destroy');
        });

        Route::controller(CourseMaterialController::class)->group(function (): void {
            Route::get('/courses/{courseId}/materials', 'index');
            Route::post('/courses/{courseId}/materials', 'store');
            Route::get('/courses/{courseId}/materials/{materialId}', 'show');
            Route::patch('/courses/{courseId}/materials/{materialId}', 'update');
            Route::delete('/courses/{courseId}/materials/{materialId}', 'destroy');
            Route::post('/courses/{courseId}/materials/{materialId}/downloads', 'download');
        });

        Route::controller(LessonMediaController::class)->group(function (): void {
            Route::get('/lessons/{lessonId}/media', 'index');
            Route::post('/lessons/{lessonId}/media', 'store');
            Route::get('/lessons/{lessonId}/media/{mediaId}', 'show');
            Route::patch('/lessons/{lessonId}/media/{mediaId}', 'update');
            Route::delete('/lessons/{lessonId}/media/{mediaId}', 'destroy');
        });

        Route::controller(EnrollmentController::class)->group(function (): void {
            Route::get('/enrollments', 'index');
            Route::post('/enrollments', 'store');
            Route::get('/enrollments/{id}/progress', 'progress');
            Route::get('/enrollments/{id}', 'show');
        });
    });
