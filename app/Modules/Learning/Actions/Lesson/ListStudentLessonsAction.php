<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Actions\Access\ResolveStudentAccessAction;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

class ListStudentLessonsAction
{
    public function __construct(
        private readonly ResolveStudentAccessAction $resolveStudentAccessAction,
    ) {}

    public function handle(ApiContext $context, int $courseId, int $moduleId): CursorPaginator
    {
        $course = $this->resolveStudentAccessAction->requireCourse($context, $courseId);
        $module = CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $course->id)
            ->whereKey($moduleId)
            ->whereHas('lessons', fn (Builder $query): Builder => $query
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('status', 'published')
                ->where('is_active', true))
            ->firstOrFail();
        /** @var Enrollment $enrollment */
        $enrollment = $course->getRelation('studentEnrollment');

        return Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_module_id', $module->id)
            ->where('status', 'published')
            ->where('is_active', true)
            ->with(['progress' => function ($query) use ($context, $course, $enrollment): void {
                $query
                    ->where('tenant_id', $context->requiredTenant()->id)
                    ->where('user_id', $context->requiredUser()->id)
                    ->where('course_id', $course->id)
                    ->where('enrollment_id', $enrollment->id);
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
