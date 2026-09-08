<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use App\Modules\Learning\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInstructorEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Enrollment::STATUSES)],
            'cursor' => ['nullable', 'string'],
            'per_page' => ['prohibited'],
            'user_id' => ['prohibited'],
            'sort' => ['prohibited'],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'course_id' => ['description' => 'Reduz o roster aos cursos próprios informados.', 'example' => 1],
            'status' => ['description' => 'Status explícito: pending, active, expired ou cancelled.', 'example' => 'active'],
            'cursor' => ['description' => 'Cursor da próxima página.', 'example' => 'eyJpZCI6MTV9'],
        ];
    }
}
