<?php

namespace App\Modules\Learning\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_path' => ['required', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'file_path.required' => 'Material file path is required.',
            'file_path.string' => 'Material file path must be a string.',
            'file_path.max' => 'Material file path must not exceed 2048 characters.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'file_path' => [
                'description' => 'Caminho lógico do material no storage do tenant.',
                'example' => 'tenants/12/materials/course-outline.pdf',
            ],
        ];
    }
}
