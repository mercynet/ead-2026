<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequestTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);
        Log::withoutContext();
        Log::withContext(['request_id' => $requestId]);

        $startedAt = microtime(true);
        $response = null;
        $status = 500;

        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } catch (Throwable $exception) {
            Log::critical('exception.unhandled', [
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        } finally {
            $tenant = $request->attributes->get('tenant');
            $user = $request->user('sanctum') ?? $request->user();
            $route = $request->route();
            $routeName = is_object($route) ? $route->getName() : null;
            $context = [
                'request_id' => $requestId,
                'tenant_id' => is_object($tenant) && isset($tenant->id) ? (int) $tenant->id : null,
                'user_id' => is_object($user) && isset($user->id) ? (int) $user->id : null,
                'route' => $routeName ?? $request->path(),
                'status' => $status,
                'latency_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
            Log::withoutContext();
            Log::withContext($context);
            Log::log($status >= 500 ? 'error' : 'info', 'http.request', $context);
        }
    }

    private function requestId(Request $request): string
    {
        $incoming = $request->header('X-Request-ID');

        return is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();
    }
}
