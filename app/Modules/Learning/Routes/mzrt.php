<?php

use App\Modules\Learning\Http\Controllers\Mzrt\CategoryController;
use Illuminate\Support\Facades\Route;

/*
 * Área Mzrt — operador da plataforma. Superfície global, sem contexto nem header
 * de tenant: o recurso é da plataforma, não de um tenant. Guard de área:
 * area.guard:mzrt. Ver docs/specs/00-architecture/areas-surfaces.md.
 */
Route::prefix('v1/mzrt')
    ->middleware(['auth:sanctum', 'area.guard:mzrt', 'api.context'])
    ->group(function (): void {
        Route::controller(CategoryController::class)
            ->group(function (): void {
                Route::post('/categories', 'store');
                Route::put('/categories/{id}', 'update');
                Route::delete('/categories/{id}', 'destroy');
            });
    });
