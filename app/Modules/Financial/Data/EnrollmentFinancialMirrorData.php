<?php

namespace App\Modules\Financial\Data;

readonly class EnrollmentFinancialMirrorData
{
    public function __construct(
        public int $enrollmentId,
        public int $tenantId,
        public int $userId,
        public int $courseId,
        public string $courseTitle,
        public string $courseSlug,
        public int $coursePriceCents,
        public string $enrollmentStatus,
        public string $source,
        public ?string $billingType,
        public ?int $createdByInstructorId,
        public string $occurredAt,
    ) {}
}
