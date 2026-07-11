<?php

namespace App\Modules\Learning\Http\Requests\Course;

use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'file_path' => [
                'required',
                'string',
                'max:2048',
                'starts_with:tenants/'.$tenant->id.'/',
                'not_regex:/\.\./',
                'not_regex:/\\\\/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file_path.required' => 'Material file path is required.',
            'file_path.string' => 'Material file path must be a string.',
            'file_path.max' => 'Material file path must not exceed 2048 characters.',
            'file_path.starts_with' => 'Material file path must stay inside the current tenant folder.',
            'file_path.not_regex' => 'Material file path contains invalid segments.',
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
