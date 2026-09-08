<?php

namespace App\Modules\Assessment\Http\Requests\Instructor;

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
            'tenant_id' => ['prohibited'],
            'instructor_id' => ['prohibited'],
            'owner' => ['prohibited'],
            'question' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', 'in:single_choice,multiple_choice,true_false'],
            'options' => ['sometimes', 'array', 'min:2'],
            'options.*.text' => ['required', 'string'],
            'correct_options' => ['sometimes', 'array', 'min:1'],
            'correct_options.*' => ['required', 'integer', 'min:0'],
            'explanation' => ['sometimes', 'nullable', 'string'],
            'points' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'nullable', 'array', 'distinct'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'question' => ['description' => 'Novo enunciado.', 'example' => 'Qual é a resposta revisada?'],
            'options' => ['description' => 'Novas opções.', 'example' => [['text' => 'A'], ['text' => 'B']]],
            'correct_options' => ['description' => 'Novos índices corretos.', 'example' => [1]],
            'category_ids' => ['description' => 'Categorias System/Custom disponíveis.', 'example' => [1]],
        ];
    }
}
