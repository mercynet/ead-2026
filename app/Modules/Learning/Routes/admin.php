<?php

use App\Modules\Learning\Http\Controllers\Admin\CategoryController;
use App\Modules\Learning\Http\Controllers\Admin\CourseController;
use App\Modules\Learning\Http\Controllers\Admin\CourseMaterialController;
use App\Modules\Learning\Http\Controllers\Admin\EnrollmentController;
use App\Modules\Learning\Http\Controllers\Admin\LessonController;
use App\Modules\Learning\Http\Controllers\Admin\LessonMediaController;
use App\Modules\Learning\Http\Controllers\Admin\ModuleController;
use Illuminate\Support\Facades\Route;

/*
 * Área Admin — administrador do tenant. Vê tudo do próprio tenant (e só dele),
 * incluindo drafts. Guard de área: area.guard:admin. Ver
 * docs/specs/00-architecture/areas-surfaces.md.
 */
Route::prefix('v1/admin')
    ->middleware([
        'resolve.tenant.optional',
        'api.context',
        'auth:sanctum',
        'area.guard:admin',
        'tenant.required.unless.developer',
        'tenant.access',
    ])
    ->group(function (): void {
        Route::controller(CourseController::class)
            ->group(function (): void {
                Route::get('/courses', 'index');
                Route::post('/courses', 'store');
                Route::get('/courses/{id}', 'show');
                Route::patch('/courses/{id}', 'update');
                Route::delete('/courses/{id}', 'destroy');
                Route::post('/courses/{id}/publish', 'publish');
                Route::post('/courses/{id}/unpublish', 'unpublish');
                Route::put('/courses/{id}/categories', 'syncCategories');
            });

        Route::controller(ModuleController::class)
            ->group(function (): void {
                Route::get('/courses/{courseId}/modules', 'index');
                Route::post('/modules', 'store');
                Route::patch('/modules/reorder', 'reorder');
                Route::get('/modules/{id}', 'show');
                Route::patch('/modules/{id}', 'update');
                Route::delete('/modules/{id}', 'destroy');
            });

        Route::controller(LessonController::class)
            ->group(function (): void {
                Route::get('/modules/{moduleId}/lessons', 'index');
                Route::post('/lessons', 'store');
                Route::post('/lessons/{id}/publish', 'publish');
                Route::post('/lessons/{id}/unpublish', 'unpublish');
                Route::patch('/lessons/reorder', 'reorder');
                Route::get('/lessons/{id}', 'show');
                Route::patch('/lessons/{id}', 'update');
                Route::delete('/lessons/{id}', 'destroy');
            });

        Route::controller(CourseMaterialController::class)
            ->group(function (): void {
                Route::get('/courses/{courseId}/materials', 'index');
                Route::post('/courses/{courseId}/materials', 'store');
                Route::get('/courses/{courseId}/materials/{materialId}', 'show');
                Route::patch('/courses/{courseId}/materials/{materialId}', 'update');
                Route::delete('/courses/{courseId}/materials/{materialId}', 'destroy');
            });

        Route::controller(LessonMediaController::class)
            ->group(function (): void {
                Route::get('/lessons/{lessonId}/media', 'index');
                Route::post('/lessons/{lessonId}/media', 'store');
                Route::get('/lessons/{lessonId}/media/{mediaId}', 'show');
                Route::patch('/lessons/{lessonId}/media/{mediaId}', 'update');
                Route::delete('/lessons/{lessonId}/media/{mediaId}', 'destroy');
            });

        Route::controller(CategoryController::class)
            ->group(function (): void {
                Route::post('/categories', 'store');
                Route::put('/categories/{id}', 'update');
                Route::delete('/categories/{id}', 'destroy');
            });

        Route::controller(EnrollmentController::class)
            ->group(function (): void {
                Route::get('/enrollments', 'index');
                Route::post('/enrollments', 'store');
                Route::get('/enrollments/{id}', 'show');
                Route::patch('/enrollments/{id}', 'update');
                Route::delete('/enrollments/{id}', 'destroy');
            });
    });
