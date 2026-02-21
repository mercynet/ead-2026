<?php

namespace App\Actions\Core\Auth;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class RequestTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?IsTenant
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
}
