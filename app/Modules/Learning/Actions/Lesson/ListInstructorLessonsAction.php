<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListInstructorLessonsAction
{
    public function handle(ApiContext $context, int $moduleId): CursorPaginator
    {
        return Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_module_id', $moduleId)
            ->whereHas('courseModule.course', fn ($query) => $query->where('instructor_id', $context->requiredUser()->id))
            ->with(['courseModule.course:id,title,instructor_id'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
