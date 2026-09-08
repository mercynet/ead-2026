<?php

namespace App\Modules\Learning\Http\Resources\Instructor;

use App\Modules\Learning\Models\CourseModule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseModule
 *
 * @property int $course_id
 * @property int $sort_order
 */
class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'sort_order' => $this->sort_order,
            $this->mergeWhen($this->relationLoaded('course'), [
                'course' => [
                    'id' => $this->course->id,
                    'title' => $this->course->title,
                ],
            ]),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
