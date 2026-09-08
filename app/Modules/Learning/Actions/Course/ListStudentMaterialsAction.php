<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Actions\Access\ResolveStudentAccessAction;
use App\Modules\Learning\Models\CourseMaterial;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListStudentMaterialsAction
{
    public function __construct(
        private readonly ResolveStudentAccessAction $resolveStudentAccessAction,
    ) {}

    public function handle(ApiContext $context, int $courseId): CursorPaginator
    {
        $course = $this->resolveStudentAccessAction->requireCourse($context, $courseId);

        return CourseMaterial::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
