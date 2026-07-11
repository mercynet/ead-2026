<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'time_spent_seconds' => ['required', 'integer', 'min:0'],
            'current_time_seconds' => ['nullable', 'integer', 'min:0'],
            'total_time_seconds' => ['nullable', 'integer', 'min:0'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_completed' => ['required', 'boolean'],
            'lesson_media_id' => [
                'nullable',
                'integer',
                Rule::exists('lesson_media', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenant->id)
                    ->where('lesson_id', $this->route('id'))
                    ->where('is_active', true)
                ),
            ],
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
            'lesson_media_id.integer' => 'A mídia da aula deve ser um identificador válido.',
            'lesson_media_id.exists' => 'A mídia informada deve pertencer ao tenant e à aula atual.',
        ];
    }

    /**
     * Body parameters for Scribe documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'time_spent_seconds' => [
                'description' => 'Tempo total assistido em segundos',
                'example' => 120,
            ],
            'current_time_seconds' => [
                'description' => 'Tempo atual do vídeo em segundos',
                'example' => 60,
            ],
            'total_time_seconds' => [
                'description' => 'Tempo total do vídeo em segundos',
                'example' => 600,
            ],
            'progress_percentage' => [
                'description' => 'Porcentagem de progresso (0-100)',
                'example' => 50,
            ],
            'is_completed' => [
                'description' => 'Se a aula foi concluída',
                'example' => false,
            ],
            'lesson_media_id' => [
                'description' => 'ID da mídia alvo da atualização de progresso. Opcional quando houver apenas uma mídia ativa.',
                'example' => 12,
            ],
        ];
    }
}
