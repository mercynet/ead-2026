<?php

namespace App\Modules\Assessment\Http\Middleware;

use App\Modules\Core\Enums\UserType;
use App\Shared\Exceptions\AccessDeniedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BlockLegacyStudentAssessment
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (($request->user('sanctum') ?? $request->user())?->user_type === UserType::Student) {
            throw AccessDeniedException::make('legacy Student Assessment', 'attempts');
        }

        return $next($request);
    }
}
