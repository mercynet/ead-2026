<?php

use App\Modules\Assessment\Http\Controllers\Admin\QuestionController;
use App\Modules\Assessment\Http\Controllers\Admin\QuestionnaireController;
use Illuminate\Support\Facades\Route;

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
        Route::controller(QuestionnaireController::class)
            ->prefix('questionnaires')
            ->group(function (): void {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });

        Route::controller(QuestionController::class)
            ->prefix('questions')
            ->group(function (): void {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
            });
    });
