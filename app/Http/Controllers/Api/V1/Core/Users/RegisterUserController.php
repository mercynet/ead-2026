<?php

namespace App\Http\Controllers\Api\V1\Core\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\RegisterUserRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RegisterUserController extends Controller
{
    public function __invoke(RegisterUserRequest $request): JsonResponse
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

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'meta' => [],
        ], 201);
    }
}
