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
            'url_expires_at' => $this->resolvedUrlExpiresAt?->toIso8601String(),
            'content' => $this->content,
            'duration_seconds' => $this->duration_seconds,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
        ];
    }
}
