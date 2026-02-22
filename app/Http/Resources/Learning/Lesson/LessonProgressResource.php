<?php

namespace App\Http\Resources\Learning\Lesson;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'progress_percentage' => $this->progress_percentage,
            'time_spent_seconds' => $this->time_spent_seconds,
            'current_time_seconds' => $this->current_time_seconds,
            'total_time_seconds' => $this->total_time_seconds,
            'is_completed' => $this->isCompleted(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'last_watched_at' => $this->last_watched_at?->toISOString(),
        ];
    }
}
