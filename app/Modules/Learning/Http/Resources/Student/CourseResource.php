<?php

namespace App\Modules\Learning\Http\Resources\Student;

use App\Modules\Learning\Http\Resources\Catalog\CategoryResource;
use App\Modules\Learning\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $short_description
 * @property string|null $thumbnail
 * @property string|null $banner
 * @property string $level
 * @property int $duration_hours
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
            'thumbnail' => $this->thumbnail,
            'banner' => $this->banner,
            'level' => $this->level,
            'duration_hours' => $this->duration_hours,
            'is_free' => $this->isFree(),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'enrollment' => EnrollmentResource::make($this->whenLoaded('studentEnrollment')),
        ];
    }
}
