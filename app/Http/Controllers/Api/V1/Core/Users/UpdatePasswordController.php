<?php

namespace App\Http\Controllers\Api\V1\Core\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\UpdatePasswordRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdatePasswordController extends Controller
{
    public function __invoke(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        /** @var Tenant|null $tenant */
        $tenant = $request->attributes->get('tenant');

        if ($user === null || $tenant === null || Gate::denies('core.users.update-self', [$tenant, $user])) {
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

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is invalid.',
            ]);
        }

        $user->password = $request->string('password')->toString();
        $user->save();

        return response()->json([
            'data' => [
                'password_updated' => true,
            ],
            'meta' => [],
        ]);
    }
}
