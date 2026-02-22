<?php

namespace App\Http\Requests\Learning\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_spent_seconds' => ['required', 'integer', 'min:0'],
            'current_time_seconds' => ['nullable', 'integer', 'min:0'],
            'total_time_seconds' => ['nullable', 'integer', 'min:0'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_completed' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'time_spent_seconds.required' => 'O tempo assistido é obrigatório.',
            'time_spent_seconds.integer' => 'O tempo assistido deve ser um número inteiro.',
            'time_spent_seconds.min' => 'O tempo assistido deve ser maior ou igual a zero.',
            'current_time_seconds.integer' => 'O tempo atual deve ser um número inteiro.',
            'current_time_seconds.min' => 'O tempo atual deve ser maior ou igual a zero.',
            'total_time_seconds.integer' => 'O tempo total deve ser um número inteiro.',
            'total_time_seconds.min' => 'O tempo total deve ser maior ou igual a zero.',
            'progress_percentage.integer' => 'A porcentagem de progresso deve ser um número inteiro.',
            'progress_percentage.min' => 'A porcentagem de progresso deve ser maior ou igual a zero.',
            'progress_percentage.max' => 'A porcentagem de progresso deve ser menor ou igual a 100.',
            'is_completed.required' => 'O status de conclusão é obrigatório.',
            'is_completed.boolean' => 'O status de conclusão deve ser verdadeiro ou falso.',
        ];
    }
}
