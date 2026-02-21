<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
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
        $tenantId = $request->header('X-Tenant-ID');
        $tenantDomain = $request->header('X-Tenant-Domain');
        $host = $request->getHost();

        $tenantQuery = Tenant::query()->where('is_active', true);

        $tenant = null;

        if ($tenantId !== null && $tenantId !== '') {
            $tenant = (clone $tenantQuery)->whereKey((int) $tenantId)->first();
        } elseif ($tenantDomain !== null && $tenantDomain !== '') {
            $tenant = (clone $tenantQuery)->where('domain', $tenantDomain)->first();
        } elseif ($host !== '') {
            $tenant = (clone $tenantQuery)->where('domain', $host)->first();
        }

        if ($tenant === null) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'tenant_not_resolved',
                        'message' => 'Tenant context is required.',
                    ],
                ],
            ], 422);
        }

        $request->attributes->set('tenant', $tenant);
        app()->instance(Tenant::class, $tenant);

        return $next($request);
    }
}
