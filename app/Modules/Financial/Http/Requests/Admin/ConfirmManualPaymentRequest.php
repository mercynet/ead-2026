<?php

namespace App\Modules\Financial\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'order_id' => ['prohibited'],
            'status' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'gateway_response' => ['prohibited'],
            'metadata' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tenant_id.prohibited' => 'Tenant do pedido não pode ser alterado.',
            'user_id.prohibited' => 'Responsável pelo pedido não pode ser alterado.',
            'order_id.prohibited' => 'Pedido é definido pela rota.',
            'status.prohibited' => 'Status é definido pela confirmação manual.',
            'payment_id.prohibited' => 'Pagamento elegível é definido pelo pedido.',
            'gateway_response.prohibited' => 'Resposta do gateway não pode ser enviada.',
            'metadata.prohibited' => 'Metadados do pedido não podem ser enviados.',
        ];
    }

    /** @return array<string, array{description: string, example: mixed}> */
    public function bodyParameters(): array
    {
        return [];
    }
}
