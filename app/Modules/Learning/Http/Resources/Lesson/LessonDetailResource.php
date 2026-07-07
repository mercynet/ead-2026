<?php

namespace App\Modules\Learning\Http\Resources\Lesson;

use App\Modules\Learning\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonDetailResource extends JsonResource
{
    public function __construct(
        Lesson $resource,
        private readonly bool $canAccess,
        private readonly ?int $timeSpentSeconds = null,
        private readonly bool $isCompleted = false,
        private readonly ?int $currentTimeSeconds = null,
        private readonly array $resolvedMediaUrls = [],
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'can_access' => $this->canAccess,
            'module' => [
                'id' => $this->courseModule->id,
                'title' => $this->courseModule->title,
            ],
            'course' => [
                'id' => $this->courseModule->course->id,
                'title' => $this->courseModule->course->title,
                'slug' => $this->courseModule->course->slug,
            ],
            'media' => $this->canAccess
                ? $this->media
                    ->map(fn ($media): LessonMediaResource => new LessonMediaResource(
                        $media,
                        $this->resolvedMediaUrls[$media->id]['url'] ?? $media->url,
                        $this->resolvedMediaUrls[$media->id]['expires_at'] ?? null,
                        $this->resolvedMediaUrls[$media->id]['kind'] ?? null,
                    ))
                    ->values()
                : null,
            'progress' => $this->canAccess ? [
                'time_spent_seconds' => $this->timeSpentSeconds,
                'current_time_seconds' => $this->currentTimeSeconds,
                'is_completed' => $this->isCompleted,
            ] : null,
        ];
    }
}
