<?php

namespace App\Http\Controllers\Api\V1\Core\Users;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListUsersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
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

        if (Gate::denies('core.users.list', [$tenant])) {
            return response()->json([
                'data' => null,
                'meta' => [],
                'errors' => [
                    [
                        'code' => 'forbidden',
                        'message' => 'Not authorized to list users.',
                    ],
                ],
            ], 403);
        }

        $usersQuery = User::query();

        if (! $authenticatedUser->isDeveloper()) {
            $usersQuery->where('tenant_id', $tenant->id);
        }

        $paginator = $usersQuery
            ->orderBy('id')
            ->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(static function (User $user): array {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'headline' => $user->headline,
                ];
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
