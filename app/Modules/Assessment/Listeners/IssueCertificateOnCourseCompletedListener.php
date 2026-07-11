<?php

namespace App\Modules\Assessment\Listeners;

use App\Modules\Assessment\Actions\Certificate\IssueCertificateAction;
use App\Modules\Learning\Events\CourseCompletedEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

class IssueCertificateOnCourseCompletedListener
{
    public function __construct(
        private readonly IssueCertificateAction $issueCertificateAction,
    ) {}

    public function handle(CourseCompletedEvent $event): void
    {
        try {
            $this->issueCertificateAction->handle($event);
        } catch (Throwable $exception) {
            Log::error('Certificate issuance failed after course completion.', [
                'tenant_id' => $event->enrollment->tenant_id,
                'enrollment_id' => $event->enrollment->id,
                'course_id' => $event->course->id,
                'exception' => $exception,
            ]);
        }
    }
}
