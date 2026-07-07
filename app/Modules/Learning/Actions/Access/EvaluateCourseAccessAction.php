<?php

namespace App\Modules\Learning\Actions\Access;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;

class EvaluateCourseAccessAction
{
    public function canViewCourse(Course $course): bool
    {
        return $course->isActive();
    }

    public function canAccessPaidContent(Course $course, ApiContext $context): bool
    {
        if ($course->isFree()) {
            return true;
        }

        return $this->currentEnrollment($context, $course->id)?->isActive() === true;
    }

    public function canAccessLesson(Lesson $lesson, ApiContext $context): bool
    {
        if ($lesson->is_free) {
            return true;
        }

        return $this->canAccessPaidContent($lesson->courseModule->course, $context);
    }

    public function currentEnrollment(ApiContext $context, int $courseId): ?Enrollment
    {
        return Enrollment::query()
            ->forTenantUserCourse(
                $context->requiredTenant()->id,
                $context->requiredUser()->id,
                $courseId
            )
            ->currentStatuses()
            ->orderByDesc('id')
            ->first();
    }
}
