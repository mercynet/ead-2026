<?php

namespace App\Modules\Ecosystem\Http\Controllers\Mzrt;

use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Actions\Mzrt\ListTenantEntitlementsAction;
use App\Modules\Ecosystem\Http\Resources\Mzrt\TenantEntitlementResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group MZRT — Entitlements
 *
 * Consulta global dos entitlements de plugins de um tenant pela plataforma MZRT.
 */
class TenantEntitlementController extends Controller
{
    public function __construct(private readonly ListTenantEntitlementsAction $listTenantEntitlementsAction) {}

    /**
     * Listar entitlements do tenant
     *
     * Retorna todas as ativações de plugin do tenant, incluindo status inativos.
     *
     * @urlParam tenant int required ID do tenant. Example: 1
     *
     * @response 200 scenario="Entitlements encontrados"
     * {"data":[{"capability":"gateway.cash","status":"active"}]}
     * @response 403 scenario="Fora da área MZRT ou sem permissão"
     * {"data":null,"errors":[{"code":"access_denied","message":"Acesso negado."}]}
     * @response 404 scenario="Tenant não encontrado"
     * {"data":null,"errors":[{"code":"not_found","message":"Recurso não encontrado."}]}
     */
    public function index(ApiContext $context, Tenant $tenant): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('ecosystem.entitlements.list', [$tenant]);

        return TenantEntitlementResource::collection($this->listTenantEntitlementsAction->handle($tenant));
    }
}
