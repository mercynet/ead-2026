<?php

namespace App\Modules\Learning\Http\Resources\Instructor;

use App\Modules\Learning\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 *
 * @property string $title
 * @property string|null $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $thumbnail
 * @property string|null $video_path
 * @property string $status
 * @property string|null $content_type
 * @property string|null $duration
 * @property int $sort_order
 * @property bool $is_free
 * @property bool $is_active
 * @property-read \Illuminate\Support\Carbon|null $published_at
 */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'video_path' => $this->video_path,
            'status' => $this->status,
            'content_type' => $this->content_type,
            'duration' => $this->duration,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'is_active' => $this->is_active,
            'published_at' => $this->published_at?->toIso8601String(),
            $this->mergeWhen($this->relationLoaded('courseModule'), [
                'module' => [
                    'id' => $this->courseModule->id,
                    'title' => $this->courseModule->title,
                ],
                'course' => [
                    'id' => $this->courseModule->course?->id,
                    'title' => $this->courseModule->course?->title,
                ],
            ]),
        ];
    }
}
