<?php

namespace App\Http\Controllers\Api\V1\Core\Users;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShowUserController extends Controller
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $authenticatedUser = $request->user();

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($authenticatedUser === null || $tenant === null || (int) $authenticatedUser->tenant_id !== (int) $tenant->id) {
            if (! ($authenticatedUser?->isDeveloper() && $tenant !== null)) {
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
        }

        $user = User::query()->whereKey($id)->first();

        if ($user === null) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'not_found',
                        'message' => 'User not found.',
                    ],
                ],
            ], 404);
        }

        if (Gate::denies('core.users.show', [$tenant, $user])) {
            if ($authenticatedUser->isTenantAdmin() && ! $authenticatedUser->isDeveloper() && ! $user->belongsToTenant($tenant)) {
                return response()->json([
                    'data' => null,
                    'meta' => [],
                    'errors' => [
                        [
                            'code' => 'not_found',
                            'message' => 'User not found.',
                        ],
                    ],
                ], 404);
            }

            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'forbidden',
                        'message' => 'Not authorized to view this user.',
                    ],
                ],
            ], 403);
        }

        if (! $authenticatedUser->isDeveloper() && ! $user->belongsToTenant($tenant)) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'not_found',
                        'message' => 'User not found.',
                    ],
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'headline' => $user->headline,
                    'bio' => $user->bio,
                    'avatar' => $user->avatar,
                    'cpf' => $user->cpf,
                    'linkedin_url' => $user->linkedin_url,
                    'twitter_url' => $user->twitter_url,
                ],
            ],
            'meta' => [],
        ]);
    }
}
