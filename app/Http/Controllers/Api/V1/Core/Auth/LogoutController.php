<?php

namespace App\Http\Controllers\Api\V1\Core\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($user === null || $tenant === null || (! $user->isDeveloper() && (int) $user->tenant_id !== (int) $tenant->id)) {
            return response()->json([
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

        $user->currentAccessToken()->delete();

        return response()->json([
            'data' => [
                'logged_out' => true,
            ],
            'meta' => [],
        ]);
    }
}
