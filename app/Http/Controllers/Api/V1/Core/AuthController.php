<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Actions\Core\Auth\LoginAction;
use App\Actions\Core\Auth\LogoutAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Auth\LoginRequest;
use App\Http\Resources\Core\Auth\AuthUserResource;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly LogoutAction $logoutAction,
    ) {}

    public function login(LoginRequest $request, ApiContext $context): JsonResponse
    {
        $result = $this->loginAction->handle($request, $context);

        return new JsonResponse([
            'data' => $result,
        ]);
    }

    public function me(ApiContext $context): AuthUserResource
    {
        return AuthUserResource::make($context->requiredUser());
    }

    public function logout(ApiContext $context): JsonResponse
    {
        $this->logoutAction->handle($context->requiredUser());

        return new JsonResponse([
            'data' => [
                'logged_out' => true,
            ],
        ]);
    }
}
