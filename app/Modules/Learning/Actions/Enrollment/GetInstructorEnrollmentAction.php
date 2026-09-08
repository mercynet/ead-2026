<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;

class GetInstructorEnrollmentAction
{
    public function handle(ApiContext $context, int $id): Enrollment
    {
        return Enrollment::query()
            ->whereKey($id)
            ->where('tenant_id', $context->requiredTenant()->id)
            ->whereHas('course', fn ($query) => $query
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('instructor_id', $context->requiredUser()->id))
            ->with([
                'user:id,name,avatar',
                'course:id,tenant_id,title,slug,instructor_id',
                'course.modules:id,course_id,title,sort_order',
                'course.modules.lessons:id,course_module_id,title,status,is_active,sort_order',
                'lessonProgress' => fn ($query) => $query
                    ->where('tenant_id', $context->requiredTenant()->id)
                    ->orderBy('lesson_id'),
            ])
            ->firstOrFail();
    }
}
