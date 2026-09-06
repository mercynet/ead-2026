<?php

namespace App\Modules\Learning\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminEnrollmentRequest extends FormRequest
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
            'tenant_id' => ['prohibited'],
            'billing_type' => ['prohibited'],
            'created_by_instructor_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'status' => ['prohibited'],
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
            'tenant_id.prohibited' => 'Tenant scope is selected by the request context.',
            'billing_type.prohibited' => 'Admin enrollment does not accept external billing.',
            'created_by_instructor_id.prohibited' => 'Enrollment author is selected by the authenticated user.',
            'instructor_id.prohibited' => 'Enrollment author is selected by the authenticated user.',
            'status.prohibited' => 'Enrollment status is selected by the enrollment workflow.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'course_id' => [
                'description' => 'ID do curso a matricular',
                'example' => 1,
            ],
            'user_id' => [
                'description' => 'ID do aluno a matricular',
                'example' => 2,
            ],
        ];
    }
}
