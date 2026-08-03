<?php

namespace App\Modules\Learning\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class SyncCourseCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['present', 'array'],
            'categories.*' => ['array'],
            'categories.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'categories.*.is_featured' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'categories.present' => 'The categories list is required.',
            'categories.array' => 'The categories list must be an array.',
            'categories.*.id.required' => 'Each category entry requires an id.',
            'categories.*.id.integer' => 'Each category id must be an integer.',
            'categories.*.id.distinct' => 'The categories list must not repeat a category.',
            'categories.*.is_featured.boolean' => 'The is_featured flag must be true or false.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'categories' => [
                'description' => 'Conjunto completo de categorias do curso. A ordem do array vira a ordem de exibição; array vazio remove todos os vínculos.',
                'example' => [['id' => 12, 'is_featured' => true], ['id' => 30]],
            ],
            'categories.*.id' => [
                'description' => 'ID de categoria do próprio tenant ou de sistema.',
                'example' => 12,
            ],
            'categories.*.is_featured' => [
                'description' => 'Destaca o curso dentro da categoria.',
                'example' => true,
            ],
        ];
    }
}
