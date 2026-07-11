<?php

namespace App\Modules\Learning\Http\Resources\Lesson;

use App\Modules\Learning\Http\Resources\Course\RatingStatsResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'lesson_id' => $this->rateable_id,
            'stars' => $this->stars,
            'reaction' => $this->reaction,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'stats' => RatingStatsResource::make($this->rateable?->ratingStats),
        ];
    }
}
