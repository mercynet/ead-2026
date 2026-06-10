<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Users\ListUsersAction;
use App\Modules\Core\Actions\Users\RegisterUserAction;
use App\Modules\Core\Actions\Users\ShowUserAction;
use App\Modules\Core\Actions\Users\UpdatePasswordAction;
use App\Modules\Core\Actions\Users\UpdateProfileAction;
use App\Modules\Core\Http\Requests\Users\RegisterUserRequest;
use App\Modules\Core\Http\Requests\Users\UpdatePasswordRequest;
use App\Modules\Core\Http\Requests\Users\UpdateProfileRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Models\User;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
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
