<?php

namespace App\Modules\Learning\Services;

use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;

class AssessmentCatalogResolver implements AssessmentCatalog
{
    public function parentBelongsToTenant(string $type, int $id, int $tenantId): bool
    {
        $modelClass = match ($type) {
            'lesson' => Lesson::class,
            'course' => Course::class,
            default => null,
        };

        return $modelClass !== null && $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->exists();
    }

    public function parentBelongsToInstructor(string $type, int $id, int $tenantId, int $instructorId): bool
    {
        $modelClass = match ($type) {
            'lesson' => Lesson::class,
            'course' => Course::class,
            default => null,
        };

        if ($modelClass === Course::class) {
            return Course::query()
                ->where('tenant_id', $tenantId)
                ->where('instructor_id', $instructorId)
                ->whereKey($id)
                ->exists();
        }

        return $modelClass === Lesson::class && Lesson::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->whereHas('courseModule', fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereHas('courseModule.course', fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('instructor_id', $instructorId))
            ->exists();
    }

    public function ownedParentIds(string $type, int $tenantId, int $instructorId): array
    {
        $modelClass = match ($type) {
            'lesson' => Lesson::class,
            'course' => Course::class,
            default => null,
        };

        if ($modelClass === Course::class) {
            return Course::query()
                ->where('tenant_id', $tenantId)
                ->where('instructor_id', $instructorId)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        if ($modelClass === Lesson::class) {
            return Lesson::query()
                ->where('tenant_id', $tenantId)
                ->whereHas('courseModule', fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereHas('courseModule.course', fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('instructor_id', $instructorId))
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        return [];
    }

    public function courseIdForParent(string $type, int $id, int $tenantId): ?int
    {
        if ($type === 'course') {
            return Course::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->value('id');
        }

        if ($type !== 'lesson') {
            return null;
        }

        return Lesson::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->whereHas('courseModule', fn ($query) => $query->where('tenant_id', $tenantId))
            ->with('courseModule')
            ->first()?->courseModule?->course_id;
    }

    /**
     * @param  list<int>  $parentIds
     * @return array<int, int>
     */
    public function courseIdsForParentIds(string $type, array $parentIds, int $tenantId): array
    {
        $parentIds = array_values(array_unique(array_map(static fn (int $id): int => $id, $parentIds)));

        if ($parentIds === []) {
            return [];
        }

        if ($type === 'course') {
            return Course::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $parentIds)
                ->pluck('id')
                ->mapWithKeys(static fn (int|string $id): array => [(int) $id => (int) $id])
                ->all();
        }

        if ($type !== 'lesson') {
            return [];
        }

        return Lesson::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $parentIds)
            ->whereHas('courseModule', fn ($query) => $query->where('tenant_id', $tenantId))
            ->with('courseModule:id,course_id')
            ->get(['id', 'course_module_id'])
            ->mapWithKeys(function (Lesson $lesson): array {
                $courseId = $lesson->courseModule?->getAttribute('course_id');

                return $courseId === null
                    ? []
                    : [(int) $lesson->getAttribute('id') => (int) $courseId];
            })
            ->all();
    }

    public function enrolledUserIdsForCourse(int $courseId, int $tenantId): array
    {
        return Course::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($courseId)
            ->firstOrFail()
            ->enrollments()
            ->where('tenant_id', $tenantId)
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, list<int>>
     */
    public function enrolledUserIdsForCourses(array $courseIds, int $tenantId): array
    {
        $courseIds = array_values(array_unique(array_map(static fn (int $id): int => $id, $courseIds)));

        if ($courseIds === []) {
            return [];
        }

        return Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('course_id', $courseIds)
            ->get(['course_id', 'user_id'])
            ->groupBy('course_id')
            ->map(fn ($enrollments): array => $enrollments
                ->pluck('user_id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->values()
                ->all())
            ->all();
    }

    public function categoryIdsAvailableForTenant(array $categoryIds, int $tenantId): array
    {
        return Category::query()
            ->whereIn('id', $categoryIds)
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
