<?php

namespace App\Modules\Financial\Listeners;

use App\Modules\Financial\Actions\Enrollment\CreateEnrollmentFinancialMirrorAction;
use App\Modules\Financial\Data\EnrollmentFinancialMirrorData;
use App\Modules\Learning\Events\EnrollmentCreatedEvent;

class CreateEnrollmentFinancialMirrorListener
{
    public function __construct(
        private readonly CreateEnrollmentFinancialMirrorAction $createEnrollmentFinancialMirror,
    ) {}

    public function handle(EnrollmentCreatedEvent $event): void
    {
        if ($event->source !== 'manual' || $event->billingType !== null) {
            return;
        }

        $this->createEnrollmentFinancialMirror->handle(new EnrollmentFinancialMirrorData(
            enrollmentId: $event->enrollmentId,
            tenantId: $event->tenantId,
            userId: $event->userId,
            courseId: $event->courseId,
            courseTitle: $event->courseTitle,
            courseSlug: $event->courseSlug,
            coursePriceCents: $event->coursePriceCents,
            enrollmentStatus: $event->status,
            source: $event->source,
            billingType: $event->billingType,
            createdByInstructorId: $event->createdByInstructorId,
            occurredAt: $event->occurredAt,
        ));
    }
}
