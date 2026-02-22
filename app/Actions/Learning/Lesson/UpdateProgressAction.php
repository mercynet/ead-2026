<?php

namespace App\Actions\Learning\Lesson;

use App\Events\Learning\LessonCompletedEvent;
use App\Http\Context\ApiContext;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Event;

class UpdateProgressAction
{
    public function handle(ApiContext $context, Lesson $lesson, array $data): LessonProgress
    {
        $course = $lesson->courseModule->course;

        $enrollment = Enrollment::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $wasCompleted = LessonProgress::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('course_id', $course->id)
            ->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->value('is_completed') ?? false;

        $isCompleted = $data['is_completed'] ?? false;

        $progress = LessonProgress::query()
            ->updateOrCreate(
                [
                    'tenant_id' => $context->requiredTenant()->id,
                    'user_id' => $context->requiredUser()->id,
                    'course_id' => $course->id,
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'time_spent_seconds' => $data['time_spent_seconds'] ?? 0,
                    'current_time_seconds' => $data['current_time_seconds'] ?? 0,
                    'total_time_seconds' => $data['total_time_seconds'] ?? 0,
                    'progress_percentage' => $data['progress_percentage'] ?? 0,
                    'is_completed' => $isCompleted,
                    'completed_at' => $isCompleted ? now() : null,
                    'started_at' => now(),
                    'last_watched_at' => now(),
                ]
            );

        $this->updateEnrollmentProgress($enrollment);

        if ($isCompleted && ! $wasCompleted) {
            Event::dispatch(new LessonCompletedEvent(
                $lesson,
                $context->requiredUser(),
                $course
            ));
        }

        return $progress;
    }

    private function updateEnrollmentProgress(Enrollment $enrollment): void
    {
        $course = $enrollment->course;

        $totalLessons = Lesson::query()
            ->whereHas('courseModule', fn ($q) => $q->where('course_id', $course->id))
            ->count();

        if ($totalLessons === 0) {
            return;
        }

        $completedLessons = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('is_completed', true)
            ->count();

        $percentage = (int) round(($completedLessons / $totalLessons) * 100);

        $enrollment->update([
            'progress_percentage' => min($percentage, 100),
            'status' => $percentage >= 100 ? 'completed' : $enrollment->status,
            'completed_at' => $percentage >= 100 ? now() : null,
        ]);
    }
}
