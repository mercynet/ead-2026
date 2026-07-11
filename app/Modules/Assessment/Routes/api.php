<?php

use App\Modules\Assessment\Http\Controllers\AttemptController;
use App\Modules\Assessment\Http\Controllers\CertificateController;
use App\Modules\Assessment\Http\Controllers\QuestionController;
use App\Modules\Assessment\Http\Controllers\QuestionnaireController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/assessment')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::prefix('questionnaires')
            ->controller(QuestionnaireController::class)
            ->middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
            ->group(function (): void {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });

        Route::prefix('questions')
            ->controller(QuestionController::class)
            ->middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
            ->group(function (): void {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
            });

        Route::prefix('attempts')
            ->controller(AttemptController::class)
            ->middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
            ->group(function (): void {
                Route::post('/questionnaires/{questionnaireId}', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::post('/{id}/finish', 'finish');
            });

        Route::prefix('certificates')
            ->controller(CertificateController::class)
            ->group(function (): void {
                Route::get('/verify/{certificateNumber}', 'verify');

                Route::middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
                    ->group(function (): void {
                        Route::get('/', 'index');
                        Route::get('/{id}', 'show');
                    });
            });
    });
