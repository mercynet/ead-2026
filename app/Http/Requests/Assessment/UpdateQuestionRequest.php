<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', 'in:single_choice,multiple_choice,true_false'],
            'options' => ['sometimes', 'array', 'min:2'],
            'options.*.text' => ['required', 'string'],
            'correct_options' => ['sometimes', 'array', 'min:1'],
            'correct_options.*' => ['required', 'integer'],
            'explanation' => ['nullable', 'string'],
            'points' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
