<?php

namespace App\Modules\Learning\Http\Resources\Student;

use App\Modules\Learning\Enums\LessonMediaProgressStrategy;
use App\Modules\Learning\Models\LessonMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonMedia
 *
 * @property int $id
 * @property string $media_type
 * @property string $provider
 * @property string|null $student_url
 * @property string|null $student_url_kind
 * @property \Illuminate\Support\Carbon|null $student_url_expires_at
 * @property int|null $duration_seconds
 * @property \App\Modules\Learning\Enums\LessonMediaProgressStrategy|string|null $progress_strategy
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 */
class LessonMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_type' => $this->media_type,
            'provider' => $this->provider,
            'url' => $this->student_url,
            'url_kind' => $this->student_url_kind,
            'url_expires_at' => $this->student_url_expires_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'progress_strategy' => $this->progress_strategy instanceof LessonMediaProgressStrategy
                ? $this->progress_strategy->value
                : $this->progress_strategy,
            'progress_config' => $this->progressConfig(),
            'sort_order' => $this->sort_order,
        ];
    }

    /**
     * @return array{required_seconds:int}|null
     */
    private function progressConfig(): ?array
    {
        $requiredSeconds = data_get($this->metadata, 'required_seconds');

        return $requiredSeconds === null ? null : ['required_seconds' => (int) $requiredSeconds];
    }
}
