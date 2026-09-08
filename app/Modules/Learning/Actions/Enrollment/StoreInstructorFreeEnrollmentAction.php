<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\TenantCustomization;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class StoreInstructorFreeEnrollmentAction
{
    public function __construct(
        private readonly EnrollStudentInCourseAction $enrollStudentInCourseAction,
    ) {}

    public function handle(ApiContext $context, int $courseId, int $userId): Enrollment
    {
        $tenant = $context->requiredTenant();
        $instructor = $context->requiredUser();
        $course = Course::query()
            ->where('tenant_id', $tenant->id)
            ->where('instructor_id', $instructor->id)
            ->whereKey($courseId)
            ->firstOrFail();

        if (! $course->isActive()) {
            throw ValidationException::withMessages(['course_id' => 'Course must be published and active.']);
        }

        if (! $course->isFree()) {
            throw ValidationException::withMessages(['course_id' => 'Instructor enrollment is available only for free courses.']);
        }

        $customization = TenantCustomization::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $customization?->manualFreeEnrollmentEnabled()) {
            throw ValidationException::withMessages(['course_id' => 'Manual free enrollment is disabled for this tenant.']);
        }

        $student = User::query()
            ->whereKey($userId)
            ->where('tenant_id', $tenant->id)
            ->where('user_type', UserType::Student->value)
            ->firstOrFail();

        $requiresApproval = $customization->manualFreeEnrollmentRequiresApproval();

        $enrollment = $this->enrollStudentInCourseAction->handle(new EnrollStudentInCourseData(
            tenantId: $tenant->id,
            courseId: $course->id,
            userId: $student->id,
            source: 'manual',
            status: $requiresApproval ? 'pending' : 'active',
            createdByInstructorId: $instructor->id,
            duplicatePolicy: 'ignore',
        ));

        return $enrollment->load([
            'course.modules.lessons',
            'lessonProgress' => fn ($progressQuery) => $progressQuery
                ->where('tenant_id', $tenant->id)
                ->orderBy('lesson_id'),
        ]);
    }
}
