<?php

namespace App\Modules\Assessment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'passing_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'show_results' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, array{description: string, example: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Título do questionário.', 'example' => 'Avaliação revisada'],
            'description' => ['description' => 'Descrição do questionário.', 'example' => 'Conteúdo atualizado'],
            'passing_score' => ['description' => 'Percentual mínimo para aprovação.', 'example' => 70],
            'time_limit_minutes' => ['description' => 'Limite de tempo em minutos.', 'example' => 30],
            'is_active' => ['description' => 'Indica se o questionário está ativo.', 'example' => true],
            'show_results' => ['description' => 'Indica se o resultado será exibido.', 'example' => true],
        ];
    }
}
