<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Actions\Access\ResolveStudentAccessAction;
use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListStudentModulesAction
{
    public function __construct(
        private readonly ResolveStudentAccessAction $resolveStudentAccessAction,
    ) {}

    public function handle(ApiContext $context, int $courseId): CursorPaginator
    {
        $course = $this->resolveStudentAccessAction->requireCourse($context, $courseId);

        return CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $course->id)
            ->whereHas('lessons', fn ($query) => $query
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('status', 'published')
                ->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
