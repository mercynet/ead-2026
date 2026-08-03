<?php

namespace App\Modules\Core\Http\Requests\Tenants;

use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'database' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'admin' => ['required', 'array'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.password' => ['required', 'string', 'min:8'],
            'admin.cpf' => ['nullable', 'string', 'max:14'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'domain.required' => 'O domínio é obrigatório.',
            'admin.required' => 'Os dados do admin são obrigatórios.',
            'admin.name.required' => 'O nome do admin é obrigatório.',
            'admin.email.required' => 'O e-mail do admin é obrigatório.',
            'admin.email.email' => 'O e-mail do admin deve ser válido.',
            'admin.password.required' => 'A senha do admin é obrigatória.',
            'admin.password.min' => 'A senha do admin deve ter pelo menos 8 caracteres.',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Nome do tenant.', 'example' => 'Escola Exemplo'],
            'domain' => ['description' => 'Domínio único do tenant.', 'example' => 'escola.exemplo'],
            'database' => ['description' => 'Banco de dados opcional do tenant.', 'example' => 'tenant_escola'],
            'description' => ['description' => 'Descrição opcional do tenant.', 'example' => 'Ambiente principal.'],
            'admin.name' => ['description' => 'Nome do primeiro admin.', 'example' => 'Ana Admin'],
            'admin.email' => ['description' => 'E-mail do primeiro admin.', 'example' => 'ana@escola.exemplo'],
            'admin.password' => ['description' => 'Senha do primeiro admin, mínimo de 8 caracteres.', 'example' => 'senha-forte-123'],
            'admin.cpf' => ['description' => 'CPF opcional do primeiro admin.', 'example' => '12345678901'],
        ];
    }
}
