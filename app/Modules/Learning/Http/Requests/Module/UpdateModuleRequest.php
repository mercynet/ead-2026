<?php

namespace App\Modules\Learning\Http\Requests\Module;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleRequest extends FormRequest
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
            'title' => ['description' => 'Novo título do módulo.', 'example' => 'Módulo atualizado'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Module title is required.',
            'title.string' => 'Module title must be a string.',
            'title.max' => 'Module title must not exceed 200 characters.',
        ];
    }
}
