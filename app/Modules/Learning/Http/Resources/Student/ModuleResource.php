<?php

namespace App\Modules\Learning\Http\Resources\Student;

use App\Modules\Learning\Models\CourseModule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseModule
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
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
        ];
    }
}
