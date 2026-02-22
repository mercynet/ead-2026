<?php

namespace App\Http\Requests\Assessment;

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'in:lesson,course,standalone'],
            'quizable_type' => ['nullable', 'string', 'in:lesson,course'],
            'quizable_id' => ['nullable', 'integer'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'show_results' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título do questionário é obrigatório.',
            'type.in' => 'O tipo deve ser: lesson, course ou standalone.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'title' => [
                'description' => 'Título do questionário',
                'example' => 'Simulado Final',
            ],
            'description' => [
                'description' => 'Descrição do questionário',
                'example' => 'Questionário final do curso',
            ],
            'type' => [
                'description' => 'Tipo do questionário: lesson (aula), course (prova final), standalone (avulso)',
                'example' => 'course',
            ],
            'quizable_id' => [
                'description' => 'ID da aula ou curso vinculado',
                'example' => 1,
            ],
            'passing_score' => [
                'description' => 'Nota mínima para aprovação (0-100)',
                'example' => 70,
            ],
        ];
    }
}
