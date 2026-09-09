<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

/**
 * Gates e rotas de domínio vivem nos providers de módulo
 * (App\Modules\<M>\Providers) — ver bootstrap/providers.php.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        TrustProxies::at(config('app.trusted_proxies', []));

        $trustedHosts = config('app.trusted_hosts', []);
        if ($trustedHosts !== []) {
            TrustHosts::at($trustedHosts, subdomains: false);
        }
    }
}
