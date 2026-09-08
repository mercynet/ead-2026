<?php

namespace App\Modules\Learning\Http\Resources\Student;

use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property int $sort_order
 * @property int $duration
 * @property bool $is_free
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Learning\Models\LessonProgress> $progress
 */
class LessonSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LessonProgress|null $progress */
        $progress = $this->progress->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'duration' => $this->duration,
            'is_free' => $this->is_free,
            'progress' => $progress === null ? null : [
                'progress_percentage' => $progress->getAttribute('progress_percentage'),
                'is_completed' => $progress->isCompleted(),
                'current_time_seconds' => $progress->getAttribute('current_time_seconds'),
            ],
        ];
    }
}
