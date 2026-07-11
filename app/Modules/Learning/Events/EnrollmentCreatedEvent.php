<?php

namespace App\Modules\Learning\Events;

class EnrollmentCreatedEvent
{
    public function __construct(
        public readonly int $enrollmentId,
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly int $courseId,
        public readonly string $status,
        public readonly string $source,
        public readonly ?string $billingType,
        public readonly ?int $createdByInstructorId,
        public readonly string $occurredAt,
    ) {}
}
