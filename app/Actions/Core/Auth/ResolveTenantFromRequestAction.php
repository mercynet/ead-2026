<?php

namespace App\Actions\Core\Auth;

use App\Models\Tenant;
use Illuminate\Http\Request;

class ResolveTenantFromRequestAction
{
    public function handle(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');
        $tenantDomain = $request->header('X-Tenant-Domain');
        $host = $request->getHost();

        $tenantQuery = Tenant::query()->where('is_active', true);

        if ($tenantId !== null && $tenantId !== '') {
            return (clone $tenantQuery)->whereKey((int) $tenantId)->first();
        }

        if ($tenantDomain !== null && $tenantDomain !== '') {
            return (clone $tenantQuery)->where('domain', $tenantDomain)->first();
        }

        if ($host !== '') {
            return (clone $tenantQuery)->where('domain', $host)->first();
        }

        return null;
    }

    public function resolveAndBind(Request $request): ?Tenant
    {
        $tenant = $this->handle($request);
        $this->bind($request, $tenant);

        return $tenant;
    }

    public function bind(Request $request, ?Tenant $tenant): void
    {
        $request->attributes->set('tenant', $tenant);
        app()->instance('tenant', $tenant);

        if ($tenant !== null) {
            app()->instance(Tenant::class, $tenant);
        }
    }
}
