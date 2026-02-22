<?php

namespace App\Http\Requests\Learning\Catalog;

use App\Http\Context\ApiContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->boolean('is_system')) {
            app(ApiContext::class)->requiredTenant();
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_system' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.string' => 'Category name must be a string.',
            'name.max' => 'Category name must not exceed 120 characters.',
            'parent_id.integer' => 'Parent category must be a valid identifier.',
            'parent_id.exists' => 'Parent category was not found.',
            'is_system.boolean' => 'The is_system flag must be true or false.',
        ];
    }

    /**
     * Body parameters for Scribe documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nome da categoria',
                'example' => 'Desenvolvimento Web',
            ],
            'parent_id' => [
                'description' => 'ID da categoria pai (opcional)',
                'example' => null,
            ],
            'is_system' => [
                'description' => 'Se true, cria como categoria de sistema (global). Requer permissão de developer.',
                'example' => false,
            ],
        ];
    }
}
