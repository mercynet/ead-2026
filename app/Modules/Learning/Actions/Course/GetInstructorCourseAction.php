<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;

class GetInstructorCourseAction
{
    public function handle(ApiContext $context, int $courseId): Course
    {
        return Course::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('instructor_id', $context->requiredUser()->id)
            ->with([
                'categories:id,name,slug',
                'modules' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')->with([
                    'lessons' => fn ($lessonQuery) => $lessonQuery->orderBy('sort_order')->orderBy('id'),
                ]),
            ])
            ->findOrFail($courseId);
    }
}
