<?php

namespace App\Modules\Financial\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('status')) {
            return;
        }

        $gatewaySlug = $this->route('gateway_slug');
        if ($gatewaySlug === 'mercadopago') {
            $this->prepareMercadoPagoPayload();

            return;
        }

        if ($gatewaySlug === 'pagseguro') {
            $this->preparePagSeguroPayload();

            return;
        }

        if ($gatewaySlug === 'asaas') {
            $this->prepareAsaasPayload();
        }
    }

    private function prepareMercadoPagoPayload(): void
    {
        $externalId = data_get($this->all(), 'data.id')
            ?? $this->query('data.id')
            ?? $this->query('data_id');

        if (! is_string($externalId) || $externalId === '') {
            return;
        }

        $this->merge([
            'status' => 'synchronize',
            'external_id' => $externalId,
        ]);
    }

    private function preparePagSeguroPayload(): void
    {
        $externalId = $this->header('x-product-id') ?? $this->input('id');
        $providerStatus = data_get($this->all(), 'charges.0.status');

        if (! is_string($externalId) || $externalId === '' || ! is_string($providerStatus)) {
            return;
        }

        $status = match (strtoupper($providerStatus)) {
            'PAID' => 'paid',
            'DECLINED', 'CANCELED', 'CANCELLED' => 'failed',
            default => 'synchronize',
        };

        $this->merge([
            'status' => $status,
            'external_id' => $externalId,
        ]);
    }

    private function prepareAsaasPayload(): void
    {
        $externalId = data_get($this->all(), 'checkout.id');
        $orderNumber = data_get($this->all(), 'checkout.externalReference');
        $event = $this->input('event');

        if (! is_string($externalId) || $externalId === '' || ! is_string($event)) {
            return;
        }

        $status = match ($event) {
            'CHECKOUT_PAID' => 'paid',
            'CHECKOUT_CANCELED', 'CHECKOUT_EXPIRED' => 'failed',
            default => 'synchronize',
        };

        $payload = [
            'status' => $status,
            'external_id' => $externalId,
        ];

        if (is_string($orderNumber) && $orderNumber !== '') {
            $payload['order_number'] = $orderNumber;
        }

        $this->merge($payload);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:paid,failed,synchronize'],
            'order_number' => ['nullable', 'string', 'max:255', 'required_without:external_id'],
            'external_id' => ['nullable', 'string', 'max:255', 'required_without:order_number'],
            'tenant_id' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'gateway_slug' => ['prohibited'],
            'signature' => ['prohibited'],
            'gateway_response' => ['prohibited'],
            'metadata' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.in' => 'Status de webhook inválido.',
            'order_number.required_without' => 'Informe o pedido ou o identificador externo.',
            'external_id.required_without' => 'Informe o pedido ou o identificador externo.',
            'tenant_id.prohibited' => 'Tenant é resolvido pelo pagamento.',
            'payment_id.prohibited' => 'Pagamento é resolvido pelo gateway.',
            'gateway_slug.prohibited' => 'Gateway é definido pela rota.',
            'signature.prohibited' => 'Assinatura deve ser enviada no cabeçalho.',
            'gateway_response.prohibited' => 'Resposta do gateway é controlada pelo servidor.',
            'metadata.prohibited' => 'Metadados não podem ser enviados.',
        ];
    }

    /** @return array<string, array{description: string, example: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Novo estado informado pelo gateway. Notificações nativas do Mercado Pago usam synchronize para consultar o estado autoritativo.',
                'example' => 'paid',
            ],
            'order_number' => [
                'description' => 'Número do pedido, quando o gateway o devolve.',
                'example' => 'ORD-01J8WEBHOOK',
            ],
            'external_id' => [
                'description' => 'Identificador da cobrança no gateway.',
                'example' => 'pay_123456',
            ],
        ];
    }
}
