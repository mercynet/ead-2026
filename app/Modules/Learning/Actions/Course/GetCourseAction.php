<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;

class GetCourseAction
{
    public function handle(ApiContext $context, int $courseId): Course
    {
        return Course::query()
            ->where('tenant_id', $context->tenant?->id)
            ->findOrFail($courseId);
    }
}
