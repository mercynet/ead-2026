<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;

class PreviewCourseAction
{
    public function handle(ApiContext $context, int $courseId): Course
    {
        return Course::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->with([
                'categories',
                'modules' => fn ($query) => $query->orderBy('sort_order')->with([
                    'lessons' => fn ($lessonQuery) => $lessonQuery->orderBy('sort_order'),
                ]),
            ])
            ->findOrFail($courseId);
    }
}
