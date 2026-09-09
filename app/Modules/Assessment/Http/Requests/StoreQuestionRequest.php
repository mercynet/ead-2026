<?php

namespace App\Modules\Assessment\Http\Requests;

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

    /** @return array<string, array{description: string, example: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'question' => ['description' => 'Enunciado da pergunta.', 'example' => 'Qual é a resposta correta?'],
            'type' => ['description' => 'Tipo da pergunta.', 'example' => 'single_choice'],
            'options' => ['description' => 'Opções disponíveis.', 'example' => [['text' => 'A'], ['text' => 'B']]],
            'correct_options' => ['description' => 'Índices das opções corretas.', 'example' => [0]],
            'explanation' => ['description' => 'Explicação da resposta.', 'example' => 'A alternativa A é correta.'],
            'points' => ['description' => 'Pontuação da pergunta.', 'example' => 10],
            'is_active' => ['description' => 'Indica se a pergunta está ativa.', 'example' => true],
            'category_ids' => ['description' => 'Categorias associadas.', 'example' => [1]],
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
