<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Actions\Access\EvaluateCourseAccessAction;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\LessonProgress;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Collection;

class GetCourseModulesAction
{
    public function __construct(
        private readonly EvaluateCourseAccessAction $evaluateCourseAccessAction,
    ) {}

    /**
     * @return Collection<int, CourseModule>
     */
    public function handle(ApiContext $context, int $courseId): Collection
    {
        $course = Course::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('id', $courseId)
            ->firstOrFail();

        $modules = CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $course->id)
            ->with(['lessons' => function ($query): void {
                $query->where('status', 'published')
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $currentEnrollment = $this->evaluateCourseAccessAction->currentEnrollment($context, $course->id);
        $lessonProgress = $currentEnrollment === null
            ? collect()
            : $this->getLessonProgress($context, $course->id, $currentEnrollment->id);
        $canAccessPaidContent = $this->evaluateCourseAccessAction->canAccessPaidContent($course, $context);

        $modules->each(function ($module) use ($context, $lessonProgress, $canAccessPaidContent): void {
            $module->lessons->each(function ($lesson) use ($context, $lessonProgress, $canAccessPaidContent): void {
                $progress = $lessonProgress->get($lesson->id);
                $lesson->progress = $progress;
                $lesson->can_access = $this->evaluateCourseAccessAction->canAccessLesson($lesson, $context);
                $lesson->can_access_paid_content = $canAccessPaidContent;
            });
        });

        return $modules;
    }

    /**
     * @return \Illuminate\Support\Collection<int, LessonProgress>
     */
    private function getLessonProgress(ApiContext $context, int $courseId, int $enrollmentId): \Illuminate\Support\Collection
    {
        return LessonProgress::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('course_id', $courseId)
            ->where('enrollment_id', $enrollmentId)
            ->get()
            ->keyBy('lesson_id');
    }
}
