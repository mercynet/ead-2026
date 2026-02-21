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
}
