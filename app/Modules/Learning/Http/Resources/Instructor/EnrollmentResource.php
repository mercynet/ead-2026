<?php

namespace App\Modules\Learning\Http\Resources\Instructor;

use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Enrollment */
/**
 * @property int $id
 * @property int $course_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $enrolled_at
 * @property \Illuminate\Support\Carbon|null $access_expires_at
 * @property-read User $user
 * @property-read Course $course
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LessonProgress> $lessonProgress
 */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'status' => $this->status,
            'enrolled_at' => $this->enrolled_at?->toISOString(),
            'access_expires_at' => $this->access_expires_at?->toISOString(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
            'progress' => $this->progressPayload(),
        ];
    }

    /** @return array{percentage:int,completed_lessons:int,total_lessons:int,last_activity:string|null,lessons:array<int,array<string,mixed>>} */
    private function progressPayload(): array
    {
        /** @var Collection<int, CourseModule> $modules */
        $modules = $this->course->modules;
        /** @var Collection<int, Lesson> $lessons */
        $lessons = $modules
            ->flatMap(function (CourseModule $module): Collection {
                /** @var Collection<int, Lesson> $moduleLessons */
                $moduleLessons = $module->getRelation('lessons');

                return $moduleLessons;
            })
            ->filter(fn (Lesson $lesson): bool => $lesson->getAttribute('status') === 'published'
                && (bool) $lesson->getAttribute('is_active'))
            ->values();
        $progressByLesson = $this->lessonProgress->keyBy('lesson_id');
        $completed = $lessons->filter(fn (Lesson $lesson): bool => $progressByLesson->get($lesson->id)?->isCompleted() === true)->count();
        $latest = $this->lessonProgress->sortByDesc(fn (LessonProgress $progress): mixed => $progress->getAttribute('last_watched_at') ?? $progress->getAttribute('updated_at'))->first();

        return [
            'percentage' => $lessons->count() === 0 ? 0 : (int) round(($completed / $lessons->count()) * 100),
            'completed_lessons' => $completed,
            'total_lessons' => $lessons->count(),
            'last_activity' => $this->iso8601($latest?->getAttribute('last_watched_at') ?? $latest?->getAttribute('updated_at')),
            'lessons' => $lessons->map(function (Lesson $lesson) use ($progressByLesson): array {
                $progress = $progressByLesson->get($lesson->id);

                return [
                    'lesson_id' => $lesson->id,
                    'progress_percentage' => (int) ($progress?->getAttribute('progress_percentage') ?? 0),
                    'is_completed' => $progress?->isCompleted() ?? false,
                    'completed_at' => $this->iso8601($progress?->getAttribute('completed_at')),
                    'last_watched_at' => $this->iso8601($progress?->getAttribute('last_watched_at')),
                    'time_spent_seconds' => (int) ($progress?->getAttribute('time_spent_seconds') ?? 0),
                ];
            })->all(),
        ];
    }

    private function iso8601(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
