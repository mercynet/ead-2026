<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListAdminLessonsAction
{
    public function handle(ApiContext $context, int $courseModuleId): CursorPaginator
    {
        return Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('course_module_id', $courseModuleId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
