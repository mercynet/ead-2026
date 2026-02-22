<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Actions\Core\Users\ListUsersAction;
use App\Actions\Core\Users\RegisterUserAction;
use App\Actions\Core\Users\ShowUserAction;
use App\Actions\Core\Users\UpdatePasswordAction;
use App\Actions\Core\Users\UpdateProfileAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\RegisterUserRequest;
use App\Http\Requests\Core\Users\UpdatePasswordRequest;
use App\Http\Requests\Core\Users\UpdateProfileRequest;
use App\Http\Resources\Core\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Usuários
 *
 * Gerenciamento de usuários
 */
class UserController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $registerUserAction,
        private readonly ListUsersAction $listUsersAction,
        private readonly ShowUserAction $showUserAction,
        private readonly UpdateProfileAction $updateProfileAction,
        private readonly UpdatePasswordAction $updatePasswordAction,
    ) {}

    /**
     * Listar Usuários
     *
     * Retorna uma lista paginada de usuários do tenant.
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->user)->authorize('core.users.list', [$context->tenant]);

        $paginator = $this->listUsersAction->handle($context);

        return UserResource::collection($paginator);
    }

    /**
     * Criar Usuário
     *
     * Registra um novo usuário no sistema.
     *
     * @unauthenticated
     */
    public function store(RegisterUserRequest $request, ApiContext $context): UserResource
    {
        $user = $this->registerUserAction->handle($context->requiredTenant(), $request->validated());

        return UserResource::make($user);
    }

    /**
     * Mostrar Usuário
     *
     * Retorna os dados de um usuário específico.
     */
    public function show(ApiContext $context, User $user): UserResource
    {
        Gate::forUser($context->user)->authorize('core.users.show', [$context->tenant, $user]);

        return UserResource::make($user);
    }

    /**
     * Atualizar Perfil
     *
     * Atualiza os dados do próprio usuário autenticado.
     */
    public function updateMe(UpdateProfileRequest $request, ApiContext $context): UserResource
    {
        Gate::forUser($context->user)->authorize('core.users.update-self', [$context->tenant, $context->requiredUser()]);

        $user = $this->updateProfileAction->handle($context->requiredUser(), $request->validated());

        return UserResource::make($user);
    }

    /**
     * Atualizar Senha
     *
     * Altera a senha do usuário autenticado.
     */
    public function updatePassword(UpdatePasswordRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->user)->authorize('core.users.update-self', [$context->tenant, $context->requiredUser()]);

        $this->updatePasswordAction->handle(
            $context->requiredUser(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return new JsonResponse([
            'data' => [
                'password_updated' => true,
            ],
        ]);
    }
}
