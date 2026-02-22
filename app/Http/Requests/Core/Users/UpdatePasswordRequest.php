<?php

namespace App\Http\Requests\Core\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Body parameters for Scribe documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'current_password' => [
                'description' => 'Senha atual do usuário',
                'example' => 'oldpassword123',
            ],
            'password' => [
                'description' => 'Nova senha (mínimo 8 caracteres)',
                'example' => 'newpassword123',
            ],
            'password_confirmation' => [
                'description' => 'Confirmação da nova senha',
                'example' => 'newpassword123',
            ],
        ];
    }
}
