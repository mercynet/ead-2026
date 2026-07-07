<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_type' => ['required', 'string', Rule::in(['video', 'audio', 'document', 'text', 'embed'])],
            'provider' => ['nullable', 'string', Rule::in(['youtube', 'vimeo', 's3', 'internal', 'embed'])],
            'provider_ref' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'content' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'media_type.required' => 'Media type is required.',
            'media_type.in' => 'Media type must be one of: video, audio, document, text or embed.',
            'provider.in' => 'Provider must be one of: youtube, vimeo, s3, internal or embed.',
            'url.url' => 'Media URL must be a valid URL.',
            'duration_seconds.integer' => 'Duration must be a valid integer.',
            'duration_seconds.min' => 'Duration must be at least 1 second.',
            'sort_order.integer' => 'Sort order must be a valid integer.',
            'sort_order.min' => 'Sort order must be at least 1.',
            'metadata.array' => 'Metadata must be an object.',
        ];
    }

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
                'description' => 'Identificador devolvido pelo provider, quando existir.',
                'example' => 'lesson-video-01',
            ],
            'url' => [
                'description' => 'URL do player, documento ou mídia resolvida.',
                'example' => 'https://video.example/lesson-01',
            ],
            'content' => [
                'description' => 'Conteúdo textual para aulas text/embed quando aplicável.',
                'example' => '<p>Resumo da aula</p>',
            ],
            'duration_seconds' => [
                'description' => 'Duração da mídia em segundos.',
                'example' => 300,
            ],
            'sort_order' => [
                'description' => 'Ordem opcional da mídia dentro da aula. Se omitido, vai para o fim.',
                'example' => 2,
            ],
            'is_active' => [
                'description' => 'Define se a mídia nasce ativa.',
                'example' => true,
            ],
            'metadata' => [
                'description' => 'Payload JSON flexível para configurações adicionais do provider.',
                'example' => ['quality' => 'hd'],
            ],
        ];
    }
}
