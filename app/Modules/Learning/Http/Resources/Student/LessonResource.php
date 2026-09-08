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
 * @property string|null $short_description
 * @property string|null $description
 * @property array<string, mixed>|null $content
 * @property string $content_type
 * @property int $duration
 * @property int $sort_order
 * @property bool $is_free
 * @property bool $student_preview
 * @property-read \App\Modules\Learning\Models\CourseModule $courseModule
 */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LessonProgress|null $progress */
        $progress = $this->getAttribute('studentProgress');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'content' => $this->content,
            'content_type' => $this->content_type,
            'duration' => $this->duration,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'access_mode' => $this->student_preview ? 'preview' : 'enrolled',
            'module' => [
                'id' => $this->courseModule->id,
                'title' => $this->courseModule->title,
            ],
            'course' => [
                'id' => $this->courseModule->course->id,
                'title' => $this->courseModule->course->title,
                'slug' => $this->courseModule->course->slug,
            ],
            'media' => LessonMediaResource::collection($this->media),
            'progress' => $progress === null ? null : [
                'progress_percentage' => $progress->getAttribute('progress_percentage'),
                'is_completed' => $progress->isCompleted(),
                'time_spent_seconds' => $progress->getAttribute('time_spent_seconds'),
                'current_time_seconds' => $progress->getAttribute('current_time_seconds'),
                'total_time_seconds' => $progress->getAttribute('total_time_seconds'),
                'started_at' => $progress->getAttribute('started_at')?->toIso8601String(),
                'completed_at' => $progress->getAttribute('completed_at')?->toIso8601String(),
                'last_watched_at' => $progress->getAttribute('last_watched_at')?->toIso8601String(),
            ],
        ];
    }
}
