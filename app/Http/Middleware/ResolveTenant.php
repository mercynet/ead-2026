<?php

namespace App\Http\Middleware;

use App\Exceptions\TenantContextRequiredException;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var TenantFinder $tenantFinder */
        $tenantFinder = app(config('multitenancy.tenant_finder'));
        /** @var Tenant|null $tenant */
        $tenant = $tenantFinder->findForRequest($request);

        if ($tenant !== null) {
            $tenant->makeCurrent();
        } else {
            app(IsTenant::class)::forgetCurrent();
        }

        $request->attributes->set('tenant', $tenant);
        app()->instance('tenant', $tenant);
        if ($tenant !== null) {
            app()->instance(Tenant::class, $tenant);
        }

        if ($tenant === null) {
            throw TenantContextRequiredException::make();
        }

        return $next($request);
    }
}
