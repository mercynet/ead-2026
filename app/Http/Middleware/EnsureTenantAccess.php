<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $authenticatedUser */
        $authenticatedUser = $request->user('sanctum') ?? $request->user();
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($authenticatedUser === null || $tenant === null) {
            return $next($request);
        }

        if (! $authenticatedUser->isDeveloper() && ! $authenticatedUser->belongsToTenant($tenant)) {
            return response([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'forbidden',
                        'message' => 'User does not belong to tenant.',
                    ],
                ],
            ], 403);
        }

        return $next($request);
    }
}
