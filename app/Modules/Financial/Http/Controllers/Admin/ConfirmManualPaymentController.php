<?php

namespace App\Modules\Financial\Http\Controllers\Admin;

use App\Modules\Financial\Actions\Admin\ConfirmManualPaymentAction;
use App\Modules\Financial\Http\Requests\Admin\ConfirmManualPaymentRequest;
use App\Modules\Financial\Http\Resources\Admin\ManualPaymentConfirmationResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Pedidos
 *
 * Confirma pagamentos manuais em dinheiro para pedidos pendentes do tenant.
 */
class ConfirmManualPaymentController extends Controller
{
    public function __construct(
        private readonly ConfirmManualPaymentAction $confirmManualPaymentAction,
    ) {}

    /**
     * Confirmar Pagamento Manual
     *
     * Confirma pagamento pendente em dinheiro e emite evento para processamento posterior da matrícula.
     *
     * @urlParam id integer required Identificador do pedido. Example: 1
     */
    public function confirm(ConfirmManualPaymentRequest $request, int $id, ApiContext $context): JsonResource
    {
        Gate::forUser($context->requiredUser())->authorize('financial.orders.confirm-manual-payment', [$context->requiredTenant()]);

        $request->validated();

        return ManualPaymentConfirmationResource::make($this->confirmManualPaymentAction->handle($context, $id));
    }
}
