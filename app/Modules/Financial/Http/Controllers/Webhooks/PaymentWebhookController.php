<?php

namespace App\Modules\Financial\Http\Controllers\Webhooks;

use App\Modules\Financial\Actions\Webhooks\AcceptPaymentWebhookAction;
use App\Modules\Financial\Http\Requests\Webhooks\StorePaymentWebhookRequest;
use App\Modules\Financial\Http\Resources\Webhooks\PaymentWebhookAcceptedResource;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @group Webhooks · Pagamentos
 *
 * Recebe confirmações assinadas de gateways de pagamento.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly AcceptPaymentWebhookAction $acceptPaymentWebhookAction,
    ) {}

    /**
     * Receber Webhook de Pagamento
     *
     * Valida a assinatura e enfileira o processamento financeiro. O pedido não
     * é alterado durante a requisição HTTP.
     *
     * @unauthenticated
     *
     * @urlParam gateway_slug string required Identificador do gateway. Example: stripe
     *
     * @header X-Webhook-Signature sha256=abc123
     * @header Stripe-Signature t=1700000000,v1=abc123
     * @header X-Signature ts=1700000000000,v1=abc123
     * @header X-Payload-Signature MEQCIA...
     * @header Asaas-Access-Token token-do-webhook
     *
     * @response 202 scenario="Aceito" {"data":{"accepted":true}}
     * @response 200 scenario="Asaas aceito" {"data":{"accepted":true}}
     * @response 422 scenario="Webhook inválido" {"data":null,"errors":[{"code":"validation_error","message":"Webhook inválido."}]}
     * @response 503 scenario="Gateway indisponível" {"data":null,"errors":[{"code":"gateway_unavailable","message":"Gateway de pagamento indisponível."}]}
     */
    public function store(StorePaymentWebhookRequest $request, string $gatewaySlug): JsonResponse
    {
        $this->acceptPaymentWebhookAction->handle(
            gatewaySlug: $gatewaySlug,
            rawPayload: $request->getContent(),
            payload: $request->validated(),
            signature: $request->header('Asaas-Access-Token')
                ?? $request->header('Stripe-Signature')
                ?? $request->header('X-Signature')
                ?? $request->header('X-Payload-Signature')
                ?? $request->header('X-Webhook-Signature'),
            signatureContext: [
                'request_id' => $request->header('X-Request-Id'),
                'data_id' => $request->query('data.id') ?? $request->query('data_id'),
            ],
        );

        return PaymentWebhookAcceptedResource::make(['accepted' => true])
            ->response()
            ->setStatusCode($gatewaySlug === 'asaas' ? 200 : 202);
    }
}
