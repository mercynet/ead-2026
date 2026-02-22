<?php

use App\Http\Controllers\Api\V1\Core\AuthController;
use App\Http\Controllers\Api\V1\Core\UserController;
use App\Http\Controllers\Api\V1\Learning\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Learning\Catalog\CourseController as CatalogCourseController;
use App\Http\Controllers\Api\V1\Learning\Course\CourseController;
use App\Http\Controllers\Api\V1\Learning\Enrollment\EnrollmentController;
use App\Http\Controllers\Api\V1\Learning\Lesson\LessonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/core')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::prefix('auth')
            ->controller(AuthController::class)
            ->group(function (): void {
                Route::post('/login', 'login')->middleware('throttle:5,1');

                Route::middleware([
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
            ->middleware('tenant.required.unless.developer')
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

Route::prefix('v1/assessment')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::prefix('questionnaires')
            ->controller(\App\Http\Controllers\Api\V1\Assessment\QuestionnaireController::class)
            ->middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
            ->group(function (): void {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });

        Route::prefix('questions')
            ->controller(\App\Http\Controllers\Api\V1\Assessment\QuestionController::class)
            ->middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
            ->group(function (): void {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
            });

        Route::prefix('attempts')
            ->controller(\App\Http\Controllers\Api\V1\Assessment\AttemptController::class)
            ->middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
            ->group(function (): void {
                Route::post('/questionnaires/{questionnaireId}', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::post('/{id}/finish', 'finish');
            });

        Route::prefix('certificates')
            ->controller(\App\Http\Controllers\Api\V1\Assessment\CertificateController::class)
            ->group(function (): void {
                Route::get('/verify/{certificateNumber}', 'verify');

                Route::middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
                    ->group(function (): void {
                        Route::get('/', 'index');
                        Route::get('/{id}', 'show');
                    });
            });
    });
