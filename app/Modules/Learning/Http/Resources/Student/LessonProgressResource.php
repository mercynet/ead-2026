<?php

namespace App\Modules\Learning\Http\Resources\Student;

use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonProgress
 *
 * @property int $id
 * @property int $lesson_id
 * @property int $progress_percentage
 * @property int $time_spent_seconds
 * @property int|null $current_time_seconds
 * @property int|null $total_time_seconds
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $last_watched_at
 */
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
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_watched_at' => $this->last_watched_at?->toIso8601String(),
        ];
    }
}
