<?php

namespace App\Http\Middleware;

use App\Http\Context\ApiContext;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectApiContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);
        $tenant = $this->resolveTenant($request);

        $context = new ApiContext($user, $tenant);

        app()->instance(ApiContext::class, $context);
        $request->attributes->set('apiContext', $context);

        return $next($request);
    }

    private function resolveUser(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        return $user;
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }
}
