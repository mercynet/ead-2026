<?php

namespace App\Modules\Learning\Services;

use App\Modules\Learning\Models\Course;
use Illuminate\Validation\ValidationException;

final class CourseCommercialReadiness
{
    public function assertPublishable(Course $course): void
    {
        $this->assertCapabilitiesAvailable(
            (bool) $course->certificate_requires_quiz,
            (bool) $course->certificate_enabled,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function assertUpdateable(Course $course, array $attributes): void
    {
        $status = (string) ($attributes['status'] ?? $course->status);
        $isActive = (bool) ($attributes['is_active'] ?? $course->is_active);

        if ($status !== 'published' || ! $isActive) {
            return;
        }

        $this->assertCapabilitiesAvailable(
            (bool) ($attributes['certificate_requires_quiz'] ?? $course->certificate_requires_quiz),
            (bool) ($attributes['certificate_enabled'] ?? $course->certificate_enabled),
        );
    }

    private function assertCapabilitiesAvailable(bool $assessmentRequired, bool $certificateEnabled): void
    {
        if ($assessmentRequired) {
            throw ValidationException::withMessages([
                'assessment' => 'Course is not commercially ready: Student Assessment is not available for a required completion condition.',
            ]);
        }

        if ($certificateEnabled) {
            throw ValidationException::withMessages([
                'certificate' => 'Course is not commercially ready: certificates are not available in the current release.',
            ]);
        }
    }
}
