<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Shared\Exceptions\TenantContextRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantRequiredForNonDeveloper
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

        if ($tenant !== null) {
            return $next($request);
        }

        if ($authenticatedUser !== null && $authenticatedUser->isDeveloper()) {
            return $next($request);
        }

        throw TenantContextRequiredException::make();
    }
}
