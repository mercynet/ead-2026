<?php

namespace App\Modules\Financial\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:paid,failed'],
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
                'description' => 'Novo estado informado pelo gateway.',
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
