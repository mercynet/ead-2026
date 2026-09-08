<?php

namespace App\Modules\Assessment\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class ReorderQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_ids' => ['required', 'array', 'distinct'],
            'question_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'question_ids' => ['description' => 'Conjunto completo em nova ordem.', 'example' => [5, 4]],
        ];
    }
}
