<?php

namespace App\Http\Requests\Assessment;

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
            'question' => ['required', 'string'],
            'type' => ['required', 'string', 'in:single_choice,multiple_choice,true_false'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.text' => ['required', 'string'],
            'correct_options' => ['required', 'array', 'min:1'],
            'correct_options.*' => ['required', 'integer'],
            'explanation' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'A pergunta é obrigatória.',
            'type.in' => 'O tipo deve ser: single_choice, multiple_choice ou true_false.',
            'options.required' => 'As opções são obrigatórias.',
            'correct_options.required' => 'Indique pelo menos uma opção correta.',
        ];
    }
}
