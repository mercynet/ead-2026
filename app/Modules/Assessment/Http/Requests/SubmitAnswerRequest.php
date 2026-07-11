<?php

namespace App\Modules\Assessment\Http\Requests;

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
            'question_id' => ['required', 'integer'],
            'selected_options' => ['required', 'array'],
            'selected_options.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_id.required' => 'Informe a questão que está sendo respondida.',
            'selected_options.required' => 'Selecione pelo menos uma opção.',
        ];
    }
}
