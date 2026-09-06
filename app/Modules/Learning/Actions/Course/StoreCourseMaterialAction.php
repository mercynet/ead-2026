<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Shared\Http\ApiContext;

class StoreCourseMaterialAction
{
    public function handle(ApiContext $context, Course $course, array $attributes, ?int $instructorId = null): CourseMaterial
    {
        $attributes['tenant_id'] = $context->requiredTenant()->id;
        $attributes['course_id'] = $course->id;
        $attributes['instructor_id'] = $instructorId;

        return CourseMaterial::query()->create($attributes);
    }
}
