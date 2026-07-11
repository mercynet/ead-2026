<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Enums\LessonMediaProgressStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonMediaRequest extends FormRequest
{
    private const STORAGE_PROVIDERS = ['s3', 'internal'];

    private const EXTERNAL_VIDEO_PROVIDERS = ['youtube', 'vimeo'];

    private const SAFE_STORAGE_DISKS = ['local', 's3'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'media_type' => ['required', 'string', Rule::in(['video', 'audio', 'document', 'text', 'embed'])],
            'provider' => ['nullable', 'string', Rule::in(['youtube', 'vimeo', 's3', 'internal', 'embed'])],
            'provider_ref' => [
                Rule::requiredIf(fn (): bool => $this->isExternalVideoProvider()),
                'nullable',
                'string',
                'max:255',
            ],
            'url' => ['nullable', 'url', 'max:2048'],
            'content' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'progress_strategy' => ['nullable', 'string', Rule::enum(LessonMediaProgressStrategy::class)],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'metadata.player_url' => ['nullable', 'url', 'max:2048'],
            'metadata.storage_path' => [
                Rule::requiredIf(fn (): bool => $this->usesStorageProvider()),
                'nullable',
                'string',
                'max:2048',
                'starts_with:tenants/'.$tenant->id.'/',
                'not_regex:/\.\./',
                'not_regex:/\\\\/',
            ],
            'metadata.storage_disk' => ['nullable', 'string', 'max:255', Rule::in(self::SAFE_STORAGE_DISKS)],
            'metadata.required_seconds' => [
                Rule::requiredIf(fn (): bool => $this->usesTimeBasedStrategy()),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'media_type.required' => 'Media type is required.',
            'media_type.in' => 'Media type must be one of: video, audio, document, text or embed.',
            'provider.in' => 'Provider must be one of: youtube, vimeo, s3, internal or embed.',
            'provider_ref.required' => 'Provider reference is required for YouTube or Vimeo media.',
            'url.url' => 'Media URL must be a valid URL.',
            'duration_seconds.integer' => 'Duration must be a valid integer.',
            'duration_seconds.min' => 'Duration must be at least 1 second.',
            'progress_strategy.enum' => 'Progress strategy must be one of: 80_percent, full_duration, manual or time_based.',
            'sort_order.integer' => 'Sort order must be a valid integer.',
            'sort_order.min' => 'Sort order must be at least 1.',
            'metadata.array' => 'Metadata must be an object.',
            'metadata.player_url.url' => 'Player URL must be a valid URL.',
            'metadata.storage_path.required' => 'Storage path is required for internal or s3 media.',
            'metadata.storage_path.starts_with' => 'Storage path must stay inside the current tenant folder.',
            'metadata.storage_path.not_regex' => 'Storage path contains invalid segments.',
            'metadata.storage_disk.in' => 'Storage disk must be one of: local or s3.',
            'metadata.required_seconds.required' => 'Required seconds is mandatory for time_based progress strategy.',
            'metadata.required_seconds.integer' => 'Required seconds must be a valid integer.',
            'metadata.required_seconds.min' => 'Required seconds must be at least 1 second.',
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
            'progress_strategy' => [
                'description' => 'Estratégia usada para concluir a aula a partir desta mídia.',
                'example' => '80_percent',
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
                'description' => 'Payload JSON flexível do subtipo. `youtube`/`vimeo` usam `provider_ref`; `embed` pode enviar `player_url`; `internal`/`s3` usam `storage_path` e opcionalmente `storage_disk`; `time_based` usa `required_seconds`.',
                'example' => ['quality' => 'hd', 'storage_path' => 'tenants/12/lessons/lesson-01.mp4', 'storage_disk' => 'local'],
            ],
            'metadata.player_url' => [
                'description' => 'URL explícita do player quando o provider expõe embed customizado.',
                'example' => 'https://player.example/lesson-01',
            ],
            'metadata.storage_path' => [
                'description' => 'Caminho lógico do arquivo no storage quando a mídia usa provider `internal` ou `s3`.',
                'example' => 'tenants/12/lessons/lesson-01.mp4',
            ],
            'metadata.storage_disk' => [
                'description' => 'Disco seguro configurado no Laravel para resolver a URL temporária da mídia.',
                'example' => 's3',
            ],
            'metadata.required_seconds' => [
                'description' => 'Limite em segundos exigido pela estratégia `time_based` para concluir a mídia.',
                'example' => 180,
            ],
        ];
    }

    private function isExternalVideoProvider(): bool
    {
        return in_array($this->input('provider'), self::EXTERNAL_VIDEO_PROVIDERS, true);
    }

    private function usesStorageProvider(): bool
    {
        return in_array($this->input('provider'), self::STORAGE_PROVIDERS, true);
    }

    private function usesTimeBasedStrategy(): bool
    {
        return $this->input('progress_strategy') === LessonMediaProgressStrategy::TimeBased->value;
    }
}
