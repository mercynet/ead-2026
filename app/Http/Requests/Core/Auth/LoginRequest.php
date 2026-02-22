<?php

namespace App\Http\Requests\Core\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Body parameters for Scribe documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Endereço de email do usuário',
                'example' => 'john@example.com',
            ],
            'password' => [
                'description' => 'Senha do usuário',
                'example' => 'password123',
            ],
        ];
    }
}
