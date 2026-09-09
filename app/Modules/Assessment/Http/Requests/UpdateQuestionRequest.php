<?php

namespace App\Modules\Assessment\Http\Requests;

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

    /** @return array<string, array{description: string, example: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'question' => ['description' => 'Novo enunciado.', 'example' => 'Qual é a resposta revisada?'],
            'type' => ['description' => 'Tipo da pergunta.', 'example' => 'single_choice'],
            'options' => ['description' => 'Novas opções.', 'example' => [['text' => 'A'], ['text' => 'B']]],
            'correct_options' => ['description' => 'Índices corretos.', 'example' => [1]],
            'explanation' => ['description' => 'Explicação da resposta.', 'example' => 'A alternativa B é correta.'],
            'points' => ['description' => 'Pontuação da pergunta.', 'example' => 10],
            'is_active' => ['description' => 'Indica se a pergunta está ativa.', 'example' => true],
            'category_ids' => ['description' => 'Categorias associadas.', 'example' => [1]],
        ];
    }
}
