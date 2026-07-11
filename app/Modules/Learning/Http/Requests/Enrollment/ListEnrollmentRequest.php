<?php

namespace App\Modules\Learning\Http\Requests\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(Enrollment::STATUSES)],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be pending, active, cancelled, or expired.',
            'course_id.integer' => 'Course must be a valid identifier.',
            'course_id.exists' => 'Course was not found.',
            'user_id.integer' => 'User must be a valid identifier.',
            'user_id.exists' => 'User was not found.',
        ];
    }

    public function queryParameters(): array
    {
        return [
            'status' => ['description' => 'Filtrar matrículas por status', 'example' => 'active'],
            'course_id' => ['description' => 'Filtrar por curso', 'example' => 1],
            'user_id' => ['description' => 'Filtrar por usuário', 'example' => 2],
        ];
    }
}
