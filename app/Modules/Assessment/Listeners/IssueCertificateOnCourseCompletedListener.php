<?php

namespace App\Modules\Assessment\Listeners;

use App\Modules\Assessment\Actions\Certificate\IssueCertificateAction;
use App\Modules\Learning\Events\CourseCompletedEvent;

class IssueCertificateOnCourseCompletedListener
{
    public function __construct(
        private readonly IssueCertificateAction $issueCertificateAction,
    ) {}

    public function handle(CourseCompletedEvent $event): void
    {
        $this->issueCertificateAction->handle($event);
    }
}
