<?php

namespace App\Modules\Core\Http\Requests\Invitations;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
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
                'description' => 'Token opaco recebido no convite',
                'example' => 'aBc123...',
            ],
            'name' => [
                'description' => 'Nome completo do novo usuário',
                'example' => 'Maria Silva',
            ],
            'password' => [
                'description' => 'Senha do novo usuário (mínimo 8 caracteres)',
                'example' => 'senha-forte-123',
            ],
            'password_confirmation' => [
                'description' => 'Confirmação da senha',
                'example' => 'senha-forte-123',
            ],
        ];
    }
}
