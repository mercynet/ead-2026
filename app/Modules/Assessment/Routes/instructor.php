<?php

use App\Modules\Assessment\Http\Controllers\Instructor\QuestionController;
use App\Modules\Assessment\Http\Controllers\Instructor\QuestionnaireController;
use App\Modules\Assessment\Http\Controllers\Instructor\ResultController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/instructor/assessment')
    ->middleware([
        'resolve.tenant.optional',
        'api.context',
        'auth:sanctum',
        'area.guard:instructor',
        'tenant.required.unless.developer',
        'tenant.access',
    ])
    ->group(function (): void {
        Route::controller(QuestionnaireController::class)->prefix('questionnaires')->group(function (): void {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show');
            Route::patch('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
            Route::get('/{questionnaireId}/questions', 'questions');
            Route::post('/{questionnaireId}/questions', 'attach');
            Route::patch('/{questionnaireId}/questions/reorder', 'reorder');
            Route::delete('/{questionnaireId}/questions/{questionId}', 'detach');
        });

        Route::controller(QuestionController::class)->prefix('questions')->group(function (): void {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show');
            Route::patch('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });

        Route::controller(ResultController::class)->prefix('results')->group(function (): void {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
        });
    });
