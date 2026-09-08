<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListStudentCoursesAction
{
    public function handle(ApiContext $context): CursorPaginator
    {
        $tenantId = $context->requiredTenant()->id;
        $userId = $context->requiredUser()->id;

        return Course::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('is_active', true)
            ->whereHas('enrollments', function ($query) use ($tenantId, $userId): void {
                $query
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where(function ($expiryQuery): void {
                        $expiryQuery->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now());
                    });
            })
            ->with([
                'categories',
                'enrollments' => function ($query) use ($tenantId, $userId): void {
                    $query
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $userId)
                        ->where('status', 'active')
                        ->where(function ($expiryQuery): void {
                            $expiryQuery->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now());
                        })
                        ->latest('id');
                },
            ])
            ->orderBy('id')
            ->cursorPaginate(15)
            ->through(function (Course $course): Course {
                $course->setRelation('studentEnrollment', $course->enrollments->first());

                return $course;
            });
    }
}
