<?php

namespace App\Http\Resources\Learning\Course;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonWithProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'duration' => $this->duration,
            'progress' => $this->progress ? [
                'is_completed' => $this->progress->is_completed,
                'progress_percentage' => $this->progress->progress_percentage,
                'time_spent_seconds' => $this->progress->time_spent_seconds,
                'current_time_seconds' => $this->progress->current_time_seconds,
                'total_time_seconds' => $this->progress->total_time_seconds,
                'started_at' => $this->progress->started_at?->toISOString(),
                'completed_at' => $this->progress->completed_at?->toISOString(),
                'last_watched_at' => $this->progress->last_watched_at?->toISOString(),
            ] : null,
        ];
    }
}
