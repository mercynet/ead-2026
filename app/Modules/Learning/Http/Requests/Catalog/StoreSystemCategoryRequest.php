<?php

namespace App\Modules\Learning\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Criação de categoria de sistema (área Mzrt). `is_system` é implícito na área e
 * proibido no payload.
 */
class StoreSystemCategoryRequest extends FormRequest
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
            'is_system.prohibited' => 'System categories are implicit in the mzrt area.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nome da categoria global de sistema.',
                'example' => 'Tecnologia',
            ],
            'parent_id' => [
                'description' => 'ID da categoria pai, que também precisa ser de sistema.',
                'example' => null,
            ],
        ];
    }
}
