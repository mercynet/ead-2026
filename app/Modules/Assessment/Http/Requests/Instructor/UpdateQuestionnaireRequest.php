<?php

namespace App\Modules\Assessment\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'owner' => ['prohibited'],
            'type' => ['prohibited'],
            'quizable_type' => ['prohibited'],
            'quizable_id' => ['prohibited'],
            'parent' => ['prohibited'],
            'course_id' => ['prohibited'],
            'lesson_id' => ['prohibited'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'passing_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'show_results' => ['sometimes', 'boolean'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Novo título.', 'example' => 'Avaliação revisada'],
            'description' => ['description' => 'Nova descrição pedagógica.', 'example' => 'Descrição'],
            'passing_score' => ['description' => 'Novo percentual mínimo.', 'example' => 75],
        ];
    }
}
