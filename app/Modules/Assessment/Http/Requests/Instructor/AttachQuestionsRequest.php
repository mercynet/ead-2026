<?php

namespace App\Modules\Assessment\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class AttachQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_ids' => ['required', 'array', 'min:1', 'distinct'],
            'question_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'question_ids' => ['description' => 'Questões próprias a anexar.', 'example' => [4, 5]],
        ];
    }
}
