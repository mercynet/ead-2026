<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Actions\Core\Auth\LoginAction;
use App\Actions\Core\Auth\LogoutAction;
use App\Actions\Core\Auth\MeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly MeAction $meAction,
        private readonly LogoutAction $logoutAction,
    ) {}

    public function login(LoginRequest $request): Response
    {
        $result = $this->loginAction->handle($request);

        return response($result['payload'], $result['status']);
    }

    public function me(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return response([
            'data' => [
                'user' => $this->meAction->handle($user),
            ],
            'meta' => [],
        ]);
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->logoutAction->handle($user);

        return response([
            'data' => [
                'logged_out' => true,
            ],
            'meta' => [],
        ]);
    }
}
