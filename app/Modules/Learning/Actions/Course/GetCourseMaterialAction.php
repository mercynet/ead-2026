<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\CourseMaterial;
use App\Shared\Http\ApiContext;

class GetCourseMaterialAction
{
    public function handle(ApiContext $context, int $courseId, int $materialId): CourseMaterial
    {
        return CourseMaterial::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $courseId)
            ->findOrFail($materialId);
    }
}
