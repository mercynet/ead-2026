<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Tenants\UpdateTenantStatusAction;
use App\Modules\Core\Http\Requests\Tenants\UpdateTenantStatusRequest;
use App\Modules\Core\Http\Resources\Tenants\TenantStatusResource;
use App\Modules\Core\Models\Tenant;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group MZRT — Tenants
 *
 * Gestão global de tenants pela plataforma MZRT.
 */
class MzrtTenantStatusController extends Controller
{
    public function __construct(private readonly UpdateTenantStatusAction $updateTenantStatusAction) {}

    /**
     * Atualizar status do tenant
     *
     * Ativa ou suspende um tenant. A suspensão bloqueia autenticações e tokens
     * vinculados ao tenant; repetir o status atual não gera alteração.
     *
     * @urlParam tenant int required ID do tenant. Example: 1
     *
     * @response 200 scenario="Status atualizado"
     * {
     *   "data": {"status": "suspended"}
     * }
     * @response 403 scenario="Fora da área MZRT ou sem permissão"
     * {
     *   "data": null,
     *   "errors": [{"code": "access_denied", "message": "Acesso negado."}]
     * }
     * @response 404 scenario="Tenant não encontrado"
     * {
     *   "data": null,
     *   "errors": [{"code": "not_found", "message": "Recurso não encontrado."}]
     * }
     * @response 422 scenario="Status inválido"
     * {
     *   "data": null,
     *   "errors": [{"code": "validation_error", "message": "O status deve ser active ou suspended."}]
     * }
     */
    public function update(UpdateTenantStatusRequest $request, ApiContext $context, Tenant $tenant): TenantStatusResource
    {
        Gate::forUser($context->requiredUser())->authorize('core.tenants.update-status', [$tenant]);

        return TenantStatusResource::make(
            $this->updateTenantStatusAction->handle($tenant, $request->string('status')->toString()),
        );
    }
}
