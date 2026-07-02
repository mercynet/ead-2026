<?php

namespace App\Modules\Learning\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Course is required.',
            'course_id.integer' => 'Course must be a valid identifier.',
            'course_id.exists' => 'Course was not found.',
            'user_id.integer' => 'User must be a valid identifier.',
            'user_id.exists' => 'User was not found.',
        ];
    }

    /**
     * Body parameters for Scribe documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'course_id' => [
                'description' => 'ID do curso a matricular',
                'example' => 1,
            ],
            'user_id' => [
                'description' => 'ID do usuário a matricular (opcional; padrão = autenticado)',
                'example' => 2,
            ],
        ];
    }
}
