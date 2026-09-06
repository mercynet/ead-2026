<?php

namespace App\Modules\Learning\Http\Resources\Admin;

use App\Modules\Learning\Http\Resources\Lesson\LessonMediaResource;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $status
 * @property string $content_type
 * @property int|null $duration
 * @property int $sort_order
 * @property bool $is_free
 * @property bool $is_active
 * @property-read \Illuminate\Support\Carbon|null $published_at
 * @property-read \App\Modules\Learning\Models\CourseModule $courseModule
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Learning\Models\LessonMedia> $media
 */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'content_type' => $this->content_type,
            'duration' => $this->duration,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'is_active' => $this->is_active,
            'published_at' => $this->published_at?->toIso8601String(),
            'module' => [
                'id' => $this->courseModule->id,
                'title' => $this->courseModule->title,
            ],
            'course' => [
                'id' => $this->courseModule->course->id,
                'title' => $this->courseModule->course->title,
            ],
            'media' => LessonMediaResource::collection($this->media),
        ];
    }
}
