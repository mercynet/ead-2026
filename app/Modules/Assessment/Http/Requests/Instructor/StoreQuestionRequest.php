<?php

namespace App\Modules\Assessment\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
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
            'question' => ['required', 'string'],
            'type' => ['required', 'string', 'in:single_choice,multiple_choice,true_false'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.text' => ['required', 'string'],
            'correct_options' => ['required', 'array', 'min:1'],
            'correct_options.*' => ['required', 'integer', 'min:0'],
            'explanation' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array', 'distinct'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'A pergunta é obrigatória.',
            'type.in' => 'O tipo deve ser single_choice, multiple_choice ou true_false.',
            'category_ids.distinct' => 'Uma categoria não pode ser repetida.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'question' => ['description' => 'Enunciado da questão.', 'example' => 'Qual é a resposta?'],
            'type' => ['description' => 'Tipo core da questão.', 'example' => 'single_choice'],
            'options' => ['description' => 'Opções sem autoridade de ownership.', 'example' => [['text' => 'A'], ['text' => 'B']]],
            'correct_options' => ['description' => 'Índices corrigidos server-side.', 'example' => [0]],
            'category_ids' => ['description' => 'Categorias System/Custom disponíveis.', 'example' => [1, 2]],
        ];
    }
}
