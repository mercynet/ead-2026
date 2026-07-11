<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Modules\Learning\Enums\EnrollmentBillingType;

readonly class EnrollStudentInCourseData
{
    public function __construct(
        public int $tenantId,
        public int $courseId,
        public int $userId,
        public ?EnrollmentBillingType $billingType = null,
        public string $source = 'manual',
        public string $status = 'active',
        public ?int $createdByInstructorId = null,
        public string $duplicatePolicy = 'error',
    ) {}

    /**
     * @param  array{course_id:int,user_id?:int,billing_type?:string|null}  $attributes
     */
    public static function manual(array $attributes, int $tenantId, int $defaultUserId): self
    {
        return new self(
            tenantId: $tenantId,
            courseId: (int) $attributes['course_id'],
            userId: (int) ($attributes['user_id'] ?? $defaultUserId),
            billingType: isset($attributes['billing_type']) ? EnrollmentBillingType::tryFrom((string) $attributes['billing_type']) : null,
            source: 'manual',
        );
    }

    public static function orderPaid(int $tenantId, int $courseId, int $userId): self
    {
        return new self(
            tenantId: $tenantId,
            courseId: $courseId,
            userId: $userId,
            source: 'order_paid',
            status: 'active',
            duplicatePolicy: 'ignore',
        );
    }
}
