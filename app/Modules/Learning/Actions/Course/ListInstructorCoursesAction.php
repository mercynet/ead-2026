<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListInstructorCoursesAction
{
    public function handle(ApiContext $context): CursorPaginator
    {
        return Course::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('instructor_id', $context->requiredUser()->id)
            ->with(['categories:id,name,slug'])
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
