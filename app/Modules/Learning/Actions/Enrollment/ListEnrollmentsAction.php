<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListEnrollmentsAction
{
    public function handle(Request $request, ApiContext $context, bool $instructorOwned = false): CursorPaginator
    {
        $query = Enrollment::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->when($instructorOwned, fn ($query) => $query->whereHas('course', fn ($courseQuery) => $courseQuery
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('instructor_id', $context->requiredUser()->id)))
            ->with([
                'course:id,tenant_id,title,slug,instructor_id',
                'course.modules:id,course_id,title,sort_order',
                'course.modules.lessons:id,course_module_id,title,status,is_active,sort_order',
                'user:id,name,avatar',
                'lessonProgress' => fn ($progressQuery) => $progressQuery
                    ->where('tenant_id', $context->requiredTenant()->id)
                    ->orderBy('lesson_id'),
            ])
            ->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } elseif ($instructorOwned) {
            $query->where('status', 'active');
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->integer('course_id'));
        }

        if (! $instructorOwned && $request->filled('user_id')) {
            $query->where('user_id', (int) $request->integer('user_id'));
        }

        return $query->cursorPaginate(15);
    }
}
