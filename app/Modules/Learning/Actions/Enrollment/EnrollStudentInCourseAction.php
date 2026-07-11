<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Events\EnrollmentCreatedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class EnrollStudentInCourseAction
{
    public function handle(EnrollStudentInCourseData $data): Enrollment
    {
        return DB::transaction(function () use ($data): Enrollment {
            $course = Course::query()
                ->whereKey($data->courseId)
                ->where('tenant_id', $data->tenantId)
                ->firstOrFail();

            $currentEnrollmentConflictMessage = [
                'course_id' => 'User already has a current enrollment for this course.',
            ];

            $existingEnrollment = Enrollment::query()
                ->forTenantUserCourse($data->tenantId, $data->userId, $course->id)
                ->currentStatuses()
                ->lockForUpdate()
                ->first();

            if ($existingEnrollment !== null) {
                if ($data->duplicatePolicy === 'ignore') {
                    return $existingEnrollment;
                }

                throw ValidationException::withMessages($currentEnrollmentConflictMessage);
            }

            $enrollment = new Enrollment;
            $enrollment->fill([
                'tenant_id' => $data->tenantId,
                'user_id' => $data->userId,
                'course_id' => $course->id,
                'created_by_instructor_id' => $data->createdByInstructorId,
                'billing_type' => $data->billingType?->value,
                'status' => $data->status,
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

            DB::afterCommit(function () use ($enrollment, $data): void {
                Event::dispatch(new EnrollmentCreatedEvent(
                    enrollmentId: $enrollment->id,
                    tenantId: $data->tenantId,
                    userId: $enrollment->user_id,
                    courseId: $enrollment->course_id,
                    status: (string) $enrollment->status,
                    source: $data->source,
                    billingType: $data->billingType?->value,
                    createdByInstructorId: $data->createdByInstructorId,
                    occurredAt: now()->toIso8601String(),
                ));
            });

            return $enrollment->load(['course:id,title,slug']);
        });
    }
}
