<?php

namespace App\Http\Requests\Core\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'cpf' => ['sometimes', 'nullable', 'string', 'max:14'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_url' => ['sometimes', 'nullable', 'url', 'max:255'],
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
            'headline' => [
                'description' => 'Título profissional/headline',
                'example' => 'Desenvolvedor Full Stack',
            ],
            'bio' => [
                'description' => 'Biografia do usuário',
                'example' => 'Desenvolvedor com 5 anos de experiência...',
            ],
            'avatar' => [
                'description' => 'URL do avatar do usuário',
                'example' => 'https://example.com/avatar.jpg',
            ],
            'cpf' => [
                'description' => 'CPF do usuário (formato: XXX.XXX.XXX-XX)',
                'example' => '123.456.789-00',
            ],
            'linkedin_url' => [
                'description' => 'URL do perfil LinkedIn',
                'example' => 'https://linkedin.com/in/johndoe',
            ],
            'twitter_url' => [
                'description' => 'URL do perfil Twitter/X',
                'example' => 'https://twitter.com/johndoe',
            ],
        ];
    }
}
