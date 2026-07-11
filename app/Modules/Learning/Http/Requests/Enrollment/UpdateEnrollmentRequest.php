<?php

namespace App\Modules\Learning\Http\Requests\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(Enrollment::STATUSES)],
            'progress_percentage' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'access_expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be pending, active, cancelled, or expired.',
            'progress_percentage.integer' => 'Progress percentage must be an integer.',
            'progress_percentage.min' => 'Progress percentage must be at least 0.',
            'progress_percentage.max' => 'Progress percentage must be at most 100.',
            'access_expires_at.date' => 'Access expires at must be a valid date.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => ['description' => 'Novo status da matrícula', 'example' => 'active'],
            'progress_percentage' => ['description' => 'Progresso em porcentagem', 'example' => 80],
            'access_expires_at' => ['description' => 'Data de expiração de acesso', 'example' => '2026-12-31'],
        ];
    }
}
