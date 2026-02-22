<?php

namespace App\Actions\Learning\Lesson;

use App\Http\Context\ApiContext;
use App\Models\Enrollment;
use App\Models\Lesson;

class GetLessonAction
{
    public function handle(ApiContext $context, int $lessonId): Lesson
    {
        return Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('id', $lessonId)
            ->with(['courseModule.course'])
            ->firstOrFail();
    }

    public function canAccess(Lesson $lesson, ApiContext $context): bool
    {
        if ($lesson->is_free) {
            return true;
        }

        $course = $lesson->courseModule->course;

        if ($course->isFree()) {
            return true;
        }

        $enrollment = Enrollment::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('course_id', $course->id)
            ->first();

        if ($enrollment === null) {
            return false;
        }

        return $enrollment->isActive();
    }
}
