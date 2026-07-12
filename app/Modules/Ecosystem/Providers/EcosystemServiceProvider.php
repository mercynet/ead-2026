<?php

namespace App\Modules\Ecosystem\Providers;

use App\Modules\Ecosystem\Contracts\TenantGatewayProvider;
use App\Modules\Ecosystem\Services\EcosystemTenantGatewayProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EcosystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantGatewayProvider::class, EcosystemTenantGatewayProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
    }
}
