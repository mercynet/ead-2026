<?php

namespace App\Modules\Learning\Http\Requests\Admin;

use App\Modules\Learning\Http\Requests\Lesson\UpdateLessonMediaRequest as BaseUpdateLessonMediaRequest;

class UpdateLessonMediaRequest extends BaseUpdateLessonMediaRequest
{
    public function bodyParameters(): array
    {
        return [
            'media_type' => [
                'description' => 'Tipo de mídia da aula.',
                'example' => 'video',
            ],
            'provider' => [
                'description' => 'Provider externo ou interno da mídia.',
                'example' => 'embed',
            ],
            'provider_ref' => [
                'description' => 'Identificador do provider, quando aplicável.',
                'example' => 'lesson-video-01',
            ],
            'url' => [
                'description' => 'URL do player, documento ou mídia resolvida.',
                'example' => 'https://video.example/lesson-01',
            ],
            'content' => [
                'description' => 'Conteúdo textual da mídia, quando aplicável.',
                'example' => '<p>Resumo atualizado</p>',
            ],
            'duration_seconds' => [
                'description' => 'Duração da mídia em segundos.',
                'example' => 300,
            ],
            'progress_strategy' => [
                'description' => 'Estratégia de conclusão da mídia.',
                'example' => '80_percent',
            ],
            'sort_order' => [
                'description' => 'Ordem da mídia dentro da aula.',
                'example' => 2,
            ],
            'is_active' => [
                'description' => 'Define se a mídia permanece ativa.',
                'example' => true,
            ],
            'metadata' => [
                'description' => 'Payload JSON do subtipo da mídia.',
                'example' => ['quality' => 'hd'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tenant_id' => ['prohibited'],
            'lesson_id' => ['prohibited'],
        ];
    }
}
