<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'course_module_id' => [
                'required',
                'integer',
                Rule::exists('course_modules', 'id')
                    ->where('tenant_id', $tenant->id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'is_free' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_module_id.required' => 'Module is required.',
            'course_module_id.integer' => 'Module must be a valid integer.',
            'course_module_id.exists' => 'Module must belong to the current tenant and be active.',
            'title.required' => 'Lesson title is required.',
            'title.string' => 'Lesson title must be a string.',
            'title.max' => 'Lesson title must not exceed 200 characters.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'course_module_id' => [
                'description' => 'ID do módulo do tenant atual ao qual a aula pertence.',
                'example' => 10,
            ],
            'title' => [
                'description' => 'Título da aula.',
                'example' => 'Aula 1 — Introdução',
            ],
            'is_free' => [
                'description' => 'Define se a aula é gratuita para degustação.',
                'example' => false,
            ],
            'is_active' => [
                'description' => 'Define se a aula nasce ativa.',
                'example' => true,
            ],
        ];
    }
}
