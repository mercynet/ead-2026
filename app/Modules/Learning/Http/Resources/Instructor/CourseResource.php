<?php

namespace App\Modules\Learning\Http\Resources\Instructor;

use App\Modules\Learning\Http\Resources\Catalog\CategoryResource;
use App\Modules\Learning\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 *
 * @property string|null $description
 * @property string|null $short_description
 * @property string|null $target_audience
 * @property string|null $requirements
 * @property string|null $what_you_will_learn
 * @property string|null $what_you_will_build
 * @property string $status
 * @property bool $is_featured
 * @property int $access_days
 * @property string|null $thumbnail
 * @property string|null $banner
 * @property string $level
 * @property int $duration_hours
 * @property bool $certificate_enabled
 * @property int $certificate_min_progress
 * @property bool $certificate_requires_quiz
 * @property int $certificate_min_score
 * @property bool $is_active
 * @property-read \Illuminate\Support\Carbon|null $published_at
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'target_audience' => $this->target_audience,
            'requirements' => $this->requirements,
            'what_you_will_learn' => $this->what_you_will_learn,
            'what_you_will_build' => $this->what_you_will_build,
            'status' => $this->status,
            'price_cents' => $this->price_cents,
            'is_free' => $this->isFree(),
            'is_featured' => $this->is_featured,
            'access_days' => $this->access_days,
            'thumbnail' => $this->thumbnail,
            'banner' => $this->banner,
            'level' => $this->level,
            'duration_hours' => $this->duration_hours,
            'certificate_enabled' => $this->certificate_enabled,
            'certificate_min_progress' => $this->certificate_min_progress,
            'certificate_requires_quiz' => $this->certificate_requires_quiz,
            'certificate_min_score' => $this->certificate_min_score,
            'is_active' => $this->is_active,
            'instructor_id' => $this->instructor_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'modules' => ModuleResource::collection($this->whenLoaded('modules')),
        ];
    }
}
