<?php

use App\Modules\Ecosystem\Http\Controllers\Admin\PaymentGatewayController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::middleware(['tenant.required.unless.developer', 'auth:sanctum', 'area.guard:admin', 'tenant.access'])
            ->controller(PaymentGatewayController::class)
            ->group(function (): void {
                Route::get('/admin/payment-gateways', 'index');
                Route::put('/admin/payment-gateways/{plugin}', 'update');
            });
    });
