<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Enums\Area;
use App\Modules\Core\Models\User;
use App\Shared\Exceptions\AreaAccessDeniedException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda de área: valida que o UserType autenticado pode alcançar a superfície.
 *
 * A área é o teto de superfície (qual audiência); o RBAC continua decidindo a
 * ação dentro dela. Áreas não-públicas exigem autenticação. Ver areas-surfaces.md.
 */
class EnsureAreaAccess
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $resolvedArea = Area::from($area);

        /** @var User|null $authenticatedUser */
        $authenticatedUser = $request->user('sanctum') ?? $request->user();

        if ($authenticatedUser === null) {
            if (! $resolvedArea->requiresAuthentication()) {
                return $next($request);
            }

            throw new AuthenticationException;
        }

        if (! $resolvedArea->allows($authenticatedUser->user_type)) {
            throw AreaAccessDeniedException::make($resolvedArea);
        }

        return $next($request);
    }
}
