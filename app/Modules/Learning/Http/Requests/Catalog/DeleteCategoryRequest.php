<?php

namespace App\Modules\Learning\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'force' => ['nullable', 'boolean'],
            'confirm' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'force.boolean' => 'The force flag must be true or false.',
            'confirm.boolean' => 'The confirm flag must be true or false.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'force' => [
                'description' => 'Desvincula cursos da categoria antes de removê-la. Obrigatório quando há cursos vinculados.',
                'example' => true,
            ],
            'confirm' => [
                'description' => 'Confirma a desvinculação dos cursos. Obrigatório junto com force.',
                'example' => true,
            ],
        ];
    }
}
