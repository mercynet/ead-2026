<?php

namespace App\Http\Requests\Learning\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
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
        ];
    }

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
        ];
    }
}
