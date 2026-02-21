<?php

namespace App\Http\Middleware;

use App\Actions\Core\Auth\ResolveTenantFromRequestAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantOptional
{
    public function __construct(private readonly ResolveTenantFromRequestAction $resolveTenantFromRequestAction) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->resolveTenantFromRequestAction->resolveAndBind($request);

        return $next($request);
    }
}
