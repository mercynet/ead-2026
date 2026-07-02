<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class StoreEnrollmentAction
{
    public function handle(ApiContext $context, array $attributes): Enrollment
    {
        $tenant = $context->requiredTenant();
        $authenticatedUser = $context->requiredUser();

        $course = Course::query()
            ->whereKey($attributes['course_id'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $userId = (int) ($attributes['user_id'] ?? $authenticatedUser->id);
        $targetUser = User::query()
            ->whereKey($userId)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->firstOrFail();

        if ($targetUser->id !== $authenticatedUser->id && ! $authenticatedUser->isDeveloper() && ! $authenticatedUser->isAdmin()) {
            throw AccessDeniedException::make('enrollment', $targetUser->id);
        }

        $currentEnrollmentConflictMessage = [
            'course_id' => 'User already has a current enrollment for this course.',
        ];

        $existingEnrollment = Enrollment::query()
            ->forTenantUserCourse($tenant->id, $targetUser->id, $course->id)
            ->currentStatuses()
            ->first();

        if ($existingEnrollment !== null) {
            throw ValidationException::withMessages($currentEnrollmentConflictMessage);
        }

        $enrollment = new Enrollment;
        $enrollment->fill([
            'tenant_id' => $tenant->id,
            'user_id' => $targetUser->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'progress_percentage' => 0,
            'access_expires_at' => $course->access_days === null ? null : now()->addDays($course->access_days),
        ]);
        try {
            $enrollment->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                throw ValidationException::withMessages($currentEnrollmentConflictMessage);
            }

            throw $exception;
        }

        return $enrollment->load(['course:id,title,slug']);
    }
}
