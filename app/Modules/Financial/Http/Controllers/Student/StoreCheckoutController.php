<?php

namespace App\Modules\Financial\Http\Controllers\Student;

use App\Modules\Financial\Actions\Student\StoreCheckoutAction;
use App\Modules\Financial\Http\Requests\Student\StoreCheckoutRequest;
use App\Modules\Financial\Http\Resources\Student\CheckoutResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StoreCheckoutController extends Controller
{
    public function __construct(private readonly StoreCheckoutAction $storeCheckoutAction) {}

    /**
     * Criar checkout de curso
     *
     * Cria ou reproduz idempotentemente checkout do curso para aluno autenticado.
     *
     * @header Idempotency-Key string required UUID único da tentativa. Example: 3b4e1dc1-0ef6-46d8-9bea-aa992d719744
     *
     * @response 201 scenario="Novo checkout" {"data":{"id":1,"status":"pending","origin_type":"direct"}}
     */
    public function store(StoreCheckoutRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('financial.checkout.create', [$context->requiredTenant()]);

        $order = $this->storeCheckoutAction->handle(
            $context,
            (int) $request->validated('course_id'),
            (string) $request->header('Idempotency-Key'),
        );

        return CheckoutResource::make($order)
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }
}
