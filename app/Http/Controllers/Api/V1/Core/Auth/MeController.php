<?php

namespace App\Http\Controllers\Api\V1\Core\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($user === null || $tenant === null || (int) $user->tenant_id !== (int) $tenant->id) {
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

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'meta' => [],
        ]);
    }
}
