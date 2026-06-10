<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\LessonProgress;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Collection;

class GetCourseModulesAction
{
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

        $lessonProgress = $this->getLessonProgress($context, $course->id);

        $modules->each(function ($module) use ($lessonProgress): void {
            $module->lessons->each(function ($lesson) use ($lessonProgress): void {
                $progress = $lessonProgress->get($lesson->id);
                $lesson->progress = $progress;
            });
        });

        return $modules;
    }

    /**
     * @return \Illuminate\Support\Collection<int, LessonProgress>
     */
    private function getLessonProgress(ApiContext $context, int $courseId): \Illuminate\Support\Collection
    {
        return LessonProgress::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('course_id', $courseId)
            ->get()
            ->keyBy('lesson_id');
    }
}
