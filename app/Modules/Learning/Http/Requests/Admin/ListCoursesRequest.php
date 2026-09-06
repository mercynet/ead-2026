<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCoursesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'archived'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Course status must be draft, published or archived.',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'status' => [
                'description' => 'Filtrar cursos pelo status administrativo.',
                'example' => 'draft',
            ],
        ];
    }
}
