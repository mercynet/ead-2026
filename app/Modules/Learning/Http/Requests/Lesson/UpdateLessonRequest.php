<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
        ];
    }

    /** @return array<string, array{description: string, example: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Novo título da lesson.', 'example' => 'Lesson atualizada'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Lesson title is required.',
            'title.string' => 'Lesson title must be a string.',
            'title.max' => 'Lesson title must not exceed 200 characters.',
        ];
    }
}
