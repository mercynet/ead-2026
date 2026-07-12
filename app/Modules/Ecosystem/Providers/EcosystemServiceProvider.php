<?php

namespace App\Modules\Ecosystem\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EcosystemServiceProvider extends ServiceProvider
{
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
