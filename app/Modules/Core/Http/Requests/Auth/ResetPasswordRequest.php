<?php

namespace App\Modules\Core\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'token' => [
                'description' => 'Token opaco recebido por e-mail',
                'example' => 'aBc123...',
            ],
            'password' => [
                'description' => 'Nova senha (mínimo 8 caracteres)',
                'example' => 'nova-senha-forte-123',
            ],
            'password_confirmation' => [
                'description' => 'Confirmação da nova senha',
                'example' => 'nova-senha-forte-123',
            ],
        ];
    }
}
