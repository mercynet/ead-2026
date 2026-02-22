<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_snapshot' => ['required', 'array'],
            'question_snapshot.question' => ['required', 'string'],
            'question_snapshot.type' => ['required', 'string'],
            'question_snapshot.options' => ['required', 'array'],
            'question_snapshot.correct_options' => ['required', 'array'],
            'question_snapshot.points' => ['required', 'integer'],
            'selected_options' => ['required', 'array'],
            'selected_options.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_snapshot.required' => 'O snapshot da questão é obrigatório.',
            'selected_options.required' => 'Selecione pelo menos uma opção.',
        ];
    }
}
