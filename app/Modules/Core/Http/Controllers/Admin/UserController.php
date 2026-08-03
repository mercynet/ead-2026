<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Actions\Users\DeleteUserAction;
use App\Modules\Core\Actions\Users\UpdateProfileAction;
use App\Modules\Core\Http\Requests\Users\AdminUpdateUserRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Models\User;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Usuários
 *
 * Administração de instructor e student do próprio tenant (área Admin). Outro
 * admin e developer não são alcançáveis: admin editando admin é escalada lateral.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UpdateProfileAction $updateProfileAction,
        private readonly DeleteUserAction $deleteUserAction,
    ) {}

    /**
     * Atualizar Usuário (Admin)
     *
     * Atualiza dados de perfil de um instructor ou student do próprio tenant.
     * `user_type`, `email`, `cpf` e `password` são proibidos no payload.
     *
     * @urlParam user int required ID do usuário
     *
     * @response 200 scenario="Usuário atualizado"
     * {
     *   "data": {"id": 7, "name": "Maria Silva", "email": "maria@tenant.local"}
     * }
     * @response 403 scenario="Fora da área ou alvo não administrável"
     * {
     *   "data": null,
     *   "errors": [{"code": "area_forbidden", "message": "Acesso negado à área admin."}]
     * }
     * @response 404 scenario="Usuário de outro tenant, developer ou inexistente"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     * @response 422 scenario="Campo proibido no payload"
     * {
     *   "data": null,
     *   "errors": [{"code": "validation_error", "message": "User type changes are restricted to developers."}]
     * }
     */
    public function update(AdminUpdateUserRequest $request, ApiContext $context, User $user): UserResource
    {
        Gate::forUser($context->requiredUser())->authorize('core.users.update-check', [$context->tenant, $user]);

        return UserResource::make($this->updateProfileAction->handle($user, $request->validated()));
    }

    /**
     * Excluir Usuário (Admin)
     *
     * Soft delete de um instructor ou student do próprio tenant. As sessões
     * Sanctum do usuário são revogadas na mesma transação, e o histórico
     * (matrículas, orders, auditoria) é preservado.
     *
     * O e-mail continua reservado no tenant depois da exclusão — o `unique` de
     * e-mail é sobre a linha, não sobre a linha ativa.
     *
     * @urlParam user int required ID do usuário
     *
     * @response 200 scenario="Usuário excluído"
     * {
     *   "message": "User deleted successfully."
     * }
     * @response 404 scenario="Usuário de outro tenant, developer ou inexistente"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     */
    public function destroy(ApiContext $context, User $user): array
    {
        Gate::forUser($context->requiredUser())->authorize('core.users.delete-check', [$context->tenant, $user]);

        $this->deleteUserAction->handle($user);

        return ['message' => 'User deleted successfully.'];
    }
}
