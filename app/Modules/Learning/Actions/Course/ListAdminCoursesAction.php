<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Http\Requests\Admin\ListCoursesRequest;
use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListAdminCoursesAction
{
    public function handle(ListCoursesRequest $request, ApiContext $context): CursorPaginator
    {
        $query = Course::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->with(['categories:id,name,slug', 'instructor:id,name'])
            ->orderBy('id');

        $status = $request->validated('status');
        if (is_string($status)) {
            $query->where('status', $status);
        }

        return $query->cursorPaginate(15);
    }
}
