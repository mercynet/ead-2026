<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Actions\Access\EvaluateCourseAccessAction;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;
use App\Shared\Http\ApiContext;

class GetLessonAction
{
    public function __construct(
        private readonly EvaluateCourseAccessAction $evaluateCourseAccessAction,
    ) {}

    public function handle(ApiContext $context, int $lessonId): Lesson
    {
        return Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('id', $lessonId)
            ->with([
                'courseModule.course',
                'media' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->firstOrFail();
    }

    public function canAccess(Lesson $lesson, ApiContext $context): bool
    {
        return $this->evaluateCourseAccessAction->canAccessLesson($lesson, $context);
    }

    public function progressFor(Lesson $lesson, ApiContext $context): ?LessonProgress
    {
        $enrollment = $this->evaluateCourseAccessAction->currentEnrollment($context, $lesson->courseModule->course->id);

        if ($enrollment === null) {
            return null;
        }

        return LessonProgress::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->first();
    }
}
