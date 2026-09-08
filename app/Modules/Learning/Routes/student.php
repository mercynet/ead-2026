<?php

use App\Modules\Learning\Http\Controllers\Student\CourseController;
use App\Modules\Learning\Http\Controllers\Student\CourseMaterialController;
use App\Modules\Learning\Http\Controllers\Student\LessonController;
use App\Modules\Learning\Http\Controllers\Student\ModuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/student')
    ->middleware([
        'resolve.tenant.optional',
        'api.context',
        'auth:sanctum',
        'area.guard:student',
        'tenant.required.unless.developer',
        'tenant.access',
    ])
    ->group(function (): void {
        Route::controller(CourseController::class)->group(function (): void {
            Route::get('/courses', 'index');
            Route::get('/courses/{courseId}', 'show');
            Route::get('/courses/{courseId}/modules', 'modules');
        });

        Route::controller(ModuleController::class)->group(function (): void {
            Route::get('/courses/{courseId}/modules/{moduleId}/lessons', 'lessons');
        });

        Route::controller(LessonController::class)->group(function (): void {
            Route::get('/lessons/{lessonId}', 'show');
            Route::get('/lessons/{lessonId}/media', 'media');
            Route::post('/lessons/{lessonId}/progress', 'progress');
        });

        Route::controller(CourseMaterialController::class)->group(function (): void {
            Route::get('/courses/{courseId}/materials', 'index');
            Route::post('/courses/{courseId}/materials/{materialId}/downloads', 'download');
        });
    });
