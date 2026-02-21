<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiLinks
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $payload = null;
        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
        } elseif ($response instanceof IlluminateResponse) {
            $originalContent = $response->getOriginalContent();
            if (is_array($originalContent)) {
                $payload = $originalContent;
            }
        }

        if (! is_array($payload)) {
            return $response;
        }

        $existingLinks = $payload['links'] ?? [];
        if (! is_array($existingLinks)) {
            $existingLinks = [];
        }

        $payload['links'] = [
            'first' => $existingLinks['first'] ?? null,
            'last' => $existingLinks['last'] ?? null,
            'prev' => $existingLinks['prev'] ?? null,
            'next' => $existingLinks['next'] ?? null,
        ];

        if ($response instanceof JsonResponse) {
            $response->setData($payload);
        } else {
            $response->setContent((string) json_encode($payload));
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
