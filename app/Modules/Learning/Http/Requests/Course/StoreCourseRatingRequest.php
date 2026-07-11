<?php

namespace App\Modules\Learning\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'reaction' => ['nullable', 'string', 'in:like,dislike'],
        ];
    }

    public function messages(): array
    {
        return [
            'stars.required' => 'A avaliação em estrelas é obrigatória.',
            'stars.integer' => 'A avaliação em estrelas deve ser um número inteiro.',
            'stars.min' => 'A avaliação em estrelas deve ser no mínimo 1.',
            'stars.max' => 'A avaliação em estrelas deve ser no máximo 5.',
            'reaction.string' => 'A reação deve ser um texto válido.',
            'reaction.in' => 'A reação deve ser like ou dislike.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'stars' => [
                'description' => 'Quantidade de estrelas da avaliação (1 a 5).',
                'example' => 5,
            ],
            'reaction' => [
                'description' => 'Reação opcional associada à avaliação.',
                'example' => 'like',
            ],
        ];
    }
}
