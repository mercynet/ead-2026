<?php

namespace App\Modules\Ecosystem\Http\Controllers\Admin;

use App\Modules\Ecosystem\Actions\Admin\ListTenantPaymentGatewaysAction;
use App\Modules\Ecosystem\Actions\Admin\UpdateTenantPaymentGatewayAction;
use App\Modules\Ecosystem\Http\Requests\Admin\UpdatePaymentGatewayRequest;
use App\Modules\Ecosystem\Http\Resources\Admin\TenantPaymentGatewayResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Gateways de pagamento
 *
 * Consulta e mantém configuração de gateways de pagamento habilitados para o tenant.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly ListTenantPaymentGatewaysAction $listTenantPaymentGatewaysAction,
        private readonly UpdateTenantPaymentGatewayAction $updateTenantPaymentGatewayAction,
    ) {}

    /**
     * Listar Gateways de Pagamento
     *
     * Lista gateways ativos para o tenant, com disponibilidade do adaptador e schema seguro de configuração.
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('financial.payment-gateways.list', [$context->requiredTenant()]);

        return TenantPaymentGatewayResource::collection($this->listTenantPaymentGatewaysAction->handle($context));
    }

    /**
     * Atualizar Gateway de Pagamento
     *
     * Habilita ou desabilita um gateway ativo do tenant e atualiza sua configuração conforme schema publicado.
     * Valores secretos não são retornados na resposta.
     *
     * @urlParam plugin string required Slug do plugin de gateway. Example: cash
     */
    public function update(UpdatePaymentGatewayRequest $request, string $plugin, ApiContext $context): JsonResource
    {
        Gate::forUser($context->requiredUser())->authorize('financial.payment-gateways.update', [$context->requiredTenant()]);

        return TenantPaymentGatewayResource::make(
            $this->updateTenantPaymentGatewayAction->handle($context, $plugin, $request->validated())
        );
    }
}
