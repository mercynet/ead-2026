<?php

namespace App\Modules\Financial\Http\Controllers\Student;

use App\Modules\Financial\Actions\Student\ListStudentOrdersAction;
use App\Modules\Financial\Actions\Student\ShowStudentOrderAction;
use App\Modules\Financial\Http\Resources\Student\OrderResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Student · Pedidos
 *
 * Histórico financeiro dos pedidos próprios do aluno.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly ListStudentOrdersAction $listStudentOrdersAction,
        private readonly ShowStudentOrderAction $showStudentOrderAction,
    ) {}

    /**
     * Listar Meus Pedidos
     *
     * Lista os pedidos do aluno atual por cursor.
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->requiredUser())->authorize('financial.orders.list', [$context->requiredTenant()]);

        return OrderResource::collection($this->listStudentOrdersAction->handle($context));
    }

    /**
     * Ver Meu Pedido
     *
     * Retorna o detalhe de um pedido pertencente ao aluno atual.
     *
     * @urlParam id integer required Identificador do pedido. Example: 1
     */
    public function show(int $id, ApiContext $context): OrderResource
    {
        Gate::forUser($context->requiredUser())->authorize('financial.orders.view', [$context->requiredTenant()]);

        return OrderResource::make($this->showStudentOrderAction->handle($context, $id));
    }
}
