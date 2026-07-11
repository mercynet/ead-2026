<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Core\Models\User;
use App\Modules\Learning\Enums\EnrollmentBillingType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class StoreEnrollmentAction
{
    public function __construct(
        private readonly EnrollStudentInCourseAction $enrollStudentInCourseAction,
    ) {}

    public function handle(ApiContext $context, array $attributes): Enrollment
    {
        $tenant = $context->requiredTenant();
        $authenticatedUser = $context->requiredUser();
        $manualFreeEnrollmentEnabled = $authenticatedUser->isInstructor()
            && $tenant->customization?->manualFreeEnrollmentEnabled() === true;
        $manualFreeEnrollmentRequiresApproval = $authenticatedUser->isInstructor()
            && $tenant->customization?->manualFreeEnrollmentRequiresApproval() === true;

        $userId = (int) ($attributes['user_id'] ?? $authenticatedUser->id);
        $targetUser = User::query()
            ->whereKey($userId)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->firstOrFail();

        $billingType = $attributes['billing_type'] ?? null;

        $course = Course::query()
            ->whereKey($attributes['course_id'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $isInstructorExternalPaidEnrollment = $authenticatedUser->isInstructor()
            && $course->price_cents > 0
            && $billingType === EnrollmentBillingType::External->value;

        if ($targetUser->id !== $authenticatedUser->id
            && ! $authenticatedUser->isDeveloper()
            && ! $authenticatedUser->isAdmin()
            && ! $manualFreeEnrollmentEnabled
            && ! $isInstructorExternalPaidEnrollment
        ) {
            throw AccessDeniedException::make('enrollment', $targetUser->id);
        }

        if ($billingType === EnrollmentBillingType::External->value && ! $authenticatedUser->isInstructor()) {
            throw ValidationException::withMessages([
                'billing_type' => 'External billing is only available for instructor manual enrollments.',
            ]);
        }

        if ($billingType === EnrollmentBillingType::External->value && $course->price_cents === 0) {
            throw ValidationException::withMessages([
                'billing_type' => 'External billing is only allowed for paid courses.',
            ]);
        }

        if ($authenticatedUser->isInstructor()
            && $course->price_cents > 0
            && $billingType !== EnrollmentBillingType::External->value
        ) {
            throw ValidationException::withMessages([
                'course_id' => 'Instructors can only create manual enrollments for free courses.',
            ]);
        }

        return $this->enrollStudentInCourseAction->handle(
            new EnrollStudentInCourseData(
                tenantId: $tenant->id,
                courseId: (int) $attributes['course_id'],
                userId: $targetUser->id,
                billingType: $billingType !== null ? EnrollmentBillingType::tryFrom((string) $billingType) : null,
                source: 'manual',
                status: (($authenticatedUser->isInstructor() && $course->price_cents > 0 && $billingType === EnrollmentBillingType::External->value) || $manualFreeEnrollmentRequiresApproval)
                    ? 'pending'
                    : 'active',
                createdByInstructorId: $authenticatedUser->isInstructor() ? $authenticatedUser->id : null,
                duplicatePolicy: 'error',
            )
        );
    }
}
