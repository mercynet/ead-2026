<?php

namespace App\Http\Resources\Learning\Course;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseModulesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'sort_order' => $this->sort_order,
            'lessons' => LessonWithProgressResource::collection($this->lessons),
        ];
    }
}
