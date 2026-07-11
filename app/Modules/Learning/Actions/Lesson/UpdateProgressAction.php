<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Enums\LessonMediaProgressStrategy;
use App\Modules\Learning\Events\LessonCompletedEvent;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonMediaProgress;
use App\Modules\Learning\Models\LessonProgress;
use App\Shared\Http\ApiContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class UpdateProgressAction
{
    public function handle(ApiContext $context, Lesson $lesson, array $data): LessonProgress
    {
        return DB::transaction(function () use ($context, $lesson, $data): LessonProgress {
            $course = $lesson->courseModule->course;
            $lessonMedia = $this->resolveLessonMedia($context, $lesson, $data);

            $enrollment = Enrollment::query()
                ->forTenantUserCourse(
                    $context->requiredTenant()->id,
                    $context->requiredUser()->id,
                    $course->id
                )
                ->currentStatuses()
                ->orderByDesc('id')
                ->firstOrFail();

            $progress = LessonProgress::query()
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('user_id', $context->requiredUser()->id)
                ->where('course_id', $course->id)
                ->where('enrollment_id', $enrollment->id)
                ->where('lesson_id', $lesson->id)
                ->lockForUpdate()
                ->first();

            $wasCompleted = $progress?->isCompleted() ?? false;
            $progressSnapshot = $this->resolveProgressSnapshot($lessonMedia, $data, $progress);

            if ($progress === null) {
                $progress = new LessonProgress([
                    'tenant_id' => $context->requiredTenant()->id,
                    'user_id' => $context->requiredUser()->id,
                    'course_id' => $course->id,
                    'enrollment_id' => $enrollment->id,
                    'lesson_id' => $lesson->id,
                    'started_at' => now(),
                ]);
            }

            $currentTime = now();
            $progress->fill([
                'time_spent_seconds' => (int) max(0, $data['time_spent_seconds'] ?? 0),
                'current_time_seconds' => (int) max(0, $data['current_time_seconds'] ?? 0),
                'total_time_seconds' => (int) max(0, $data['total_time_seconds'] ?? 0),
                'progress_percentage' => $progressSnapshot['progress_percentage'],
                'is_completed' => $progressSnapshot['is_completed'],
                'completed_at' => $progressSnapshot['is_completed']
                    ? ($progress->completed_at ?? $currentTime)
                    : $progress->completed_at,
                'started_at' => $progress->started_at ?? $currentTime,
                'last_watched_at' => $currentTime,
            ]);
            $progress->save();

            if ($lessonMedia !== null) {
                $this->updateLessonMediaProgress($context, $lessonMedia, $data, $progressSnapshot);
            }

            $this->updateEnrollmentProgress($enrollment);

            if ($progressSnapshot['is_completed'] && ! $wasCompleted) {
                Event::dispatch(new LessonCompletedEvent(
                    $lesson,
                    $context->requiredUser(),
                    $course
                ));
            }

            return $progress->refresh();
        });
    }

    /**
     * @param  array{time_spent_seconds:int,current_time_seconds:int,total_time_seconds:int,progress_percentage:int,is_completed:bool}  $data
     * @return array{progress_percentage:int,is_completed:bool,completion_percentage:string}
     */
    private function resolveProgressSnapshot(?LessonMedia $lessonMedia, array $data, ?LessonProgress $existingProgress = null): array
    {
        $requestedProgressPercentage = (int) min(max($data['progress_percentage'] ?? 0, 0), 100);
        $requestedIsCompleted = (bool) ($data['is_completed'] ?? false);

        if ($lessonMedia === null) {
            if ($existingProgress?->isCompleted() === true) {
                $requestedProgressPercentage = max($requestedProgressPercentage, (int) $existingProgress->progress_percentage);
                $requestedIsCompleted = true;
            }

            return [
                'progress_percentage' => $requestedIsCompleted ? max($requestedProgressPercentage, 100) : $requestedProgressPercentage,
                'is_completed' => $requestedIsCompleted,
                'completion_percentage' => number_format((float) ($requestedIsCompleted ? 100 : $requestedProgressPercentage), 2, '.', ''),
            ];
        }

        $playbackPercentage = $this->resolvePlaybackPercentage($lessonMedia, $data);
        $progressPercentage = max($requestedProgressPercentage, $playbackPercentage);
        $strategy = $lessonMedia->progress_strategy ?? LessonMediaProgressStrategy::EightyPercent;

        $isCompleted = match ($strategy) {
            LessonMediaProgressStrategy::Manual => $requestedIsCompleted,
            LessonMediaProgressStrategy::FullDuration => $progressPercentage >= 100,
            LessonMediaProgressStrategy::TimeBased => $this->hasReachedTimeBasedThreshold($lessonMedia, $data),
            LessonMediaProgressStrategy::EightyPercent => $progressPercentage >= 80,
        };

        if ($existingProgress?->isCompleted() === true) {
            $isCompleted = true;
        }

        $existingProgressPercentage = (int) ($existingProgress?->progress_percentage ?? 0);

        return [
            'progress_percentage' => $isCompleted ? 100 : max($progressPercentage, $existingProgressPercentage),
            'is_completed' => $isCompleted,
            'completion_percentage' => number_format((float) ($isCompleted ? 100 : $progressPercentage), 2, '.', ''),
        ];
    }

    /**
     * @param  array{time_spent_seconds:int,current_time_seconds:int,total_time_seconds:int,progress_percentage:int,is_completed:bool}  $data
     */
    private function resolveLessonMedia(ApiContext $context, Lesson $lesson, array $data): ?LessonMedia
    {
        $query = $lesson->media()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        $lessonMediaId = $data['lesson_media_id'] ?? null;

        if ($lessonMediaId !== null) {
            return $query->whereKey($lessonMediaId)->firstOrFail();
        }

        $activeMedia = $query->get();

        if ($activeMedia->count() === 1) {
            return $activeMedia->first();
        }

        if ($activeMedia->count() > 1) {
            throw ValidationException::withMessages([
                'lesson_media_id' => 'A aula possui mais de uma mídia ativa; informe a mídia alvo explicitamente.',
            ]);
        }

        return null;
    }

    /**
     * @param  array{time_spent_seconds:int,current_time_seconds:int,total_time_seconds:int,progress_percentage:int,is_completed:bool}  $data
     */
    private function resolvePlaybackPercentage(LessonMedia $lessonMedia, array $data): int
    {
        $totalSeconds = (int) ($data['total_time_seconds'] ?? 0);

        if ($totalSeconds <= 0) {
            $totalSeconds = (int) ($lessonMedia->duration_seconds ?? 0);
        }

        if ($totalSeconds <= 0) {
            return 0;
        }

        $currentSeconds = (int) ($data['current_time_seconds'] ?? 0);

        return (int) min(100, round(($currentSeconds / $totalSeconds) * 100));
    }

    /**
     * @param  array{time_spent_seconds:int,current_time_seconds:int,total_time_seconds:int,progress_percentage:int,is_completed:bool}  $data
     */
    private function hasReachedTimeBasedThreshold(LessonMedia $lessonMedia, array $data): bool
    {
        $requiredSeconds = (int) data_get($lessonMedia->metadata, 'required_seconds', 0);

        if ($requiredSeconds <= 0) {
            $requiredSeconds = (int) ($lessonMedia->duration_seconds ?? 0);
        }

        if ($requiredSeconds <= 0) {
            $requiredSeconds = (int) ($data['total_time_seconds'] ?? 0);
        }

        if ($requiredSeconds <= 0) {
            return false;
        }

        return (int) ($data['time_spent_seconds'] ?? 0) >= $requiredSeconds;
    }

    /**
     * @param  array{time_spent_seconds:int,current_time_seconds:int,total_time_seconds:int,progress_percentage:int,is_completed:bool}  $data
     * @param  array{progress_percentage:int,is_completed:bool,completion_percentage:string}  $progressSnapshot
     */
    private function updateLessonMediaProgress(
        ApiContext $context,
        LessonMedia $lessonMedia,
        array $data,
        array $progressSnapshot
    ): void {
        $currentTime = now();

        $lessonMediaProgress = LessonMediaProgress::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('user_id', $context->requiredUser()->id)
            ->where('lesson_media_id', $lessonMedia->id)
            ->lockForUpdate()
            ->first();

        if ($lessonMediaProgress === null) {
            $lessonMediaProgress = new LessonMediaProgress([
                'tenant_id' => $context->requiredTenant()->id,
                'user_id' => $context->requiredUser()->id,
                'lesson_media_id' => $lessonMedia->id,
            ]);
        }

        $existingCompleted = $lessonMediaProgress->completed_at !== null || (bool) $lessonMediaProgress->is_completed;
        $completionPercentage = $progressSnapshot['completion_percentage'];

        if ($existingCompleted) {
            $completionPercentage = number_format(
                max((float) ($lessonMediaProgress->completion_percentage ?? 0), (float) $completionPercentage),
                2,
                '.',
                ''
            );
        }

        $lessonMediaProgress->fill([
            'watched_seconds' => (int) max(0, $data['time_spent_seconds'] ?? 0),
            'completion_percentage' => $completionPercentage,
            'watch_sessions' => [
                'last_position_seconds' => (int) ($data['current_time_seconds'] ?? 0),
                'last_reported_at' => $currentTime->toIso8601String(),
            ],
            'is_completed' => $progressSnapshot['is_completed'] || $existingCompleted,
            'completed_at' => $progressSnapshot['is_completed']
                ? ($lessonMediaProgress->completed_at ?? $currentTime)
                : $lessonMediaProgress->completed_at,
            'last_watched_at' => $currentTime,
        ]);
        $lessonMediaProgress->save();
    }

    private function updateEnrollmentProgress(Enrollment $enrollment): void
    {
        $course = $enrollment->course;

        $publishedLessonIds = Lesson::query()
            ->whereHas('courseModule', fn ($q) => $q->where('course_id', $course->id))
            ->where('status', 'published')
            ->where('is_active', true)
            ->pluck('id');

        if ($publishedLessonIds->isEmpty()) {
            return;
        }

        $completedLessons = LessonProgress::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $publishedLessonIds)
            ->where('is_completed', true)
            ->count();

        $percentage = min((int) round(($completedLessons / $publishedLessonIds->count()) * 100), 100);

        $enrollment->update([
            'progress_percentage' => $percentage,
            'completed_at' => $percentage >= 100
                ? ($enrollment->completed_at ?? now())
                : $enrollment->completed_at,
        ]);
    }
}
