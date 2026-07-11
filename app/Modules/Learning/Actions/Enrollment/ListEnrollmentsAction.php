<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListEnrollmentsAction
{
    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        $query = Enrollment::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->with(['course:id,title,slug', 'user:id,name,tenant_id'])
            ->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->integer('course_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->integer('user_id'));
        }

        return $query->cursorPaginate(15);
    }
}
