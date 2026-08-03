<?php

use App\Modules\Learning\Http\Controllers\Admin\CourseController;
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
                Route::get('/courses/{id}', 'show');
                Route::post('/courses/{id}/publish', 'publish');
                Route::post('/courses/{id}/unpublish', 'unpublish');
            });
    });
