<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Auth\LoginAction;
use App\Modules\Core\Actions\Auth\LogoutAction;
use App\Modules\Core\Actions\Auth\RequestPasswordResetAction;
use App\Modules\Core\Actions\Auth\ResetPasswordAction;
use App\Modules\Core\Http\Requests\Auth\ForgotPasswordRequest;
use App\Modules\Core\Http\Requests\Auth\LoginRequest;
use App\Modules\Core\Http\Requests\Auth\ResetPasswordRequest;
use App\Modules\Core\Http\Resources\Auth\AuthUserResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @group Autenticação
 *
 * Endpoints para gerenciamento de autenticação
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly LogoutAction $logoutAction,
        private readonly RequestPasswordResetAction $requestPasswordResetAction,
        private readonly ResetPasswordAction $resetPasswordAction,
    ) {}

    /**
     * Login
     *
     * Autentica o usuário e retorna um token de acesso.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request, ApiContext $context): JsonResponse
    {
        $result = $this->loginAction->handle($request, $context);

        return new JsonResponse([
            'data' => $result,
        ]);
    }

    /**
     * Esqueci a Senha
     *
     * Emite um pedido de redefinição de senha para o email informado no tenant
     * atual e envia o token por e-mail. A resposta é sempre genérica, exista o
     * email ou não (anti-enumeração).
     *
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request, ApiContext $context): JsonResponse
    {
        $this->requestPasswordResetAction->handle(
            $context->requiredTenant(),
            $request->string('email')->toString(),
        );

        return new JsonResponse([
            'data' => [
                'message' => 'Se o email existir, enviaremos instruções de redefinição.',
            ],
        ]);
    }

    /**
     * Redefinir Senha
     *
     * Consome o token de redefinição e troca a senha. Token inválido, expirado
     * ou já usado falha genericamente. Todas as sessões anteriores são revogadas.
     *
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request, ApiContext $context): JsonResponse
    {
        $this->resetPasswordAction->handle(
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return new JsonResponse([
            'data' => [
                'password_reset' => true,
            ],
        ]);
    }

    /**
     * Me
     *
     * Retorna os dados do usuário autenticado.
     */
    public function me(ApiContext $context): AuthUserResource
    {
        return AuthUserResource::make($context->requiredUser());
    }

    /**
     * Logout
     *
     * Invalida o token de acesso do usuário.
     */
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
