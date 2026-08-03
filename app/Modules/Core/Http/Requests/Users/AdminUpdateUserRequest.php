<?php

namespace App\Modules\Core\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Atualização de usuário do tenant pela área Admin. `user_type` é proibido — o
 * teto de permissions é decisão de developer — e `email`/`cpf`/`password` também:
 * são identidade e credencial, alteradas pelo próprio usuário ou por reset.
 */
class AdminUpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'user_type' => ['prohibited'],
            'email' => ['prohibited'],
            'cpf' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_type.prohibited' => 'User type changes are restricted to developers.',
            'email.prohibited' => 'Email changes are done by the user, not by the tenant admin.',
            'cpf.prohibited' => 'CPF changes are done by the user, not by the tenant admin.',
            'password.prohibited' => 'Passwords are changed by the user or by password reset.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nome completo do usuário.',
                'example' => 'Maria Silva',
            ],
            'headline' => [
                'description' => 'Título profissional/headline.',
                'example' => 'Instrutora de Design',
            ],
            'bio' => [
                'description' => 'Biografia do usuário.',
                'example' => 'Atua com design de produto há 8 anos.',
            ],
            'avatar' => [
                'description' => 'URL do avatar.',
                'example' => 'https://example.com/avatar.jpg',
            ],
            'linkedin_url' => [
                'description' => 'URL do perfil LinkedIn.',
                'example' => 'https://linkedin.com/in/mariasilva',
            ],
            'twitter_url' => [
                'description' => 'URL do perfil Twitter/X.',
                'example' => 'https://twitter.com/mariasilva',
            ],
        ];
    }
}
