<?php

namespace App\Modules\Learning\Http\Resources\Lesson;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonMediaResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly ?string $resolvedUrl = null,
        private readonly ?CarbonInterface $resolvedUrlExpiresAt = null,
        private readonly ?string $resolvedUrlKind = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'media_type' => $this->media_type,
            'provider' => $this->provider,
            'provider_ref' => $this->provider_ref,
            'url' => $this->resolvedUrl ?? $this->url,
            'url_kind' => $this->resolvedUrlKind,
            'url_expires_at' => $this->resolvedUrlExpiresAt?->toIso8601String(),
            'content' => $this->content,
            'duration_seconds' => $this->duration_seconds,
            'progress_strategy' => $this->progress_strategy?->value ?? $this->progress_strategy,
            'provider_config' => $this->providerConfig(),
            'progress_config' => $this->progressConfig(),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function providerConfig(): ?array
    {
        $provider = $this->provider;

        if ($provider === 'youtube' || $provider === 'vimeo') {
            return $this->filterNullValues([
                'video_id' => $this->provider_ref,
                'player_url' => data_get($this->metadata, 'player_url'),
            ]);
        }

        if ($provider === 'embed') {
            return $this->filterNullValues([
                'player_url' => data_get($this->metadata, 'player_url'),
            ]);
        }

        if ($provider === 'internal' || $provider === 's3') {
            return $this->filterNullValues([
                'storage_path' => data_get($this->metadata, 'storage_path'),
                'storage_disk' => data_get($this->metadata, 'storage_disk'),
            ]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function progressConfig(): ?array
    {
        $requiredSeconds = data_get($this->metadata, 'required_seconds');

        if ($requiredSeconds === null) {
            return null;
        }

        return [
            'required_seconds' => (int) $requiredSeconds,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function filterNullValues(array $values): ?array
    {
        $filtered = array_filter($values, fn (mixed $value): bool => $value !== null);

        return $filtered === [] ? null : $filtered;
    }
}
