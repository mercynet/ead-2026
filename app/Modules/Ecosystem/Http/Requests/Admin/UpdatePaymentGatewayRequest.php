<?php

namespace App\Modules\Ecosystem\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'tenant_id' => ['prohibited'],
            'plugin_id' => ['prohibited'],
            'slug' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'enabled.required' => 'Gateway enabled status is required.',
            'enabled.boolean' => 'Gateway enabled status must be true or false.',
            'configuration.array' => 'Gateway configuration must be an array.',
            'tenant_id.prohibited' => 'Tenant ownership cannot be changed.',
            'plugin_id.prohibited' => 'Plugin ownership cannot be changed.',
            'slug.prohibited' => 'Gateway slug cannot be changed.',
        ];
    }

    /** @return array<string, array{description: string, example: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'enabled' => [
                'description' => 'Define se gateway fica habilitado para tenant.',
                'example' => true,
            ],
            'configuration' => [
                'description' => 'Configuração dinâmica. Campos aceitos vêm de `configuration_schema` retornado no GET.',
                'example' => [],
            ],
        ];
    }
}
