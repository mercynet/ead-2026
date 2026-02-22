<?php

namespace App\Http\Requests\Core\Users;

use App\Http\Context\ApiContext;
use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(ApiContext::class)->requiredTenant();

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Body parameters for Scribe documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nome completo do usuário',
                'example' => 'John Doe',
            ],
            'email' => [
                'description' => 'Endereço de email do usuário',
                'example' => 'john@example.com',
            ],
            'password' => [
                'description' => 'Senha do usuário (mínimo 8 caracteres)',
                'example' => 'password123',
            ],
            'password_confirmation' => [
                'description' => 'Confirmação da senha',
                'example' => 'password123',
            ],
        ];
    }
}
