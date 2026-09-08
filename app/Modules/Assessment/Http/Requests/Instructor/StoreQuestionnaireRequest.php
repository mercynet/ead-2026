<?php

namespace App\Modules\Assessment\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionnaireRequest extends FormRequest
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
            'parent' => ['prohibited'],
            'course_id' => ['prohibited'],
            'lesson_id' => ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:lesson,course'],
            'quizable_type' => ['required', 'string', 'in:lesson,course', 'same:type'],
            'quizable_id' => ['required', 'integer', 'min:1'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'show_results' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Instructor só pode criar questionários de curso ou aula.',
            'quizable_type.same' => 'O tipo do parent deve corresponder ao tipo do questionário.',
            'quizable_id.required' => 'O parent próprio é obrigatório.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Título do questionário próprio.', 'example' => 'Avaliação final'],
            'description' => ['description' => 'Descrição pedagógica.', 'example' => 'Avaliação do curso'],
            'type' => ['description' => 'Parent permitido: course ou lesson.', 'example' => 'course'],
            'quizable_type' => ['description' => 'Tipo do parent, igual a type.', 'example' => 'course'],
            'quizable_id' => ['description' => 'ID do Course/Lesson próprio.', 'example' => 10],
            'passing_score' => ['description' => 'Percentual mínimo para aprovação.', 'example' => 70],
        ];
    }
}
