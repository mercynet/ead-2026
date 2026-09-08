<?php

namespace App\Modules\Learning\Actions\Module;

use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListInstructorModulesAction
{
    public function handle(ApiContext $context, int $courseId): CursorPaginator
    {
        return CourseModule::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $courseId)
            ->whereHas('course', fn ($query) => $query->where('instructor_id', $context->requiredUser()->id))
            ->with('course:id,title')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
