<?php

namespace App\Http\Controllers\Api\V1\Core\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\UpdateProfileRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class UpdateMeController extends Controller
{
    public function __invoke(UpdateProfileRequest $request): JsonResponse
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

        $user->fill($request->validated());
        $user->save();

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
