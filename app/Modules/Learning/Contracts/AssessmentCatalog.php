<?php

namespace App\Modules\Learning\Contracts;

interface AssessmentCatalog
{
    public function parentBelongsToTenant(string $type, int $id, int $tenantId): bool;

    public function parentBelongsToInstructor(string $type, int $id, int $tenantId, int $instructorId): bool;

    /**
     * @return list<int>
     */
    public function ownedParentIds(string $type, int $tenantId, int $instructorId): array;

    public function courseIdForParent(string $type, int $id, int $tenantId): ?int;

    /**
     * @param  list<int>  $parentIds
     * @return array<int, int> parent ID => course ID
     */
    public function courseIdsForParentIds(string $type, array $parentIds, int $tenantId): array;

    /**
     * @return list<int>
     */
    public function enrolledUserIdsForCourse(int $courseId, int $tenantId): array;

    /**
     * @param  list<int>  $courseIds
     * @return array<int, list<int>> course ID => enrolled user IDs
     */
    public function enrolledUserIdsForCourses(array $courseIds, int $tenantId): array;

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    public function categoryIdsAvailableForTenant(array $categoryIds, int $tenantId): array;
}
