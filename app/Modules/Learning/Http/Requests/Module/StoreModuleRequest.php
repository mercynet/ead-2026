<?php

namespace App\Modules\Learning\Http\Requests\Module;

use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Course is required.',
            'course_id.integer' => 'Course must be a valid integer.',
            'course_id.exists' => 'Course must belong to the current tenant and be active.',
            'title.required' => 'Module title is required.',
            'title.string' => 'Module title must be a string.',
            'title.max' => 'Module title must not exceed 200 characters.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'course_id' => [
                'description' => 'ID do curso do tenant atual ao qual o módulo pertence.',
                'example' => 10,
            ],
            'title' => [
                'description' => 'Título do módulo.',
                'example' => 'Módulo 1 — Fundamentos',
            ],
        ];
    }
}
