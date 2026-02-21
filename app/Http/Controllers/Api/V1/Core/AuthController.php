<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Actions\Core\Auth\LoginAction;
use App\Actions\Core\Auth\LogoutAction;
use App\Actions\Core\Auth\MeAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Auth\LoginRequest;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly MeAction $meAction,
        private readonly LogoutAction $logoutAction,
    ) {}

    public function login(LoginRequest $request, ApiContext $context): Response
    {
        $result = $this->loginAction->handle($request, $context);

        return response([
            'data' => $result,
        ]);
    }

    public function me(ApiContext $context): Response
    {
        return response([
            'data' => $this->meAction->handle($context->requiredUser()),
        ]);
    }

    public function logout(ApiContext $context): Response
    {
        $this->logoutAction->handle($context->requiredUser());

        return response([
            'data' => [
                'logged_out' => true,
            ],
        ]);
    }
}
