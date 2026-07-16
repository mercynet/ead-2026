<?php

namespace App\Modules\Core\Providers;

use App\Modules\Core\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerGates();
        $this->registerRateLimiters();
        $this->registerRoutes();
    }

    /**
     * Buckets de rate limit NOMEADOS e separados por rota. Sem nome, o throttle
     * padrão chaveia rotas anônimas por `domínio|IP` — login, forgot, reset e
     * aceite de convite dividiriam o MESMO contador (SEC-001). Cada limiter aqui
     * isola o seu bucket por IP.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('password-forgot', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('invitation-accept', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('invitation-create', fn (Request $request): Limit => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
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
