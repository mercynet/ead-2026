<?php

namespace App\Modules\Financial\Providers;

use App\Modules\Financial\Contracts\GatewayConfigurationRegistry;
use App\Modules\Financial\Gateways\Adapters\CashPaymentGateway;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use App\Modules\Financial\Listeners\CreateEnrollmentFinancialMirrorListener;
use App\Modules\Learning\Events\EnrollmentCreatedEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FinancialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->alias(PaymentGatewayManager::class, GatewayConfigurationRegistry::class);
    }

    public function boot(PaymentGatewayManager $paymentGatewayManager): void
    {
        $paymentGatewayManager->register(new CashPaymentGateway);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerListeners();
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
    }

    private function registerListeners(): void
    {
        Event::listen(EnrollmentCreatedEvent::class, CreateEnrollmentFinancialMirrorListener::class);
    }
}
