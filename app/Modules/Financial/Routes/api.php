<?php

use App\Modules\Financial\Http\Controllers\Admin\ConfirmManualPaymentController;
use App\Modules\Financial\Http\Controllers\Student\OrderController;
use App\Modules\Financial\Http\Controllers\Student\StoreCheckoutController;
use App\Modules\Financial\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('throttle:payment-webhook')
    ->controller(PaymentWebhookController::class)
    ->group(function (): void {
        Route::post('/webhooks/gateways/{gateway_slug}', 'store')->where('gateway_slug', '[a-z0-9-]+');
    });

Route::prefix('v1')
    ->middleware(['resolve.tenant.optional', 'api.context'])
    ->group(function (): void {
        Route::middleware(['auth:sanctum', 'area.guard:admin', 'tenant.required.unless.developer', 'tenant.access'])
            ->controller(ConfirmManualPaymentController::class)
            ->group(function (): void {
                Route::post('/admin/orders/{id}/confirm-manual-payment', 'confirm')->whereNumber('id');
            });

        Route::middleware(['auth:sanctum', 'area.guard:student', 'tenant.required.unless.developer', 'tenant.access'])
            ->controller(StoreCheckoutController::class)
            ->group(function (): void {
                Route::post('/student/checkout', 'store');
            });

        Route::middleware(['auth:sanctum', 'area.guard:student', 'tenant.required.unless.developer', 'tenant.access'])
            ->controller(OrderController::class)
            ->group(function (): void {
                Route::get('/student/orders', 'index');
                Route::get('/student/orders/{id}', 'show')->whereNumber('id');
            });
    });
