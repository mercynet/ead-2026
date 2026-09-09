<?php

namespace App\Shared\Http;

use App\Shared\Operations\ReadinessCheck;
use Illuminate\Http\JsonResponse;

final class ReadinessController extends Controller
{
    public function __invoke(ReadinessCheck $readiness): JsonResponse
    {
        $result = $readiness->run();

        return response()
            ->json($result, $result['status'] === 'ready' ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
