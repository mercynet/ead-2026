<?php

namespace App\Modules\Learning\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação de categoria de tenant (área Admin). `is_system` é proibido: categoria
 * global é contrato da área Mzrt, não escolha de payload.
 */
class StoreTenantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_system' => ['prohibited'],
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
            'is_system.prohibited' => 'System categories are managed in the mzrt area.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nome da categoria do tenant.',
                'example' => 'Desenvolvimento Web',
            ],
            'parent_id' => [
                'description' => 'ID da categoria pai (sistema ou do próprio tenant).',
                'example' => null,
            ],
        ];
    }
}
