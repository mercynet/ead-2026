<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstructorEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'billing_type' => ['prohibited'],
            'created_by_instructor_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'price_cents' => ['prohibited'],
            'amount_cents' => ['prohibited'],
            'gateway' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Course is required.',
            'course_id.exists' => 'Course was not found.',
            'user_id.required' => 'Student is required.',
            'user_id.exists' => 'Student was not found.',
            '*.prohibited' => 'This field is selected by the enrollment workflow.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'course_id' => [
                'description' => 'ID do curso próprio a matricular.',
                'example' => 1,
            ],
            'user_id' => [
                'description' => 'ID do aluno do tenant a matricular.',
                'example' => 2,
            ],
        ];
    }
}
