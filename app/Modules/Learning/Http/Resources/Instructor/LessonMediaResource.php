<?php

namespace App\Modules\Learning\Http\Resources\Instructor;

use App\Modules\Learning\Enums\LessonMediaProgressStrategy;
use App\Modules\Learning\Models\LessonMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LessonMedia */
/**
 * @property int $id
 * @property int $lesson_id
 * @property string $media_type
 * @property string $provider
 * @property string|null $provider_ref
 * @property string|null $url
 * @property string|null $content
 * @property int|null $duration_seconds
 * @property \App\Modules\Learning\Enums\LessonMediaProgressStrategy|string|null $progress_strategy
 * @property int $sort_order
 * @property bool $is_active
 */
class LessonMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'media_type' => $this->media_type,
            'provider' => $this->provider,
            'provider_ref' => $this->provider_ref,
            'url' => $this->provider === 'internal' || $this->provider === 's3' ? null : $this->url,
            'content' => $this->content,
            'duration_seconds' => $this->duration_seconds,
            'progress_strategy' => $this->progress_strategy instanceof LessonMediaProgressStrategy
                ? $this->progress_strategy->value
                : $this->progress_strategy,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
