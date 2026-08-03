<?php

use App\Modules\Financial\Http\Controllers\Admin\ConfirmManualPaymentController;
use App\Modules\Financial\Http\Controllers\Student\StoreCheckoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::middleware(['tenant.required.unless.developer', 'auth:sanctum', 'area.guard:admin', 'tenant.access'])
            ->controller(ConfirmManualPaymentController::class)
            ->group(function (): void {
                Route::post('/admin/orders/{id}/confirm-manual-payment', 'confirm')->whereNumber('id');
            });

        Route::middleware(['tenant.required.unless.developer', 'auth:sanctum', 'area.guard:student', 'tenant.access'])
            ->controller(StoreCheckoutController::class)
            ->group(function (): void {
                Route::post('/student/checkout', 'store');
            });
    });
