<?php

namespace App\Modules\Financial\Providers;

use App\Modules\Financial\Contracts\GatewayConfigurationRegistry;
use App\Modules\Financial\Gateways\Adapters\CashPaymentGateway;
use App\Modules\Financial\Gateways\Adapters\E2eWebhookGateway;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use App\Modules\Financial\Listeners\CreateEnrollmentFinancialMirrorListener;
use App\Modules\Financial\Policies\OrderPolicy;
use App\Modules\Learning\Events\EnrollmentCreatedEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        if (app()->environment(['testing', 'e2e'])) {
            $paymentGatewayManager->register(new E2eWebhookGateway);
        }

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerGates();
        $this->registerRateLimiters();
        $this->registerListeners();
        $this->registerRoutes();
    }

    private function registerGates(): void
    {
        Gate::define('financial.orders.list', [OrderPolicy::class, 'list']);
        Gate::define('financial.orders.view', [OrderPolicy::class, 'view']);
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('payment-webhook', fn (Request $request): Limit => Limit::perMinute(120)->by(
            (string) $request->route('gateway_slug').'|'.(string) $request->ip()
        ));
    }

    private function registerListeners(): void
    {
        Event::listen(EnrollmentCreatedEvent::class, CreateEnrollmentFinancialMirrorListener::class);
    }
}
