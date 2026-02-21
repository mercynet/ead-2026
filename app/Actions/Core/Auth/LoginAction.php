<?php

namespace App\Actions\Core\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function handle(Request $request): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return [
                'status' => 401,
                'payload' => [
                    'data' => null,
                    'errors' => [
                        [
                            'code' => 'invalid_credentials',
                            'message' => 'Invalid credentials.',
                        ],
                    ],
                ],
            ];
        }

        if (! $user->isDeveloper() && $tenant === null) {
            return [
                'status' => 422,
                'payload' => [
                    'data' => null,
                    'errors' => [
                        [
                            'code' => 'tenant_not_resolved',
                            'message' => 'Tenant context is required.',
                        ],
                    ],
                ],
            ];
        }

        if (! $user->isDeveloper() && (int) $user->tenant_id !== (int) $tenant?->id) {
            return [
                'status' => 401,
                'payload' => [
                    'data' => null,
                    'errors' => [
                        [
                            'code' => 'invalid_credentials',
                            'message' => 'Invalid credentials.',
                        ],
                    ],
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'data' => [
                    'token' => $user->createToken('core-auth')->plainTextToken,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ],
            ],
        ];
    }
}
