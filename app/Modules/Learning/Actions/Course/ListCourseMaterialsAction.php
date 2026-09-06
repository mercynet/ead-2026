<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\CourseMaterial;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListCourseMaterialsAction
{
    public function handle(ApiContext $context, int $courseId): CursorPaginator
    {
        return CourseMaterial::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_id', $courseId)
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
