<?php

namespace App\Http\Controllers\Api\V1\Core\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($tenant === null) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'tenant_not_resolved',
                        'message' => 'Tenant context is required.',
                    ],
                ],
            ], 422);
        }

        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'invalid_credentials',
                        'message' => 'Invalid credentials.',
                    ],
                ],
            ], 401);
        }

        if (! $user->isDeveloper() && (int) $user->tenant_id !== (int) $tenant->id) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'invalid_credentials',
                        'message' => 'Invalid credentials.',
                    ],
                ],
            ], 401);
        }

        $plainTextToken = $user->createToken('core-auth')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $plainTextToken,
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
