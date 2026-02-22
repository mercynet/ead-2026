<?php

namespace App\Http\Requests\Learning\Course;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'target_audience' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'what_you_will_learn' => ['nullable', 'string'],
            'what_you_will_build' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,published,archived'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'banner' => ['nullable', 'string', 'max:500'],
            'level' => ['nullable', 'string', 'in:beginner,intermediate,advanced,all'],
            'price_cents' => ['sometimes', 'required', 'integer', 'min:0'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'access_days' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'certificate_enabled' => ['nullable', 'boolean'],
            'certificate_min_progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'certificate_requires_quiz' => ['nullable', 'boolean'],
            'certificate_min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Course title is required.',
            'title.string' => 'Course title must be a string.',
            'title.max' => 'Course title must not exceed 200 characters.',
            'status.in' => 'Status must be one of: draft, published, archived.',
            'price_cents.integer' => 'Price must be a valid integer (cents).',
            'price_cents.min' => 'Price cannot be negative.',
            'level.in' => 'Level must be one of: beginner, intermediate, advanced, all.',
            'certificate_min_progress.integer' => 'Minimum progress must be a valid integer.',
            'certificate_min_progress.min' => 'Minimum progress must be at least 0.',
            'certificate_min_progress.max' => 'Minimum progress must not exceed 100.',
            'certificate_min_score.integer' => 'Minimum score must be a valid integer.',
            'certificate_min_score.min' => 'Minimum score must be at least 0.',
            'certificate_min_score.max' => 'Minimum score must not exceed 100.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Título do curso',
                'example' => 'Desenvolvimento Web Completo',
            ],
            'description' => [
                'description' => 'Descrição completa do curso',
                'example' => 'Aprenda desenvolvimento web do zero.',
            ],
            'short_description' => [
                'description' => 'Descrição curta (para cartões)',
                'example' => 'Curso completo de desenvolvimento web.',
            ],
            'status' => [
                'description' => 'Status do curso',
                'example' => 'draft',
            ],
            'price_cents' => [
                'description' => 'Preço em centavos',
                'example' => 9900,
            ],
            'level' => [
                'description' => 'Nível do curso',
                'example' => 'beginner',
            ],
            'is_featured' => [
                'description' => 'Se o curso é destacado',
                'example' => false,
            ],
        ];
    }
}
