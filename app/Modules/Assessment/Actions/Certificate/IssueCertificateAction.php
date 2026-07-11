<?php

namespace App\Modules\Assessment\Actions\Certificate;

use App\Modules\Assessment\Models\Certificate;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Learning\Events\CourseCompletedEvent;

class IssueCertificateAction
{
    public function handle(CourseCompletedEvent $event): ?Certificate
    {
        $course = $event->course;
        $enrollment = $event->enrollment;

        if (! $course->certificate_enabled) {
            return null;
        }

        if ((int) $enrollment->progress_percentage < (int) $course->certificate_min_progress) {
            return null;
        }

        if ($course->certificate_requires_quiz && ! $this->hasPassingQuizAttempt($event)) {
            return null;
        }

        $existing = Certificate::query()
            ->where('tenant_id', $enrollment->tenant_id)
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'issued')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Certificate::create([
            'tenant_id' => $enrollment->tenant_id,
            'user_id' => $event->user->id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'certificate_number' => Certificate::generateCertificateNumber($enrollment->tenant_id),
            'issued_at' => now(),
            'status' => 'issued',
        ]);
    }

    private function hasPassingQuizAttempt(CourseCompletedEvent $event): bool
    {
        $questionnaireIds = Questionnaire::query()
            ->where('tenant_id', $event->enrollment->tenant_id)
            ->where('quizable_type', $event->course->getMorphClass())
            ->where('quizable_id', $event->course->id)
            ->pluck('id');

        if ($questionnaireIds->isEmpty()) {
            return false;
        }

        return QuizAttempt::query()
            ->where('tenant_id', $event->enrollment->tenant_id)
            ->where('user_id', $event->user->id)
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->where('passed', true)
            ->where('score', '>=', (int) $event->course->certificate_min_score)
            ->exists();
    }
}
