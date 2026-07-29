<?php

use App\Modules\Financial\Http\Controllers\Admin\ConfirmManualPaymentController;
use App\Modules\Financial\Http\Controllers\Student\StoreCheckoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::middleware(['tenant.required.unless.developer', 'auth:sanctum', 'tenant.access'])
            ->group(function (): void {
                Route::controller(ConfirmManualPaymentController::class)->group(function (): void {
                    Route::post('/admin/orders/{id}/confirm-manual-payment', 'confirm')->whereNumber('id');
                });
                Route::controller(StoreCheckoutController::class)->group(function (): void {
                    Route::post('/student/checkout', 'store');
                });
            });
    });
