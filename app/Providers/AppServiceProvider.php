<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Gates e rotas de domínio vivem nos providers de módulo
 * (App\Modules\<M>\Providers) — ver bootstrap/providers.php.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
