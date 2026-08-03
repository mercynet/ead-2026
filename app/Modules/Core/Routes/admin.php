<?php

use App\Modules\Core\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Área Admin — administrador do tenant. Administra instructor e student do
 * próprio tenant. Guard de área: area.guard:admin. Ver
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
        Route::controller(UserController::class)
            ->group(function (): void {
                Route::patch('/users/{user}', 'update');
                Route::delete('/users/{user}', 'destroy');
            });
    });
