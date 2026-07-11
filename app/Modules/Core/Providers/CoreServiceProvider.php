<?php

namespace App\Modules\Core\Providers;

use App\Modules\Core\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerGates();
        $this->registerRoutes();
    }

    private function registerGates(): void
    {
        Gate::define('core.users.list', [UserPolicy::class, 'list']);
        Gate::define('core.users.show', [UserPolicy::class, 'show']);
        Gate::define('core.users.update-self', [UserPolicy::class, 'updateSelf']);
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__.'/../Routes/api.php');
    }
}
